<?php
session_start();
include("../connection/db.php");

$treatmentType = isset($_GET['treatment_type']) ? trim($_GET['treatment_type']) : '';

if ($treatmentType !== '') {
    $stmt = $conn->prepare("SELECT * FROM home_care WHERE treatment_type = ? ORDER BY id DESC");
    $stmt->bind_param("s", $treatmentType);
} else {
    $stmt = $conn->prepare("SELECT * FROM home_care ORDER BY id DESC");
}
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo '<p>No home care items found.</p>';
} else {
    while ($row = $res->fetch_assoc()) {
        $title = htmlspecialchars($row['title'] ?? 'Untitled');
        $type = htmlspecialchars($row['treatment_type'] ?? '');
        $description = htmlspecialchars($row['description'] ?? 'No description available');
        $precaution = htmlspecialchars($row['precaution'] ?? 'Not specified');
        $img = htmlspecialchars($row['image_path'] ?? '');

        echo '<div class="col-md-4">';
        echo '  <div class="card h-100 shadow-sm p-3">';
        if ($img) {
            echo '<img src="../' . $img . '" class="card-img-top" style="max-height:160px; object-fit:cover;">';
        }
        echo '    <div class="card-body">';
        echo '      <h6 class="card-title">' . $title . '</h6>';
        if ($type) echo '<p class="mb-1"><small class="text-muted">' . $type . '</small></p>';
        echo '      <p class="card-text">' . $description . '</p>';
        echo '      <p class="mb-0"><b>Precaution:</b> ' . $precaution . '</p>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }
}
$stmt->close();
