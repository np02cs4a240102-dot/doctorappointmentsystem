<?php
require_once 'config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit; }
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$appointment_id = intval($input['appointment_id'] ?? 0);

if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid appointment ID']);
    exit;
}

try {
    if ($action === 'cancel') {
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'CANCELLED' WHERE id = ?");
        $stmt->execute([$appointment_id]);
        echo json_encode(['success' => true, 'message' => 'Appointment cancelled']);
    } 
    elseif ($action === 'reschedule') {
        $new_date = trim($input['new_date'] ?? '');
        $new_time_slot = trim($input['new_time_slot'] ?? '');
        
        if ($new_date === '' || $new_time_slot === '') {
            echo json_encode(['success' => false, 'error' => 'Date and time required']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, time_slot = ? WHERE id = ?");
        $stmt->execute([$new_date, $new_time_slot, $appointment_id]);
        echo json_encode(['success' => true, 'message' => 'Appointment rescheduled']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>