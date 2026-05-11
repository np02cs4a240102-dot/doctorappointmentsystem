<?php
// api/login.php
// POST /api/login.php
// Body: { email, password, role }

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed.', 405);
}

// ── Get and validate input ──────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

// Support both JSON body and form POST
if (!$input) {
    $input = $_POST;
}

$email    = sanitize($input['email']    ?? '');
$password = $input['password']          ?? '';
$role     = sanitize($input['role']     ?? '');

if (!$email || !$password || !$role) {
    errorResponse('Please fill in all fields.', 400);
}

if (!isValidEmail($email)) {
    errorResponse('Invalid email format.', 400, 'email');
}

if (!in_array($role, ['patient', 'doctor'])) {
    errorResponse('Invalid role. Must be patient or doctor.', 400, 'role');
}

// ── Rate limiting — simple session-based ───────────────
$attemptKey = 'login_attempts_' . md5($email);
$lockKey    = 'login_locked_'   . md5($email);

if (!empty($_SESSION[$lockKey]) && $_SESSION[$lockKey] > time()) {
    $wait = ceil(($_SESSION[$lockKey] - time()) / 60);
    errorResponse("Account temporarily locked. Please try again in {$wait} minute(s).", 429);
}

// ── Query correct table based on role ──────────────────
$db = getDB();

if ($role === 'patient') {
    $stmt = $db->prepare('SELECT id, name, email, password FROM patients WHERE email = ? LIMIT 1');
} else {
    $stmt = $db->prepare("SELECT id, name, email, password FROM doctors WHERE email = ? AND status = 'ACTIVE' LIMIT 1");
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

// ── Verify password ────────────────────────────────────
// Support plain text passwords (sample data) and bcrypt hashes
$passwordValid = false;
if ($user) {
    if (password_get_info($user['password'])['algo']) {
        // bcrypt hash
        $passwordValid = verifyPassword($password, $user['password']);
    } else {
        // plain text (sample/dev data only — remove in production)
        $passwordValid = ($password === $user['password']);
    }
}

if (!$user || !$passwordValid) {
    // Track failed attempts
    $_SESSION[$attemptKey] = ($_SESSION[$attemptKey] ?? 0) + 1;
    if ($_SESSION[$attemptKey] >= 5) {
        $_SESSION[$lockKey]    = time() + (15 * 60); // lock for 15 mins
        $_SESSION[$attemptKey] = 0;
        errorResponse('Too many failed attempts. Account locked for 15 minutes.', 429);
    }
    errorResponse('Invalid email or password.', 401);
}

// ── Success — set session ──────────────────────────────
$_SESSION[$attemptKey] = 0;
unset($_SESSION[$lockKey]);

$_SESSION['user_id'] = $user['id'];
$_SESSION['name']    = $user['name'];
$_SESSION['email']   = $user['email'];
$_SESSION['role']    = $role;

$redirect = ($role === 'patient') ? 'find_doctor.html' : 'doctor_dashboard.html';

successResponse('Login successful.', [
    'user_id'  => $user['id'],
    'name'     => $user['name'],
    'email'    => $user['email'],
    'role'     => $role,
    'redirect' => $redirect
]);
