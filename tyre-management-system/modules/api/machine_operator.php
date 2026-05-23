<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/machine_service.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$machineId = (int)($_GET['machine_id'] ?? 0);
$date = (string)($_GET['date'] ?? date('Y-m-d'));
if ($machineId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'machine_id required']);
    exit;
}

$pdo = Database::connection();
$asg = mach_active_assignment($pdo, $machineId, preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d'));

if (!$asg) {
    echo json_encode(['operator_id' => null, 'operator_name' => null, 'employee_code' => null]);
    exit;
}

echo json_encode([
    'operator_id' => (int)$asg['employee_id'],
    'operator_name' => (string)($asg['operator_name'] ?? ''),
    'employee_code' => (string)($asg['employee_code'] ?? ''),
    'shift' => (string)($asg['shift'] ?? ''),
]);
