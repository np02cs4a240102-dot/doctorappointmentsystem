<?php
// api/admin_dashboard.php
// GET    /api/admin_dashboard.php?type=stats|doctors|patients|appointments
// PUT    /api/admin_dashboard.php?action=toggle_doctor   → activate/deactivate doctor
// DELETE /api/admin_dashboard.php?action=delete_doctor   → delete doctor
// Auth: admin session required

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

startSession();

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    errorResponse('Unauthorized. Please log in as admin.', 401);
}

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$type   = sanitize($_GET['type']   ?? '');
$action = sanitize($_GET['action'] ?? '');

// ══════════════════════════════════════════════════════
//  GET — fetch data
// ══════════════════════════════════════════════════════
if ($method === 'GET') {

    // ── Stats overview ─────────────────────────────
    if ($type === 'stats' || !$type) {
        $total_doctors      = $db->query("SELECT COUNT(*) as c FROM doctors")->fetch_assoc()['c'];
        $active_doctors     = $db->query("SELECT COUNT(*) as c FROM doctors WHERE status='ACTIVE'")->fetch_assoc()['c'];
        $total_patients     = $db->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc()['c'];
        $total_appointments = $db->query("SELECT COUNT(*) as c FROM appointments")->fetch_assoc()['c'];
        $booked_appts       = $db->query("SELECT COUNT(*) as c FROM appointments WHERE status='BOOKED'")->fetch_assoc()['c'];
        $cancelled_appts    = $db->query("SELECT COUNT(*) as c FROM appointments WHERE status='CANCELLED'")->fetch_assoc()['c'];

        successResponse('Stats retrieved.', [
            'stats' => [
                'total_doctors'      => $total_doctors,
                'active_doctors'     => $active_doctors,
                'total_patients'     => $total_patients,
                'total_appointments' => $total_appointments,
                'booked_appts'       => $booked_appts,
                'cancelled_appts'    => $cancelled_appts,
            ]
        ]);
    }

    // ── All doctors ────────────────────────────────
    if ($type === 'doctors') {
        $result = $db->query("SELECT id, name, email, phone, specialization, experience_years, allowed_days, status, created_at FROM doctors ORDER BY created_at DESC");
        $doctors = [];
        while ($row = $result->fetch_assoc()) $doctors[] = $row;
        successResponse('Doctors retrieved.', ['doctors' => $doctors]);
    }

    // ── All patients ───────────────────────────────
    if ($type === 'patients') {
        $result = $db->query("SELECT id, name, email, phone, created_at FROM patients ORDER BY created_at DESC");
        $patients = [];
        while ($row = $result->fetch_assoc()) $patients[] = $row;
        successResponse('Patients retrieved.', ['patients' => $patients]);
    }

    // ── All appointments ───────────────────────────
    if ($type === 'appointments') {
        $result = $db->query("SELECT id, patient_name, patient_email, doctor_name, specialization, appointment_date, time_slot, status, created_at FROM appointments ORDER BY appointment_date DESC, time_slot DESC");
        $appointments = [];
        while ($row = $result->fetch_assoc()) $appointments[] = $row;
        successResponse('Appointments retrieved.', ['appointments' => $appointments]);
    }
}

// ══════════════════════════════════════════════════════
//  PUT — toggle doctor status
// ══════════════════════════════════════════════════════
if ($method === 'PUT' && $action === 'toggle_doctor') {
    $input     = json_decode(file_get_contents('php://input'), true) ?: [];
    $doctor_id = intval($input['doctor_id'] ?? 0);

    if (!$doctor_id) errorResponse('Doctor ID is required.', 400);

    $stmt   = $db->prepare("SELECT status FROM doctors WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doctor) errorResponse('Doctor not found.', 404);

    $new_status = $doctor['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
    $stmt2 = $db->prepare("UPDATE doctors SET status = ? WHERE id = ?");
    $stmt2->bind_param('si', $new_status, $doctor_id);
    $stmt2->execute();
    $stmt2->close();
    $db->close();

    successResponse("Doctor status updated to {$new_status}.", ['new_status' => $new_status]);
}

// ══════════════════════════════════════════════════════
//  DELETE — delete doctor
// ══════════════════════════════════════════════════════
if ($method === 'DELETE' && $action === 'delete_doctor') {
    $input     = json_decode(file_get_contents('php://input'), true) ?: [];
    $doctor_id = intval($input['doctor_id'] ?? $_GET['id'] ?? 0);

    if (!$doctor_id) errorResponse('Doctor ID is required.', 400);

    $stmt = $db->prepare("DELETE FROM doctors WHERE id = ?");
    $stmt->bind_param('i', $doctor_id);
    if (!$stmt->execute()) errorResponse('Failed to delete doctor.', 500);
    $stmt->close();
    $db->close();

    successResponse('Doctor deleted successfully.');
}

errorResponse('Invalid request.', 400);
