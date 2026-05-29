<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/accounts_salary_payroll.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$pdo = Database::connection();
acc_salary_ensure_schema($pdo);
$action = (string)($_REQUEST['action'] ?? '');

try {
    if ($action === 'employee') {
        $salaryId = (int)($_GET['salary_id'] ?? 0);
        $salary = acc_salary_get_employee_row($pdo, $salaryId);
        if (!$salary) {
            throw new InvalidArgumentException('Employee salary record not found.');
        }
        $payments = acc_salary_employee_payments($pdo, $salaryId);
        echo json_encode([
            'ok' => true,
            'salary' => [
                'id' => (int)$salary['id'],
                'full_name' => (string)$salary['full_name'],
                'employee_code' => (string)$salary['employee_code'],
                'dept_label' => (string)($salary['dept_label'] ?? ''),
                'desig_label' => (string)($salary['desig_label'] ?? ''),
                'month_year' => (string)$salary['month_year'],
                'month_label' => payroll_format_month_label((string)$salary['month_year']),
                'net_salary' => (float)$salary['net_salary'],
                'amount_paid' => (float)$salary['amount_paid'],
                'pending' => (float)$salary['pending'],
                'pay_status' => (string)$salary['pay_status'],
            ],
            'profile' => [
                'phone' => (string)($salary['phone'] ?? ''),
                'email' => (string)($salary['email'] ?? ''),
                'designation' => (string)($salary['designation'] ?? ''),
            ],
            'payments' => array_map(static function (array $p): array {
                return [
                    'id' => (int)$p['id'],
                    'date' => (string)$p['payment_date'],
                    'amount' => (float)$p['amount'],
                    'mode' => (string)$p['payment_mode'],
                    'reference' => (string)($p['reference_no'] ?? ''),
                    'remarks' => (string)($p['remarks'] ?? ''),
                    'receipt_url' => route_url('accounts/salary-payment-receipt', ['id' => (int)$p['id']]),
                ];
            }, $payments),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'save_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $salaryId = (int)($_POST['salary_id'] ?? 0);
        $result = acc_salary_record_employee_payment($pdo, $salaryId, $_POST);
        $s = $result['salary'];
        echo json_encode([
            'ok' => true,
            'message' => 'Salary payment recorded. Cash & Bank and transaction history updated.',
            'payment_id' => $result['payment_id'],
            'receipt_url' => $result['receipt_url'],
            'salary' => [
                'id' => (int)($s['id'] ?? 0),
                'net_salary' => (float)($s['net_salary'] ?? 0),
                'amount_paid' => (float)($s['amount_paid'] ?? 0),
                'pending' => (float)($s['pending'] ?? 0),
                'pay_status' => (string)($s['pay_status'] ?? ''),
            ],
            'dashboard' => acc_salary_dashboard_kpis($pdo, (string)($s['month_year'] ?? date('Y-m'))),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    throw new InvalidArgumentException('Unknown action.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
