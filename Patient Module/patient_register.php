<?php
// api/patient_register.php
// POST /api/patient_register.php
// Body: { email, password, confirm_password }

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed.', 405);
}

// ── Get input ──────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$email    = sanitize($input['email']            ?? '');
$password = $input['password']                  ?? '';
$confirm  = $input['confirm_password']          ?? '';

// ── Validate ───────────────────────────────────────────
if (!$email || !$password || !$confirm) {
    errorResponse('Please fill in all fields.', 400);
}

if (!isValidEmail($email)) {
    errorResponse('Invalid email format.', 400, 'email');
}

if (strlen($email) > 255) {
    errorResponse('Email must not exceed 255 characters.', 400, 'email');
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

// Also check doctors table — same email cannot exist in both
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

// Extract name from email as default (before @)
$name = explode('@', $email)[0];

$stmt3 = $db->prepare('INSERT INTO patients (name, email, password) VALUES (?, ?, ?)');
$stmt3->bind_param('sss', $name, $email, $hashed);

if (!$stmt3->execute()) {
    $stmt3->close();
    errorResponse('Registration failed. Please try again.', 500);
}

$stmt3->close();
$db->close();

successResponse('Registration successful. Please log in.', [
    'redirect' => 'login_signup.html?signup=success'
]);
