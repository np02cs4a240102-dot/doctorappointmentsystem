<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = $_SESSION['doctor_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $slots = $input['slots'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Delete old availability
        $stmt = $pdo->prepare("DELETE FROM availability WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        
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
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>