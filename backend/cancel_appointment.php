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

$id = intval($data["appointmentId"] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Invalid appointment id."]);
  exit;
}

$check = $conn->prepare("SELECT id, status FROM appointments WHERE id=?");
$check->bind_param("i", $id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows === 0) {
  http_response_code(404);
  echo json_encode(["ok"=>false,"message"=>"Appointment not found."]);
  exit;
}

$row = $res->fetch_assoc();
if ($row["status"] === "CANCELLED") {
  echo json_encode(["ok"=>true,"message"=>"Appointment already cancelled."]);
  exit;
}

$upd = $conn->prepare("UPDATE appointments SET status='CANCELLED' WHERE id=?");
$upd->bind_param("i", $id);

if (!$upd->execute()) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"message"=>"Server error while cancelling."]);
  exit;
}

echo json_encode(["ok"=>true,"message"=>"Appointment cancelled successfully."]);
