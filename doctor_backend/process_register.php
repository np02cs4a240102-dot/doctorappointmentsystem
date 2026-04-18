<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $specialization = $_POST['specialization'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $days = $_POST['days'] ?? [];
    $am_start = $_POST['am_start'] ?? '';
    $am_end = $_POST['am_end'] ?? '';
    $pm_start = $_POST['pm_start'] ?? '';
    $pm_end = $_POST['pm_end'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Insert doctor
        $stmt = $pdo->prepare("INSERT INTO doctors (name, email, phone, specialization) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $specialization]);
        $doctor_id = $pdo->lastInsertId();
        
        // Insert availability
        $stmt = $pdo->prepare("INSERT INTO availability (doctor_id, day, start_time, end_time) VALUES (?, ?, ?, ?)");
        foreach ($days as $day) {
            if ($am_start && $am_end) {
                $stmt->execute([$doctor_id, $day, $am_start, $am_end]);
            }
            if ($pm_start && $pm_end) {
                $stmt->execute([$doctor_id, $day, $pm_start, $pm_end]);
            }
        }
        
        $pdo->commit();
        $_SESSION['doctor_id'] = $doctor_id;
        $_SESSION['doctor_name'] = $name;
        
        echo json_encode(['success' => true, 'doctor_id' => $doctor_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>