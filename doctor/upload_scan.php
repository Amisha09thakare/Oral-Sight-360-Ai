<?php
session_start();
include("../connection/db.php");

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['patient_id'])) {
    http_response_code(403); exit('Not allowed');
}

$uploader_id = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : (isset($_SESSION['patient_id'])? (int)$_SESSION['patient_id'] : 0);

if (!$patient_id) { echo "Select patient"; exit; }

if (!isset($_FILES['scan_file'])) { echo "No file"; exit; }

$allowed = ['png','jpg','jpeg','pdf','stl','obj','ply','glb','gltf'];
$orig = $_FILES['scan_file']['name'];
$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

if (!in_array($ext,$allowed)) { echo "Invalid type"; exit; }

$targetDir = '../uploads/scans/';
if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

$filename = time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
$target = $targetDir . $filename;

if (!move_uploaded_file($_FILES['scan_file']['tmp_name'], $target)) {
    echo "Upload failed"; exit;
}

$patient_name = $conn->real_escape_string($_POST['patient_name'] ?? '');
$scan_file = $conn->real_escape_string("uploads/scans/".$filename);
$file_type = $conn->real_escape_string($ext);

$sql = "INSERT INTO scans (admin_id, patient_id, file_path, patient_name, scan_file, file_type, created_at, status) 
        VALUES (".($uploader_id? $uploader_id : "NULL").", $patient_id, '$scan_file', '$patient_name', '$scan_file', '$file_type', NOW(), 'pending')";
if ($conn->query($sql)) {
    header("Location: ../patient/dashboard.php?upload=1");
} else {
    echo "DB error: " . $conn->error;
}
