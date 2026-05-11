<?php
// api/patient_register.php
// POST /api/patient_register.php
// Body: { email, name, phone, age, gender, password, confirm_password }

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed.', 405);
}

$input    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email    = sanitize($input['email']            ?? '');
$name     = sanitize($input['name']             ?? '');
$phone    = sanitize($input['phone']            ?? '');
$age      = intval($input['age']                ?? 0);
$gender   = sanitize($input['gender']           ?? '');
$password = $input['password']                  ?? '';
$confirm  = $input['confirm_password']          ?? '';

// ── Validate ───────────────────────────────────────────
if (!$email || !$name || !$phone || !$age || !$gender || !$password || !$confirm) {
    errorResponse('Please fill in all fields.', 400);
}

if (!isValidEmail($email)) {
    errorResponse('Invalid email format.', 400, 'email');
}

if (strlen($name) < 2 || strlen($name) > 255) {
    errorResponse('Name must be between 2 and 255 characters.', 400, 'name');
}

if (!isValidPhone($phone)) {
    errorResponse('Invalid phone number. Must be 10–15 digits.', 400, 'phone');
}

if ($age < 1 || $age > 120) {
    errorResponse('Age must be between 1 and 120.', 400, 'age');
}

$allowed_genders = ['Male', 'Female', 'Other'];
if (!in_array($gender, $allowed_genders)) {
    errorResponse('Invalid gender selected.', 400, 'gender');
}

if (strlen($password) < 6) {
    errorResponse('Password must be at least 6 characters.', 400, 'password');
}

if ($password !== $confirm) {
    errorResponse('Passwords do not match.', 400, 'confirm_password');
}

// ── Check email uniqueness ─────────────────────────────
$db = getDB();

$stmt = $db->prepare('SELECT id FROM patients WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    errorResponse('An account with this email already exists.', 409, 'email');
}
$stmt->close();

$stmt2 = $db->prepare('SELECT id FROM doctors WHERE email = ? LIMIT 1');
$stmt2->bind_param('s', $email);
$stmt2->execute();
$stmt2->store_result();
if ($stmt2->num_rows > 0) {
    $stmt2->close();
    errorResponse('An account with this email already exists.', 409, 'email');
}
$stmt2->close();

// ── Hash password and insert ───────────────────────────
$hashed = hashPassword($password);

$stmt3 = $db->prepare('INSERT INTO patients (name, email, phone, age, gender, password) VALUES (?, ?, ?, ?, ?, ?)');
$stmt3->bind_param('sssiss', $name, $email, $phone, $age, $gender, $hashed);

if (!$stmt3->execute()) {
    $stmt3->close();
    errorResponse('Registration failed. Please try again.', 500);
}

$patient_id = $db->insert_id;
$stmt3->close();
$db->close();

// ── Auto-login: set session ────────────────────────────
startSession();
$_SESSION['user_id'] = $patient_id;
$_SESSION['name']    = $name;
$_SESSION['email']   = $email;
$_SESSION['role']    = 'patient';

successResponse('Registration successful.', [
    'redirect' => 'find_doctor.html'
]);