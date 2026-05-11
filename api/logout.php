<?php
// api/logout.php
// POST /api/logout.php

header('Content-Type: application/json');
require_once '../includes/helpers.php';

startSession();
session_destroy();

successResponse('Logged out successfully.', ['redirect' => 'login_signup.html']);
