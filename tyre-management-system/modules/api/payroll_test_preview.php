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

if ($employeeId < 1 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid employee or month']);
    exit;
}

$st = $pdo->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
$st->execute(['id' => $employeeId]);
$emp = $st->fetch(PDO::FETCH_ASSOC);
if (!$emp) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Employee not found']);
    exit;
}

$defaults = payroll_test_default_counts();

try {
    $calc = payroll_build_calculation_synthetic($pdo, $emp, $month, [
        'present' => (float)($_GET['test_present'] ?? $defaults['present']),
        'half_days' => (float)($_GET['test_half_days'] ?? $defaults['half_days']),
        'absent' => (float)($_GET['test_absent'] ?? $defaults['absent']),
        'late' => (float)($_GET['test_late'] ?? $defaults['late']),
        'ot_hours' => (float)($_GET['test_ot_hours'] ?? $defaults['ot_hours']),
    ]);

    echo json_encode([
        'ok' => true,
        'calc' => $calc,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
