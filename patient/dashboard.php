<?php
// dashboard.php
// Standalone patient dashboard with inline backend endpoints (switchable via ?action=...)
// Place this file in the patient folder. Requires ../connection/db.php for $conn (mysqli).
// Session must be used for patient login (patient_id)

session_start();
include("../connection/db.php");

/* Helper: escape */
function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
/* Helper: get doctor name */
function getDoctorName($conn, $doctor_id) {
    $stmt = $conn->prepare("SELECT username FROM admins WHERE id = ?");
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) return $row['username'];
    return 'Unknown';
}
 // provide $conn (mysqli)

// --------------------------
// Config: holidays & slot definitions
// --------------------------
$HOLIDAYS = [
    '2025-01-26',
    '2025-08-15',
    '2025-10-02',
    // add more 'YYYY-MM-DD' strings as needed
];

// Default template slots (weekday)
$DEFAULT_SLOTS = ["09:00 AM", "10:00 AM", "11:00 AM", "02:00 PM", "03:00 PM", "04:00 PM"];
// Saturday uses only first 2
$SATURDAY_LIMIT = 2;

// Items per page for appointment tables
$APPTS_PER_PAGE = 5;

// Helper: send JSON and exit
function json_out($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Normalize input helper
function input_get($k, $default = null) {
    return isset($_GET[$k]) ? $_GET[$k] : $default;
}
function input_post($k, $default = null) {
    return isset($_POST[$k]) ? $_POST[$k] : $default;
}

// --------------------------
// If action parameter is present -> handle API endpoints (inline helpers)
// --------------------------
$action = $_GET['action'] ?? null;
if ($action) {
    // some endpoints rely on session
    switch ($action) {
        // Request appointment (create pending)
        case 'request_appointment':
            header('Content-Type: application/json');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['status'=>'error','message'=>'Use POST']);
            if (!isset($_SESSION['patient_id'])) json_out(['status'=>'error','message'=>'Not logged in']);
            $patient_id = (int) $_SESSION['patient_id'];
            $doctor_id = (int) (input_post('doctor_id', 0));
            $appointment_date = input_post('appointment_date', '');
            $slot = trim(input_post('slot', ''));
            if (!$doctor_id || !$appointment_date || $slot === '') json_out(['status'=>'error','message'=>'Missing fields']);
            // check if slot still free for given doctor/date/slot
            $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND slot=? AND status IN ('pending','approved','booked')");
            $stmt->bind_param("iss", $doctor_id, $appointment_date, $slot);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->close();
                json_out(['status'=>'error','message'=>'Slot already taken']);
            }
            $stmt->close();
            // insert pending appointment
            $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, slot, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iiss", $patient_id, $doctor_id, $appointment_date, $slot);
            if ($stmt->execute()) {
                $stmt->close();
                json_out(['status'=>'success','message'=>'Appointment request sent']);
            } else {
                $err = $stmt->error;
                $stmt->close();
                json_out(['status'=>'error','message'=>'Database error: '.$err]);
            }
            break;

        // Reschedule appointment -> updates appointment (status back to pending)
        case 'reschedule_appointment':
            header('Content-Type: application/json');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['status'=>'error','message'=>'Use POST']);
            if (!isset($_SESSION['patient_id'])) json_out(['status'=>'error','message'=>'Not logged in']);
            $patient_id = (int) $_SESSION['patient_id'];
            $appointment_id = (int) (input_post('appointment_id', 0));
            $new_date = input_post('appointment_date', '');
            $new_slot = trim(input_post('slot', ''));
            if (!$appointment_id || !$new_date || $new_slot === '') json_out(['status'=>'error','message'=>'Missing fields']);
            // verify ownership
            $stmt = $conn->prepare("SELECT id FROM appointments WHERE id=? AND patient_id=?");
            $stmt->bind_param("ii", $appointment_id, $patient_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                json_out(['status'=>'error','message'=>'Unauthorized or appointment not found']);
            }
            $stmt->close();
            // ensure new slot is not already taken by someone else
            $stmt = $conn->prepare("SELECT id FROM appointments WHERE appointment_date=? AND slot=? AND id!=? AND status IN ('pending','approved','booked')");
            $stmt->bind_param("ssi", $new_date, $new_slot, $appointment_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->close();
                json_out(['status'=>'error','message'=>'Selected slot already taken']);
            }
            $stmt->close();
            // update appointment
            $stmt = $conn->prepare("UPDATE appointments SET appointment_date=?, slot=?, status='pending' WHERE id=?");
            $stmt->bind_param("ssi", $new_date, $new_slot, $appointment_id);
            if ($stmt->execute()) {
                $stmt->close();
                json_out(['status'=>'success','message'=>'Reschedule request submitted']);
            } else {
                $err = $stmt->error;
                $stmt->close();
                json_out(['status'=>'error','message'=>'Database error: '.$err]);
            }
            break;

        // Cancel appointment (patient cancels) -> mark as rejected (so it appears in history)
        case 'cancel_appointment':
            header('Content-Type: application/json');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['status'=>'error','message'=>'Use POST']);
            if (!isset($_SESSION['patient_id'])) json_out(['status'=>'error','message'=>'Not logged in']);
            $patient_id = (int) $_SESSION['patient_id'];
            $appointment_id = (int) (input_post('appointment_id', 0));
            if (!$appointment_id) json_out(['status'=>'error','message'=>'Missing appointment id']);
            $stmt = $conn->prepare("SELECT id FROM appointments WHERE id=? AND patient_id=?");
            $stmt->bind_param("ii", $appointment_id, $patient_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                json_out(['status'=>'error','message'=>'Unauthorized or not found']);
            }
            $stmt->close();
            $stmt = $conn->prepare("UPDATE appointments SET status='rejected' WHERE id=?");
            $stmt->bind_param("i", $appointment_id);
            if ($stmt->execute()) {
                $stmt->close();
                json_out(['status'=>'success','message'=>'Appointment cancelled']);
            } else {
                $err = $stmt->error;
                $stmt->close();
                json_out(['status'=>'error','message'=>'Database error: '.$err]);
            }
            break;

        // get_slots -> return JSON array of slots (each slot object includes label and available)
        case 'get_slots':
            header('Content-Type: application/json');
            $doctor_id = (int) (input_get('doctor_id', 0));
            $date = input_get('date', '');
            if (!$doctor_id || !$date) json_out(['status'=>'error','message'=>'Missing params']);
            $holidays = $HOLIDAYS;
            $day = date('w', strtotime($date)); // 0=Sunday, 6=Saturday
            if ($day == 0 || in_array($date, $holidays)) {
                // no slots
                json_out(['status'=>'success','slots'=>[]]);
            }
            // prepare slots: saturday limit
            $slots = $DEFAULT_SLOTS;
            if ($day == 6) $slots = array_slice($slots, 0, $SATURDAY_LIMIT);
            // fetch booked slots
            $stmt = $conn->prepare("SELECT slot FROM appointments WHERE doctor_id=? AND appointment_date=? AND status IN ('pending','approved','booked')");
            $stmt->bind_param("is", $doctor_id, $date);
            $stmt->execute();
            $res = $stmt->get_result();
            $booked = [];
            while ($r = $res->fetch_assoc()) $booked[] = $r['slot'];
            $stmt->close();
            // build slots objects
            $out = [];
            foreach ($slots as $s) {
                $available = !in_array($s, $booked);
                $out[] = ['label'=>$s, 'slot'=>$s, 'available'=>$available];
            }
            json_out(['status'=>'success','slots'=>$out]);
            break;

        // get_month_availability -> return map date => {hasSlots:bool, remainingSlots:int}
        case 'get_month_availability':
            header('Content-Type: application/json');
            $doctor_id = (int) (input_get('doctor_id', 0));
            $year = (int) (input_get('year', date('Y')));
            $month = (int) (input_get('month', date('m')));
            if (!$doctor_id || !$month || !$year) json_out(['status'=>'error','message'=>'Missing params']);
            $holidays = $HOLIDAYS;
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $availability = [];
            for ($d=1;$d<=$daysInMonth;$d++) {
                $date = sprintf("%04d-%02d-%02d", $year, $month, $d);
                $day = date('w', strtotime($date)); // 0 Sunday
                if ($day == 0 || in_array($date, $holidays)) {
                    $availability[$date] = ['hasSlots'=>false, 'remainingSlots'=>0];
                    continue;
                }
                // slots for this day
                $slots = $DEFAULT_SLOTS;
                if ($day == 6) $slots = array_slice($slots, 0, $SATURDAY_LIMIT);
                $totalSlots = count($slots);
                // count booked
                $stmt = $conn->prepare("SELECT COUNT(*) as c FROM appointments WHERE doctor_id=? AND appointment_date=? AND status IN ('pending','approved','booked')");
                $stmt->bind_param("is", $doctor_id, $date);
                $stmt->execute();
                $cnt = (int) $stmt->get_result()->fetch_assoc()['c'];
                $stmt->close();
                $remaining = max(0, $totalSlots - $cnt);
                $has = $remaining > 0;
                $availability[$date] = ['hasSlots'=>$has, 'remainingSlots'=>$remaining];
            }
            json_out(['status'=>'success','availability'=>$availability]);
            break;

        // get_appointments -> return appointments for current patient (JSON) with pagination and search
        case 'get_appointments':
            header('Content-Type: application/json');
            if (!isset($_SESSION['patient_id'])) json_out(['total'=>0,'appointments'=>[]]);
            $patient_id = (int) $_SESSION['patient_id'];
            $page = max(1, (int)(input_get('page', 1)));
            $search = trim(input_get('search', ''));
            $offset = ($page - 1) * $APPTS_PER_PAGE;

            // base where
            $where = "ap.patient_id = ?";
            $params = [];
            $types = "i";
            $params[] = $patient_id;

            if ($search !== '') {
                // search doctor name, slot, date, status
                $where .= " AND (a.username LIKE ? OR ap.slot LIKE ? OR ap.appointment_date LIKE ? OR ap.status LIKE ?)";
                $like = "%{$search}%";
                $types .= "ssss";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            // total count
            $countSql = "SELECT COUNT(*) as c FROM appointments ap JOIN admins a ON ap.doctor_id=a.id WHERE $where";
            $stmt = $conn->prepare($countSql);
            // bind dynamically
            $stmt_bind_names = [];
            if ($types) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $total = (int)$stmt->get_result()->fetch_assoc()['c'];
            $stmt->close();

            // fetch page
            $sql = "SELECT ap.id, ap.appointment_date, ap.slot, ap.status, ap.doctor_id, a.username as doctor_name 
                    FROM appointments ap JOIN admins a ON ap.doctor_id=a.id
                    WHERE $where
                    ORDER BY ap.appointment_date ASC, FIELD(ap.status,'pending','approved','booked','completed','rejected'), ap.slot ASC
                    LIMIT ? OFFSET ?";
            // add limit & offset types/params
            $types2 = $types . "ii";
            $params2 = $params;
            $params2[] = $APPTS_PER_PAGE;
            $params2[] = $offset;
            $stmt = $conn->prepare($sql);
            // bind
            $stmt->bind_param($types2, ...$params2);
            $stmt->execute();
            $res = $stmt->get_result();
            $appointments = [];
            while ($r = $res->fetch_assoc()) $appointments[] = $r;
            $stmt->close();

            json_out(['total'=>$total,'per_page'=>$APPTS_PER_PAGE,'page'=>$page,'appointments'=>$appointments]);
            break;

        // get_doctors -> return doctors list (optionally search)
        case 'get_doctors':
            header('Content-Type: application/json');
            $search = trim(input_get('search', ''));
            if ($search) {
                $like = "%{$search}%";
                $stmt = $conn->prepare("SELECT id, username, specialization FROM admins WHERE (role='doctor' OR role='admin') AND (username LIKE ? OR specialization LIKE ?) ORDER BY username ASC LIMIT 50");
                $stmt->bind_param("ss", $like, $like);
            } else {
                $stmt = $conn->prepare("SELECT id, username, specialization FROM admins WHERE role='doctor' OR role='admin' ORDER BY username ASC LIMIT 200");
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $out = [];
            while ($r = $res->fetch_assoc()) $out[] = $r;
            $stmt->close();
            echo json_encode($out);
            exit;
            break;

        default:
            json_out(['status'=>'error','message'=>'Unknown action']);
    }
    // all actions call exit from json_out or echo+exit
}

// --------------------------
// If no action param -> render the dashboard page (HTML + JS)
// --------------------------
if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit;
}
$patient_id = (int) $_SESSION['patient_id'];

/* -------------------------
   Fetch data for page
   ------------------------- */
// fetch patient
$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$patient) { echo "<div class='alert alert-danger'>Patient not found.</div>"; exit; }

// latest assigned doctor (approved)
$stmt = $conn->prepare("
    SELECT a.username FROM appointments ap
    JOIN admins a ON ap.doctor_id = a.id
    WHERE ap.patient_id = ? AND ap.status = 'approved'
    ORDER BY ap.appointment_date DESC LIMIT 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$doctorRes = $stmt->get_result()->fetch_assoc();
$doctorName = $doctorRes['username'] ?? 'Not Assigned';
$stmt->close();

// treatment plan
$stmt = $conn->prepare("SELECT * FROM treatment_plans WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$tplan = $stmt->get_result()->fetch_assoc();
$stmt->close();

$progress = 0; $weeksCompleted = 0; $weeksLeft = 0;
if ($tplan && !empty($tplan['start_date']) && !empty($tplan['total_weeks'])) {
    $totalWeeks = (int)$tplan['total_weeks'];
    $weeksCompleted = (int) floor((time() - strtotime($tplan['start_date'])) / (7*24*60*60));
    if ($weeksCompleted < 0) $weeksCompleted = 0;
    if ($weeksCompleted > $totalWeeks) $weeksCompleted = $totalWeeks;
    $progress = ($totalWeeks>0) ? round(min(100, ($weeksCompleted / $totalWeeks * 100))) : 0;
    $weeksLeft = max(0, $totalWeeks - $weeksCompleted);
}

// visits completed
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ? AND status = 'completed'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$visitsCount = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// next appointment approved or booked >= today
$stmt = $conn->prepare("
    SELECT ap.*, a.username as doctor FROM appointments ap
    JOIN admins a ON ap.doctor_id = a.id
    WHERE ap.patient_id = ? AND ap.status IN ('approved','booked') AND ap.appointment_date >= CURDATE()
    ORDER BY ap.appointment_date ASC LIMIT 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$nextAppointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// doctors list (for dropdowns)
$doctors = [];
if ($res = $conn->query("SELECT id, username FROM admins WHERE role='doctor' OR role='admin' ORDER BY username ASC")) {
    while ($r = $res->fetch_assoc()) $doctors[] = $r;
    $res->close();
}

// scans
$stmt = $conn->prepare("SELECT * FROM scans WHERE patient_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$scans = $stmt->get_result();
$stmt->close();

// homecare inline (hardcoded youtube links)
$homecare_data = [
  'Braces' => [
    ['title'=>'How to Brush with Braces','desc'=>'A short guide to keep your braces clean.','yt'=>'https://www.youtube.com/watch?v=VIDEO_ID_1'],
    ['title'=>'Foods to Avoid with Braces','desc'=>'What to eat and what to avoid.','yt'=>'https://www.youtube.com/watch?v=VIDEO_ID_2'],
    ['title'=>'Maintaining Braces Comfort','desc'=>'Tips to reduce irritation after adjustment.','yt'=>'https://www.youtube.com/watch?v=VIDEO_ID_3'],
  ],
  'Whitening' => [
    ['title'=>'At-home Whitening Basics','desc'=>'Safe whitening practices at home.','yt'=>'https://www.youtube.com/watch?v=VIDEO_ID_4'],
    ['title'=>'After Whitening Care','desc'=>'How to keep your teeth bright longer.','yt'=>'https://www.youtube.com/watch?v=VIDEO_ID_5'],
    ['title'=>'Whitening Myths','desc'=>'Don\'t fall for these myths.','yt'=>'https://www.youtube.com/watch?v=VIDEO_ID_6'],
  ],
  'Root Canal' => [
    ['title'=>'Root Canal Explained','desc'=>'What to expect during a root canal.','yt'=>'https://www.youtube.com/watch?v=VIDEO_ID_7'],
    ['title'=>'Post Root Canal Care','desc'=>'Pain control and hygiene tips.','yt'=>'https://www.youtube.com/watch?v=VIDEO_ID_8'],
  ],
  'Implants' => [
    ['title'=>'Dental Implants Overview','desc'=>'Is an implant right for you?','yt'=>'https://www.youtube.com/watch?v=VIDEO_ID_9'],
    ['title'=>'Implant Aftercare','desc'=>'How to care for new implants.','yt'=>'https://www.youtube.com/watch?v=VIDEO_ID_10'],
  ],
];

// treatment types for filter (derive from $homecare_data)
$treatmentTypes = array_keys($homecare_data);

// --------------------------
// Render HTML page
// --------------------------
function h($v){ return htmlspecialchars($v ?? ''); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Patient Dashboard | Oral Sight AI 360</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <style>
    body { background: #f7f9fc; }
    .sidebar { height:100vh; background: linear-gradient(180deg,#1565c0,#42a5f5); color:white; padding-top:20px; position:fixed; width:220px; z-index:5; }
    .sidebar a{ color:white; text-decoration:none; display:block; padding:12px; margin:5px 10px; border-radius:8px; }
    .sidebar a:hover{ background: rgba(255,255,255,0.12); }
    .main { margin-left:240px; padding:20px; }
    .progress{ height:20px; }
    .scan-preview { border:1px solid #e2e8f0; border-radius:8px; padding:10px; margin-bottom:15px; background:#fff; }
    .slot{ padding:8px 10px; border-radius:6px; cursor:pointer; margin:5px 6px 5px 0; display:inline-block; font-size:14px; }
    .slot.available{ background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .slot.unavailable{ background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; cursor:not-allowed; opacity:0.9; }
    .slot.selected{ background:#0d6efd; color:#fff; border:1px solid #0b5ed7; }
    .calendar { display:flex; flex-wrap:wrap; gap:6px; }
    .calendar .day { width:40px; height:40px; display:flex; align-items:center; justify-content:center; border-radius:6px; cursor:pointer; }
    .calendar .day.available{ background:#d4edda; color:#155724; }
    .calendar .day.unavailable{ background:#f8d7da; color:#721c24; }
    .calendar .day.today{ outline:2px solid #0d6efd; }
    .homecare-card { min-height:120px; }
    .treatment-section { margin-bottom:1.2rem; }
    .watch-btn-wrap { display:flex; justify-content:center; margin-top:8px; }
    .table-wrapper { max-height:420px; overflow:auto; }
    @media (max-width:768px) {
      .sidebar { position:relative; width:100%; height:auto; }
      .main { margin-left:0; padding:10px; }
      nav.navbar { margin-left:0; }
    }
  </style>
</head>
<body>

<div class="sidebar">
  <h4 class="text-center">🦷 Oral Sight AI 360</h4>
  <a href="#dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
  <a href="#appointments"><i class="bi bi-calendar-check"></i> Appointments</a>
  <a href="#scans"><i class="bi bi-camera"></i> My Scans</a>
  <a href="#homecare"><i class="bi bi-house-heart"></i> Home Care</a>
  <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm" style="margin-left:240px">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Dashboard</a>
    <div class="d-flex align-items-center">
      <div class="me-3 small text-muted">Logged in as <b><?= h($patient['name']) ?></b></div>
      <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="main">
  <div id="dashboard" class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3>Welcome, <?= h($patient['name']) ?></h3>
    </div>

    <!-- Personal + Progress -->
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card shadow-sm p-3">
          <h5>Personal Info</h5>
          <p><b>Email:</b> <?= h($patient['email']) ?></p>
          <p><b>Phone:</b> <?= h($patient['phone']) ?></p>
          <p class="small text-muted"><b>DOB:</b> <?= h($patient['dob']) ?> &nbsp; <b>Age:</b> <?= h($patient['age']) ?></p>
          <p><b>Gender:</b> <?= h($patient['gender']) ?></p>
          <p><b>Doctor:</b> <?= h($doctorName) ?></p>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card shadow-sm p-3">
          <h5>Treatment Progress</h5>
          <?php if ($tplan): ?>
            <div class="progress mb-2">
              <div class="progress-bar bg-success" role="progressbar" style="width: <?= (int)$progress ?>%"><?= (int)$progress ?>%</div>
            </div>
            <p><b>Weeks Completed:</b> <?= (int)$weeksCompleted ?></p>
            <p><b>Weeks Left:</b> <?= (int)$weeksLeft ?> / <?= h($tplan['total_weeks']) ?></p>
            <p><b>Total Visits Completed:</b> <?= (int)$visitsCount ?></p>
          <?php else: ?>
            <p>No treatment plan assigned yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Next Appointment -->
    <div class="card shadow-sm p-3 mb-4">
      <h5>Next Appointment</h5>
      <?php if ($nextAppointment): ?>
        <p><b>Date:</b> <?= h($nextAppointment['appointment_date']) ?></p>
        <p><b>Slot:</b> <?= h($nextAppointment['slot']) ?></p>
        <p><b>Doctor:</b> <?= h($nextAppointment['doctor']) ?></p>
      <?php else: ?>
        <p>No upcoming appointments.</p>
      <?php endif; ?>
    </div>

    <!-- Unified Appointment Request -->
    <div class="card shadow-sm p-3 mb-4" id="request-appointment-card">
      <h5>Unified Appointment Request</h5>
      <div class="mb-2 small text-muted">🟢 Green = has available slots • 🔴 Red = fully booked / weekend / holiday</div>
      <div id="requestAlertPlaceholder"></div>

      <form id="appointmentForm" onsubmit="return false;">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Choose Doctor</label>
            <select id="doctorSelect" class="form-select" required>
              <option value="">-- Select Doctor --</option>
              <?php foreach ($doctors as $d): ?>
                <option value="<?= (int)$d['id'] ?>"><?= h($d['username']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="calPrev">&laquo;</button>
              <div id="calMonthLabel" class="fw-bold"></div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="calNext">&raquo;</button>
            </div>
            <div id="miniCalendar" class="calendar"></div>
            <p class="small text-muted mt-2">Click a green date to see slots</p>
          </div>
        </div>

        <hr>
        <div>
          <h6 class="mb-2">Available Slots</h6>
          <div id="daySlots" class="small text-muted">Select a date</div>
          <div id="daySlotsContainer" class="mt-2"></div>

          <input type="hidden" id="appointmentDate" name="appointment_date">
          <input type="hidden" id="selectedSlotInput" name="slot">

          <div class="mt-2">
            <button type="button" id="clearSelectedSlot" class="btn btn-sm btn-outline-secondary">Clear Slot</button>
          </div>
        </div>

        <div class="mt-3">
          <button id="sendRequestBtn" class="btn btn-primary">Send Request</button>
        </div>
      </form>
    </div>

    <!-- Appointments Tabs -->
    <div class="card shadow-sm p-3 mb-4" id="appointments-list">
      <h5>Your Appointments</h5>

      <ul class="nav nav-tabs" id="appointmentTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcomingTab" type="button" role="tab">Upcoming</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#historyTab" type="button" role="tab">History</button>
        </li>
      </ul>

      <div class="tab-content mt-3">
        <!-- Upcoming -->
        <div class="tab-pane fade show active" id="upcomingTab" role="tabpanel">
          <div class="d-flex gap-2 mb-2">
            <input id="searchUpcoming" class="form-control" placeholder="Search by doctor/slot/date/status">
            <button id="searchUpcomingBtn" class="btn btn-primary">Search</button>
            <button id="clearUpcomingSearch" class="btn btn-outline-secondary">Clear</button>
          </div>
          <div class="table-wrapper">
            <table class="table table-striped table-sm" id="upcomingTable">
              <thead>
                <tr>
                  <th>Doctor</th>
                  <th>Date</th>
                  <th>Slot</th>
                  <th>Status</th>
                  <th style="width:170px">Actions</th>
                </tr>
              </thead>
              <tbody id="upcomingBody"></tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <div id="upcomingInfo" class="text-muted small"></div>
            <div>
              <button id="upPrev" class="btn btn-sm btn-outline-secondary">Prev</button>
              <button id="upNext" class="btn btn-sm btn-outline-secondary">Next</button>
            </div>
          </div>
        </div>

        <!-- History -->
        <div class="tab-pane fade" id="historyTab" role="tabpanel">
          <div class="d-flex gap-2 mb-2">
            <input id="searchHistory" class="form-control" placeholder="Search history by doctor/slot/date/status">
            <button id="searchHistoryBtn" class="btn btn-primary">Search</button>
            <button id="clearHistorySearch" class="btn btn-outline-secondary">Clear</button>
          </div>
          <div class="table-wrapper">
            <table class="table table-striped table-sm" id="historyTable">
              <thead>
                <tr>
                  <th>Doctor</th>
                  <th>Date</th>
                  <th>Slot</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="historyBody"></tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <div id="historyInfo" class="text-muted small"></div>
            <div>
              <button id="histPrev" class="btn btn-sm btn-outline-secondary">Prev</button>
              <button id="histNext" class="btn btn-sm btn-outline-secondary">Next</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Scans -->
    <div class="card shadow-sm p-3 mb-4" id="scans">
      <h5>My Scans</h5>
      <?php while ($s = $scans->fetch_assoc()): 
        $path = $s['file_path'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      ?>
        <div class="scan-preview">
          <p><b>Uploaded:</b> <?= h($s['created_at']) ?></p>
          <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
            <img src="../<?= h($path) ?>" class="img-fluid rounded" alt="scan">
          <?php elseif ($ext == 'pdf'): ?>
            <a href="../<?= h($path) ?>" target="_blank"><i class="bi bi-file-earmark-pdf text-danger"></i> View PDF</a>
          <?php else: ?>
            <a href="../<?= h($path) ?>" target="_blank"><i class="bi bi-file-earmark"></i> Download File</a>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    </div>

    <!-- Home Care: single Watch Now per treatment group, button placed immediately below cards -->
    <div class="card shadow-sm p-3 mb-4" id="homecare">
      <h5>Home Care</h5>
      <div class="mb-3">
        <select id="treatmentFilter" class="form-select">
          <option value="">-- All Treatments --</option>
          <?php foreach ($treatmentTypes as $tt): ?>
            <option value="<?= h($tt) ?>"><?= h($tt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php foreach ($homecare_data as $treatment => $cards): ?>
        <div class="treatment-section" data-treatment="<?= h($treatment) ?>">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><?= h($treatment) ?></h6>
          </div>

          <div class="row g-3 mb-2">
            <?php foreach ($cards as $c): ?>
              <div class="col-md-4 homecare-card">
                <div class="card shadow-sm h-100">
                  <div class="card-body">
                    <h6 class="card-title"><?= h($c['title']) ?></h6>
                    <p class="card-text small text-muted"><?= h($c['desc']) ?></p>
                  </div>
                  <div class="card-footer small text-muted"><?= h($treatment) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="watch-btn-wrap">
            <?php $watchLink = $cards[0]['yt'] ?? '#'; ?>
            <a href="<?= h($watchLink) ?>" target="_blank" class="btn btn-primary">Watch Now ▶</a>
          </div>
          <hr>
        </div>
      <?php endforeach; ?>

    </div>

  </div> <!-- dashboard -->
</div> <!-- main -->

<!-- Reschedule Modal (IDs used by JS) -->
<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-labelledby="rescheduleLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="rescheduleForm">
        <div class="modal-header">
          <h5 class="modal-title" id="rescheduleLabel">Reschedule Appointment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="rescheduleAppointmentId" name="appointment_id">
          <div class="mb-3">
            <label class="form-label">Doctor</label>
            <input type="text" id="rescheduleDoctor" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="date" id="rescheduleDate" name="appointment_date" class="form-control" required min="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Slot</label>
            <select id="rescheduleSlot" name="slot" class="form-select" required>
              <option value="">Select doctor/date</option>
            </select>
          </div>
          <div id="rescheduleMsg" class="small text-muted"></div>
        </div>
        <div class="modal-footer">
          <button type="button" id="saveReschedule" class="btn btn-primary">Save changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* --------------------------
   Helpers
   -------------------------- */
const qs = s => document.querySelector(s);
const qsa = s => Array.from(document.querySelectorAll(s));
function safeJsonPromise(resp){ return resp.json().catch(()=> resp.text()); }

/* --------------------------
   Minor UI helpers
   -------------------------- */
qs('#treatmentFilter').addEventListener('change', function(){
  const v = this.value;
  qsa('[data-treatment]').forEach(sec => {
    sec.style.display = (!v || sec.getAttribute('data-treatment') === v) ? '' : 'none';
  });
});

/* --------------------------
   Appointments: Table, Pagination & Search (AJAX)
   - upcoming: pending + approved + booked
   - history: rejected + completed
   - per-page: <?= $APPTS_PER_PAGE ?> (from PHP)
   -------------------------- */
let upPage = 1;
let histPage = 1;
const PER_PAGE = <?= (int)$APPTS_PER_PAGE ?>;

function fetchAppointments(page=1, search='', callback) {
    const url = `dashboard.php?action=get_appointments&page=${encodeURIComponent(page)}&search=${encodeURIComponent(search)}`;
    fetch(url)
      .then(r => r.json())
      .then(data => callback && callback(data))
      .catch(err => { console.error('fetchAppointments error', err); callback && callback(null); });
}

function renderTablesFromData(data, tableType) {
    // tableType: 'upcoming' or 'history'
    if (!data) {
      if (tableType === 'upcoming') {
        qs('#upcomingBody').innerHTML = '<tr><td colspan="5" class="text-danger">Failed to load</td></tr>';
        qs('#upcomingInfo').textContent = '';
      } else {
        qs('#historyBody').innerHTML = '<tr><td colspan="4" class="text-danger">Failed to load</td></tr>';
        qs('#historyInfo').textContent = '';
      }
      return;
    }
    const appts = data.appointments || [];
    // split upcoming/history
    const upcoming = appts.filter(a => a.status === 'pending' || a.status === 'approved' || a.status === 'booked');
    const history = appts.filter(a => a.status === 'rejected' || a.status === 'completed');

    // If user is on upcoming tab, render upcoming, else render history.
    // But we will render both using the same last fetched data, for simplicity fetch twice with respective searches/pages.
    if (tableType === 'upcoming') {
      const tbody = qs('#upcomingBody');
      tbody.innerHTML = '';
      if (upcoming.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted">No upcoming appointments.</td></tr>';
      } else {
        upcoming.forEach(a => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${escapeHtml(a.doctor_name)}</td>
            <td>${escapeHtml(a.appointment_date)}</td>
            <td>${escapeHtml(a.slot)}</td>
            <td><span class="badge ${a.status === 'approved' ? 'bg-success' : 'bg-warning'}">${escapeHtml(a.status)}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1" onclick='openRescheduleModalClient(${a.id}, ${a.doctor_id}, ${JSON.stringify(a.doctor_name)}, "${a.appointment_date}", "${a.slot.replace(/"/g,'\\"')}")'>Reschedule</button>
              <button class="btn btn-sm btn-outline-danger" onclick="cancelAppointment(${a.id})">Cancel</button>
            </td>
          `;
          tbody.appendChild(tr);
        });
      }
      // info & pagination buttons
      const total = data.total || 0;
      const page = data.page || 1;
      const start = ((page - 1) * data.per_page) + 1;
      const end = Math.min(total, page * data.per_page);
      qs('#upcomingInfo').textContent = total === 0 ? 'No records' : `Showing ${start} - ${end} of ${total}`;
      // Next/Prev enable/disable
      qs('#upPrev').disabled = page <= 1;
      qs('#upNext').disabled = end >= total;
    } else {
      const tbody = qs('#historyBody');
      tbody.innerHTML = '';
      if (history.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted">No history records.</td></tr>';
      } else {
        history.forEach(a => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${escapeHtml(a.doctor_name)}</td>
            <td>${escapeHtml(a.appointment_date)}</td>
            <td>${escapeHtml(a.slot)}</td>
            <td><span class="badge ${a.status === 'completed' ? 'bg-success' : 'bg-danger'}">${escapeHtml(a.status)}</span></td>
          `;
          tbody.appendChild(tr);
        });
      }
      const total = data.total || 0;
      const page = data.page || 1;
      const start = ((page - 1) * data.per_page) + 1;
      const end = Math.min(total, page * data.per_page);
      qs('#historyInfo').textContent = total === 0 ? 'No records' : `Showing ${start} - ${end} of ${total}`;
      qs('#histPrev').disabled = page <= 1;
      qs('#histNext').disabled = end >= total;
    }
}

// escapeHtml helper
function escapeHtml(s) {
  if (s === null || s === undefined) return '';
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/* Upcoming controls */
function loadUpcoming() {
  const q = qs('#searchUpcoming').value.trim();
  fetchAppointments(upPage, q, (data) => renderTablesFromData(data, 'upcoming'));
}
qs('#upNext').addEventListener('click', ()=> { upPage++; loadUpcoming(); });
qs('#upPrev').addEventListener('click', ()=> { if (upPage>1) upPage--; loadUpcoming(); });
qs('#searchUpcomingBtn').addEventListener('click', ()=> { upPage=1; loadUpcoming(); });
qs('#clearUpcomingSearch').addEventListener('click', ()=> { qs('#searchUpcoming').value=''; upPage=1; loadUpcoming(); });

/* History controls */
function loadHistory() {
  const q = qs('#searchHistory').value.trim();
  fetchAppointments(histPage, q, (data) => renderTablesFromData(data, 'history'));
}
qs('#histNext').addEventListener('click', ()=> { histPage++; loadHistory(); });
qs('#histPrev').addEventListener('click', ()=> { if (histPage>1) histPage--; loadHistory(); });
qs('#searchHistoryBtn').addEventListener('click', ()=> { histPage=1; loadHistory(); });
qs('#clearHistorySearch').addEventListener('click', ()=> { qs('#searchHistory').value=''; histPage=1; loadHistory(); });

/* Cancel appointment (calls inline cancel endpoint) */
function cancelAppointment(id){
  if(!confirm('Cancel this appointment?')) return;
  fetch('dashboard.php?action=cancel_appointment', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: 'appointment_id=' + encodeURIComponent(id)
  }).then(r => r.json().catch(()=>null))
    .then(j => {
      if (j && j.status === 'success') {
        alert(j.message || 'Cancelled');
        // refresh both tables
        loadUpcoming();
        loadHistory();
      } else {
        alert('Error: ' + (j && j.message ? j.message : 'Failed to cancel'));
      }
    })
    .catch(err => { console.error(err); alert('Request failed'); });
}

/* --------------------------
   Calendar & slots for new request
   -------------------------- */
let currentCalendarYear = new Date().getFullYear();
let currentCalendarMonth = new Date().getMonth();

function buildCalendar(year, month, doctorId){
  const miniCal = qs('#miniCalendar');
  const label = qs('#calMonthLabel');
  miniCal.innerHTML = '';
  const daysInMonth = new Date(year, month+1, 0).getDate();
  const monthName = new Date(year, month, 1).toLocaleString('default', { month: 'long' });
  label.textContent = monthName + ' ' + year;

  if (!doctorId) {
    for (let d=1; d<=daysInMonth; d++){
      const dt = new Date(year, month, d);
      const div = document.createElement('div');
      div.className = 'day';
      div.textContent = d;
      if (dt.getDay() === 0) div.classList.add('unavailable');
      if (dt.toDateString() === new Date().toDateString()) div.classList.add('today');
      div.addEventListener('click', ()=> {
        if (dt.getDay() === 0) return;
        const yyyy = dt.getFullYear(), mm = String(dt.getMonth()+1).padStart(2,'0'), dd = String(dt.getDate()).padStart(2,'0');
        const dateStr = `${yyyy}-${mm}-${dd}`;
        qs('#appointmentDate').value = dateStr;
        qs('#daySlots').textContent = 'Select a doctor to load slots for ' + dateStr;
      });
      miniCal.appendChild(div);
    }
    return;
  }

  fetch(`dashboard.php?action=get_month_availability&doctor_id=${encodeURIComponent(doctorId)}&year=${encodeURIComponent(year)}&month=${encodeURIComponent(month+1)}`)
    .then(r => r.json())
    .then(j => {
      if (!j || j.status === 'error') {
        miniCal.innerHTML = '<p class="text-danger">Error loading availability</p>';
        return;
      }
      const availability = j.availability || {};
      for (let d=1; d<=daysInMonth; d++){
        const yyyy = year;
        const mm = String(month+1).padStart(2,'0');
        const dd = String(d).padStart(2,'0');
        const dateStr = `${yyyy}-${mm}-${dd}`;
        const info = availability[dateStr];

        const dt = new Date(year, month, d);
        const div = document.createElement('div');
        div.className = 'day';
        div.textContent = d;
        if (dt.toDateString() === new Date().toDateString()) div.classList.add('today');

        if (info && typeof info === 'object') {
          if (info.hasSlots) div.classList.add('available'); else div.classList.add('unavailable');
        } else {
          if (info) div.classList.add('available'); else div.classList.add('unavailable');
        }

        if (div.classList.contains('available')) {
          div.addEventListener('click', ()=> {
            qs('#appointmentDate').value = dateStr;
            loadSlotsForSelected(doctorId, dateStr);
          });
        }
        miniCal.appendChild(div);
      }
    })
    .catch(err => {
      console.error('get_month_availability error', err);
      miniCal.innerHTML = '<p class="text-danger">Failed to load calendar</p>';
    });
}

qs('#calPrev').addEventListener('click', ()=> {
  currentCalendarMonth--;
  if (currentCalendarMonth < 0){ currentCalendarMonth = 11; currentCalendarYear--; }
  buildCalendar(currentCalendarYear, currentCalendarMonth, qs('#doctorSelect').value);
});
qs('#calNext').addEventListener('click', ()=> {
  currentCalendarMonth++;
  if (currentCalendarMonth > 11){ currentCalendarMonth = 0; currentCalendarYear++; }
  buildCalendar(currentCalendarYear, currentCalendarMonth, qs('#doctorSelect').value);
});

function loadSlotsForSelected(doctorId, date){
  qs('#daySlotsContainer').innerHTML = '';
  qs('#daySlots').textContent = 'Loading available slots...';
  if (!doctorId || !date) { qs('#daySlots').textContent = 'Select a date and doctor to load slots'; return; }

  fetch(`dashboard.php?action=get_slots&doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`)
    .then(r => r.json())
    .then(j => {
      if (!j || j.status === 'error') { qs('#daySlots').textContent = 'Failed to load slots'; return; }
      const arr = j.slots || [];
      qs('#daySlots').textContent = `Slots for ${date}`;
      const container = qs('#daySlotsContainer');
      container.innerHTML = '';
      if (!Array.isArray(arr) || arr.length === 0) { container.innerHTML = '<p class="text-muted">No slots available</p>'; return; }

      arr.forEach(s=>{
        const div = document.createElement('div');
        div.className = 'slot ' + (s.available ? 'available' : 'unavailable');
        div.textContent = s.label || s.slot;
        if (s.available) {
          div.addEventListener('click', ()=> {
            qsa('#daySlotsContainer .slot').forEach(x=>x.classList.remove('selected'));
            div.classList.add('selected');
            qs('#selectedSlotInput').value = s.slot || s.label;
            qs('#appointmentDate').value = date;
          });
        }
        container.appendChild(div);
      });
    })
    .catch(err => { console.error('get_slots error', err); qs('#daySlots').textContent = 'Error loading slots'; });
}

qs('#doctorSelect').addEventListener('change', ()=> {
  const doc = qs('#doctorSelect').value;
  qs('#appointmentDate').value = '';
  qs('#daySlotsContainer').innerHTML = '';
  buildCalendar(currentCalendarYear, currentCalendarMonth, doc);
});

qs('#clearSelectedSlot').addEventListener('click', ()=> {
  qsa('#daySlotsContainer .slot').forEach(x=>x.classList.remove('selected'));
  if (qs('#selectedSlotInput')) qs('#selectedSlotInput').value = '';
});

/* Send request (AJAX) -> calls dashboard.php?action=request_appointment */
qs('#sendRequestBtn').addEventListener('click', function(e){
  e.preventDefault();
  const doctor = qs('#doctorSelect').value;
  const date = qs('#appointmentDate').value;
  const slot = qs('#selectedSlotInput').value;
  if (!doctor || !date || !slot) { alert('Please pick doctor, date and slot'); return; }
  qs('#sendRequestBtn').disabled = true;
  const orig = qs('#sendRequestBtn').textContent; qs('#sendRequestBtn').textContent = 'Sending...';

  const fd = new FormData();
  fd.append('doctor_id', doctor);
  fd.append('appointment_date', date);
  fd.append('slot', slot);

  fetch('dashboard.php?action=request_appointment', { method:'POST', body: fd })
    .then(r => safeJsonPromise(r))
    .then(j => {
      qs('#sendRequestBtn').disabled = false;
      qs('#sendRequestBtn').textContent = orig;
      if (!j) { alert('Unexpected response'); return; }
      if (typeof j === 'string') { alert(j); return; }
      if (j.status === 'success') {
        const ph = qs('#requestAlertPlaceholder');
        ph.innerHTML = '<div class="alert alert-success">'+ (j.message || 'Requested') +'</div>';
        setTimeout(()=> ph.innerHTML = '', 4000);
        qs('#appointmentForm').reset(); qs('#daySlotsContainer').innerHTML = '';
        // reload both
        upPage = 1; histPage = 1;
        loadUpcoming(); loadHistory();
      } else {
        alert('Error: ' + (j.message || 'Failed'));
      }
    })
    .catch(err => {
      console.error('request_appointment error', err);
      qs('#sendRequestBtn').disabled = false; qs('#sendRequestBtn').textContent = orig;
      alert('Request failed');
    });
});

/* --------------------------
   Reschedule modal logic (client-side)
   -------------------------- */
   
function openRescheduleModalClient(apptId, doctorId, doctorName, currentDate, currentSlot){
  // set fields
  qs('#rescheduleAppointmentId').value = apptId;
  qs('#rescheduleDoctor').value = doctorName || '';
  qs('#rescheduleDate').value = currentDate || '';
  qs('#rescheduleSlot').innerHTML = '<option value="">Select doctor/date</option>';
  qs('#rescheduleMsg').textContent = '';
  // store doctorId on modal element
  qs('#rescheduleModal').dataset.doctorId = doctorId;
  // show modal
  const bs = new bootstrap.Modal(qs('#rescheduleModal'));
  bs.show();
  // If date exists, load slots
  if (currentDate) loadRescheduleSlots(currentDate, currentSlot);
}

function loadRescheduleSlots(date, preselect=''){
  const doctorId = qs('#rescheduleModal').dataset.doctorId;
  const select = qs('#rescheduleSlot');
  select.innerHTML = '<option>Loading...</option>';
  if (!doctorId || !date) { select.innerHTML = '<option>Select doctor/date</option>'; return; }

  fetch(`dashboard.php?action=get_slots&doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`)
    .then(r => r.json())
    .then(j => {
      select.innerHTML = '';
      if (!j || j.status === 'error') { select.innerHTML = '<option>Error</option>'; qs('#rescheduleMsg').textContent = 'Failed to load slots'; return; }
      const arr = j.slots || [];
      if (!Array.isArray(arr) || arr.length === 0) {
        select.innerHTML = '<option value="">No slots available</option>'; qs('#rescheduleMsg').textContent = 'No slots on this date'; return;
      }
      select.appendChild(new Option('Select slot',''));
      arr.forEach(s => {
        const opt = new Option(s.label || s.slot, s.slot || s.label);
        if (s.available === false) opt.disabled = true;
        select.appendChild(opt);
      });
      if (preselect) select.value = preselect;
      qs('#rescheduleMsg').textContent = '';
    })
    .catch(err => { console.error('loadRescheduleSlots error', err); select.innerHTML = '<option>Error</option>'; qs('#rescheduleMsg').textContent = 'Failed to load slots'; });
}

qs('#rescheduleDate').addEventListener('change', ()=> loadRescheduleSlots(qs('#rescheduleDate').value));

qs('#saveReschedule').addEventListener('click', function(){
  const form = qs('#rescheduleForm');
  const fd = new FormData(form);
  fetch('dashboard.php?action=reschedule_appointment', { method:'POST', body: fd })
    .then(r => safeJsonPromise(r))
    .then(j => {
      if (!j) { alert('Unexpected response'); return; }
      if (j.status === 'success') {
        alert(j.message || 'Rescheduled');
        // reload tables and close modal
        const modalEl = qs('#rescheduleModal');
        const bs = bootstrap.Modal.getInstance(modalEl);
        if (bs) bs.hide();
        upPage = 1; histPage = 1;
        loadUpcoming(); loadHistory();
      } else {
        alert('Error: ' + (j.message || 'Failed to reschedule'));
      }
    })
    .catch(err => { console.error('reschedule error', err); alert('Request failed'); });
});

/* --------------------------
   Initial loads
   -------------------------- */
document.addEventListener('DOMContentLoaded', ()=>{
  // initial loads: upcoming & history use separate queries but same endpoint; we call twice with different pages/search
  loadUpcoming();
  loadHistory();
  buildCalendar(currentCalendarYear, currentCalendarMonth, qs('#doctorSelect').value);
});
</script>

<!-- DataTables CSS/JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  jQuery('.datatable').DataTable({
    pageLength: 5,
    lengthMenu: [5,10,25,50],
    ordering: true,
    responsive: true
  });
});
</script>

</body>
</html>
