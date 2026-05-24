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

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId < 1) {
    echo json_encode(['ok' => false, 'lines' => []]);
    exit;
}

$pdo = Database::connection();
$order = sales_get_order($pdo, $orderId);
$lines = sales_order_lines_remaining($pdo, $orderId);
echo json_encode([
    'ok' => true,
    'order' => $order ? ['id' => $orderId, 'so_number' => $order['so_number'], 'customer_id' => $order['customer_id'], 'company_name' => $order['company_name']] : null,
    'lines' => $lines,
]);
