<?php
require_once 'config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit; }

$doctor_id = intval($_GET['doctor_id'] ?? $_POST['doctor_id'] ?? 0);

if ($doctor_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Doctor ID required']);
    exit;
}

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