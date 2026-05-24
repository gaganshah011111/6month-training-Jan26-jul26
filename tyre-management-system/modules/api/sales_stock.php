<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !is_sales_manager()) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$tyre = trim((string)($_GET['tyre_type'] ?? ''));
$qty = (int)($_GET['qty'] ?? 0);
if ($tyre === '') {
    echo json_encode(['ok' => false, 'error' => 'Tyre type required']);
    exit;
}

$snap = sales_stock_snapshot(Database::connection(), $tyre, max(1, $qty));
echo json_encode(['ok' => true, 'stock' => $snap]);
