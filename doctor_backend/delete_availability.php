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
$availability_id = intval($input['availability_id'] ?? 0);
$doctor_id = intval($input['doctor_id'] ?? 0);

if ($availability_id <= 0 || $doctor_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // Verify this availability belongs to the doctor
    $check = $pdo->prepare("SELECT id FROM availability WHERE id = ? AND doctor_id = ?");
    $check->execute([$availability_id, $doctor_id]);
    
    if ($check->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Slot not found or access denied']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM availability WHERE id = ?");
    $stmt->execute([$availability_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>