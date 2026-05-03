<?php
// api/doctor_register.php
// POST /api/doctor_register.php
// Body: { name, email, phone, specialization, days[], am_start, am_end, pm_start, pm_end, password, confirm_password }

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// ── Get fields ─────────────────────────────────────────
$name           = sanitize($input['name']           ?? '');
$email          = sanitize($input['email']          ?? '');
$phone          = sanitize($input['phone']          ?? '');
$specialization = sanitize($input['specialization'] ?? '');
$days           = $input['days']                    ?? [];
$am_start       = $input['am_start']                ?? '';
$am_end         = $input['am_end']                  ?? '';
$pm_start       = $input['pm_start']                ?? '';
$pm_end         = $input['pm_end']                  ?? '';
$password       = $input['password']                ?? '';
$confirm        = $input['confirm_password']        ?? '';

// ── Validate required fields ───────────────────────────
if (!$name || !$email || !$phone || !$specialization || !$am_start || !$am_end || !$password || !$confirm) {
    errorResponse('Please fill in all required fields.', 400);
}

if (strlen($name) < 3 || strlen($name) > 100) {
    errorResponse('Name must be between 3 and 100 characters.', 400, 'name');
}

if (!preg_match('/^[A-Za-z .]+$/', $name)) {
    errorResponse('Name can only contain letters, spaces, and dots.', 400, 'name');
}

if (!isValidEmail($email)) {
    errorResponse('Invalid email format.', 400, 'email');
}

if (!isValidPhone($phone)) {
    errorResponse('Invalid phone number. Must be 10–15 digits.', 400, 'phone');
}

$allowed_specs = ['Cardiologist','Dermatologist','Dentist','Neurologist','Pediatrician','Orthopedic','Gynecologist','General Physician'];
if (!in_array($specialization, $allowed_specs)) {
    errorResponse('Invalid specialization selected.', 400, 'specialization');
}

if (empty($days) || !is_array($days)) {
    errorResponse('Please select at least one available day.', 400, 'days');
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

// Validate time slots
if ($am_start >= $am_end) {
    errorResponse('Morning start time must be before end time.', 400, 'am_start');
}

if ($pm_start || $pm_end) {
    if (!$pm_start || !$pm_end) {
        errorResponse('Both evening start and end times are required if setting evening availability.', 400, 'pm_start');
    }
    if ($pm_start <= $am_end) {
        errorResponse('Evening start time must be after morning end time.', 400, 'pm_start');
    }
    if ($pm_start >= $pm_end) {
        errorResponse('Evening start time must be before evening end time.', 400, 'pm_end');
    }
}

if (strlen($password) < 6) {
    errorResponse('Password must be at least 6 characters.', 400, 'password');
}

if ($password !== $confirm) {
    errorResponse('Passwords do not match.', 400, 'confirm_password');
}

// ── Check email uniqueness ─────────────────────────────
$db = getDB();

$stmt = $db->prepare('SELECT id FROM doctors WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) { $stmt->close(); errorResponse('An account with this email already exists.', 409, 'email'); }
$stmt->close();

$stmt2 = $db->prepare('SELECT id FROM patients WHERE email = ? LIMIT 1');
$stmt2->bind_param('s', $email);
$stmt2->execute();
$stmt2->store_result();
if ($stmt2->num_rows > 0) { $stmt2->close(); errorResponse('An account with this email already exists.', 409, 'email'); }
$stmt2->close();

// ── Insert doctor ──────────────────────────────────────
$hashed      = hashPassword($password);
$days_str    = implode(',', $days);

// Build allowed_slots string from AM/PM times
$slots = [];
$am_s = new DateTime($am_start); $am_e = new DateTime($am_end);
$interval = new DateInterval('PT1H');
$period = new DatePeriod($am_s, $interval, $am_e);
foreach ($period as $dt) { $slots[] = $dt->format('g:i A'); }

if ($pm_start && $pm_end) {
    $pm_s = new DateTime($pm_start); $pm_e = new DateTime($pm_end);
    $period2 = new DatePeriod($pm_s, $interval, $pm_e);
    foreach ($period2 as $dt) { $slots[] = $dt->format('g:i A'); }
}
$slots_str = implode(',', $slots);

$stmt3 = $db->prepare('INSERT INTO doctors (name, email, phone, specialization, allowed_days, allowed_slots, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, "ACTIVE")');
$stmt3->bind_param('sssssss', $name, $email, $phone, $specialization, $days_str, $slots_str, $hashed);

if (!$stmt3->execute()) {
    $stmt3->close(); errorResponse('Registration failed. Please try again.', 500);
}

$doctor_id = $db->insert_id;
$stmt3->close();

// ── Insert availability rows ───────────────────────────
$stmt4 = $db->prepare('INSERT INTO availability (doctor_id, day, start_time, end_time) VALUES (?, ?, ?, ?)');

foreach ($days as $day) {
    $stmt4->bind_param('isss', $doctor_id, $day, $am_start, $am_end);
    $stmt4->execute();

    if ($pm_start && $pm_end) {
        $stmt4->bind_param('isss', $doctor_id, $day, $pm_start, $pm_end);
        $stmt4->execute();
    }
}

$stmt4->close();
$db->close();

successResponse('Doctor registered successfully.', [
    'doctor_id' => $doctor_id,
    'redirect'  => 'doctor_dashboard.html?registered=success'
]);
