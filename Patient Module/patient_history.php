<?php
// api/patient_history.php
// GET /api/patient_history.php
// Auth: patient session required

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

startSession();

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    errorResponse('Unauthorized. Please log in as a patient.', 401, null);
}

$patient_email = $_SESSION['email'];
$db = getDB();

$stmt = $db->prepare("
    SELECT doctor_name, specialization, appointment_date, time_slot, status, created_at
    FROM appointments
    WHERE patient_email = ?
    ORDER BY appointment_date DESC, time_slot DESC
");
$stmt->bind_param('s', $patient_email);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

$stmt->close();
$db->close();

successResponse('Appointments retrieved.', ['appointments' => $appointments]);
