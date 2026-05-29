<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/accounts_expenses.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin', 'Sales Manager'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$pdo = Database::connection();
$action = (string)($_REQUEST['action'] ?? '');

try {
    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        $row = acc_get_expense($pdo, $id);
        if (!$row) {
            throw new InvalidArgumentException('Expense not found.');
        }
        $files = [];
        foreach (['bill' => 'attachment_bill', 'receipt' => 'attachment_receipt', 'invoice' => 'attachment_invoice'] as $k => $col) {
            if (!empty($row[$col])) {
                $files[$k] = [
                    'label' => ucfirst($k),
                    'name' => basename((string)$row[$col]),
                    'url' => acc_expense_file_url($id, $k),
                ];
            }
        }
        echo json_encode([
            'ok' => true,
            'expense' => [
                'id' => (int)$row['id'],
                'expense_date' => (string)$row['expense_date'],
                'category' => (string)$row['category'],
                'amount' => (float)$row['amount'],
                'payment_mode' => (string)$row['payment_mode'],
                'reference_no' => (string)($row['reference_no'] ?? ''),
                'remarks' => (string)($row['remarks'] ?? ''),
                'created_by' => (string)($row['created_by'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'source_type' => (string)($row['source_type'] ?? ''),
                'files' => $files,
            ],
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        acc_delete_expense($pdo, (int)($_POST['id'] ?? 0));
        echo json_encode(['ok' => true, 'message' => 'Expense deleted.'], JSON_THROW_ON_ERROR);
        exit;
    }

    throw new InvalidArgumentException('Invalid request.');
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
}
