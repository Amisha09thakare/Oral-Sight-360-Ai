<?php
session_start();
include '../config/db.php';

// Ensure patient is logged in
if(!isset($_SESSION['patient_id'])){
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$result = $conn->query("SELECT * FROM scans WHERE patient_id = $patient_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
  <title>My Scans</title>
  <script src="https://cdn.jsdelivr.net/npm/three@0.155.0/build/three.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.155.0/examples/js/loaders/STLLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.155.0/examples/js/loaders/PLYLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.155.0/examples/js/loaders/OBJLoader.js"></script>
</head>
<body>
  <h2>My Uploaded Scans</h2>

  <?php while($row = $result->fetch_assoc()): ?>
    <div style="border:1px solid #ccc; padding:10px; margin:10px;">
        <p><b>Uploaded by Doctor ID:</b> <?= $row['admin_id'] ?></p>
        <p><b>Date:</b> <?= $row['created_at'] ?></p>

        <?php if(in_array($row['file_type'], ['jpg','png'])): ?>
            <img src="<?= $row['scan_file'] ?>" width="300" alt="Scan Image">
        
        <?php else: ?>
            <div id="viewer<?= $row['id'] ?>" style="width:400px; height:300px;"></div>
            <script>
              const scene<?= $row['id'] ?> = new THREE.Scene();
              const camera<?= $row['id'] ?> = new THREE.PerspectiveCamera(75, 400/300, 0.1, 1000);
              const renderer<?= $row['id'] ?> = new THREE.WebGLRenderer();
              renderer<?= $row['id'] ?>.setSize(400, 300);
              document.getElementById("viewer<?= $row['id'] ?>").appendChild(renderer<?= $row['id'] ?>.domElement);

              const light<?= $row['id'] ?> = new THREE.DirectionalLight(0xffffff, 1);
              light<?= $row['id'] ?>.position.set(1,1,1).normalize();
              scene<?= $row['id'] ?>.add(light<?= $row['id'] ?>);

              let loader<?= $row['id'] ?>;
              <?php if($row['file_type'] == 'stl'): ?>
                loader<?= $row['id'] ?> = new THREE.STLLoader();
                loader<?= $row['id'] ?>.load("<?= $row['scan_file'] ?>", function(geometry){
                    const material = new THREE.MeshPhongMaterial({color: 0x0055ff});
                    const mesh = new THREE.Mesh(geometry, material);
                    scene<?= $row['id'] ?>.add(mesh);
                    camera<?= $row['id'] ?>.position.z = 100;
                    renderer<?= $row['id'] ?>.render(scene<?= $row['id'] ?>, camera<?= $row['id'] ?>);
                });
              <?php elseif($row['file_type'] == 'ply'): ?>
                loader<?= $row['id'] ?> = new THREE.PLYLoader();
                loader<?= $row['id'] ?>.load("<?= $row['scan_file'] ?>", function(geometry){
                    geometry.computeVertexNormals();
                    const material = new THREE.MeshPhongMaterial({color: 0x00ff55});
                    const mesh = new THREE.Mesh(geometry, material);
                    scene<?= $row['id'] ?>.add(mesh);
                    camera<?= $row['id'] ?>.position.z = 100;
                    renderer<?= $row['id'] ?>.render(scene<?= $row['id'] ?>, camera<?= $row['id'] ?>);
                });
              <?php elseif($row['file_type'] == 'obj'): ?>
                loader<?= $row['id'] ?> = new THREE.OBJLoader();
                loader<?= $row['id'] ?>.load("<?= $row['scan_file'] ?>", function(object){
                    scene<?= $row['id'] ?>.add(object);
                    camera<?= $row['id'] ?>.position.z = 100;
                    renderer<?= $row['id'] ?>.render(scene<?= $row['id'] ?>, camera<?= $row['id'] ?>);
                });
              <?php endif; ?>
            </script>
        <?php endif; ?>
    </div>
  <?php endwhile; ?>
</body>
</html>
