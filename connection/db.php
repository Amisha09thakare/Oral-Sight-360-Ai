<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "oral_sight_ai360";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
