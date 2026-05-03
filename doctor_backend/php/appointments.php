<?php
// api/appointments.php
// GET    /api/appointments.php            → get doctor's appointments
// PUT    /api/appointments.php?action=reschedule  → reschedule
// DELETE /api/appointments.php?action=cancel      → cancel
// Auth: doctor session required

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

startSession();

// ── Auth check ─────────────────────────────────────────
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    errorResponse('Unauthorized. Please log in as a doctor.', 401);
}

$doctor_id = intval($_SESSION['user_id']);
$db        = getDB();
$method    = $_SERVER['REQUEST_METHOD'];
$action    = sanitize($_GET['action'] ?? '');

// ══════════════════════════════════════════════════════
//  GET — fetch all appointments for logged-in doctor
// ══════════════════════════════════════════════════════
if ($method === 'GET') {

    $stmt = $db->prepare("SELECT id, patient_name, patient_email, appointment_date, time_slot, reason, status
                          FROM appointments
                          WHERE doctor_id = ? AND status NOT IN ('CANCELLED')
                          ORDER BY appointment_date ASC, time_slot ASC");
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt->close();
    $db->close();

    successResponse('Appointments retrieved.', ['appointments' => $appointments]);
}

// ══════════════════════════════════════════════════════
//  PUT — reschedule appointment
// ══════════════════════════════════════════════════════
if ($method === 'PUT' && $action === 'reschedule') {

    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $appt_id  = intval($input['appointment_id'] ?? 0);
    $new_date = sanitize($input['new_date']      ?? '');
    $new_slot = sanitize($input['new_slot']       ?? '');

    if (!$appt_id || !$new_date || !$new_slot) {
        errorResponse('Appointment ID, new date, and new slot are required.', 400);
    }

    if (!DateTime::createFromFormat('Y-m-d', $new_date)) {
        errorResponse('Invalid date format.', 400, 'new_date');
    }

    if ($new_date < date('Y-m-d')) {
        errorResponse('New date cannot be in the past.', 400, 'new_date');
    }

    // Verify appointment belongs to this doctor and is not cancelled
    $stmt = $db->prepare("SELECT id, doctor_id FROM appointments WHERE id = ? AND doctor_id = ? AND status != 'CANCELLED' LIMIT 1");
    $stmt->bind_param('ii', $appt_id, $doctor_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        errorResponse('Appointment not found or already cancelled.', 404);
    }
    $stmt->close();

    // Check new slot not already taken
    $stmt2 = $db->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND time_slot = ? AND status != 'CANCELLED' AND id != ? LIMIT 1");
    $stmt2->bind_param('issi', $doctor_id, $new_date, $new_slot, $appt_id);
    $stmt2->execute();
    $stmt2->store_result();

    if ($stmt2->num_rows > 0) {
        $stmt2->close();
        errorResponse('The new time slot is already booked. Please choose a different slot.', 409);
    }
    $stmt2->close();

    // Update
    $stmt3 = $db->prepare("UPDATE appointments SET appointment_date = ?, time_slot = ?, status = 'confirmed', updated_at = NOW() WHERE id = ?");
    $stmt3->bind_param('ssi', $new_date, $new_slot, $appt_id);

    if (!$stmt3->execute()) {
        $stmt3->close();
        errorResponse('Reschedule failed. Please try again.', 500);
    }
    $stmt3->close();
    $db->close();

    successResponse('Appointment rescheduled successfully.', [
        'appointment_id'   => $appt_id,
        'new_date'         => $new_date,
        'new_slot'         => $new_slot
    ]);
}

// ══════════════════════════════════════════════════════
//  DELETE — cancel appointment (soft delete)
// ══════════════════════════════════════════════════════
if ($method === 'DELETE' && $action === 'cancel') {

    $input   = json_decode(file_get_contents('php://input'), true) ?: [];
    $appt_id = intval($input['appointment_id'] ?? $_GET['id'] ?? 0);

    if (!$appt_id) {
        errorResponse('Appointment ID is required.', 400);
    }

    // Verify ownership and not already cancelled
    $stmt = $db->prepare("SELECT id, status FROM appointments WHERE id = ? AND doctor_id = ? LIMIT 1");
    $stmt->bind_param('ii', $appt_id, $doctor_id);
    $stmt->execute();
    $appt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$appt) {
        errorResponse('Appointment not found.', 404);
    }

    if ($appt['status'] === 'CANCELLED') {
        errorResponse('Appointment is already cancelled.', 409);
    }

    // Soft delete — update status only
    $stmt2 = $db->prepare("UPDATE appointments SET status = 'CANCELLED', updated_at = NOW() WHERE id = ?");
    $stmt2->bind_param('i', $appt_id);

    if (!$stmt2->execute()) {
        $stmt2->close();
        errorResponse('Cancellation failed. Please try again.', 500);
    }
    $stmt2->close();
    $db->close();

    successResponse('Appointment cancelled successfully.', ['appointment_id' => $appt_id]);
}

errorResponse('Invalid request.', 400);
