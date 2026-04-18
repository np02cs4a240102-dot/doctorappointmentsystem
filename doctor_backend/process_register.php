<?php
require_once __DIR__ . "/config.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit; }
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["success"=>false, "error"=>"Method not allowed"]);
  exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$email = trim($data["email"] ?? "");
$name = trim($data["name"] ?? "");
$specialization = trim($data["specialization"] ?? "");
$phone = trim($data["phone"] ?? "");
$experience = intval($data["experience"] ?? 0);
$days = $data["days"] ?? [];
$am_start = trim($data["am_start"] ?? "");
$am_end = trim($data["am_end"] ?? "");
$pm_start = trim($data["pm_start"] ?? "");
$pm_end = trim($data["pm_end"] ?? "");

if ($email === "" || $name === "" || $specialization === "") {
  http_response_code(400);
  echo json_encode(["success"=>false, "error"=>"Missing required fields"]);
  exit;
}

try {
    $pdo->beginTransaction();
    
    // Update doctor record
    $allowedDaysStr = implode(",", $days);
    
    // Build allowed slots string from actual times
    $slotList = [];
    if ($am_start && $am_end) {
        $startHour = intval(explode(":", $am_start)[0]);
        for ($h = $startHour; $h < intval(explode(":", $am_end)[0]); $h++) {
            $ampm = $h >= 12 ? 'PM' : 'AM';
            $hour = $h % 12;
            if ($hour == 0) $hour = 12;
            $slotList[] = $hour . ":00 " . $ampm;
        }
    }
    if ($pm_start && $pm_end) {
        $startHour = intval(explode(":", $pm_start)[0]);
        for ($h = $startHour; $h < intval(explode(":", $pm_end)[0]); $h++) {
            $ampm = $h >= 12 ? 'PM' : 'AM';
            $hour = $h % 12;
            if ($hour == 0) $hour = 12;
            $slotList[] = $hour . ":00 " . $ampm;
        }
    }
    $allowedSlotsStr = implode(",", $slotList);
    
    $stmt = $pdo->prepare("
        UPDATE doctors 
        SET name = ?, phone = ?, specialization = ?, experience_years = ?, 
            allowed_days = ?, allowed_slots = ?
        WHERE email = ? AND status = 'ACTIVE'
    ");
    $stmt->execute([$name, $phone, $specialization, $experience, $allowedDaysStr, $allowedSlotsStr, $email]);
    
    // Get doctor ID
    $stmt = $pdo->prepare("SELECT id FROM doctors WHERE email = ?");
    $stmt->execute([$email]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        throw new Exception("Doctor not found");
    }
    
    $doctor_id = $doctor['id'];
    
    // Delete old availability
    $stmt = $pdo->prepare("DELETE FROM availability WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    
    // Insert new availability
    $stmt = $pdo->prepare("INSERT INTO availability (doctor_id, day, start_time, end_time) VALUES (?, ?, ?, ?)");
    
    foreach ($days as $day) {
        if ($am_start && $am_end) {
            $stmt->execute([$doctor_id, $day, $am_start, $am_end]);
        }
        if ($pm_start && $pm_end) {
            $stmt->execute([$doctor_id, $day, $pm_start, $pm_end]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        "success" => true, 
        "message" => "Doctor profile completed successfully",
        "doctor_id" => $doctor_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>