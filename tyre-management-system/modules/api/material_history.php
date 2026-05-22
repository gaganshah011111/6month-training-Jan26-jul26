<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_service.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!has_role(['Inventory Manager', 'Super Admin', 'Admin', 'Production Manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$materialId = (int)($_GET['material_id'] ?? 0);
if ($materialId < 1) {
    echo json_encode(['error' => 'Invalid material']);
    exit;
}

$pdo = Database::connection();
$mat = inv_get_material_row($pdo, $materialId);
if (!$mat) {
    echo json_encode(['error' => 'Material not found']);
    exit;
}

$history = [];
foreach (inv_material_history($pdo, $materialId, 100) as $h) {
    $qty = (float)($h['qty_signed'] ?? $h['qty'] ?? 0);
    $signed = isset($h['qty_signed']) ? (float)$h['qty_signed'] : -abs((float)($h['qty'] ?? 0));
    if (($h['txn_type'] ?? '') === 'Added') {
        $signed = abs((float)($h['qty'] ?? 0));
    }
    $history[] = [
        'date' => (string)($h['dt'] ?? ''),
        'type' => (string)($h['txn_type'] ?? ''),
        'quantity' => $signed,
        'department' => (string)($h['department'] ?? '—'),
        'operator' => (string)($h['operator_name'] ?? '—'),
        'remarks' => (string)($h['remarks'] ?? ''),
        'batch' => (string)($h['batch_no'] ?? ''),
        'reason' => (string)($h['usage_reason'] ?? $h['reason'] ?? ''),
    ];
}

echo json_encode([
    'material' => [
        'id' => $materialId,
        'name' => $mat['material_name'],
        'code' => $mat['material_code'],
        'unit' => $mat['unit'],
        'stock' => (float)$mat['stock_qty'],
    ],
    'history' => $history,
]);
