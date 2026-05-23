<?php
declare(strict_types=1);

/** @deprecated Use MACHINE_STATUS_ACTIVE — kept for legacy references */
const MACHINE_STATUS_RUNNING = 'Active';
const MACHINE_STATUS_MAINTENANCE = 'Under Repair';
const MACHINE_STATUS_BREAKDOWN = 'Under Repair';

const MACHINE_STATUSES = [
    'Active',
    'Idle',
    'Under Repair',
    'Scrap / Deactivated',
];

const PRODUCTION_SHIFTS = ['Morning', 'Evening', 'Night'];

const TYRE_TYPES = [
    'PCR Car',
    'PCR SUV',
    'TBR Truck',
    'Farm / OTR',
    'Two Wheeler',
    'Retread',
    'Other',
];

/** Statuses that allow new production entries on a machine. */
function production_machine_can_run(?string $status): bool
{
    return mach_status_can_produce($status);
}

/** Map legacy machine status values after DB migration. */
function production_normalize_machine_status(string $status): string
{
    return mach_normalize_status($status);
}

/** @return list<array<string, mixed>> */
function production_list_machines(PDO $pdo, bool $includeInactive = false): array
{
    require_once __DIR__ . '/machine_service.php';

    return mach_list_machines($pdo, ['include_inactive' => $includeInactive]);
}

function production_save_machine(PDO $pdo, array $data, ?int $id = null): int
{
    require_once __DIR__ . '/machine_service.php';

    return mach_save_machine($pdo, $data, $id);
}

/**
 * Operators available for production (excludes employees marked Absent today).
 *
 * @return list<array<string, mixed>>
 */
function production_available_operators(PDO $pdo, string $dateYmd): array
{
    $sql = "SELECT e.id, e.full_name, e.employee_code, e.department, e.designation,
            a.status AS att_status
        FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = :d
        WHERE COALESCE(e.status, 'active') = 'active'
          AND (e.employee_type IS NULL OR e.employee_type IN ('Staff', 'Worker'))
          AND (a.status IS NULL OR a.status NOT IN ('Absent'))
        ORDER BY e.full_name ASC";
    $st = $pdo->prepare($sql);
    $st->execute(['d' => $dateYmd]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function production_running_machines(PDO $pdo): array
{
    $all = production_list_machines($pdo);

    return array_values(array_filter($all, static fn(array $m): bool => production_machine_can_run((string)($m['status'] ?? ''))));
}

function production_calc_efficiency(int $planned, int $produced): float
{
    if ($planned <= 0) {
        return $produced > 0 ? 100.0 : 0.0;
    }

    return round(min(999.99, ($produced / $planned) * 100), 2);
}

/**
 * Save production shift entry. Raw material deduction is optional hook (inventory_deducted flag).
 *
 * @param array<string, mixed> $data
 */
function production_save_entry(PDO $pdo, array $data): int
{
    $date = (string)($data['production_date'] ?? '');
    $machineId = (int)($data['machine_id'] ?? 0);
    $operatorId = (int)($data['operator_id'] ?? 0);
    $shift = (string)($data['shift'] ?? '');
    $tyreType = trim((string)($data['tyre_type'] ?? ''));
    $planned = max(0, (int)($data['planned_quantity'] ?? 0));
    $produced = max(0, (int)($data['produced_quantity'] ?? 0));
    $rejected = max(0, (int)($data['rejected_quantity'] ?? 0));
    $downtime = max(0, (int)($data['downtime_minutes'] ?? 0));
    $remarks = trim((string)($data['remarks'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid production date is required.');
    }
    if ($machineId < 1) {
        throw new InvalidArgumentException('Machine is required.');
    }
    if ($operatorId < 1) {
        throw new InvalidArgumentException('Operator is required.');
    }
    if (!in_array($shift, PRODUCTION_SHIFTS, true)) {
        throw new InvalidArgumentException('Invalid shift.');
    }
    if ($tyreType === '') {
        throw new InvalidArgumentException('Tyre type is required.');
    }
    if ($produced < 1 && $rejected < 1) {
        throw new InvalidArgumentException('Enter produced or rejected quantity.');
    }
    if ($rejected > $produced) {
        throw new InvalidArgumentException('Rejected quantity cannot exceed produced quantity.');
    }

    $mSt = $pdo->prepare('SELECT status FROM machines WHERE id = :id LIMIT 1');
    $mSt->execute(['id' => $machineId]);
    $machine = $mSt->fetch(PDO::FETCH_ASSOC);
    if (!$machine) {
        throw new InvalidArgumentException('Machine not found.');
    }
    $mStatus = production_normalize_machine_status((string)($machine['status'] ?? ''));
    if (!production_machine_can_run($mStatus)) {
        throw new RuntimeException('Machine is not in Running status. Production entry blocked.');
    }

    $ops = production_available_operators($pdo, $date);
    $allowedOp = false;
    foreach ($ops as $op) {
        if ((int)$op['id'] === $operatorId) {
            $allowedOp = true;
            break;
        }
    }
    if (!$allowedOp) {
        throw new RuntimeException('Selected operator is absent or unavailable for this date.');
    }

    $efficiency = production_calc_efficiency($planned, $produced);
    $entryStatus = $produced > 0 ? 'Submitted' : 'Draft';

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'INSERT INTO production (
                production_date, machine_id, shift, operator_id, tyre_type,
                planned_quantity, output_quantity, rejected_quantity, downtime_minutes,
                remarks, efficiency_pct, entry_status, inventory_deducted,
                raw_material_id, material_used_qty
            ) VALUES (
                :d, :mid, :sh, :op, :tt,
                :plan, :out, :rej, :down,
                :rm, :eff, :st, 0,
                NULL, NULL
            )'
        );
        $st->execute([
            'd' => $date,
            'mid' => $machineId,
            'sh' => $shift,
            'op' => $operatorId,
            'tt' => $tyreType,
            'plan' => $planned,
            'out' => $produced,
            'rej' => $rejected,
            'down' => $downtime,
            'rm' => $remarks !== '' ? $remarks : null,
            'eff' => $efficiency,
            'st' => $entryStatus,
        ]);
        $id = (int)$pdo->lastInsertId();

        // Future: production_apply_inventory_deduction($pdo, $id);
        $pdo->commit();

        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** @return list<array<string, mixed>> */
function production_list_entries(PDO $pdo, int $limit = 100, ?string $from = null, ?string $to = null): array
{
    $sql = 'SELECT p.*, m.machine_code, m.machine_name, e.full_name AS operator_name, e.employee_code
        FROM production p
        INNER JOIN machines m ON m.id = p.machine_id
        LEFT JOIN employees e ON e.id = p.operator_id
        WHERE 1=1';
    $params = [];
    if ($from !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $sql .= ' AND p.production_date >= :f';
        $params['f'] = $from;
    }
    if ($to !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $sql .= ' AND p.production_date <= :t';
        $params['t'] = $to;
    }
    $sql .= ' ORDER BY p.production_date DESC, p.id DESC LIMIT ' . max(1, min(500, $limit));
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Dashboard aggregates. */
function production_dashboard_stats(PDO $pdo): array
{
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    $todayProduced = (int)$pdo->query(
        "SELECT COALESCE(SUM(output_quantity),0) FROM production WHERE production_date = CURDATE()"
    )->fetchColumn();
    $todayRejected = (int)$pdo->query(
        "SELECT COALESCE(SUM(rejected_quantity),0) FROM production WHERE production_date = CURDATE()"
    )->fetchColumn();
    $stMonth = $pdo->prepare('SELECT COALESCE(SUM(output_quantity),0) FROM production WHERE production_date >= :m');
    $stMonth->execute(['m' => $monthStart]);
    $monthProduced = (int)$stMonth->fetchColumn();

    $running = 0;
    $down = 0;
    foreach (production_list_machines($pdo) as $m) {
        $s = production_normalize_machine_status((string)($m['status'] ?? ''));
        if ($s === 'Active') {
            $running++;
        } elseif ($s === 'Under Repair') {
            $down++;
        }
    }

    $lowRaw = 0;
    try {
        $lowRaw = (int)$pdo->query(
            'SELECT COUNT(*) FROM raw_materials WHERE stock_qty <= reorder_level'
        )->fetchColumn();
    } catch (Throwable) {
    }

    return [
        'today_produced' => $todayProduced,
        'today_rejected' => $todayRejected,
        'month_produced' => $monthProduced,
        'machines_running' => $running,
        'machines_down' => $down,
        'low_raw' => $lowRaw,
    ];
}

function production_entry_status_badge(string $status): array
{
    return match ($status) {
        'Completed' => ['class' => 'text-bg-success', 'label' => 'Completed'],
        'QC Pending' => ['class' => 'text-bg-warning', 'label' => 'QC Pending'],
        'Draft' => ['class' => 'text-bg-secondary', 'label' => 'Draft'],
        default => ['class' => 'text-bg-primary', 'label' => $status !== '' ? $status : 'Submitted'],
    };
}

function production_machine_status_badge(string $status): array
{
    require_once __DIR__ . '/machine_service.php';
    $b = mach_status_badge($status);

    return match (mach_normalize_status($status)) {
        'Active' => ['class' => 'prod-badge prod-badge--run', 'label' => $b['label']],
        'Idle' => ['class' => 'prod-badge prod-badge--idle', 'label' => $b['label']],
        'Under Repair' => ['class' => 'prod-badge prod-badge--maint', 'label' => $b['label']],
        'Scrap / Deactivated' => ['class' => 'prod-badge prod-badge--down', 'label' => $b['label']],
        default => ['class' => 'prod-badge', 'label' => $b['label']],
    };
}
