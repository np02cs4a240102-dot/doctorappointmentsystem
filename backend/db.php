<?php
// backend/db.php
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "medicare_connect";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(["ok" => false, "message" => "Database connection failed."]);
  exit;
}

$conn->set_charset("utf8mb4");
?>
