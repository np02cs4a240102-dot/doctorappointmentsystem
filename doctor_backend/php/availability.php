<?php
// api/availability.php
// GET    /api/availability.php            → get doctor's current availability
// POST   /api/availability.php            → save availability slots
// DELETE /api/availability.php?id=        → remove a slot
// Auth: doctor session required

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

startSession();

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    errorResponse('Unauthorized. Please log in as a doctor.', 401);
}

$doctor_id = intval($_SESSION['user_id']);
$db        = getDB();
$method    = $_SERVER['REQUEST_METHOD'];

// ══════════════════════════════════════════════════════
//  GET — fetch current availability
// ══════════════════════════════════════════════════════
if ($method === 'GET') {
    $stmt = $db->prepare('SELECT id, day, start_time, end_time FROM availability WHERE doctor_id = ? ORDER BY FIELD(day,"Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"), start_time');
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $slots[] = [
            'id'         => $row['id'],
            'day'        => $row['day'],
            'start_time' => date('h:i A', strtotime($row['start_time'])),
            'end_time'   => date('h:i A', strtotime($row['end_time'])),
        ];
    }
    $stmt->close();
    $db->close();

    successResponse('Availability retrieved.', ['slots' => $slots]);
}

// ══════════════════════════════════════════════════════
//  POST — save new availability
// ══════════════════════════════════════════════════════
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $days     = $input['days']     ?? [];
    $am_from  = $input['am_from']  ?? '';
    $am_to    = $input['am_to']    ?? '';
    $pm_from  = $input['pm_from']  ?? '';
    $pm_to    = $input['pm_to']    ?? '';

    // Validate
    if (empty($days) || !is_array($days)) {
        errorResponse('Please select at least one day.', 400, 'days');
    }

    $allowed_days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
    foreach ($days as $day) {
        if (!in_array($day, $allowed_days)) {
            errorResponse("Invalid day: {$day}.", 400, 'days');
        }
    }

    if (count($days) !== count(array_unique($days))) {
        errorResponse('Duplicate days are not allowed.', 400, 'days');
    }

    if (!$am_from || !$am_to) {
        errorResponse('Morning start and end times are required.', 400, 'am_from');
    }

    if ($am_from >= $am_to) {
        errorResponse('Morning start time must be before end time.', 400, 'am_from');
    }

    if ($pm_from || $pm_to) {
        if (!$pm_from || !$pm_to) {
            errorResponse('Both evening start and end times are required.', 400, 'pm_from');
        }
        if ($pm_from <= $am_to) {
            errorResponse('Evening start must be after morning end time (no overlap).', 400, 'pm_from');
        }
        if ($pm_from >= $pm_to) {
            errorResponse('Evening start must be before evening end time.', 400, 'pm_to');
        }
    }

    // Insert new slots
    $stmt = $db->prepare('INSERT INTO availability (doctor_id, day, start_time, end_time) VALUES (?, ?, ?, ?)');

    $inserted = 0;
    foreach ($days as $day) {
        $stmt->bind_param('isss', $doctor_id, $day, $am_from, $am_to);
        $stmt->execute();
        $inserted++;

        if ($pm_from && $pm_to) {
            $stmt->bind_param('isss', $doctor_id, $day, $pm_from, $pm_to);
            $stmt->execute();
            $inserted++;
        }
    }

    // Also update allowed_days and allowed_slots on doctors table
    $all_days  = $db->prepare('SELECT DISTINCT day FROM availability WHERE doctor_id = ?');
    $all_days->bind_param('i', $doctor_id);
    $all_days->execute();
    $dres = $all_days->get_result();
    $day_list = [];
    while ($d = $dres->fetch_assoc()) { $day_list[] = substr($d['day'], 0, 3); }
    $all_days->close();
    $days_str = implode(',', $day_list);

    $upd = $db->prepare('UPDATE doctors SET allowed_days = ? WHERE id = ?');
    $upd->bind_param('si', $days_str, $doctor_id);
    $upd->execute();
    $upd->close();

    $stmt->close();
    $db->close();

    successResponse("Availability saved successfully. {$inserted} slot(s) added.");
}

// ══════════════════════════════════════════════════════
//  DELETE — remove a single availability slot
// ══════════════════════════════════════════════════════
if ($method === 'DELETE') {
    $slot_id = intval($_GET['id'] ?? 0);

    if (!$slot_id) {
        errorResponse('Slot ID is required.', 400);
    }

    // Verify slot belongs to this doctor
    $stmt = $db->prepare('SELECT id, day, start_time, end_time FROM availability WHERE id = ? AND doctor_id = ? LIMIT 1');
    $stmt->bind_param('ii', $slot_id, $doctor_id);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$slot) {
        errorResponse('Availability slot not found.', 404);
    }

    // Check for upcoming bookings on this slot
    $today = date('Y-m-d');
    $stmt2 = $db->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND time_slot = ? AND appointment_date >= ? AND status NOT IN ('CANCELLED') LIMIT 1");
    $slot_time_fmt = date('g:i A', strtotime($slot['start_time']));
    $stmt2->bind_param('iss', $doctor_id, $slot_time_fmt, $today);
    $stmt2->execute();
    $stmt2->store_result();

    if ($stmt2->num_rows > 0) {
        $stmt2->close();
        errorResponse('Cannot remove this slot — it has upcoming bookings. Please cancel those appointments first.', 409);
    }
    $stmt2->close();

    // Delete slot
    $stmt3 = $db->prepare('DELETE FROM availability WHERE id = ? AND doctor_id = ?');
    $stmt3->bind_param('ii', $slot_id, $doctor_id);

    if (!$stmt3->execute()) {
        $stmt3->close();
        errorResponse('Failed to remove slot. Please try again.', 500);
    }
    $stmt3->close();
    $db->close();

    successResponse('Availability slot removed successfully.', ['slot_id' => $slot_id]);
}

errorResponse('Invalid request.', 400);
