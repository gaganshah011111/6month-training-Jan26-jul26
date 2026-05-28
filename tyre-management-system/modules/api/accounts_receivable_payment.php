<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/sales_service.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin', 'Sales Manager'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$pdo = Database::connection();
$action = (string)($_REQUEST['action'] ?? '');

try {
    if ($action === 'invoice') {
        $invoiceId = (int)($_GET['invoice_id'] ?? 0);
        $inv = sales_get_invoice($pdo, $invoiceId);
        if (!$inv) {
            throw new InvalidArgumentException('Invoice not found.');
        }
        $disp = sales_invoice_display_status($inv);
        $payments = sales_invoice_payments($pdo, $invoiceId);
        echo json_encode([
            'ok' => true,
            'invoice' => [
                'id' => (int)$inv['id'],
                'invoice_no' => (string)$inv['invoice_no'],
                'customer' => (string)$inv['company_name'],
                'customer_id' => (int)$inv['customer_id'],
                'total' => (float)$inv['total_amount'],
                'paid' => (float)$inv['amount_paid'],
                'remaining' => (float)$disp['pending'],
                'due_date' => (string)($inv['due_date'] ?? ''),
                'status' => (string)$disp['label'],
            ],
            'payments' => array_map(static function (array $p): array {
                $pid = (int)$p['id'];

                return [
                    'id' => $pid,
                    'date' => (string)$p['payment_date'],
                    'amount' => (float)$p['amount'],
                    'mode' => (string)$p['payment_mode'],
                    'reference' => (string)($p['reference_no'] ?? ''),
                    'remarks' => (string)($p['remarks'] ?? ''),
                    'receipt_url' => route_url('accounts/payment-receipt', ['id' => $pid]),
                ];
            }, $payments),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'save_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $message = 'Payment recorded successfully.';
        if ($paymentId > 0) {
            $existing = sales_get_payment($pdo, $paymentId);
            if (!$existing) {
                throw new InvalidArgumentException('Payment not found.');
            }
            if ((int)$existing['invoice_id'] !== $invoiceId) {
                throw new InvalidArgumentException('Payment does not belong to selected invoice.');
            }
            $newAmount = max(0, (float)($_POST['amount'] ?? 0));
            $newDate = trim((string)($_POST['payment_date'] ?? date('Y-m-d')));
            $newMode = trim((string)($_POST['payment_mode'] ?? 'Bank Transfer'));
            if (!in_array($newMode, SALES_PAYMENT_MODES, true)) {
                throw new InvalidArgumentException('Invalid payment mode.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
                throw new InvalidArgumentException('Valid payment date is required.');
            }
            if ($newAmount <= 0) {
                throw new InvalidArgumentException('Payment amount must be greater than zero.');
            }
            $inv = sales_get_invoice($pdo, $invoiceId);
            if (!$inv) {
                throw new InvalidArgumentException('Invoice not found.');
            }
            $oldAmount = (float)$existing['amount'];
            $paidWithoutThis = max(0.0, sales_round_money((float)$inv['amount_paid'] - $oldAmount));
            $maxAllowed = sales_invoice_pending_amount((float)$inv['total_amount'], $paidWithoutThis);
            if ($newAmount > $maxAllowed + 0.001) {
                throw new InvalidArgumentException('Amount exceeds invoice pending balance (' . sales_format_money($maxAllowed) . ').');
            }
            if ($newAmount >= $maxAllowed - 0.001) {
                $newAmount = $maxAllowed;
            }
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'UPDATE sales_payments
                     SET payment_date = :dt, amount = :amt, payment_mode = :mode, reference_no = :ref, remarks = :rm
                     WHERE id = :id'
                )->execute([
                    'dt' => $newDate,
                    'amt' => $newAmount,
                    'mode' => $newMode,
                    'ref' => trim((string)($_POST['reference_no'] ?? '')) ?: null,
                    'rm' => trim((string)($_POST['remarks'] ?? '')) ?: null,
                    'id' => $paymentId,
                ]);
                $pdo->prepare('UPDATE sales_invoices SET amount_paid = :paid WHERE id = :id')
                    ->execute(['paid' => $paidWithoutThis + $newAmount, 'id' => $invoiceId]);
                sales_refresh_invoice_payment_status($pdo, $invoiceId);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            $message = 'Payment updated successfully.';
        } else {
            $paymentId = sales_save_payment($pdo, $_POST);
        }

        $inv = sales_get_invoice($pdo, $invoiceId);
        if (!$inv) {
            throw new RuntimeException('Invoice not found after save.');
        }
        $disp = sales_invoice_display_status($inv);
        $dash = sales_payment_dashboard($pdo);
        echo json_encode([
            'ok' => true,
            'message' => $message,
            'payment_id' => $paymentId,
            'receipt_url' => route_url('accounts/payment-receipt', ['id' => $paymentId]),
            'invoice' => [
                'id' => (int)$inv['id'],
                'paid' => (float)$inv['amount_paid'],
                'remaining' => (float)$disp['pending'],
                'status' => (string)$disp['label'],
            ],
            'dashboard' => [
                'total_receivable' => (float)$dash['total_receivable'],
                'collected' => (float)$dash['collected'],
                'pending' => (float)$dash['pending'],
                'overdue' => (float)$dash['overdue'],
            ],
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    throw new InvalidArgumentException('Invalid request.');
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
}
