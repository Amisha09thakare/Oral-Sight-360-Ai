<?php
// doctor_dashboard.php (UPDATED)
// KEEP A BACKUP of your original file before replacing.
// Requirements: session doctor_id, ../connection/db.php available.

session_start();
include("../connection/db.php");

if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_login.php");
    exit;
}
$doctor_id = (int) $_SESSION['doctor_id'];

// Ensure upload directory exists
$upload_base = __DIR__ . "/../uploads/shared_cases";
if (!is_dir($upload_base)) mkdir($upload_base, 0755, true);

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Get doctor details
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Initialize messages
$success = $error = "";

// Slot timings mapping (for display)
$slot_times = [
    'slot1' => '09:00 AM - 10:00 AM',
    'slot2' => '11:00 AM - 12:00 PM',
    'slot3' => '02:00 PM - 03:00 PM',
    'slot4' => '04:00 PM - 05:00 PM'
];

// --- Utility: sanitize output
function e($v){ return htmlspecialchars($v ?? ''); }

// ---------------------------
// Ensure shared_cases and shared_cases_files tables exist (safe to run multiple times)
// ---------------------------
$conn->query("
CREATE TABLE IF NOT EXISTS shared_cases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_type VARCHAR(20) NOT NULL,
  case_id INT NOT NULL,
  shared_by INT NOT NULL,
  shared_with INT NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_read TINYINT(1) DEFAULT 0,
  INDEX(shared_with),
  INDEX(shared_by),
  FOREIGN KEY (shared_by) REFERENCES admins(id) ON DELETE CASCADE,
  FOREIGN KEY (shared_with) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conn->query("
CREATE TABLE IF NOT EXISTS shared_cases_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shared_case_id INT NOT NULL,
  file_path VARCHAR(1024) NOT NULL,
  original_name VARCHAR(255) DEFAULT '',
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(shared_case_id),
  FOREIGN KEY (shared_case_id) REFERENCES shared_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ---------------------------
// Handle POST actions: add_patient, assign_appointment, share_case (with optional uploads)
// ---------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // basic CSRF check for all mutating forms
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if ($posted_csrf !== $csrf_token) {
        $error = "Invalid request (CSRF).";
    } else {
        // Add patient
        if (isset($_POST['add_patient'])) {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $dob = trim($_POST['dob'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $age = (int) ($_POST['age'] ?? 0);
            $gender = trim($_POST['gender'] ?? '');

            // Basic validation
            if ($name === '' || $email === '' || $dob === '' || $phone === '') {
                $error = "Please fill required fields.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email.";
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                $error = "DOB must be YYYY-MM-DD.";
            } else {
                // check unique email
                $stmt = $conn->prepare("SELECT id FROM patients WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    $error = "Email already exists.";
                    $stmt->close();
                } else {
                    $stmt->close();
                    // create account: auto password = dob(YYYYMMDD) + last digit phone
                    $autoPass = date('Ymd', strtotime($dob)) . substr($phone, -1);
                    $passwordHash = password_hash($autoPass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO patients (name, email, password, age, gender, phone, dob, doctor_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("sssisssi", $name, $email, $passwordHash, $age, $gender, $phone, $dob, $doctor_id);
                    if ($stmt->execute()) {
                        $success = "Patient added. Auto password: " . e($autoPass);
                        $stmt->close();
                    } else {
                        $error = "Database error while adding patient.";
                        $stmt->close();
                    }
                }
            }
        }

        // Assign appointment (doctor assigns patient directly into a slot)
        if (isset($_POST['assign_appointment'])) {
            $patient_id_post = (int) ($_POST['patient_id'] ?? 0);
            $appointment_date = trim($_POST['appointment_date'] ?? '');
            $slot = trim($_POST['slot'] ?? '');
            if (!$patient_id_post || !$appointment_date || $slot === '') {
                $error = "Missing fields for assign appointment.";
            } else {
                // prevent conflicts
                $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND slot=? AND status IN ('pending','approved','booked')");
                $stmt->bind_param("iss", $doctor_id, $appointment_date, $slot);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $stmt->close();
                    $error = "Slot already taken.";
                } else {
                    $stmt->close();
                    $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, slot, status) VALUES (?, ?, ?, ?, 'booked')");
                    $stmt->bind_param("iiss", $patient_id_post, $doctor_id, $appointment_date, $slot);
                    if ($stmt->execute()) {
                        $success = "Appointment assigned successfully.";
                        $stmt->close();
                    } else {
                        $error = "DB error assigning appointment.";
                        $stmt->close();
                    }
                }
            }
        }

        // Share case (doctor -> doctor) with optional file uploads
        if (isset($_POST['share_case'])) {
            $case_type = trim($_POST['case_type'] ?? 'scan'); // 'scan' or 'tplan'
            $case_id = (int) ($_POST['case_id'] ?? 0);
            $shared_with = (int) ($_POST['shared_with'] ?? 0);
            $notes = trim($_POST['share_notes'] ?? '');
            if (!$case_id || !$shared_with) {
                $error = "Select a case and a doctor to share with.";
            } else {
                // Check if this case has already been shared with this doctor to prevent duplicates
                $stmt = $conn->prepare("SELECT id FROM shared_cases WHERE case_type = ? AND case_id = ? AND shared_by = ? AND shared_with = ?");
                $stmt->bind_param("siii", $case_type, $case_id, $doctor_id, $shared_with);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $stmt->close();
                    $error = "This case has already been shared with this doctor.";
                } else {
                    $stmt->close();
                    
                    // insert into shared_cases
                    $stmt = $conn->prepare("INSERT INTO shared_cases (case_type, case_id, shared_by, shared_with, notes) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("siiis", $case_type, $case_id, $doctor_id, $shared_with, $notes);
                    if ($stmt->execute()) {
                        $shared_case_id = (int)$stmt->insert_id;
                        $stmt->close();

                        // handle file uploads (multiple)
                        if (!empty($_FILES['share_files']) && is_array($_FILES['share_files']['tmp_name'])) {
                            $files = $_FILES['share_files'];
                            for ($i = 0; $i < count($files['tmp_name']); $i++) {
                                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                                $tmp = $files['tmp_name'][$i];
                                $orig = basename($files['name'][$i]);
                                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                                // allowed types
                                $allowed = ['pdf','jpg','jpeg','png','doc','docx'];
                                if (!in_array($ext, $allowed)) continue;
                                $safeName = bin2hex(random_bytes(8)) . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $orig);
                                $destRel = "uploads/shared_cases/{$safeName}";
                                $destAbs = __DIR__ . "/../" . $destRel;
                                if (move_uploaded_file($tmp, $destAbs)) {
                                    // insert file record
                                    $stmt2 = $conn->prepare("INSERT INTO shared_cases_files (shared_case_id, file_path, original_name) VALUES (?, ?, ?)");
                                    $stmt2->bind_param("iss", $shared_case_id, $destRel, $orig);
                                    $stmt2->execute();
                                    $stmt2->close();
                                }
                            }
                        }
                        $success = "Case shared successfully.";
                    } else {
                        $error = "DB error while sharing case.";
                        $stmt->close();
                    }
                }
            }
        }
        
        // Delete treatment plan
        if (isset($_POST['delete_tplan'])) {
            $tplan_id = (int) ($_POST['tplan_id'] ?? 0);
            if ($tplan_id) {
                // Verify the treatment plan belongs to this doctor
                $stmt = $conn->prepare("SELECT id FROM treatment_plans WHERE id = ? AND doctor_id = ?");
                $stmt->bind_param("ii", $tplan_id, $doctor_id);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $stmt->close();
                    // Delete the treatment plan
                    $stmt = $conn->prepare("DELETE FROM treatment_plans WHERE id = ?");
                    $stmt->bind_param("i", $tplan_id);
                    if ($stmt->execute()) {
                        $success = "Treatment plan deleted successfully.";
                    } else {
                        $error = "Error deleting treatment plan.";
                    }
                    $stmt->close();
                } else {
                    $stmt->close();
                    $error = "Treatment plan not found or you don't have permission to delete it.";
                }
            }
        }
        
        // Mark shared case as read
        if (isset($_POST['mark_read'])) {
            $shared_id = (int) ($_POST['shared_id'] ?? 0);
            if ($shared_id) {
                $stmt = $conn->prepare("UPDATE shared_cases SET is_read = 1 WHERE id = ? AND shared_with = ?");
                $stmt->bind_param("ii", $shared_id, $doctor_id);
                if ($stmt->execute()) {
                    $success = "Case marked as read.";
                } else {
                    $error = "Error updating case status.";
                }
                $stmt->close();
            }
        }
        
        // Reply to shared case
        if (isset($_POST['reply_case'])) {
            $shared_id = (int) ($_POST['shared_id'] ?? 0);
            $reply_notes = trim($_POST['reply_notes'] ?? '');
            if ($shared_id && $reply_notes) {
                // Get the original shared case
                $stmt = $conn->prepare("SELECT * FROM shared_cases WHERE id = ? AND shared_with = ?");
                $stmt->bind_param("ii", $shared_id, $doctor_id);
                $stmt->execute();
                $original_case = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($original_case) {
                    // Create a reply (share back to the original sender)
                    $stmt = $conn->prepare("INSERT INTO shared_cases (case_type, case_id, shared_by, shared_with, notes) VALUES (?, ?, ?, ?, ?)");
                    $reply_note = "RE: " . $original_case['notes'] . "\n\nResponse: " . $reply_notes;
                    $stmt->bind_param("siiss", $original_case['case_type'], $original_case['case_id'], $doctor_id, $original_case['shared_by'], $reply_note);
                    if ($stmt->execute()) {
                        $success = "Reply sent successfully.";
                    } else {
                        $error = "Error sending reply.";
                    }
                    $stmt->close();
                } else {
                    $error = "Case not found.";
                }
            } else {
                $error = "Please enter a reply message.";
            }
        }
        
        // Create or update treatment plan
        if (isset($_POST['save_tplan'])) {
            $patient_id_post = (int) ($_POST['tplan_patient_id'] ?? 0);
            $total_weeks = (int) ($_POST['total_weeks'] ?? 0);
            $start_date = trim($_POST['start_date'] ?? '');
            $notes = trim($_POST['tplan_notes'] ?? '');
            
            if (!$patient_id_post || !$total_weeks || !$start_date) {
                $error = "Please fill all required fields for treatment plan.";
            } else {
                // Check if patient belongs to this doctor
                $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ? AND doctor_id = ?");
                $stmt->bind_param("ii", $patient_id_post, $doctor_id);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows === 0) {
                    $stmt->close();
                    $error = "Patient not found or doesn't belong to you.";
                } else {
                    $stmt->close();
                    
                    // Check if treatment plan already exists
                    $stmt = $conn->prepare("SELECT id FROM treatment_plans WHERE patient_id = ?");
                    $stmt->bind_param("i", $patient_id_post);
                    $stmt->execute();
                    $stmt->store_result();
                    
                    if ($stmt->num_rows > 0) {
                        $stmt->close();
                        // Update existing treatment plan
                        $stmt = $conn->prepare("UPDATE treatment_plans SET total_weeks = ?, start_date = ?, notes = ? WHERE patient_id = ? AND doctor_id = ?");
                        $stmt->bind_param("issii", $total_weeks, $start_date, $notes, $patient_id_post, $doctor_id);
                    } else {
                        $stmt->close();
                        // Create new treatment plan
                        $stmt = $conn->prepare("INSERT INTO treatment_plans (patient_id, doctor_id, total_weeks, start_date, notes) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("iiiss", $patient_id_post, $doctor_id, $total_weeks, $start_date, $notes);
                    }
                    
                    if ($stmt->execute()) {
                        $success = "Treatment plan saved successfully.";
                        $stmt->close();
                    } else {
                        $error = "Error saving treatment plan: " . $stmt->error;
                        $stmt->close();
                    }
                }
            }
        }
    } // end CSRF else
} // end POST handling

// ---------------------------
// Handle GET actions (approve/reject/complete) - using GET keeps existing UI links working
// ---------------------------
if (isset($_GET['approve'])) {
    $aid = (int) $_GET['approve'];
    $stmt = $conn->prepare("UPDATE appointments SET status='approved' WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $aid, $doctor_id);
    $stmt->execute();
    $stmt->close();
    header("Location: doctor_dashboard.php");
    exit;
}
if (isset($_GET['reject'])) {
    $aid = (int) $_GET['reject'];
    // Mark appointment as cancelled (rejected)
    $stmt = $conn->prepare("UPDATE appointments SET status='cancelled' WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $aid, $doctor_id);
    $stmt->execute();
    $stmt->close();
    header("Location: doctor_dashboard.php");
    exit;
}
if (isset($_GET['complete'])) {
    $aid = (int) $_GET['complete'];
    // Mark appointment completed and increment visits in treatment_progress
    $stmt = $conn->prepare("SELECT patient_id FROM appointments WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $aid, $doctor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $patient_id_for_complete = (int)$row['patient_id'];
        $stmt->close();
        // update appointment status
        $stmt = $conn->prepare("UPDATE appointments SET status='completed' WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $aid, $doctor_id);
        $stmt->execute();
        $stmt->close();

        // increment or create treatment_progress
        $stmt = $conn->prepare("SELECT id FROM treatment_progress WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id_for_complete);
        $stmt->execute();
        $res2 = $stmt->get_result();
        if ($r2 = $res2->fetch_assoc()) {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE treatment_progress SET visits = visits + 1 WHERE patient_id = ?");
            $stmt->bind_param("i", $patient_id_for_complete);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt->close();
            // get total_weeks from latest treatment plan if exists
            $total_weeks = 0;
            $stmt = $conn->prepare("SELECT total_weeks FROM treatment_plans WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->bind_param("i", $patient_id_for_complete);
            $stmt->execute();
            $tmp = $stmt->get_result()->fetch_assoc();
            if ($tmp) $total_weeks = (int)$tmp['total_weeks'];
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO treatment_progress (patient_id, total_weeks, completed_weeks, visits) VALUES (?, ?, 0, 1)");
            $stmt->bind_param("ii", $patient_id_for_complete, $total_weeks);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $stmt->close();
    }
    header("Location: doctor_dashboard.php");
    exit;
}

// ---------------------------
// Fetch metrics and data for page
// ---------------------------
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM patients WHERE doctor_id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$totalPatients = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = ? AND status IN ('pending','approved','booked') AND appointment_date >= CURDATE()");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$upcomingAppointments = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM scans WHERE doctor_id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$scansUploaded = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Patients listing (pagination)
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 5; // Changed to 5 records per page
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM patients WHERE doctor_id = ? AND name LIKE ?");
$search_param = "%".$search."%";
$stmt->bind_param("is", $doctor_id, $search_param);
$stmt->execute();
$totalPatientsCount = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalPages = ceil(max(1, $totalPatientsCount) / $limit);

$stmt = $conn->prepare("SELECT * FROM patients WHERE doctor_id = ? AND name LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("isii", $doctor_id, $search_param, $limit, $offset);
$stmt->execute();
$patients = $stmt->get_result();
$stmt->close();

// Appointments: server-side search + pagination for large data sets
$appt_search = trim($_GET['appt_search'] ?? '');
$appt_page = isset($_GET['appt_page']) && is_numeric($_GET['appt_page']) ? (int) $_GET['appt_page'] : 1;
$appt_limit = 5; // Changed to 5 records per page
$appt_offset = ($appt_page - 1) * $appt_limit;

$where_clauses = ["ap.doctor_id = ?"];
$types = "i";
$params = [$doctor_id];

if ($appt_search !== '') {
    $where_clauses[] = "(p.name LIKE ? OR ap.appointment_date LIKE ? OR ap.slot LIKE ? OR ap.status LIKE ?)";
    $like = "%".$appt_search."%";
    $types .= "ssss";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
$where_sql = implode(" AND ", $where_clauses);

// Total count appointments
$countSql = "SELECT COUNT(*) as c FROM appointments ap JOIN patients p ON ap.patient_id = p.id WHERE $where_sql";
$stmt = $conn->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalAppts = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
$totalApptPages = ceil(max(1, $totalAppts) / $appt_limit);

// Fetch page of appointments (both upcoming & history in different tabs)
$sql = "SELECT ap.*, p.name as patient_name FROM appointments ap JOIN patients p ON ap.patient_id = p.id WHERE $where_sql ORDER BY ap.appointment_date ASC, FIELD(ap.status,'pending','approved','booked','completed','cancelled'), ap.slot ASC LIMIT ? OFFSET ?";
$types2 = $types . "ii";
$params2 = $params;
$params2[] = $appt_limit;
$params2[] = $appt_offset;
$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$appointments_paginated = $stmt->get_result();
$stmt->close();

// Appointment history (for small listing elsewhere if needed)
$stmt = $conn->prepare("SELECT ap.*, p.name FROM appointments ap JOIN patients p ON ap.patient_id = p.id WHERE ap.doctor_id = ? AND ap.status IN ('completed','cancelled') ORDER BY ap.appointment_date DESC LIMIT 200");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$history_requests = $stmt->get_result();
$stmt->close();

// Treatment Plans: simple paging and search
$tplan_search = trim($_GET['tplan_search'] ?? '');
$tplan_page = isset($_GET['tplan_page']) && is_numeric($_GET['tplan_page']) ? (int) $_GET['tplan_page'] : 1;
$tplan_limit = 5; // Changed to 5 records per page
$tplan_offset = ($tplan_page - 1) * $tplan_limit;

$t_where = "tp.doctor_id = ?";
$t_types = "i";
$t_params = [$doctor_id];
if ($tplan_search !== '') {
    $t_where .= " AND (p.name LIKE ? OR tp.start_date LIKE ?)";
    $t_types .= "ss";
    $t_params[] = "%".$tplan_search."%";
    $t_params[] = "%".$tplan_search."%";
}

// count
$countSql = "SELECT COUNT(*) as c FROM treatment_plans tp JOIN patients p ON tp.patient_id = p.id WHERE $t_where";
$stmt = $conn->prepare($countSql);
$stmt->bind_param($t_types, ...$t_params);
$stmt->execute();
$totalTPlans = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
$totalTPlanPages = ceil(max(1, $totalTPlans) / $tplan_limit);

// fetch
$sql = "SELECT tp.*, p.name as patient_name, IFNULL(tpr.completed_weeks, 0) as completed_weeks FROM treatment_plans tp JOIN patients p ON tp.patient_id = p.id LEFT JOIN treatment_progress tpr ON tp.patient_id = tpr.patient_id WHERE $t_where ORDER BY tp.id DESC LIMIT ? OFFSET ?";
$t_types2 = $t_types . "ii";
$t_params2 = $t_params;
$t_params2[] = $tplan_limit;
$t_params2[] = $tplan_offset;
$stmt = $conn->prepare($sql);
$stmt->bind_param($t_types2, ...$t_params2);
$stmt->execute();
$treatment_plans = $stmt->get_result();
$stmt->close();

// Scans for share modal (limit)
$stmt = $conn->prepare("SELECT s.id, s.scan_file, s.patient_id, p.name as patient_name FROM scans s JOIN patients p ON s.patient_id = p.id WHERE s.doctor_id = ? ORDER BY s.created_at DESC LIMIT 200");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$scans = $stmt->get_result();
$stmt->close();

// Other doctors list for sharing
$stmt = $conn->prepare("SELECT id, username FROM admins WHERE id != ? AND (role='doctor' OR role='admin') ORDER BY username ASC");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$otherDoctors = $stmt->get_result();
$stmt->close();

// Cases shared with me (paginated small set)
$shared_page = isset($_GET['shared_page']) && is_numeric($_GET['shared_page']) ? (int) $_GET['shared_page'] : 1;
$shared_limit = 5; // Changed to 5 records per page
$shared_offset = ($shared_page - 1) * $shared_limit;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM shared_cases sc JOIN admins a ON sc.shared_by = a.id WHERE sc.shared_with = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$totalSharedCount = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalSharedPages = ceil(max(1, $totalSharedCount) / $shared_limit);

$stmt = $conn->prepare("SELECT sc.*, a.username as shared_by_name FROM shared_cases sc JOIN admins a ON sc.shared_by = a.id WHERE sc.shared_with = ? ORDER BY sc.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $doctor_id, $shared_limit, $shared_offset);
$stmt->execute();
$shared_rows = $stmt->get_result();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Doctor Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f4f7fa; }
.sidebar { height: 100vh; background: linear-gradient(180deg, #1565c0, #42a5f5); color: white; padding-top: 20px; position: fixed; left: 0; top: 0; width: 220px; }
.sidebar a { color: white; text-decoration: none; display: block; padding: 12px; border-radius: 8px; margin: 5px 10px; }
.sidebar a:hover { background: rgba(255,255,255,0.2); }
.main { margin-left: 240px; padding: 20px; }
.card-metric { text-align: center; padding: 20px; }
.card-metric i { font-size: 2rem; color: #1565c0; }
.progress { height: 25px; }
.table-small td, .table-small th { padding: .4rem .6rem; font-size: .9rem; }
.small-note { font-size: .85rem; color: #6c757d; }
.file-list a { display:block; }
.progress-sm { height: 15px; }
.unread-case { background-color: #f0f8ff; font-weight: bold; }
.pagination { margin: 0; }
.pagination .page-item { margin: 0 2px; }
.pagination .page-link { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
</style>
</head>
<body>
<div class="sidebar">
<h4 class="text-center">🦷 Oral Sight AI 360</h4>
<a href="#"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a href="#patients"><i class="bi bi-people"></i> Patients</a>
<a href="#assign"><i class="bi bi-calendar-plus"></i> Assign Appointment</a>
<a href="#appointments"><i class="bi bi-calendar-check"></i> Appointments</a>
<a href="#plans"><i class="bi bi-list-task"></i> Treatment Plans</a>
<a href="#scans"><i class="bi bi-upload"></i> Upload Scans</a>
<a href="#shared"><i class="bi bi-share"></i> Shared With Me</a>
<a href="doctor_logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main">
<div class="d-flex justify-content-between align-items-center mb-4">
<h3>Welcome, Dr. <?= e($doctor['username']); ?></h3>
<a href="doctor_logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
</div>

<!-- Metrics -->
<div class="row g-3 mb-4">
<div class="col-md-4"><div class="card card-metric shadow-sm"><i class="bi bi-people"></i><h4><?= $totalPatients; ?></h4><p>Total Patients</p></div></div>
<div class="col-md-4"><div class="card card-metric shadow-sm"><i class="bi bi-calendar-event"></i><h4><?= $upcomingAppointments; ?></h4><p>Upcoming Appointments</p></div></div>
<div class="col-md-4"><div class="card card-metric shadow-sm"><i class="bi bi-upload"></i><h4><?= $scansUploaded; ?></h4><p>Scans Uploaded</p></div></div>
</div>

<!-- Add Patient -->
<div class="card shadow-sm p-3 mb-4" id="patients">
<h5>Add Patient</h5>
<?php if($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="POST" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<div class="col-md-4"><label>Name</label><input type="text" name="name" class="form-control" required></div>
<div class="col-md-4"><label>Email</label><input type="email" name="email" class="form-control" required></div>
<div class="col-md-4"><label>DOB</label><input type="date" name="dob" class="form-control" required></div>
<div class="col-md-4"><label>Phone</label><input type="text" name="phone" class="form-control" required></div>
<div class="col-md-2"><label>Age</label><input type="number" name="age" class="form-control" required></div>
<div class="col-md-2"><label>Gender</label><select name="gender" class="form-select"><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
<div class="col-md-12"><button type="submit" name="add_patient" class="btn btn-success">Add Patient</button></div>
</form>
</div>

<!-- Patients Table with Search and Pagination -->
<div class="card shadow-sm p-3 mb-4">
    <h5>All Patients</h5>
    
    <form class="row g-2 mb-3" method="GET" action="doctor_dashboard.php#patients">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search by name" value="<?= e($search) ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary">Search</button>
        </div>
    </form>
    
    <div class="table-responsive">
        <table class="table table-striped table-small">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>DOB</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($patients->num_rows === 0) {
                    echo "<tr><td colspan='7' class='text-center text-muted'>No patients found.</td></tr>";
                } else {
                    while ($p = $patients->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>".e($p['name'])."</td>";
                        echo "<td>".e($p['email'])."</td>";
                        echo "<td>".e($p['phone'])."</td>";
                        echo "<td>".e($p['age'])."</td>";
                        echo "<td>".e(ucfirst($p['gender']))."</td>";
                        echo "<td>".e($p['dob'])."</td>";
                        echo "<td>".e($p['created_at'])."</td>";
                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $page-1 ?>#patients">Previous</a></li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $i ?>#patients"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $page+1 ?>#patients">Next</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Assign Appointment -->
<div class="card shadow-sm p-3 mb-4" id="assign">
<h5>Assign Appointment</h5>
<form method="POST" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<div class="col-md-4">
<label>Patient</label>
<select name="patient_id" class="form-select" required>
<option value="">Select Patient</option>
<?php
$stmt = $conn->prepare("SELECT id, name FROM patients WHERE doctor_id = ? ORDER BY name");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$patients_list = $stmt->get_result();
while ($p = $patients_list->fetch_assoc()) {
    echo "<option value='".e($p['id'])."'>".e($p['name'])."</option>";
}
$stmt->close();
?>
</select>
</div>
<div class="col-md-3"><label>Date</label><input type="date" name="appointment_date" class="form-control" required min="<?= date('Y-m-d'); ?>"></div>
<div class="col-md-3">
<label>Slot</label>
<select name="slot" class="form-select" required>
<option value="">Select Slot</option>
<option value="slot1">09:00 AM - 10:00 AM</option>
<option value="slot2">11:00 AM - 12:00 PM</option>
<option value="slot3">02:00 PM - 03:00 PM</option>
<option value="slot4">04:00 PM - 05:00 PM</option>
</select>
</div>
<div class="col-md-2 d-flex align-items-end"><button type="submit" name="assign_appointment" class="btn btn-primary">Assign</button></div>
</form>
</div>

<!-- Appointments -->
<div class="card shadow-sm p-3 mb-4" id="appointments">
<h5>Appointments</h5>

<!-- Tab navigation -->
<ul class="nav nav-tabs mb-3" id="appointmentTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">Upcoming</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">History</button>
    </li>
</ul>

<!-- Search form -->
<form class="row g-2 mb-3" method="GET" action="doctor_dashboard.php#appointments">
    <div class="col-md-4">
        <input type="text" name="appt_search" class="form-control" placeholder="Search appointments..." value="<?= e($appt_search) ?>">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary">Search</button>
    </div>
</form>

<div class="tab-content" id="appointmentTabsContent">
    <!-- Upcoming Appointments Tab -->
    <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-striped table-small">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Slot</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $hasUpcoming = false;
                    while ($ap = $appointments_paginated->fetch_assoc()) {
                        if (!in_array($ap['status'], ['pending', 'approved', 'booked'])) continue;
                        $hasUpcoming = true;
                        echo "<tr>";
                        echo "<td>".e($ap['patient_name'])."</td>";
                        echo "<td>".e($ap['appointment_date'])."</td>";
                        echo "<td>".e($slot_times[$ap['slot']] ?? $ap['slot'])."</td>";
                        echo "<td><span class='badge bg-";
                        if ($ap['status'] == 'pending') echo "warning";
                        elseif ($ap['status'] == 'approved' || $ap['status'] == 'booked') echo "success";
                        else echo "secondary";
                        echo "'>".e(ucfirst($ap['status']))."</span></td>";
                        echo "<td>";
                        if ($ap['status'] == 'pending') {
                            echo "<a href='?approve=".e($ap['id'])."' class='btn btn-sm btn-success'>Approve</a> ";
                            echo "<a href='?reject=".e($ap['id'])."' class='btn btn-sm btn-danger'>Reject</a>";
                        } else {
                            echo "<a href='?complete=".e($ap['id'])."' class='btn btn-sm btn-primary'>Complete</a> ";
                            echo "<a href='?reject=".e($ap['id'])."' class='btn btn-sm btn-danger'>Cancel</a>";
                        }
                        echo "</td>";
                        echo "</tr>";
                    }
                    if (!$hasUpcoming) {
                        echo "<tr><td colspan='5' class='text-center text-muted'>No upcoming appointments.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- History Tab -->
    <div class="tab-pane fade" id="history" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-striped table-small">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Slot</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $hasHistory = false;
                    while ($ap = $history_requests->fetch_assoc()) {
                        $hasHistory = true;
                        echo "<tr>";
                        echo "<td>".e($ap['name'])."</td>";
                        echo "<td>".e($ap['appointment_date'])."</td>";
                        echo "<td>".e($slot_times[$ap['slot']] ?? $ap['slot'])."</td>";
                        echo "<td><span class='badge bg-";
                        if ($ap['status'] == 'completed') echo "success";
                        elseif ($ap['status'] == 'cancelled') echo "danger";
                        else echo "secondary";
                        echo "'>".e(ucfirst($ap['status']))."</span></td>";
                        echo "</tr>";
                    }
                    if (!$hasHistory) {
                        echo "<tr><td colspan='4' class='text-center text-muted'>No appointment history.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalApptPages > 1): ?>
<nav>
    <ul class="pagination justify-content-center">
        <?php if ($appt_page > 1): ?>
        <li class="page-item"><a class="page-link" href="?appt_search=<?= urlencode($appt_search) ?>&appt_page=<?= $appt_page-1 ?>#appointments">Previous</a></li>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $totalApptPages; $i++): ?>
        <li class="page-item <?= ($i === $appt_page) ? 'active' : '' ?>">
            <a class="page-link" href="?appt_search=<?= urlencode($appt_search) ?>&appt_page=<?= $i ?>#appointments"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        
        <?php if ($appt_page < $totalApptPages): ?>
        <li class="page-item"><a class="page-link" href="?appt_search=<?= urlencode($appt_search) ?>&appt_page=<?= $appt_page+1 ?>#appointments">Next</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>
</div>

<!-- Treatment Plans -->
<div class="card shadow-sm p-3 mb-4" id="plans">
<h5>Treatment Plans</h5>

<!-- Search form -->
<form class="row g-2 mb-3" method="GET" action="doctor_dashboard.php#plans">
    <div class="col-md-4">
        <input type="text" name="tplan_search" class="form-control" placeholder="Search by patient name or start date" value="<?= e($tplan_search) ?>">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary">Search</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped table-small">
        <thead class="table-light">
            <tr>
                <th>Patient</th>
                <th>Total Weeks</th>
                <th>Completed</th>
                <th>Start Date</th>
                <th>Progress</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($treatment_plans->num_rows === 0) {
                echo "<tr><td colspan='7' class='text-center text-muted'>No treatment plans found.</td></tr>";
            } else {
                while ($tp = $treatment_plans->fetch_assoc()) {
                    $completed = (int)($tp['completed_weeks'] ?? 0);
                    $total = (int)$tp['total_weeks'];
                    $percent = $total > 0 ? min(100, round(($completed / $total) * 100)) : 0;
                    
                    echo "<tr>";
                    echo "<td>".e($tp['patient_name'])."</td>";
                    echo "<td>".e($tp['total_weeks'])."</td>";
                    echo "<td>".e($completed)."</td>";
                    echo "<td>".e($tp['start_date'])."</td>";
                    echo "<td>";
                    echo "<div class='progress progress-sm'>";
                    echo "<div class='progress-bar' role='progressbar' style='width: {$percent}%' aria-valuenow='{$percent}' aria-valuemin='0' aria-valuemax='100'></div>";
                    echo "</div>";
                    echo "<small>{$percent}%</small>";
                    echo "</td>";
                    echo "<td>".e(substr($tp['notes'] ?? '', 0, 50)).(strlen($tp['notes'] ?? '') > 50 ? '...' : '')."</td>";
                    echo "<td>";
                    echo "<form method='POST' style='display:inline;'>";
                    echo "<input type='hidden' name='csrf_token' value='{$csrf_token}'>";
                    echo "<input type='hidden' name='tplan_id' value='".e($tp['id'])."'>";
                    echo "<button type='submit' name='delete_tplan' class='btn btn-sm btn-danger' onclick=\"return confirm('Delete this treatment plan?')\">Delete</button>";
                    echo "</form>";
                    echo "</td>";
                    echo "</tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalTPlanPages > 1): ?>
<nav>
    <ul class="pagination justify-content-center">
        <?php if ($tplan_page > 1): ?>
        <li class="page-item"><a class="page-link" href="?tplan_search=<?= urlencode($tplan_search) ?>&tplan_page=<?= $tplan_page-1 ?>#plans">Previous</a></li>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $totalTPlanPages; $i++): ?>
        <li class="page-item <?= ($i === $tplan_page) ? 'active' : '' ?>">
            <a class="page-link" href="?tplan_search=<?= urlencode($tplan_search) ?>&tplan_page=<?= $i ?>#plans"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        
        <?php if ($tplan_page < $totalTPlanPages): ?>
        <li class="page-item"><a class="page-link" href="?tplan_search=<?= urlencode($tplan_search) ?>&tplan_page=<?= $tplan_page+1 ?>#plans">Next</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Create/Edit Treatment Plan -->
<div class="mt-4">
    <h6>Create/Edit Treatment Plan</h6>
    <form method="POST" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <div class="col-md-4">
            <label>Patient</label>
            <select name="tplan_patient_id" class="form-select" required>
                <option value="">Select Patient</option>
                <?php
                $stmt = $conn->prepare("SELECT id, name FROM patients WHERE doctor_id = ? ORDER BY name");
                $stmt->bind_param("i", $doctor_id);
                $stmt->execute();
                $patients_list = $stmt->get_result();
                while ($p = $patients_list->fetch_assoc()) {
                    echo "<option value='".e($p['id'])."'>".e($p['name'])."</option>";
                }
                $stmt->close();
                ?>
            </select>
        </div>
        <div class="col-md-2">
            <label>Total Weeks</label>
            <input type="number" name="total_weeks" class="form-control" required min="1">
        </div>
        <div class="col-md-3">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" required>
        </div>
        <div class="col-md-12">
            <label>Notes</label>
            <textarea name="tplan_notes" class="form-control" rows="3"></textarea>
        </div>
        <div class="col-md-12">
            <button type="submit" name="save_tplan" class="btn btn-success">Save Treatment Plan</button>
        </div>
    </form>
</div>
</div>

<!-- Upload Scans -->
<div class="card shadow-sm p-3 mb-4" id="scans">
<h5>Upload Scans</h5>
<form method="POST" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<div class="col-md-4">
<label>Patient</label>
<select name="scan_patient_id" class="form-select" required>
<option value="">Select Patient</option>
<?php
$stmt = $conn->prepare("SELECT id, name FROM patients WHERE doctor_id = ? ORDER BY name");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$patients_list = $stmt->get_result();
while ($p = $patients_list->fetch_assoc()) {
    echo "<option value='".e($p['id'])."'>".e($p['name'])."</option>";
}
$stmt->close();
?>
</select>
</div>
<div class="col-md-4">
<label>Scan File (PDF, JPG, PNG)</label>
<input type="file" name="scan_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
</div>
<div class="col-md-4 d-flex align-items-end"><button type="submit" name="upload_scan" class="btn btn-primary">Upload</button></div>
</form>
</div>

<!-- Share Case -->
<div class="card shadow-sm p-3 mb-4">
<h5>Share Case</h5>
<form method="POST" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<div class="col-md-3">
<label>Case Type</label>
<select name="case_type" class="form-select" required>
<option value="scan">Scan</option>
<option value="tplan">Treatment Plan</option>
</select>
</div>
<div class="col-md-3">
<label>Case</label>
<select name="case_id" class="form-select" required>
<option value="">Select Case</option>
<?php
// Scans
$stmt = $conn->prepare("SELECT s.id, s.scan_file, p.name as patient_name FROM scans s JOIN patients p ON s.patient_id = p.id WHERE s.doctor_id = ? ORDER BY s.created_at DESC");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$scans = $stmt->get_result();
while ($s = $scans->fetch_assoc()) {
    echo "<option value='".e($s['id'])."' data-type='scan'>Scan: ".e($s['patient_name'])." (".e(basename($s['scan_file'])).")</option>";
}
$stmt->close();

// Treatment Plans
$stmt = $conn->prepare("SELECT tp.id, p.name as patient_name FROM treatment_plans tp JOIN patients p ON tp.patient_id = p.id WHERE tp.doctor_id = ? ORDER BY tp.id DESC");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$tplans = $stmt->get_result();
while ($tp = $tplans->fetch_assoc()) {
    echo "<option value='".e($tp['id'])."' data-type='tplan'>Treatment Plan: ".e($tp['patient_name'])."</option>";
}
$stmt->close();
?>
</select>
</div>
<div class="col-md-3">
<label>Share With</label>
<select name="shared_with" class="form-select" required>
<option value="">Select Doctor</option>
<?php
$stmt = $conn->prepare("SELECT id, username FROM admins WHERE id != ? AND (role='doctor' OR role='admin') ORDER BY username ASC");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$otherDoctors = $stmt->get_result();
while ($d = $otherDoctors->fetch_assoc()) {
    echo "<option value='".e($d['id'])."'>".e($d['username'])."</option>";
}
$stmt->close();
?>
</select>
</div>
<div class="col-md-12">
<label>Notes</label>
<textarea name="share_notes" class="form-control" rows="2"></textarea>
</div>
<div class="col-md-6">
<label>Files (optional)</label>
<input type="file" name="share_files[]" multiple class="form-control">
<small class="small-note">Additional files to share (PDF, JPG, PNG, DOC)</small>
</div>
<div class="col-md-12 d-flex align-items-end"><button type="submit" name="share_case" class="btn btn-primary">Share Case</button></div>
</form>
</div>

<!-- Shared With Me -->
<div class="card shadow-sm p-3 mb-4" id="shared">
<h5>Shared With Me</h5>

<div class="table-responsive">
    <table class="table table-striped table-small">
        <thead class="table-light">
            <tr>
                <th>Shared By</th>
                <th>Case Type</th>
                <th>Notes</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($shared_rows->num_rows === 0) {
                echo "<tr><td colspan='6' class='text-center text-muted'>No cases shared with you.</td></tr>";
            } else {
                while ($sr = $shared_rows->fetch_assoc()) {
                    echo "<tr class='".($sr['is_read'] == 0 ? 'unread-case' : '')."'>";
                    echo "<td>".e($sr['shared_by_name'])."</td>";
                    echo "<td>".e(ucfirst($sr['case_type']))."</td>";
                    echo "<td>".e(substr($sr['notes'] ?? '', 0, 50)).(strlen($sr['notes'] ?? '') > 50 ? '...' : '')."</td>";
                    echo "<td>".e($sr['created_at'])."</td>";
                    echo "<td>".($sr['is_read'] == 0 ? '<span class="badge bg-warning">Unread</span>' : '<span class="badge bg-success">Read</span>')."</td>";
                    echo "<td>";
                    echo "<form method='POST' style='display:inline;'>";
                    echo "<input type='hidden' name='csrf_token' value='{$csrf_token}'>";
                    echo "<input type='hidden' name='shared_id' value='".e($sr['id'])."'>";
                    if ($sr['is_read'] == 0) {
                        echo "<button type='submit' name='mark_read' class='btn btn-sm btn-info'>Mark Read</button> ";
                    }
                    echo "<button type='button' class='btn btn-sm btn-secondary' data-bs-toggle='modal' data-bs-target='#replyModal".e($sr['id'])."'>Reply</button>";
                    echo "</form>";
                    echo "</td>";
                    echo "</tr>";
                    
                    // Reply Modal for each shared case
                    echo "<div class='modal fade' id='replyModal".e($sr['id'])."' tabindex='-1' aria-hidden='true'>";
                    echo "<div class='modal-dialog'>";
                    echo "<div class='modal-content'>";
                    echo "<div class='modal-header'>";
                    echo "<h5 class='modal-title'>Reply to Shared Case</h5>";
                    echo "<button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>";
                    echo "</div>";
                    echo "<div class='modal-body'>";
                    echo "<form method='POST'>";
                    echo "<input type='hidden' name='csrf_token' value='{$csrf_token}'>";
                    echo "<input type='hidden' name='shared_id' value='".e($sr['id'])."'>";
                    echo "<div class='mb-3'>";
                    echo "<label class='form-label'>Reply Message</label>";
                    echo "<textarea name='reply_notes' class='form-control' rows='4' required></textarea>";
                    echo "</div>";
                    echo "<button type='submit' name='reply_case' class='btn btn-primary'>Send Reply</button>";
                    echo "</form>";
                    echo "</div>";
                    echo "</div>";
                    echo "</div>";
                    echo "</div>";
                }
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalSharedPages > 1): ?>
<nav>
    <ul class="pagination justify-content-center">
        <?php if ($shared_page > 1): ?>
        <li class="page-item"><a class="page-link" href="?shared_page=<?= $shared_page-1 ?>#shared">Previous</a></li>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $totalSharedPages; $i++): ?>
        <li class="page-item <?= ($i === $shared_page) ? 'active' : '' ?>">
            <a class="page-link" href="?shared_page=<?= $i ?>#shared"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        
        <?php if ($shared_page < $totalSharedPages): ?>
        <li class="page-item"><a class="page-link" href="?shared_page=<?= $shared_page+1 ?>#shared">Next</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>
</div>

</div> <!-- end main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh CSRF token every 30 minutes to prevent session expiration issues
setInterval(function() {
    fetch('refresh_csrf.php').then(r => r.json()).then(data => {
        if (data.csrf_token) {
            document.querySelectorAll('input[name="csrf_token"]').forEach(el => {
                el.value = data.csrf_token;
            });
        }
    });
}, 30 * 60 * 1000);
</script>
</body>
</html>