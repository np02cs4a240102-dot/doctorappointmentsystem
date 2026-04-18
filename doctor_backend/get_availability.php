<?php
require_once 'config.php';

$doctor_id = $_SESSION['doctor_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, day, 
               DATE_FORMAT(start_time, '%h:%i %p') as start_time,
               DATE_FORMAT(end_time, '%h:%i %p') as end_time,
               start_time as start_raw,
               end_time as end_raw
        FROM availability 
        WHERE doctor_id = ? 
        ORDER BY FIELD(day, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time
    ");
    $stmt->execute([$doctor_id]);
    $availability = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'availability' => $availability]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>