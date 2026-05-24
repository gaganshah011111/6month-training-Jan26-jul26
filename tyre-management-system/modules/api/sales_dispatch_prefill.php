<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/sales_service.php';

if (!has_role(['Dispatch Manager', 'Super Admin', 'Sales Manager'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);
$itemId = (int)($_GET['item_id'] ?? 0);

if ($orderId < 1 || $itemId < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'order_id and item_id required']);
    exit;
}

try {
    $pdo = Database::connection();
    $data = sales_dispatch_prefill($pdo, $orderId, $itemId);
    echo json_encode($data, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
