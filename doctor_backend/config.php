<?php
session_start();
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'medicare_connect';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

// Set default doctor_id for demo (in real app, use $_SESSION['doctor_id'])
if (!isset($_SESSION['doctor_id'])) {
    $_SESSION['doctor_id'] = 1; // Default to first doctor
}
?>