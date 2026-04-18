<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $availability_id = $input['availability_id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM availability WHERE id = ?");
        $stmt->execute([$availability_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>