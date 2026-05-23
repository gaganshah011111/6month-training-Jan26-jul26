<?php
declare(strict_types=1);

require_once __DIR__ . '/department_hierarchy.php';

/** Shifts used for machine assignments (matches production module). */
const MACH_ASSIGNMENT_SHIFTS = ['Morning', 'Evening', 'Night'];

/** Industrial machine master statuses. */
const MACHINE_STATUS_ACTIVE = 'Active';
const MACHINE_STATUS_IDLE = 'Idle';
const MACHINE_STATUS_REPAIR = 'Under Repair';
const MACHINE_STATUS_SCRAP = 'Scrap / Deactivated';

const MACHINE_MASTER_STATUSES = [
    MACHINE_STATUS_ACTIVE,
    MACHINE_STATUS_IDLE,
    MACHINE_STATUS_REPAIR,
    MACHINE_STATUS_SCRAP,
];

const MACHINE_PRODUCTION_DEPARTMENTS = ['Mixing', 'Building', 'Curing'];

/** Runtime schema for machine module (idempotent). */
function mach_ensure_schema(PDO $pdo): void
{
    if (!dh_table_exists($pdo, 'machines')) {
        return;
    }

    $cols = [
        'section' => 'ALTER TABLE machines ADD COLUMN section VARCHAR(80) NULL AFTER department',
        'installation_date' => 'ALTER TABLE machines ADD COLUMN installation_date DATE NULL AFTER section',
        'remarks' => 'ALTER TABLE machines ADD COLUMN remarks TEXT NULL AFTER notes',
        'is_active' => 'ALTER TABLE machines ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1',
        'deactivated_at' => 'ALTER TABLE machines ADD COLUMN deactivated_at DATETIME NULL AFTER is_active',
    ];
    foreach ($cols as $col => $sql) {
        if (!dh_column_exists($pdo, 'machines', $col)) {
            try {
                $pdo->exec($sql);
            } catch (Throwable) {
            }
        }
    }

  try {
        $pdo->exec("UPDATE machines SET status = 'Active' WHERE status IN ('Running','Active','active')");
        $pdo->exec("UPDATE machines SET status = 'Idle' WHERE status IN ('Inactive','inactive') AND COALESCE(is_active,1) = 1");
        $pdo->exec("UPDATE machines SET status = 'Under Repair' WHERE status IN ('Maintenance','Breakdown','Under Maintenance')");
        $pdo->exec("UPDATE machines SET status = 'Scrap / Deactivated', is_active = 0 WHERE status IN ('Scrap','Deactivated')");
    } catch (Throwable) {
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS machine_operator_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        machine_id INT NOT NULL,
        employee_id INT NOT NULL,
        department VARCHAR(40) NOT NULL,
        shift VARCHAR(20) NULL,
        assigned_from DATE NOT NULL,
        assigned_till DATE NULL,
        remarks TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        closed_at DATETIME NULL,
        closed_reason VARCHAR(120) NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_moa_machine (machine_id),
        INDEX idx_moa_employee (employee_id),
        INDEX idx_moa_active (machine_id, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS machine_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        machine_id INT NOT NULL,
        old_status VARCHAR(40) NULL,
        new_status VARCHAR(40) NOT NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        changed_by INT NULL,
        remarks TEXT NULL,
        INDEX idx_msh_machine (machine_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS machine_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        machine_id INT NOT NULL,
        field_name VARCHAR(40) NOT NULL,
        old_value VARCHAR(255) NULL,
        new_value VARCHAR(255) NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        changed_by INT NULL,
        remarks TEXT NULL,
        INDEX idx_mal_machine (machine_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function mach_normalize_status(string $status): string
{
    return match (trim($status)) {
        'Running', 'Active', 'active' => MACHINE_STATUS_ACTIVE,
        'Inactive', 'inactive' => MACHINE_STATUS_IDLE,
        'Maintenance', 'Breakdown', 'Under Maintenance' => MACHINE_STATUS_REPAIR,
        'Scrap', 'Deactivated' => MACHINE_STATUS_SCRAP,
        default => $status,
    };
}

function mach_status_can_produce(?string $status): bool
{
    return mach_normalize_status((string)$status) === MACHINE_STATUS_ACTIVE;
}

function mach_machine_selectable(array $machine): bool
{
    if ((int)($machine['is_active'] ?? 1) !== 1) {
        return false;
    }

    return mach_status_can_produce((string)($machine['status'] ?? ''));
}

function mach_status_badge(string $status): array
{
    $status = mach_normalize_status($status);

    return match ($status) {
        MACHINE_STATUS_ACTIVE => ['class' => 'mach-badge mach-badge--active', 'label' => 'Active'],
        MACHINE_STATUS_IDLE => ['class' => 'mach-badge mach-badge--idle', 'label' => 'Idle'],
        MACHINE_STATUS_REPAIR => ['class' => 'mach-badge mach-badge--repair', 'label' => 'Under Repair'],
        MACHINE_STATUS_SCRAP => ['class' => 'mach-badge mach-badge--scrap', 'label' => 'Scrap / Deactivated'],
        default => ['class' => 'mach-badge', 'label' => $status],
    };
}

function mach_current_user_id(): ?int
{
    $u = current_user();

    return isset($u['id']) ? (int)$u['id'] : null;
}

/**
 * @param array{department?: string, status?: string, include_inactive?: bool, machine_id?: int} $filters
 * @return list<array<string, mixed>>
 */
function mach_list_machines(PDO $pdo, array $filters = []): array
{
    mach_ensure_schema($pdo);

    $sql = 'SELECT m.id, m.machine_code, m.machine_name, m.department, m.section, m.machine_type,
            m.installation_date, m.shift_capacity, m.status, m.last_maintenance_date,
            m.notes, m.remarks, m.is_active, m.deactivated_at, m.created_at
        FROM machines m WHERE 1=1';
    $params = [];

    if (empty($filters['include_inactive'])) {
        $sql .= ' AND COALESCE(m.is_active, 1) = 1';
    }
    if (!empty($filters['department']) && in_array($filters['department'], MACHINE_PRODUCTION_DEPARTMENTS, true)) {
        $sql .= ' AND m.department = :dep';
        $params['dep'] = $filters['department'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND m.status = :st';
        $params['st'] = mach_normalize_status((string)$filters['status']);
    }
    if (!empty($filters['machine_id'])) {
        $sql .= ' AND m.id = :id';
        $params['id'] = (int)$filters['machine_id'];
    }

    $sql .= ' ORDER BY m.machine_name ASC, m.machine_code ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $r['status'] = mach_normalize_status((string)($r['status'] ?? ''));
    }
    unset($r);

    return $rows;
}

function mach_get_machine(PDO $pdo, int $id): ?array
{
    $rows = mach_list_machines($pdo, ['machine_id' => $id, 'include_inactive' => true]);

    return $rows[0] ?? null;
}

function mach_log_status_change(PDO $pdo, int $machineId, ?string $old, string $new, ?string $remarks = null): void
{
    if (mach_normalize_status((string)$old) === mach_normalize_status($new)) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO machine_status_history (machine_id, old_status, new_status, changed_by, remarks)
         VALUES (:mid, :old, :new, :uid, :rm)'
    )->execute([
        'mid' => $machineId,
        'old' => $old !== null && $old !== '' ? mach_normalize_status($old) : null,
        'new' => mach_normalize_status($new),
        'uid' => mach_current_user_id(),
        'rm' => $remarks,
    ]);
}

function mach_log_field_change(PDO $pdo, int $machineId, string $field, ?string $old, ?string $new, ?string $remarks = null): void
{
    $o = $old === null ? '' : trim($old);
    $n = $new === null ? '' : trim($new);
    if ($o === $n) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO machine_audit_log (machine_id, field_name, old_value, new_value, changed_by, remarks)
         VALUES (:mid, :f, :old, :new, :uid, :rm)'
    )->execute([
        'mid' => $machineId,
        'f' => $field,
        'old' => $o !== '' ? $o : null,
        'new' => $n !== '' ? $n : null,
        'uid' => mach_current_user_id(),
        'rm' => $remarks,
    ]);
}

function mach_save_machine(PDO $pdo, array $data, ?int $id = null): int
{
    mach_ensure_schema($pdo);

    $code = trim((string)($data['machine_code'] ?? ''));
    $name = trim((string)($data['machine_name'] ?? ''));
    $department = trim((string)($data['department'] ?? ''));
    $section = trim((string)($data['section'] ?? ''));
    $type = trim((string)($data['machine_type'] ?? ''));
    $status = mach_normalize_status((string)($data['status'] ?? MACHINE_STATUS_IDLE));
    $capacity = max(0, (int)($data['shift_capacity'] ?? 0));
    $install = trim((string)($data['installation_date'] ?? ''));
    $maint = trim((string)($data['last_maintenance_date'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));
    $remarks = trim((string)($data['remarks'] ?? ''));

    if ($code === '' || $name === '') {
        throw new InvalidArgumentException('Machine code and name are required.');
    }
    if ($department !== '' && !in_array($department, MACHINE_PRODUCTION_DEPARTMENTS, true)) {
        throw new InvalidArgumentException('Invalid production department for machine.');
    }
    if (!in_array($status, MACHINE_MASTER_STATUSES, true)) {
        throw new InvalidArgumentException('Invalid machine status.');
    }

    $installDate = $install !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $install) ? $install : null;
    $maintDate = $maint !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $maint) ? $maint : null;
    $deptVal = $department !== '' ? $department : null;
    $isActive = $status === MACHINE_STATUS_SCRAP ? 0 : 1;
    $deactivatedAt = $isActive === 0 ? date('Y-m-d H:i:s') : null;

    if ($id !== null && $id > 0) {
        $prev = mach_get_machine($pdo, $id);
        if (!$prev) {
            throw new InvalidArgumentException('Machine not found.');
        }
        if ($isActive === 1 && (int)($prev['is_active'] ?? 1) === 0 && $status !== MACHINE_STATUS_SCRAP) {
            $deactivatedAt = null;
        }

        $st = $pdo->prepare(
            'UPDATE machines SET machine_code = :c, machine_name = :n, department = :dep, section = :sec,
             machine_type = :t, shift_capacity = :cap, status = :s, installation_date = :inst,
             last_maintenance_date = :d, notes = :notes, remarks = :rmk, is_active = :ia, deactivated_at = :da
             WHERE id = :id'
        );
        $st->execute([
            'c' => $code,
            'n' => $name,
            'dep' => $deptVal,
            'sec' => $section !== '' ? $section : null,
            't' => $type !== '' ? $type : null,
            'cap' => $capacity,
            's' => $status,
            'inst' => $installDate,
            'd' => $maintDate,
            'notes' => $notes !== '' ? $notes : null,
            'rmk' => $remarks !== '' ? $remarks : null,
            'ia' => $isActive,
            'da' => $deactivatedAt,
            'id' => $id,
        ]);

        mach_log_status_change($pdo, $id, (string)($prev['status'] ?? ''), $status, $remarks !== '' ? $remarks : null);
        mach_log_field_change($pdo, $id, 'department', (string)($prev['department'] ?? ''), $deptVal ?? '', null);
        mach_log_field_change($pdo, $id, 'section', (string)($prev['section'] ?? ''), $section, null);

        if ($status === MACHINE_STATUS_SCRAP) {
            mach_close_active_assignments_for_machine($pdo, $id, 'Machine scrapped / deactivated');
        }

        return $id;
    }

    $st = $pdo->prepare(
        'INSERT INTO machines (machine_code, machine_name, department, section, machine_type, shift_capacity,
         status, installation_date, last_maintenance_date, notes, remarks, is_active, deactivated_at)
         VALUES (:c, :n, :dep, :sec, :t, :cap, :s, :inst, :d, :notes, :rmk, :ia, :da)'
    );
    $st->execute([
        'c' => $code,
        'n' => $name,
        'dep' => $deptVal,
        'sec' => $section !== '' ? $section : null,
        't' => $type !== '' ? $type : null,
        'cap' => $capacity,
        's' => $status,
        'inst' => $installDate,
        'd' => $maintDate,
        'notes' => $notes !== '' ? $notes : null,
        'rmk' => $remarks !== '' ? $remarks : null,
        'ia' => $isActive,
        'da' => $deactivatedAt,
    ]);
    $newId = (int)$pdo->lastInsertId();
    mach_log_status_change($pdo, $newId, null, $status, 'Machine created');

    return $newId;
}

/** Soft deactivate — never DELETE. */
function mach_deactivate_machine(PDO $pdo, int $id, string $reason = ''): void
{
    $m = mach_get_machine($pdo, $id);
    if (!$m) {
        throw new InvalidArgumentException('Machine not found.');
    }
    $pdo->prepare(
        "UPDATE machines SET status = :s, is_active = 0, deactivated_at = NOW(), remarks = CONCAT(COALESCE(remarks,''), :rm)
         WHERE id = :id"
    )->execute([
        's' => MACHINE_STATUS_SCRAP,
        'rm' => ($reason !== '' ? "\n[Deactivated] " . $reason : ''),
        'id' => $id,
    ]);
    mach_log_status_change($pdo, $id, (string)$m['status'], MACHINE_STATUS_SCRAP, $reason !== '' ? $reason : 'Deactivated');
    mach_close_active_assignments_for_machine($pdo, $id, $reason !== '' ? $reason : 'Machine deactivated');
}

function mach_hr_codes_for_department(string $department): array
{
    return match ($department) {
        'Mixing' => ['DEPT_MIXING'],
        'Building' => ['DEPT_TIRE_BUILD', 'DEPT_COMP_PREP'],
        'Curing' => ['DEPT_CURING'],
        default => [],
    };
}

function mach_employee_allowed_for_department(PDO $pdo, int $employeeId, string $department, string $dateYmd): bool
{
    $codes = mach_hr_codes_for_department($department);
    if ($codes === [] || !dh_table_exists($pdo, 'departments') || !dh_column_exists($pdo, 'employees', 'department_id')) {
        return false;
    }
    $ph = implode(',', array_fill(0, count($codes), '?'));
    $sql = "SELECT 1 FROM employees e
        INNER JOIN departments d ON d.id = e.department_id AND LOWER(COALESCE(d.status, 'active')) = 'active'
        WHERE e.id = ? AND LOWER(COALESCE(e.status, 'active')) = 'active'
          AND d.department_code IN ({$ph}) LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge([$employeeId], $codes));

    return (bool)$st->fetchColumn();
}

/** @return list<array<string, mixed>> */
function mach_machines_for_production(PDO $pdo, string $productionDepartment): array
{
    $dept = match ($productionDepartment) {
        'Mixing' => 'Mixing',
        'Building' => 'Building',
        'Curing' => 'Curing',
        default => '',
    };
    if ($dept === '') {
        return [];
    }

    return array_values(array_filter(
        mach_list_machines($pdo, ['department' => $dept]),
        static fn(array $m): bool => mach_machine_selectable($m)
    ));
}

function mach_active_assignment(PDO $pdo, int $machineId, ?string $onDate = null): ?array
{
    mach_ensure_schema($pdo);
    $date = $onDate ?? date('Y-m-d');

    $st = $pdo->prepare(
        "SELECT a.*, e.full_name AS operator_name, e.employee_code
         FROM machine_operator_assignments a
         INNER JOIN employees e ON e.id = a.employee_id
         WHERE a.machine_id = :mid AND a.is_active = 1
           AND a.assigned_from <= :d
           AND (a.assigned_till IS NULL OR a.assigned_till >= :d2)
         ORDER BY a.assigned_from DESC, a.id DESC
         LIMIT 1"
    );
    $st->execute(['mid' => $machineId, 'd' => $date, 'd2' => $date]);

    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function mach_close_active_assignments_for_machine(PDO $pdo, int $machineId, string $reason): void
{
    mach_ensure_schema($pdo);
    $today = date('Y-m-d');
    $pdo->prepare(
        "UPDATE machine_operator_assignments
         SET is_active = 0, assigned_till = COALESCE(assigned_till, :t), closed_at = NOW(), closed_reason = :r
         WHERE machine_id = :mid AND is_active = 1"
    )->execute(['t' => $today, 'r' => $reason, 'mid' => $machineId]);
}

function mach_close_assignments_for_employee(PDO $pdo, int $employeeId, string $reason): void
{
    mach_ensure_schema($pdo);
    $today = date('Y-m-d');
    $pdo->prepare(
        "UPDATE machine_operator_assignments
         SET is_active = 0, assigned_till = COALESCE(assigned_till, :t), closed_at = NOW(), closed_reason = :r
         WHERE employee_id = :eid AND is_active = 1"
    )->execute(['t' => $today, 'r' => $reason, 'eid' => $employeeId]);
}

/**
 * @return list<array<string, mixed>>
 */
function mach_list_assignments(PDO $pdo, array $filters = []): array
{
    mach_ensure_schema($pdo);

    $sql = "SELECT a.*, m.machine_code, m.machine_name, m.department AS machine_department, m.status AS machine_status,
            e.full_name AS operator_name, e.employee_code
        FROM machine_operator_assignments a
        INNER JOIN machines m ON m.id = a.machine_id
        INNER JOIN employees e ON e.id = a.employee_id
        WHERE 1=1";
    $params = [];

    if (!empty($filters['active_only'])) {
        $sql .= ' AND a.is_active = 1';
    }
    if (!empty($filters['machine_id'])) {
        $sql .= ' AND a.machine_id = :mid';
        $params['mid'] = (int)$filters['machine_id'];
    }
    if (!empty($filters['department']) && in_array($filters['department'], MACHINE_PRODUCTION_DEPARTMENTS, true)) {
        $sql .= ' AND a.department = :dep';
        $params['dep'] = $filters['department'];
    }

    $sql .= ' ORDER BY a.is_active DESC, a.assigned_from DESC, a.id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mach_save_assignment(PDO $pdo, array $data, ?int $id = null): int
{
    mach_ensure_schema($pdo);

    $machineId = (int)($data['machine_id'] ?? 0);
    $employeeId = (int)($data['employee_id'] ?? 0);
    $department = trim((string)($data['department'] ?? ''));
    $shift = trim((string)($data['shift'] ?? ''));
    $from = trim((string)($data['assigned_from'] ?? ''));
    $till = trim((string)($data['assigned_till'] ?? ''));
    $remarks = trim((string)($data['remarks'] ?? ''));

    if ($machineId < 1 || $employeeId < 1) {
        throw new InvalidArgumentException('Machine and operator are required.');
    }
    if (!in_array($department, MACHINE_PRODUCTION_DEPARTMENTS, true)) {
        throw new InvalidArgumentException('Invalid department for assignment.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        throw new InvalidArgumentException('Valid assigned-from date is required.');
    }
    $tillDate = $till !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $till) ? $till : null;
    if ($tillDate !== null && $tillDate < $from) {
        throw new InvalidArgumentException('Assigned-till cannot be before assigned-from.');
    }
    if ($shift !== '' && !in_array($shift, MACH_ASSIGNMENT_SHIFTS, true)) {
        throw new InvalidArgumentException('Invalid shift.');
    }

    $machine = mach_get_machine($pdo, $machineId);
    if (!$machine) {
        throw new InvalidArgumentException('Machine not found.');
    }
    if ((string)($machine['department'] ?? '') !== '' && (string)$machine['department'] !== $department) {
        throw new InvalidArgumentException('Assignment department must match machine department.');
    }

    if (!mach_employee_allowed_for_department($pdo, $employeeId, $department, $from)) {
        throw new InvalidArgumentException('Selected operator is not in the allowed HR department for this assignment.');
    }

    $pdo->beginTransaction();
    try {
        if ($id !== null && $id > 0) {
            $cur = $pdo->prepare('SELECT * FROM machine_operator_assignments WHERE id = :id LIMIT 1');
            $cur->execute(['id' => $id]);
            $row = $cur->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Assignment not found.');
            }
            $isActive = (int)($data['is_active'] ?? $row['is_active'] ?? 1);
            if ($isActive === 0 && (int)$row['is_active'] === 1) {
                $tillDate = $tillDate ?? date('Y-m-d');
            }
            $pdo->prepare(
                'UPDATE machine_operator_assignments SET machine_id = :mid, employee_id = :eid, department = :dep,
                 shift = :sh, assigned_from = :af, assigned_till = :at, remarks = :rm, is_active = :ia,
                 closed_at = CASE WHEN :ia2 = 0 THEN COALESCE(closed_at, NOW()) ELSE NULL END,
                 closed_reason = CASE WHEN :ia3 = 0 THEN COALESCE(closed_reason, :cr) ELSE NULL END
                 WHERE id = :id'
            )->execute([
                'mid' => $machineId,
                'eid' => $employeeId,
                'dep' => $department,
                'sh' => $shift !== '' ? $shift : null,
                'af' => $from,
                'at' => $tillDate,
                'rm' => $remarks !== '' ? $remarks : null,
                'ia' => $isActive,
                'ia2' => $isActive,
                'ia3' => $isActive,
                'cr' => 'Assignment ended',
                'id' => $id,
            ]);
            $pdo->commit();

            return $id;
        }

        mach_close_active_assignments_for_machine($pdo, $machineId, 'Reassigned to new operator');

        $dup = $pdo->prepare(
            'SELECT id FROM machine_operator_assignments WHERE machine_id = :mid AND is_active = 1 LIMIT 1'
        );
        $dup->execute(['mid' => $machineId]);
        if ($dup->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Machine still has an active assignment. Close it before creating another.');
        }

        $st = $pdo->prepare(
            'INSERT INTO machine_operator_assignments (machine_id, employee_id, department, shift, assigned_from, assigned_till, remarks, is_active, created_by)
             VALUES (:mid, :eid, :dep, :sh, :af, :at, :rm, 1, :uid)'
        );
        $st->execute([
            'mid' => $machineId,
            'eid' => $employeeId,
            'dep' => $department,
            'sh' => $shift !== '' ? $shift : null,
            'af' => $from,
            'at' => $tillDate,
            'rm' => $remarks !== '' ? $remarks : null,
            'uid' => mach_current_user_id(),
        ]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->commit();

        return $newId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function mach_remove_assignment(PDO $pdo, int $id, string $reason = ''): void
{
    $today = date('Y-m-d');
    $pdo->prepare(
        "UPDATE machine_operator_assignments SET is_active = 0, assigned_till = COALESCE(assigned_till, :t),
         closed_at = NOW(), closed_reason = :r WHERE id = :id"
    )->execute([
        't' => $today,
        'r' => $reason !== '' ? $reason : 'Assignment removed',
        'id' => $id,
    ]);
}

/** Dashboard KPIs and tables. */
function mach_dashboard(PDO $pdo): array
{
    mach_ensure_schema($pdo);
    $all = mach_list_machines($pdo, ['include_inactive' => true]);

    $counts = [
        'total' => count($all),
        'active' => 0,
        'idle' => 0,
        'repair' => 0,
        'scrap' => 0,
    ];
    foreach ($all as $m) {
        $s = mach_normalize_status((string)($m['status'] ?? ''));
        if ($s === MACHINE_STATUS_ACTIVE) {
            $counts['active']++;
        } elseif ($s === MACHINE_STATUS_IDLE) {
            $counts['idle']++;
        } elseif ($s === MACHINE_STATUS_REPAIR) {
            $counts['repair']++;
        } elseif ($s === MACHINE_STATUS_SCRAP || (int)($m['is_active'] ?? 1) === 0) {
            $counts['scrap']++;
        }
    }

    $operatorRows = [];
    foreach (mach_list_machines($pdo) as $m) {
        $asg = mach_active_assignment($pdo, (int)$m['id']);
        $operatorRows[] = [
            'machine_code' => $m['machine_code'],
            'machine_name' => $m['machine_name'],
            'department' => $m['department'] ?? '—',
            'status' => $m['status'],
            'operator' => $asg ? $asg['operator_name'] : '—',
            'shift' => $asg['shift'] ?? '—',
            'from' => $asg['assigned_from'] ?? '—',
        ];
    }

    $deptCounts = [];
    foreach (MACHINE_PRODUCTION_DEPARTMENTS as $d) {
        $deptCounts[$d] = 0;
    }
    foreach ($all as $m) {
        $d = (string)($m['department'] ?? '');
        if (isset($deptCounts[$d])) {
            $deptCounts[$d]++;
        }
    }

    $recent = mach_list_assignments($pdo);
    usort($recent, static fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
    $recent = array_slice($recent, 0, 10);

    return [
        'counts' => $counts,
        'operator_rows' => $operatorRows,
        'dept_counts' => $deptCounts,
        'recent_assignments' => $recent,
    ];
}

/**
 * Optional from/to for inventory (not required).
 *
 * @return array{from: ?string, to: ?string}
 */
function mach_parse_optional_dates(?string $from, ?string $to): array
{
    $f = is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null;
    $t = is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : null;
    if ($f !== null && $t !== null && $f > $t) {
        [$f, $t] = [$t, $f];
    }

    return ['from' => $f, 'to' => $t];
}

/** @return array<int, array{entry_count: int, last_in_period: ?string}> */
function mach_production_stats_by_machine(PDO $pdo, ?string $from, ?string $to): array
{
    $stats = [];
    foreach (['mixing_entries', 'building_entries', 'curing_entries'] as $tbl) {
        if (!dh_table_exists($pdo, $tbl)) {
            continue;
        }
        $sql = "SELECT machine_id, COUNT(*) AS cnt, MAX(production_date) AS last_d
            FROM {$tbl} WHERE machine_id IS NOT NULL";
        $params = [];
        if ($from !== null) {
            $sql .= ' AND production_date >= :f';
            $params['f'] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND production_date <= :t';
            $params['t'] = $to;
        }
        $sql .= ' GROUP BY machine_id';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $mid = (int)$r['machine_id'];
            $cnt = (int)$r['cnt'];
            $last = (string)($r['last_d'] ?? '');
            if (!isset($stats[$mid])) {
                $stats[$mid] = ['entry_count' => 0, 'last_in_period' => null];
            }
            $stats[$mid]['entry_count'] += $cnt;
            if ($last !== '' && ($stats[$mid]['last_in_period'] === null || $last > $stats[$mid]['last_in_period'])) {
                $stats[$mid]['last_in_period'] = $last;
            }
        }
    }

    return $stats;
}

/**
 * Full machine inventory including unused machines.
 *
 * @param array{status?: string, from?: ?string, to?: ?string, activity_only?: bool} $filters
 * @return list<array<string, mixed>>
 */
function mach_inventory(PDO $pdo, array $filters = []): array
{
    mach_ensure_schema($pdo);
    $dates = mach_parse_optional_dates($filters['from'] ?? null, $filters['to'] ?? null);
    $from = $dates['from'];
    $to = $dates['to'];
    $hasPeriod = $from !== null || $to !== null;
    $activityOnly = !empty($filters['activity_only']);

    $machines = mach_list_machines($pdo, ['include_inactive' => true]);
    $lastProd = [];
    foreach (['mixing_entries', 'building_entries', 'curing_entries'] as $tbl) {
        if (!dh_table_exists($pdo, $tbl)) {
            continue;
        }
        $sql = "SELECT machine_id, MAX(production_date) AS last_date FROM {$tbl} WHERE machine_id IS NOT NULL GROUP BY machine_id";
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $mid = (int)$r['machine_id'];
            $d = (string)$r['last_date'];
            if (!isset($lastProd[$mid]) || $d > $lastProd[$mid]) {
                $lastProd[$mid] = $d;
            }
        }
    }

    $periodStats = $hasPeriod ? mach_production_stats_by_machine($pdo, $from, $to) : [];

    $out = [];
    foreach ($machines as $m) {
        $status = mach_normalize_status((string)($m['status'] ?? ''));
        if (!empty($filters['status']) && $status !== mach_normalize_status((string)$filters['status'])) {
            continue;
        }

        $mid = (int)$m['id'];
        $period = $periodStats[$mid] ?? ['entry_count' => 0, 'last_in_period' => null];
        if ($activityOnly && $hasPeriod && $period['entry_count'] < 1) {
            continue;
        }

        $asg = mach_active_assignment($pdo, $mid);
        $dept = trim((string)($m['department'] ?? ''));
        $out[] = [
            'id' => $mid,
            'machine_code' => $m['machine_code'],
            'machine_name' => $m['machine_name'],
            'department' => $dept !== '' ? $dept : '—',
            'section' => trim((string)($m['section'] ?? '')) !== '' ? $m['section'] : '—',
            'operator' => $asg ? $asg['operator_name'] . ' (' . ($asg['employee_code'] ?? '') . ')' : '—',
            'status' => $status,
            'is_active' => (int)($m['is_active'] ?? 1),
            'added_date' => substr((string)($m['created_at'] ?? ''), 0, 10),
            'last_production' => $lastProd[$mid] ?? '—',
            'period_entries' => $hasPeriod ? (int)$period['entry_count'] : null,
            'last_in_period' => $hasPeriod ? ($period['last_in_period'] ?? '—') : null,
        ];
    }

    return $out;
}

/** @param list<array<string, mixed>> $rows */
function mach_export_inventory_excel(array $rows, ?string $from, ?string $to): void
{
    $label = ($from || $to) ? (($from ?? '…') . ' to ' . ($to ?? '…')) : 'All dates';
    $filename = 'machine-inventory-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        return;
    }
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Machine Inventory Report']);
    fputcsv($out, ['Period', $label]);
    fputcsv($out, ['Generated', date('Y-m-d H:i')]);
    fputcsv($out, []);

    $hasPeriod = $from !== null || $to !== null;
    $header = ['Code', 'Name', 'Department', 'Section', 'Assigned operator', 'Status', 'Added', 'Last production'];
    if ($hasPeriod) {
        $header[] = 'Entries in period';
        $header[] = 'Last production in period';
    }
    fputcsv($out, $header);

    foreach ($rows as $r) {
        $line = [
            $r['machine_code'],
            $r['machine_name'],
            $r['department'],
            $r['section'],
            $r['operator'],
            $r['status'],
            $r['added_date'],
            $r['last_production'],
        ];
        if ($hasPeriod) {
            $line[] = (string)($r['period_entries'] ?? 0);
            $line[] = (string)($r['last_in_period'] ?? '—');
        }
        fputcsv($out, $line);
    }
    fclose($out);
}

/**
 * Combined history: assignments + status + audit.
 *
 * @param array{machine_id?: int, from?: ?string, to?: ?string, type?: string} $filters
 * @return list<array<string, mixed>>
 */
function mach_combined_history(PDO $pdo, array $filters = [], int $limit = 300): array
{
    mach_ensure_schema($pdo);
    $machineId = (int)($filters['machine_id'] ?? 0);
    $dates = mach_parse_optional_dates($filters['from'] ?? null, $filters['to'] ?? null);
    $from = $dates['from'];
    $to = $dates['to'];
    $typeFilter = trim((string)($filters['type'] ?? ''));

    $items = [];

    if ($typeFilter === '' || $typeFilter === 'Assignment') {
        $params = [];
        $asgSql = 'SELECT a.id, a.machine_id, m.machine_code, m.machine_name, a.assigned_from, a.assigned_till, a.is_active,
                a.closed_reason, e.full_name AS operator_name, a.department, a.shift, a.updated_at AS event_at
            FROM machine_operator_assignments a
            INNER JOIN machines m ON m.id = a.machine_id
            INNER JOIN employees e ON e.id = a.employee_id WHERE 1=1';
        if ($machineId > 0) {
            $asgSql .= ' AND a.machine_id = :mid';
            $params['mid'] = $machineId;
        }
        if ($from !== null) {
            $asgSql .= ' AND DATE(a.updated_at) >= :df';
            $params['df'] = $from;
        }
        if ($to !== null) {
            $asgSql .= ' AND DATE(a.updated_at) <= :dt';
            $params['dt'] = $to;
        }
        $st = $pdo->prepare($asgSql);
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $items[] = [
                'type' => 'Assignment',
                'machine_code' => $r['machine_code'],
                'machine_name' => $r['machine_name'],
                'detail' => ($r['operator_name'] ?? '') . ' · ' . ($r['department'] ?? ''),
                'from' => $r['assigned_from'],
                'till' => $r['assigned_till'] ?? ((int)$r['is_active'] ? 'Current' : '—'),
                'event_at' => $r['event_at'],
                'note' => $r['closed_reason'] ?? '',
                'shift' => $r['shift'] ?? '',
            ];
        }
    }

    if ($typeFilter === '' || $typeFilter === 'Status') {
        $params = [];
        $stSql = 'SELECT h.*, m.machine_code, m.machine_name FROM machine_status_history h
            INNER JOIN machines m ON m.id = h.machine_id WHERE 1=1';
        if ($machineId > 0) {
            $stSql .= ' AND h.machine_id = :mid';
            $params['mid'] = $machineId;
        }
        if ($from !== null) {
            $stSql .= ' AND DATE(h.changed_at) >= :df';
            $params['df'] = $from;
        }
        if ($to !== null) {
            $stSql .= ' AND DATE(h.changed_at) <= :dt';
            $params['dt'] = $to;
        }
        $st = $pdo->prepare($stSql);
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $items[] = [
                'type' => 'Status',
                'machine_code' => $r['machine_code'],
                'machine_name' => $r['machine_name'],
                'detail' => ($r['old_status'] ?? '—') . ' → ' . $r['new_status'],
                'from' => substr((string)$r['changed_at'], 0, 10),
                'till' => '—',
                'event_at' => $r['changed_at'],
                'note' => $r['remarks'] ?? '',
                'shift' => '',
            ];
        }
    }

    if ($typeFilter === '' || $typeFilter === 'Change') {
        $params = [];
        $auSql = 'SELECT l.*, m.machine_code, m.machine_name FROM machine_audit_log l
            INNER JOIN machines m ON m.id = l.machine_id WHERE 1=1';
        if ($machineId > 0) {
            $auSql .= ' AND l.machine_id = :mid';
            $params['mid'] = $machineId;
        }
        if ($from !== null) {
            $auSql .= ' AND DATE(l.changed_at) >= :df';
            $params['df'] = $from;
        }
        if ($to !== null) {
            $auSql .= ' AND DATE(l.changed_at) <= :dt';
            $params['dt'] = $to;
        }
        $st = $pdo->prepare($auSql);
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $items[] = [
                'type' => 'Change',
                'machine_code' => $r['machine_code'],
                'machine_name' => $r['machine_name'],
                'detail' => $r['field_name'] . ': ' . ($r['old_value'] ?? '—') . ' → ' . ($r['new_value'] ?? '—'),
                'from' => substr((string)$r['changed_at'], 0, 10),
                'till' => '—',
                'event_at' => $r['changed_at'],
                'note' => $r['remarks'] ?? '',
                'shift' => '',
            ];
        }
    }

    usort($items, static fn($a, $b) => strcmp((string)($b['event_at'] ?? ''), (string)($a['event_at'] ?? '')));

    return array_slice($items, 0, $limit);
}

function mach_history_type_meta(string $type): array
{
    return match ($type) {
        'Assignment' => ['icon' => 'bi-person-gear', 'class' => 'mach-timeline__item--assign'],
        'Status' => ['icon' => 'bi-arrow-repeat', 'class' => 'mach-timeline__item--status'],
        default => ['icon' => 'bi-pencil-square', 'class' => 'mach-timeline__item--change'],
    };
}

function mach_validate_for_production(PDO $pdo, int $machineId, string $productionDepartment): array
{
    $machine = mach_get_machine($pdo, $machineId);
    if (!$machine) {
        throw new InvalidArgumentException('Machine not found.');
    }
    if (!mach_machine_selectable($machine)) {
        throw new RuntimeException('Machine is not active. Production entry blocked.');
    }
    $expected = match ($productionDepartment) {
        'Mixing' => 'Mixing',
        'Building' => 'Building',
        'Curing' => 'Curing',
        default => '',
    };
    if ($expected !== '' && (string)($machine['department'] ?? '') !== '' && (string)$machine['department'] !== $expected) {
        throw new RuntimeException('Machine does not belong to this production department.');
    }

    return $machine;
}
