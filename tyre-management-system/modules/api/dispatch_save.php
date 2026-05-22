<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

verify_csrf();

$pdo = Database::connection();
$action = (string)($_POST['submit_action'] ?? 'save');
$targetStatus = $action === 'deliver' ? DISPATCH_STATUS_DELIVERED : DISPATCH_STATUS_DISPATCHED;

try {
    $tyreType = trim((string)($_POST['tyre_type'] ?? ''));
    $qty = (int)($_POST['qty'] ?? 0);
    if ($tyreType !== '' && $qty > 0) {
        dispatch_validate_stock_qty($pdo, $tyreType, $qty);
    }
    $id = dispatch_save($pdo, $_POST, $targetStatus);
    $row = dispatch_get_by_id($pdo, $id) ?: [];
    $code = (string)($row['dispatch_code'] ?? $id);
    $invoice = (string)($row['invoice_no'] ?? '');
    $msg = $targetStatus === DISPATCH_STATUS_DELIVERED
        ? 'Dispatch saved and marked delivered.'
        : 'Dispatch saved successfully.';
    echo json_encode([
        'ok' => true,
        'message' => $msg,
        'dispatch_code' => $code,
        'invoice_no' => $invoice,
        'id' => $id,
        'slip_url' => dispatch_slip_url($id),
        'print_url' => dispatch_slip_url($id, 'print'),
    ]);
} catch (InvalidArgumentException $e) {
    $field = 'form';
    $msg = $e->getMessage();
    if (stripos($msg, 'tyres available') !== false || stripos($msg, 'insufficient') !== false) {
        $field = 'qty';
    } elseif (stripos($msg, 'driver') !== false) {
        $field = 'driver_id';
    } elseif (stripos($msg, 'transport') !== false) {
        $field = 'transport_company_id';
    } elseif (stripos($msg, 'vehicle') !== false) {
        $field = 'vehicle_id';
    } elseif (stripos($msg, 'customer') !== false) {
        $field = 'customer_name';
    } elseif (stripos($msg, 'weight') !== false || stripos($msg, 'gross') !== false || stripos($msg, 'tare') !== false) {
        $field = 'gross_weight_kg';
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $msg, 'field' => $field]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save dispatch. Please try again.', 'field' => 'form']);
}
