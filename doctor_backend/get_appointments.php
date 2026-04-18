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
        SELECT 
            a.id, 
            a.patient_email, 
            a.patient_name,
            DATE_FORMAT(a.appointment_date, '%Y-%m-%d') as appointment_date,
            a.time_slot as appointment_time,
            a.reason, 
            a.status 
        FROM appointments a
        WHERE a.doctor_id = ? AND a.status != 'CANCELLED'
        ORDER BY a.appointment_date, a.time_slot
    ");
    $stmt->execute([$doctor_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'appointments' => $appointments]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>