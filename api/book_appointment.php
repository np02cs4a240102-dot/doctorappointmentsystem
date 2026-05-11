<?php
// api/book_appointment.php
// POST /api/book_appointment.php
// Body: { doctor_id, doctor_name, appointment_date, time_slot, specialization }
// Auth: patient session required

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed.', 405);
}

// ── Auth check ─────────────────────────────────────────
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    errorResponse('Unauthorized. Please log in as a patient.', 401);
}

$patient_email = $_SESSION['email'];
$patient_name  = $_SESSION['name'];

// ── Get input ──────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$doctor_id       = intval($input['doctor_id']        ?? 0);
$doctor_name     = sanitize($input['doctor_name']    ?? '');
$appt_date       = sanitize($input['appointment_date'] ?? '');
$time_slot       = sanitize($input['time_slot']      ?? '');
$specialization  = sanitize($input['specialization'] ?? '');

// ── Validate ───────────────────────────────────────────
if (!$doctor_id || !$doctor_name || !$appt_date || !$time_slot) {
    errorResponse('Please fill in all fields before confirming.', 400);
}

// Date format check
if (!DateTime::createFromFormat('Y-m-d', $appt_date)) {
    errorResponse('Invalid date format. Use YYYY-MM-DD.', 400, 'appointment_date');
}

// Cannot book in the past
if ($appt_date < date('Y-m-d')) {
    errorResponse('Appointment date cannot be in the past.', 400, 'appointment_date');
}

$db = getDB();

// ── Check doctor exists and is active ─────────────────
$stmt = $db->prepare("SELECT id, name, allowed_days, allowed_slots FROM doctors WHERE id = ? AND status = 'ACTIVE' LIMIT 1");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doctor) {
    errorResponse('Doctor not found or is no longer active.', 404);
}

// ── Check selected day is in doctor's allowed days ────
$day_of_week   = date('D', strtotime($appt_date)); // Mon, Tue...
$full_day      = date('l', strtotime($appt_date)); // Monday, Tuesday...
$allowed_days  = explode(',', $doctor['allowed_days']);

$day_match = false;
foreach ($allowed_days as $d) {
    if (strtolower(trim($d)) === strtolower(substr($full_day, 0, 3)) ||
        strtolower(trim($d)) === strtolower($full_day)) {
        $day_match = true;
        break;
    }
}

if (!$day_match) {
    errorResponse("Dr. {$doctor['name']} is not available on {$full_day}s.", 400, 'appointment_date');
}

// ── Check slot is in allowed slots ────────────────────
$allowed_slots = array_map('trim', explode(',', $doctor['allowed_slots']));
if (!in_array($time_slot, $allowed_slots)) {
    errorResponse('Selected time slot is not available for this doctor.', 400, 'time_slot');
}

// ── Check for double booking ───────────────────────────
$stmt2 = $db->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND time_slot = ? AND status NOT IN ('CANCELLED') LIMIT 1");
$stmt2->bind_param('iss', $doctor_id, $appt_date, $time_slot);
$stmt2->execute();
$stmt2->store_result();

if ($stmt2->num_rows > 0) {
    $stmt2->close();
    errorResponse('This time slot is no longer available. Please choose a different slot.', 409);
}
$stmt2->close();

// ── Insert appointment ─────────────────────────────────
$stmt3 = $db->prepare('INSERT INTO appointments (patient_email, patient_name, doctor_name, doctor_id, specialization, appointment_date, time_slot, status) VALUES (?, ?, ?, ?, ?, ?, ?, "BOOKED")');
$stmt3->bind_param('sssisss', $patient_email, $patient_name, $doctor_name, $doctor_id, $specialization, $appt_date, $time_slot);

if (!$stmt3->execute()) {
    $stmt3->close();
    errorResponse('Booking failed. Please try again.', 500);
}

$appointment_id = $db->insert_id;
$stmt3->close();
$db->close();

successResponse('Appointment booked successfully.', [
    'appointment_id'   => $appointment_id,
    'doctor_name'      => $doctor_name,
    'appointment_date' => $appt_date,
    'time_slot'        => $time_slot,
    'redirect'         => "booked.html?doctor=" . urlencode($doctor_name) . "&date={$appt_date}&slot=" . urlencode($time_slot)
]);
