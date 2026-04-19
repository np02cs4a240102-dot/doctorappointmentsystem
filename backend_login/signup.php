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

$name = trim($data["name"] ?? "");
$email = trim($data["email"] ?? "");
$phone = trim($data["phone"] ?? "");
$password = trim($data["password"] ?? "");
$role = trim($data["role"] ?? "patient");

if ($name === "" || $email === "" || $password === "") {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Name, email and password required"]);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Invalid email format"]);
  exit;
}

if ($role === "patient") {
  // Check if email exists
  $check = $conn->prepare("SELECT id FROM patients WHERE email = ?");
  $check->bind_param("s", $email);
  $check->execute();
  if ($check->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["ok"=>false,"message"=>"Email already registered"]);
    exit;
  }
  
  $stmt = $conn->prepare("INSERT INTO patients (name, email, phone, password) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("ssss", $name, $email, $phone, $password);
  
  if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["ok"=>false,"message"=>"Registration failed"]);
    exit;
  }
  
  echo json_encode([
    "ok" => true,
    "message" => "Patient registered successfully",
    "userId" => $conn->insert_id
  ]);
} else {
  // Doctor signup - check if email exists
  $check = $conn->prepare("SELECT id FROM doctors WHERE email = ?");
  $check->bind_param("s", $email);
  $check->execute();
  if ($check->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["ok"=>false,"message"=>"Email already registered"]);
    exit;
  }
  
  $stmt = $conn->prepare("INSERT INTO doctors (name, email, phone, password, status) VALUES (?, ?, ?, ?, 'ACTIVE')");
  $stmt->bind_param("ssss", $name, $email, $phone, $password);
  
  if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["ok"=>false,"message"=>"Registration failed"]);
    exit;
  }
  
  echo json_encode([
    "ok" => true,
    "message" => "Doctor registered successfully",
    "doctorId" => $conn->insert_id
  ]);
}
?>