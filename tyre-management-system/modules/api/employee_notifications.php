<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/employee_portal.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!has_role(['Employee'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$pdo = Database::connection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

if ($method === 'GET') {
    echo json_encode(emp_notifications_payload($pdo, $employeeId), JSON_THROW_ON_ERROR);
    exit;
}

if ($method === 'POST') {
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    if (!empty($body['_token'])) {
        $_POST['_token'] = (string)$body['_token'];
    }
    verify_csrf();
    $action = (string)($body['action'] ?? '');
    if ($action === 'mark_read') {
        $keys = $body['keys'] ?? [];
        if (!is_array($keys)) {
            $keys = [];
        }
        emp_notifications_mark_read($pdo, $employeeId, $keys);
        echo json_encode(['ok' => true, 'payload' => emp_notifications_payload($pdo, $employeeId)], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($action === 'mark_all_read') {
        emp_notifications_mark_all_read($pdo, $employeeId);
        echo json_encode(['ok' => true, 'payload' => emp_notifications_payload($pdo, $employeeId)], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($action === 'clear_all') {
        emp_notifications_clear_all($pdo, $employeeId);
        echo json_encode(['ok' => true, 'payload' => emp_notifications_payload($pdo, $employeeId)], JSON_THROW_ON_ERROR);
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
