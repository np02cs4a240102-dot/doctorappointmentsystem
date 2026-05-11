<?php
// api/admin_login.php
// POST /api/admin_login.php
// Body: { email, password }

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed.', 405);
}

$input    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email    = sanitize($input['email']    ?? '');
$password = $input['password']          ?? '';

if (!$email || !$password) {
    errorResponse('Please fill in all fields.', 400);
}

if (!isValidEmail($email)) {
    errorResponse('Invalid email format.', 400, 'email');
}

$db   = getDB();
$stmt = $db->prepare('SELECT id, name, email, password FROM admins WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

$passwordValid = false;
if ($admin) {
    if (password_get_info($admin['password'])['algo']) {
        $passwordValid = verifyPassword($password, $admin['password']);
    } else {
        $passwordValid = ($password === $admin['password']);
    }
}

if (!$admin || !$passwordValid) {
    errorResponse('Invalid email or password.', 401);
}

$_SESSION['user_id'] = $admin['id'];
$_SESSION['name']    = $admin['name'];
$_SESSION['email']   = $admin['email'];
$_SESSION['role']    = 'admin';

successResponse('Login successful.', [
    'name'     => $admin['name'],
    'redirect' => 'admin_dashboard.html'
]);
