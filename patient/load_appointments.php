<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['patient_id'])) {
    http_response_code(403);
    exit("Not authorized");
}

$patient_id = (int) $_SESSION['patient_id'];

// Sanitize inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 5;
$offset = ($page - 1) * $limit;

// Build search query
$searchSql = "";
$params = [$patient_id];
$types  = "i";

if ($search !== "") {
    $searchSql = " AND (ap.slot LIKE CONCAT('%', ?, '%') OR ad.username LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $types   .= "ss";
}

// Count total appointments
$totalCount = 0;
$pages = 1;

$countSql = "
    SELECT COUNT(*) AS total
    FROM appointments ap
    JOIN admins ad ON ap.doctor_id = ad.id
    WHERE ap.patient_id = ? 
  AND ap.status IN ('pending','booked')

      $searchSql
";

if ($stmt = $conn->prepare($countSql)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $totalCount = (int)$row['total'];
    }
    $stmt->close();
}

if ($totalCount > 0) {
    $pages = ceil($totalCount / $limit);
}

// Fetch appointments
$sql = "
    SELECT ap.id, ap.appointment_date, ap.slot, ap.status, ad.username AS doctor_name
    FROM appointments ap
    JOIN admins ad ON ap.doctor_id = ad.id
    WHERE ap.patient_id = ? 
      AND ap.status IN ('pending','booked') 
      $searchSql
    ORDER BY ap.appointment_date DESC
    LIMIT ? OFFSET ?
";

$params2 = $params;
$types2  = $types . "ii";
$params2[] = $limit;
$params2[] = $offset;

$result = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($types2, ...$params2);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
}
?>

<div class="table-responsive">
  <table class="table table-bordered">
    <thead class="table-dark">
      <tr>
        <th>Doctor</th>
        <th>Date</th>
        <th>Slot</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['doctor_name']) ?></td>
            <td><?= htmlspecialchars($row['appointment_date']) ?></td>
            <td><?= htmlspecialchars($row['slot']) ?></td>
            <td>
              <?php if ($row['status'] == "booked"): ?>
                <span class="badge bg-success">Approved</span>
              <?php elseif ($row['status'] == "pending"): ?>
                <span class="badge bg-warning text-dark">Pending</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($row['status'] == "pending"): ?>
                <button class="btn btn-sm btn-outline-danger" onclick="cancelAppointment(<?= $row['id'] ?>)">
                  Cancel
                </button>
              <?php elseif ($row['status'] == "booked"): ?>
                <button class="btn btn-sm btn-outline-primary" 
                  onclick="openRescheduleModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['doctor_name'], ENT_QUOTES) ?>', '<?= $row['appointment_date'] ?>', '<?= $row['slot'] ?>')">
                  Reschedule
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="text-center">No appointments found</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<nav>
  <ul class="pagination">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
        <a class="page-link" href="#" onclick="loadAppointments(<?= $p ?>); return false;"><?= $p ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>
