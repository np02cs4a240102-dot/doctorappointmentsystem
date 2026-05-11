<?php
require_once __DIR__ . "/../backend/db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit; }
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["ok"=>false,"message"=>"Method not allowed"]);
  exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");
$role = trim($data["role"] ?? "patient");

if ($email === "" || $password === "") {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Email and password required"]);
  exit;
}

if ($role === "patient") {
  $stmt = $conn->prepare("SELECT id, name, email FROM patients WHERE email = ? AND password = ?");
  $stmt->bind_param("ss", $email, $password);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["ok"=>false,"message"=>"Invalid email or password"]);
    exit;
  }
  
  $user = $result->fetch_assoc();
  echo json_encode([
    "ok" => true,
    "message" => "Login successful",
    "user" => [
      "id" => $user['id'],
      "name" => $user['name'],
      "email" => $user['email'],
      "role" => "patient"
    ]
  ]);
} else {
  $stmt = $conn->prepare("SELECT id, name, email FROM doctors WHERE email = ? AND password = ? AND status = 'ACTIVE'");
  $stmt->bind_param("ss", $email, $password);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["ok"=>false,"message"=>"Invalid email or password"]);
    exit;
  }
  
  $user = $result->fetch_assoc();
  echo json_encode([
    "ok" => true,
    "message" => "Login successful",
    "user" => [
      "id" => $user['id'],
      "name" => $user['name'],
      "email" => $user['email'],
      "role" => "doctor"
    ]
  ]);
}
?>