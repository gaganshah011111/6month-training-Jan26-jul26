<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/indian_payroll.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!has_role(['Super Admin', 'HR Manager', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$pdo = Database::connection();
$settings = payroll_settings_for_client(payroll_settings_fetch($pdo));

echo json_encode([
    'ok' => true,
    'settings' => $settings,
], JSON_THROW_ON_ERROR);
