<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_auth(['Super Admin']);
header('Location: index.php?page=dashboard');
exit;

