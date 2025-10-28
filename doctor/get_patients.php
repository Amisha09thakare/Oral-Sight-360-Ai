<?php
include("../../connection/db.php");
$q = $conn->real_escape_string($_GET['q'] ?? '');
$sql = "SELECT id, name FROM patients";
if ($q) $sql .= " WHERE name LIKE '%$q%' OR email LIKE '%$q%'";
$sql .= " ORDER BY name LIMIT 50";
$res = $conn->query($sql);
$out = [];
while($r = $res->fetch_assoc()) $out[] = $r;
header('Content-Type: application/json');
echo json_encode($out);
