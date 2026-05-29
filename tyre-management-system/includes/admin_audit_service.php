<?php
declare(strict_types=1);

require_once __DIR__ . '/department_hierarchy.php';

function admin_audit_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS erp_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        user_name VARCHAR(120) NOT NULL,
        department VARCHAR(100) NULL,
        action_text VARCHAR(255) NOT NULL,
        module_name VARCHAR(60) NOT NULL DEFAULT 'System',
        status VARCHAR(20) NOT NULL DEFAULT 'success',
        detail VARCHAR(255) NULL,
        entity_type VARCHAR(40) NULL,
        entity_id INT NULL,
        old_value VARCHAR(500) NULL,
        new_value VARCHAR(500) NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_created (created_at),
        INDEX idx_activity_module (module_name),
        INDEX idx_activity_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    foreach ([
        'user_id' => 'INT NULL AFTER id',
        'entity_type' => 'VARCHAR(40) NULL AFTER detail',
        'entity_id' => 'INT NULL AFTER entity_type',
        'old_value' => 'VARCHAR(500) NULL AFTER entity_id',
        'new_value' => 'VARCHAR(500) NULL AFTER old_value',
        'ip_address' => 'VARCHAR(45) NULL AFTER new_value',
    ] as $col => $def) {
        if (!dh_column_exists($pdo, 'erp_activity_log', $col)) {
            try {
                $pdo->exec('ALTER TABLE erp_activity_log ADD COLUMN ' . $col . ' ' . $def);
            } catch (Throwable) {
            }
        }
    }
}

function admin_audit_log(
    PDO $pdo,
    string $action,
    string $module = 'System Administration',
    string $status = 'success',
    ?string $detail = null,
    ?string $oldValue = null,
    ?string $newValue = null,
    ?string $entityType = null,
    ?int $entityId = null
): void {
    admin_audit_ensure_schema($pdo);
    $user = current_user();
    $userId = (int)($user['id'] ?? 0);
    $name = (string)($user['full_name'] ?? $user['name'] ?? 'System');
    $dept = '';
    if (!empty($user['employee_id'])) {
        try {
            $st = $pdo->prepare('SELECT department FROM employees WHERE id = :id LIMIT 1');
            $st->execute(['id' => (int)$user['employee_id']]);
            $dept = (string)($st->fetchColumn() ?: '');
        } catch (Throwable) {
        }
    }
    $st = $pdo->prepare(
        'INSERT INTO erp_activity_log (user_id, user_name, department, action_text, module_name, status, detail,
         entity_type, entity_id, old_value, new_value, ip_address)
         VALUES (:uid, :u, :d, :a, :m, :s, :dt, :et, :eid, :ov, :nv, :ip)'
    );
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $st->execute([
        'uid' => $userId > 0 ? $userId : null,
        'u' => $name,
        'd' => $dept !== '' ? $dept : null,
        'a' => $action,
        'm' => $module,
        's' => $status,
        'dt' => $detail,
        'et' => $entityType,
        'eid' => $entityId,
        'ov' => $oldValue !== null ? mb_substr($oldValue, 0, 500) : null,
        'nv' => $newValue !== null ? mb_substr($newValue, 0, 500) : null,
        'ip' => $ip !== '' ? $ip : null,
    ]);
}

/** @return list<array<string, mixed>> */
function admin_audit_list(PDO $pdo, array $filters = [], int $limit = 100): array
{
    admin_audit_ensure_schema($pdo);
    $sql = 'SELECT id, user_name, department, action_text, module_name, status, detail,
                   entity_type, entity_id, old_value, new_value, ip_address, created_at
            FROM erp_activity_log WHERE 1=1';
    $params = [];

    if (!empty($filters['module'])) {
        $sql .= ' AND module_name LIKE :mod';
        $params['mod'] = '%' . $filters['module'] . '%';
    }
    if (!empty($filters['q'])) {
        $sql .= ' AND (user_name LIKE :q OR action_text LIKE :q OR department LIKE :q OR detail LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit));
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $ts = (string)($r['created_at'] ?? '');
        $rows[] = [
            'id' => (int)$r['id'],
            'user' => (string)$r['user_name'],
            'department' => (string)($r['department'] ?? '—'),
            'action' => (string)$r['action_text'],
            'module' => (string)$r['module_name'],
            'date' => substr($ts, 0, 10),
            'time' => substr($ts, 11, 5),
            'status' => (string)$r['status'],
            'detail' => (string)($r['detail'] ?? ''),
            'entity_type' => (string)($r['entity_type'] ?? ''),
            'entity_id' => (int)($r['entity_id'] ?? 0),
            'old_value' => (string)($r['old_value'] ?? ''),
            'new_value' => (string)($r['new_value'] ?? ''),
            'ip' => (string)($r['ip_address'] ?? '—'),
        ];
    }

    return $rows;
}

/** @return array<string, mixed>|null */
function admin_audit_get(PDO $pdo, int $id): ?array
{
    admin_audit_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM erp_activity_log WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function admin_audit_status_badge(string $status): array
{
    return match ($status) {
        'success' => ['label' => 'Success', 'cls' => 'admin-badge--ok'],
        'warning' => ['label' => 'Warning', 'cls' => 'admin-badge--warn'],
        'danger', 'failed' => ['label' => 'Failed', 'cls' => 'admin-badge--danger'],
        default => ['label' => ucfirst($status), 'cls' => 'admin-badge--muted'],
    };
}
