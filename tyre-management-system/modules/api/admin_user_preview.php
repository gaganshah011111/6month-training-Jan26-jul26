<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_users_service.php';
if (!admin_can_access()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$id = (int)($_GET['id'] ?? 0);
$pdo = Database::connection();
$payload = admin_user_drawer_payload($pdo, $id);
header('Content-Type: application/json');
if (!$payload) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}
echo json_encode($payload, JSON_THROW_ON_ERROR);
