<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['patient_id'])) {
    echo "Unauthorized";
    exit;
}

$treatmentFilter = isset($_GET['treatment_type']) ? htmlspecialchars($_GET['treatment_type']) : '';

$treatmentTypes = [];
$typeResult = $conn->query("SELECT DISTINCT treatment_type FROM home_care ORDER BY treatment_type ASC");
while ($row = $typeResult->fetch_assoc()) {
    $treatmentTypes[] = $row['treatment_type'];
}
$typeResult->close();

$filterCondition = "";
if ($treatmentFilter && in_array($treatmentFilter, $treatmentTypes)) {
    $filterCondition = " WHERE treatment_type = '".$conn->real_escape_string($treatmentFilter)."'";
}
$homeCare = $conn->query("SELECT * FROM home_care $filterCondition ORDER BY created_at DESC");
?>
<div class="row g-3 mb-3">
  <?php if ($homeCare->num_rows == 0): ?>
    <p class="text-muted">No home care instructions available.</p>
  <?php endif; ?>
  <?php while($item = $homeCare->fetch_assoc()): ?>
    <div class="col-md-6">
      <div class="card shadow-sm p-3 h-100">
        <h6 class="mb-1"><?= htmlspecialchars($item['treatment_type']) ?></h6>
        <p><?= nl2br(htmlspecialchars($item['instructions'])) ?></p>
        
        <?php
        $videoFound = false;
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($item["video$i"])) {
                $videoFound = true;
                break;
            }
        }
        ?>
        
        <?php if ($videoFound): ?>
          <div class="mt-2">
            <a href="<?= htmlspecialchars($item["video1"] ?: $item["video2"] ?: $item["video3"] ?: $item["video4"]) ?>" target="_blank" class="btn btn-sm btn-primary">
              Watch Video
            </a>
          </div>
        <?php endif; ?>

        <!-- Additional Link -->
        <div class="mt-2">
          <a href="treatment_detail.php?id=<?= (int)$item['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
            Learn More
          </a>
        </div>

      </div>
    </div>
  <?php endwhile; ?>
</div>
