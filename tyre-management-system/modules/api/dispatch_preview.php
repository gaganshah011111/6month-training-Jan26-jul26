<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = Database::connection();
$force = isset($_GET['refresh']);
$preview = dispatch_form_preview($pdo, $force);

echo json_encode([
    'ok' => true,
    'invoice_no' => $preview['invoice_no'],
    'dispatch_code' => $preview['dispatch_code'],
]);
