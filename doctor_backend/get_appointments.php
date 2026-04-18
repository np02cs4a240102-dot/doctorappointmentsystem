<?php
require_once 'config.php';

$doctor_id = $_SESSION['doctor_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, patient_name, patient_email, 
               DATE_FORMAT(appointment_date, '%m/%d/%Y') as appointment_date,
               DATE_FORMAT(appointment_time, '%h:%i %p') as appointment_time,
               reason, status 
        FROM appointments 
        WHERE doctor_id = ? AND status != 'cancelled' 
        ORDER BY appointment_date, appointment_time
    ");
    $stmt->execute([$doctor_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'appointments' => $appointments]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>