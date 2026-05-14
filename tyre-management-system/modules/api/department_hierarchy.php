<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!has_role(['Super Admin', 'HR Manager', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../includes/department_hierarchy.php';

$pdo = Database::connection();
$action = (string)($_GET['action'] ?? '');

try {
    if ($action === 'categories') {
        $rows = $pdo->query("SELECT id, category_name AS label, category_code AS code FROM department_categories WHERE status = 'active' ORDER BY category_name")
            ->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'items' => $rows]);
        exit;
    }

    if ($action === 'departments') {
        $cid = (int)($_GET['category_id'] ?? 0);
        if ($cid < 1) {
            throw new InvalidArgumentException('category_id required');
        }
        $st = $pdo->prepare("SELECT id, department_name AS label, department_short_name AS short_name, department_code AS code
            FROM departments WHERE category_id = :c AND status = 'active' ORDER BY department_name");
        $st->execute(['c' => $cid]);
        echo json_encode(['ok' => true, 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'designations') {
        $did = (int)($_GET['department_id'] ?? 0);
        if ($did < 1) {
            throw new InvalidArgumentException('department_id required');
        }
        $st = $pdo->prepare("SELECT id, designation_name AS label, designation_code AS code
            FROM designations WHERE department_id = :d AND status = 'active' ORDER BY designation_name");
        $st->execute(['d' => $did]);
        echo json_encode(['ok' => true, 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
