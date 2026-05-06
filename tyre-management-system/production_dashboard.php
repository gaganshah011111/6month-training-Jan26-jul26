<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_auth(['Production Manager', 'Super Admin']);
header('Location: ' . route_url('production/dashboard'));
exit;
