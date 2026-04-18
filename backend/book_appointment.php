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

$patientEmail = trim($data["patientEmail"] ?? "");
$doctorName   = trim($data["doctorName"] ?? "");
$spec         = trim($data["specialization"] ?? "");
$date         = trim($data["date"] ?? "");
$timeSlot     = trim($data["timeSlot"] ?? "");

if ($patientEmail === "" || $doctorName === "" || $date === "" || $timeSlot === "") {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Missing required fields."]);
  exit;
}

if (!filter_var($patientEmail, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Invalid patient email."]);
  exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Invalid date format. Use YYYY-MM-DD."]);
  exit;
}

$today = date("Y-m-d");
if ($date < $today) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Cannot book an appointment in the past."]);
  exit;
}

// Global allowed slots (UI)
$allowedSlots = ["9:00 AM","10:00 AM","11:00 AM","2:00 PM","3:00 PM","4:00 PM"];
if (!in_array($timeSlot, $allowedSlots, true)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Invalid time slot selected."]);
  exit;
}

/* ---- Validate doctor + enforce doctor availability ---- */
$doc = $conn->prepare(
  "SELECT specialization, allowed_days, allowed_slots
   FROM doctors
   WHERE name=? AND status='ACTIVE'
   LIMIT 1"
);
$doc->bind_param("s", $doctorName);
$doc->execute();
$docRes = $doc->get_result();

if ($docRes->num_rows === 0) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Selected doctor is not available."]);
  exit;
}

$docRow = $docRes->fetch_assoc();
if ($spec === "") $spec = $docRow["specialization"];

// Doctor availability data (comma-separated strings in DB)
$allowedDaysStr  = trim($docRow["allowed_days"] ?? "");
$allowedSlotsStr = trim($docRow["allowed_slots"] ?? "");

$allowedDays = array_filter(array_map("trim", explode(",", $allowedDaysStr)));
$allowedSlotsForDoctor = array_filter(array_map("trim", explode(",", $allowedSlotsStr)));

// Determine day from selected date (Sun, Mon, Tue...)
$dayShort = date("D", strtotime($date)); // e.g. "Sun"
$dayMap = ["Sun"=>"Sun","Mon"=>"Mon","Tue"=>"Tue","Wed"=>"Wed","Thu"=>"Thu","Fri"=>"Fri","Sat"=>"Sat"];
$day = $dayMap[$dayShort] ?? $dayShort;

// Enforce allowed days (if configured)
if (!empty($allowedDays) && !in_array($day, $allowedDays, true)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Doctor is not available on the selected day."]);
  exit;
}

// Enforce allowed slots (if configured)
if (!empty($allowedSlotsForDoctor) && !in_array($timeSlot, $allowedSlotsForDoctor, true)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Doctor is not available at the selected time slot."]);
  exit;
}

/* ---- Prevent double-booking ONLY if there is an existing BOOKED appointment ---- */
$chk = $conn->prepare(
  "SELECT id FROM appointments
   WHERE doctor_name=? AND appointment_date=? AND time_slot=? AND status='BOOKED'
   LIMIT 1"
);
$chk->bind_param("sss", $doctorName, $date, $timeSlot);
$chk->execute();

if ($chk->get_result()->num_rows > 0) {
  http_response_code(409);
  echo json_encode(["ok"=>false,"message"=>"This time slot is already booked. Please choose another slot."]);
  exit;
}

/* ---- Insert appointment ---- */
$stmt = $conn->prepare(
  "INSERT INTO appointments (patient_email, doctor_name, specialization, appointment_date, time_slot, status)
   VALUES (?, ?, ?, ?, ?, 'BOOKED')"
);

$specParam = ($spec === "") ? null : $spec;
$stmt->bind_param("sssss", $patientEmail, $doctorName, $specParam, $date, $timeSlot);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"message"=>"Server error while booking."]);
  exit;
}

echo json_encode([
  "ok" => true,
  "message" => "Appointment booked successfully.",
  "confirmation" => [
    "appointmentId" => $conn->insert_id,
    "doctorName" => $doctorName,
    "date" => $date,
    "timeSlot" => $timeSlot,
    "status" => "BOOKED"
  ]
]);