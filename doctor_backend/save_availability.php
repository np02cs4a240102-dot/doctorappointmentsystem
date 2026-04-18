<?php
require_once 'config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit; }
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Invalid request method']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$doctor_id = intval($input['doctor_id'] ?? 0);
$slots = $input['slots'] ?? [];

if ($doctor_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Doctor ID required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Delete old availability
    $stmt = $pdo->prepare("DELETE FROM availability WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    
    // Update doctor's allowed_days and allowed_slots
    $days = array_unique(array_column($slots, 'day'));
    $allowedDaysStr = implode(",", $days);
    
    $slotTimes = [];
    foreach ($slots as $slot) {
        $slotTimes[] = date("g:i A", strtotime($slot['start']));
    }
    $allowedSlotsStr = implode(",", array_unique($slotTimes));
    
    $upd = $pdo->prepare("UPDATE doctors SET allowed_days = ?, allowed_slots = ? WHERE id = ?");
    $upd->execute([$allowedDaysStr, $allowedSlotsStr, $doctor_id]);
    
    // Insert new slots
    $stmt = $pdo->prepare("INSERT INTO availability (doctor_id, day, start_time, end_time) VALUES (?, ?, ?, ?)");
    foreach ($slots as $slot) {
        $stmt->execute([$doctor_id, $slot['day'], $slot['start'], $slot['end']]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Availability saved']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>