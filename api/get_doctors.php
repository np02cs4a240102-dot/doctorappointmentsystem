<?php
// api/get_doctors.php
// GET /api/get_doctors.php?name=&specialization=
// Auth: patient session required

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed.', 405);
}

// ── Optional filters ───────────────────────────────────
$name           = sanitize($_GET['name']           ?? '');
$specialization = sanitize($_GET['specialization'] ?? '');

if (strlen($name) > 100) {
    errorResponse('Search name is too long.', 400, 'name');
}

$allowed_specs = ['Cardiologist','Dermatologist','Dentist','Neurologist','Pediatrician','Orthopedic','Gynecologist','General Physician'];
if ($specialization && !in_array($specialization, $allowed_specs)) {
    errorResponse('Invalid specialization filter.', 400, 'specialization');
}

// ── Build query ────────────────────────────────────────
$db = getDB();

$sql    = "SELECT id, name, specialization, experience_years, allowed_days, allowed_slots, phone FROM doctors WHERE status = 'ACTIVE'";
$params = [];
$types  = '';

if ($name) {
    $sql     .= ' AND name LIKE ?';
    $params[] = "%{$name}%";
    $types   .= 's';
}

if ($specialization) {
    $sql     .= ' AND specialization = ?';
    $params[] = $specialization;
    $types   .= 's';
}

$sql .= ' ORDER BY name ASC';

$stmt = $db->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result  = $stmt->get_result();
$doctors = [];

while ($row = $result->fetch_assoc()) {
    $doctors[] = [
        'id'               => $row['id'],
        'name'             => $row['name'],
        'specialization'   => $row['specialization'],
        'experience_years' => $row['experience_years'],
        'allowed_days'     => $row['allowed_days'],
        'allowed_slots'    => $row['allowed_slots'],
    ];
}

$stmt->close();
$db->close();

successResponse('Doctors retrieved.', ['doctors' => $doctors]);
