<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';
require_once __DIR__ . '/../../includes/inventory_purchase.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin', 'Sales Manager'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$pdo = Database::connection();
inv_purchase_ensure_schema($pdo);
$action = (string)($_REQUEST['action'] ?? '');

try {
    if ($action === 'inward') {
        $id = (int)($_GET['inward_id'] ?? 0);
        $row = inv_purchase_get($pdo, $id);
        if (!$row) {
            throw new InvalidArgumentException('Purchase invoice not found.');
        }
        $payments = inv_purchase_list_payments($pdo, $id);
        echo json_encode([
            'ok' => true,
            'invoice' => [
                'id' => (int)$row['id'],
                'pinv_no' => (string)($row['pinv_no'] ?? ''),
                'invoice_no' => (string)($row['invoice_no'] ?? ''),
                'supplier' => (string)($row['supplier_name'] ?? '—'),
                'total' => (float)($row['total_amount'] ?? 0),
                'paid' => (float)($row['paid_amount'] ?? 0),
                'remaining' => (float)($row['pending_amount'] ?? 0),
                'due_date' => (string)($row['due_date'] ?? ''),
                'status' => (string)($row['payment_status'] ?? 'Unpaid'),
            ],
            'payments' => array_map(static function (array $p): array {
                return [
                    'id' => (int)$p['id'],
                    'date' => (string)$p['payment_date'],
                    'amount' => (float)$p['amount'],
                    'mode' => (string)($p['payment_mode'] ?? ''),
                    'reference' => (string)($p['payment_ref'] ?? ''),
                    'notes' => (string)($p['notes'] ?? ''),
                ];
            }, $payments),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'save_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $inwardId = (int)($_POST['inward_id'] ?? 0);
        $editPaymentId = (int)($_POST['payment_id'] ?? 0);
        $notes = trim((string)($_POST['notes'] ?? ''));
        $bankCash = trim((string)($_POST['bank_cash'] ?? ''));
        $mergedNotes = trim($notes . ($bankCash !== '' ? ($notes !== '' ? ' | ' : '') . 'Bank/Cash: ' . $bankCash : ''));
        $post = $_POST;
        $post['notes'] = $mergedNotes;
        $message = 'Supplier payment recorded successfully.';
        if ($editPaymentId > 0) {
            inv_purchase_update_payment($pdo, $editPaymentId, $post);
            $paymentId = $editPaymentId;
            $message = 'Supplier payment updated successfully.';
        } else {
            $paymentId = inv_purchase_add_payment($pdo, $inwardId, $post);
        }
        $row = inv_purchase_get($pdo, $inwardId);
        if (!$row) {
            throw new RuntimeException('Unable to reload invoice.');
        }
        $status = (string)($row['payment_status'] ?? 'Unpaid');
        $meta = inv_purchase_payment_meta($status);
        $listFilters = [
            'from' => trim((string)($_POST['filter_from'] ?? '')),
            'to' => trim((string)($_POST['filter_to'] ?? '')),
            'supplier_id' => (int)($_POST['filter_supplier_id'] ?? 0),
            'payment_status' => trim((string)($_POST['filter_payment_status'] ?? '')),
        ];
        if ($listFilters['from'] === '') {
            $listFilters['from'] = date('Y-m-01');
        }
        if ($listFilters['to'] === '') {
            $listFilters['to'] = date('Y-m-d');
        }
        echo json_encode([
            'ok' => true,
            'message' => $message,
            'payment_id' => $paymentId,
            'invoice' => [
                'id' => (int)$row['id'],
                'paid' => (float)($row['paid_amount'] ?? 0),
                'remaining' => (float)($row['pending_amount'] ?? 0),
                'status' => $status,
                'status_label' => $meta['label'],
                'status_badge' => $meta['badge'],
            ],
            'dashboard' => acc_payables_page_kpis($pdo, $listFilters),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    throw new InvalidArgumentException('Invalid request.');
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
}

