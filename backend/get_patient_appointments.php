<?php
require_once __DIR__ . "/db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit; }
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["ok"=>false,"message"=>"Method not allowed"]);
  exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$email = strtolower(trim($data["patientEmail"] ?? ""));
if ($email === "") {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Missing patientEmail"]);
  exit;
}

$stmt = $conn->prepare(
  "SELECT id, doctor_name, specialization, appointment_date, time_slot, status, created_at
   FROM appointments
   WHERE patient_email = ?
   ORDER BY appointment_date DESC, created_at DESC"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while($row = $res->fetch_assoc()){
  $rows[] = $row;
}

echo json_encode(["ok"=>true, "appointments"=>$rows]);