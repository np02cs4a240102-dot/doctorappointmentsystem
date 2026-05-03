<?php
// includes/helpers.php

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function errorResponse($message, $code = 400, $field = null) {
    $res = ['error' => true, 'message' => $message];
    if ($field) $res['field'] = $field;
    jsonResponse($res, $code);
}

function successResponse($message, $data = []) {
    jsonResponse(array_merge(['error' => false, 'message' => $message], $data));
}

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireLogin() {
    startSession();
    if (empty($_SESSION['user_id'])) {
        errorResponse('Unauthorized. Please log in.', 401);
    }
}

function requireRole($role) {
    startSession();
    if (empty($_SESSION['role']) || $_SESSION['role'] !== $role) {
        errorResponse('Forbidden. You do not have permission to perform this action.', 403);
    }
}

function sanitize($value) {
    return htmlspecialchars(strip_tags(trim($value)));
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone($phone) {
    return preg_match('/^\+?[0-9]{10,15}$/', $phone);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
