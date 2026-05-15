<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/payroll_service.php';
require_once __DIR__ . '/../../includes/payroll_test_data.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!has_role(['Super Admin', 'HR Manager', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$pdo = Database::connection();
$employeeId = (int)($_GET['employee_id'] ?? 0);
$month = (string)($_GET['month_year'] ?? date('Y-m'));
$extraOt = (float)($_GET['overtime_hours'] ?? 0);
$manualDed = (float)($_GET['deductions'] ?? 0);

if ($employeeId < 1 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid employee or month']);
    exit;
}

$st = $pdo->prepare('SELECT e.*, ' . erp_dept_label_sql('d', 'e') . ' AS dept_label, ' . erp_desig_label_sql('des', 'e') . ' AS desig_label FROM employees e LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN designations des ON des.id = e.designation_id WHERE e.id = :id LIMIT 1');
$st->execute(['id' => $employeeId]);
$emp = $st->fetch(PDO::FETCH_ASSOC);
if (!$emp) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Employee not found']);
    exit;
}

try {
    $useSynthetic = !empty($_GET['test_preview']);

    if ($useSynthetic) {
        $testDefaults = payroll_test_default_counts();
        $calc = payroll_build_calculation_synthetic($pdo, $emp, $month, [
            'present' => (float)($_GET['test_present'] ?? $testDefaults['present']),
            'half_days' => (float)($_GET['test_half_days'] ?? $testDefaults['half_days']),
            'absent' => (float)($_GET['test_absent'] ?? $testDefaults['absent']),
            'late' => (float)($_GET['test_late'] ?? $testDefaults['late']),
            'ot_hours' => (float)($_GET['test_ot_hours'] ?? $testDefaults['ot_hours']),
        ], $manualDed);
    } else {
        $calc = payroll_build_calculation($pdo, $emp, $month, $extraOt, $manualDed);
    }
    echo json_encode([
        'ok' => true,
        'employee' => [
            'id' => $employeeId,
            'full_name' => $emp['full_name'],
            'employee_code' => $emp['employee_code'],
            'department' => $emp['dept_label'] ?? $emp['department'],
            'designation' => $emp['desig_label'] ?? $emp['designation'],
            'fixed_gross' => employee_fixed_gross_monthly($emp),
        ],
        'month' => $month,
        'calc' => $calc,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
