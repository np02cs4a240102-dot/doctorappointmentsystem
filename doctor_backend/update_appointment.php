<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $appointment_id = $input['appointment_id'] ?? 0;
    
    try {
        if ($action === 'cancel') {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$appointment_id]);
            echo json_encode(['success' => true, 'message' => 'Appointment cancelled']);
        } 
        elseif ($action === 'reschedule') {
            $new_date = $input['new_date'] ?? '';
            $new_time = $input['new_time'] ?? '';
            $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ? WHERE id = ?");
            $stmt->execute([$new_date, $new_time, $appointment_id]);
            echo json_encode(['success' => true, 'message' => 'Appointment rescheduled']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>