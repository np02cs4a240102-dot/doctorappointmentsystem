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

$appointmentId = intval($data["appointmentId"] ?? 0);
$newDate = trim($data["newDate"] ?? "");
$newTimeSlot = trim($data["newTimeSlot"] ?? "");

if ($appointmentId <= 0 || $newDate === "" || $newTimeSlot === "") {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Missing required fields."]);
  exit;
}

/* ---- Validation checks ---- */

// Date format check YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Invalid date format. Use YYYY-MM-DD."]);
  exit;
}

// Not past date
$today = date("Y-m-d");
if ($newDate < $today) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Cannot reschedule to a past date."]);
  exit;
}

// Allowed time slots (global UI slots)
$allowedSlots = ["9:00 AM","10:00 AM","11:00 AM","2:00 PM","3:00 PM","4:00 PM"];
if (!in_array($newTimeSlot, $allowedSlots, true)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Invalid time slot selected."]);
  exit;
}

// Check appointment exists and get doctor name
$stmt = $conn->prepare("SELECT id, doctor_name, patient_email, status FROM appointments WHERE id=?");
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
  http_response_code(404);
  echo json_encode(["ok"=>false,"message"=>"Appointment not found."]);
  exit;
}

$appt = $res->fetch_assoc();
$doctorName = $appt["doctor_name"];

// Optional: prevent rescheduling a cancelled appointment
if (($appt["status"] ?? "") === "CANCELLED") {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Cancelled appointments cannot be rescheduled."]);
  exit;
}

/* ---- Doctor availability validation (days + slots) ---- */
$doc = $conn->prepare("SELECT allowed_days, allowed_slots FROM doctors WHERE name=? AND status='ACTIVE' LIMIT 1");
$doc->bind_param("s", $doctorName);
$doc->execute();
$docRes = $doc->get_result();

if ($docRes->num_rows === 0) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Doctor is not available."]);
  exit;
}

$docRow = $docRes->fetch_assoc();

$allowedDaysStr  = trim($docRow["allowed_days"] ?? "");
$allowedSlotsStr = trim($docRow["allowed_slots"] ?? "");

// Convert to arrays (comma-separated in DB)
$allowedDays  = array_filter(array_map("trim", explode(",", $allowedDaysStr)));
$allowedSlotsForDoctor = array_filter(array_map("trim", explode(",", $allowedSlotsStr)));

// Determine day name from newDate (Sun, Mon, Tue...)
$dayShort = date("D", strtotime($newDate)); // e.g. "Sun"
$dayMap = ["Sun"=>"Sun","Mon"=>"Mon","Tue"=>"Tue","Wed"=>"Wed","Thu"=>"Thu","Fri"=>"Fri","Sat"=>"Sat"];
$day = $dayMap[$dayShort] ?? $dayShort;

if (!empty($allowedDays) && !in_array($day, $allowedDays, true)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Doctor is not available on the selected day."]);
  exit;
}

if (!empty($allowedSlotsForDoctor) && !in_array($newTimeSlot, $allowedSlotsForDoctor, true)) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"message"=>"Doctor is not available at the selected time slot."]);
  exit;
}

/* ---- Conflict check (only BOOKED appointments block) ---- */
$chk = $conn->prepare(
  "SELECT id FROM appointments
   WHERE doctor_name=? AND appointment_date=? AND time_slot=? AND status='BOOKED' AND id<>?
   LIMIT 1"
);
$chk->bind_param("sssi", $doctorName, $newDate, $newTimeSlot, $appointmentId);
$chk->execute();
$chkRes = $chk->get_result();

if ($chkRes->num_rows > 0) {
  http_response_code(409);
  echo json_encode(["ok"=>false,"message"=>"This time slot is already booked. Choose another slot."]);
  exit;
}

// Update appointment
$upd = $conn->prepare("UPDATE appointments SET appointment_date=?, time_slot=? WHERE id=?");
$upd->bind_param("ssi", $newDate, $newTimeSlot, $appointmentId);

if (!$upd->execute()) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"message"=>"Server error while rescheduling."]);
  exit;
}

echo json_encode([
  "ok"=>true,
  "message"=>"Appointment rescheduled successfully.",
  "updated"=>[
    "appointmentId"=>$appointmentId,
    "doctorName"=>$doctorName,
    "date"=>$newDate,
    "timeSlot"=>$newTimeSlot
  ]
]);