<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['patient_id'])) {
    http_response_code(403);
    exit("Not authorized");
}

$patient_id = (int) $_SESSION['patient_id'];

$sql = "
    SELECT ap.id, ap.appointment_date, ap.slot, ap.status, ad.username AS doctor_name
    FROM appointments ap
    JOIN admins ad ON ap.doctor_id = ad.id
    WHERE ap.patient_id = ? 
      AND ap.status IN ('completed','rejected')
    ORDER BY ap.appointment_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<div class="table-responsive">
  <table class="table table-bordered">
    <thead class="table-dark">
      <tr>
        <th>Doctor</th>
        <th>Date</th>
        <th>Slot</th>
        <th>Status</th>
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
              <?php if ($row['status'] == "completed"): ?>
                <span class="badge bg-info text-dark">Completed</span>
              <?php elseif ($row['status'] == "rejected"): ?>
                <span class="badge bg-danger">Rejected</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="4" class="text-center">No past appointments</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
