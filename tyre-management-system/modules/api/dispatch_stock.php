<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/production_service.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$pdo = Database::connection();
$tyreType = trim((string)($_GET['tyre_type'] ?? ''));
$stock = dispatch_fg_stock_by_type($pdo);

if ($tyreType !== '') {
    $available = (int)($stock[$tyreType] ?? dispatch_fg_available($pdo, $tyreType));
    echo json_encode([
        'tyre_type' => $tyreType,
        'available' => $available,
        'stock' => $stock,
    ]);
    exit;
}

echo json_encode(['stock' => $stock]);
