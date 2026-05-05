<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_auth(['Employee']);
header('Location: ' . route_url('employee/dashboard'));
exit;

