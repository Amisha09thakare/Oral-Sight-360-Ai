<?php
session_start();
require_once("../connection/db.php");

// --- ADD NEW ENTRY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $treatment_type = $conn->real_escape_string($_POST['treatment_type']);
    $instructions   = $conn->real_escape_string($_POST['instructions']);
    $video1 = $conn->real_escape_string($_POST['video1']);
    $video2 = $conn->real_escape_string($_POST['video2']);
    $video3 = $conn->real_escape_string($_POST['video3']);
    $video4 = $conn->real_escape_string($_POST['video4']);

    $sql = "INSERT INTO home_care (treatment_type, instructions, video1, video2, video3, video4) 
            VALUES ('$treatment_type','$instructions','$video1','$video2','$video3','$video4')";
    $conn->query($sql);
    header("Location: homecare_manage.php");
    exit;
}

// --- DELETE ENTRY ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM home_care WHERE id=$id");
    header("Location: homecare_manage.php");
    exit;
}

// --- UPDATE ENTRY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $treatment_type = $conn->real_escape_string($_POST['treatment_type']);
    $instructions   = $conn->real_escape_string($_POST['instructions']);
    $video1 = $conn->real_escape_string($_POST['video1']);
    $video2 = $conn->real_escape_string($_POST['video2']);
    $video3 = $conn->real_escape_string($_POST['video3']);
    $video4 = $conn->real_escape_string($_POST['video4']);

    $sql = "UPDATE home_care 
            SET treatment_type='$treatment_type',
                instructions='$instructions',
                video1='$video1',
                video2='$video2',
                video3='$video3',
                video4='$video4'
            WHERE id=$id";
    $conn->query($sql);
    header("Location: homecare_manage.php");
    exit;
}

// --- FETCH ALL ENTRIES ---
$result = $conn->query("SELECT * FROM home_care ORDER BY id DESC");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home Care Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
  <h3 class="mb-4">Home Care Management</h3>

  <!-- Add New Form -->
  <div class="card mb-4">
    <div class="card-header bg-primary text-white">Add New Home Care</div>
    <div class="card-body">
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Treatment Type</label>
          <input type="text" name="treatment_type" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Instructions</label>
          <textarea name="instructions" class="form-control" rows="3" required></textarea>
        </div>
        <div class="row">
          <?php for ($i=1;$i<=4;$i++): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label">Video <?= $i ?> URL</label>
              <input type="url" name="video<?= $i ?>" class="form-control">
            </div>
          <?php endfor; ?>
        </div>
        <button type="submit" name="add" class="btn btn-success">Add Home Care</button>
      </form>
    </div>
  </div>

  <!-- List of Entries -->
  <div class="card">
    <div class="card-header bg-secondary text-white">All Home Care Entries</div>
    <div class="card-body table-responsive">
      <table class="table table-bordered align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Treatment</th>
            <th>Instructions</th>
            <th>Videos</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['treatment_type']) ?></td>
              <td><?= nl2br(htmlspecialchars($row['instructions'])) ?></td>
              <td>
                <?php for($i=1;$i<=4;$i++): ?>
                  <?php if(!empty($row["video$i"])): ?>
                    <a href="<?= htmlspecialchars($row["video$i"]) ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-1">Video <?= $i ?></a>
                  <?php endif; ?>
                <?php endfor; ?>
              </td>
              <td>
                <!-- Edit Button triggers modal -->
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">Edit</button>
                <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this entry?')">Delete</a>
              </td>
            </tr>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <form method="POST">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Home Care #<?= $row['id'] ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="id" value="<?= $row['id'] ?>">
                      <div class="mb-3">
                        <label class="form-label">Treatment Type</label>
                        <input type="text" name="treatment_type" value="<?= htmlspecialchars($row['treatment_type']) ?>" class="form-control" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Instructions</label>
                        <textarea name="instructions" class="form-control" rows="3" required><?= htmlspecialchars($row['instructions']) ?></textarea>
                      </div>
                      <div class="row">
                        <?php for ($i=1;$i<=4;$i++): ?>
                          <div class="col-md-6 mb-3">
                            <label class="form-label">Video <?= $i ?> URL</label>
                            <input type="url" name="video<?= $i ?>" value="<?= htmlspecialchars($row["video$i"]) ?>" class="form-control">
                          </div>
                        <?php endfor; ?>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="submit" name="update" class="btn btn-success">Save Changes</button>
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
