<?php
declare(strict_types=1);

require_once __DIR__ . '/production_service.php';
require_once __DIR__ . '/inventory_service.php';

const PROD_ENTRY_MIXING = 'Mixing';
const PROD_ENTRY_BUILDING = 'Building';
const PROD_ENTRY_CURING = 'Curing';
const PROD_ENTRY_QC = 'QC';

/** @return list<string> */
function prod_entry_departments(): array
{
    return [PROD_ENTRY_MIXING, PROD_ENTRY_BUILDING, PROD_ENTRY_CURING, PROD_ENTRY_QC];
}

function prod_entry_table(string $department): string
{
    return match ($department) {
        PROD_ENTRY_BUILDING => 'building_entries',
        PROD_ENTRY_CURING => 'curing_entries',
        PROD_ENTRY_QC => 'qc_entries',
        default => 'mixing_entries',
    };
}

function prod_entry_date_field(string $department): string
{
    return $department === PROD_ENTRY_QC ? 'entry_date' : 'production_date';
}

function prod_validate_entry_common(PDO $pdo, array $data, bool $requireMachine): void
{
    $date = (string)($data['production_date'] ?? $data['entry_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid date is required.');
    }
    $shift = (string)($data['shift'] ?? 'Morning');
    if ($shift !== '' && !in_array($shift, PRODUCTION_SHIFTS, true)) {
        throw new InvalidArgumentException('Invalid shift.');
    }
    $machineId = (int)($data['machine_id'] ?? 0);
    $operatorId = (int)($data['operator_id'] ?? 0);
    if ($requireMachine && $machineId < 1) {
        throw new InvalidArgumentException('Machine is required.');
    }
    if ($machineId > 0) {
        $mSt = $pdo->prepare('SELECT status FROM machines WHERE id = :id');
        $mSt->execute(['id' => $machineId]);
        $m = $mSt->fetch(PDO::FETCH_ASSOC);
        if (!$m || !production_machine_can_run(production_normalize_machine_status((string)$m['status']))) {
            throw new RuntimeException('Machine must be in Running status.');
        }
    }
    if ($operatorId > 0) {
        foreach (prod_entry_operators($pdo, $date) as $op) {
            if ((int)$op['id'] === $operatorId) {
                if ((int)($op['is_absent'] ?? 0) === 1) {
                    throw new RuntimeException('Operator is absent today.');
                }

                return;
            }
        }
        throw new RuntimeException('Invalid production operator.');
    }
}

/** @return list<array<string, mixed>> */
function prod_entry_operators(PDO $pdo, string $dateYmd): array
{
    $sql = "SELECT e.id, e.full_name, e.employee_code, e.department,
            CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END AS is_absent
        FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = :d
        WHERE COALESCE(e.status, 'active') = 'active'
        ORDER BY e.full_name ASC";
    $st = $pdo->prepare($sql);
    $st->execute(['d' => $dateYmd]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $prod = [];
    foreach ($rows as $r) {
        $dept = strtolower((string)($r['department'] ?? ''));
        if (str_contains($dept, 'production') || str_contains($dept, 'plant') || str_contains($dept, 'manufacturing')) {
            $prod[] = $r;
        }
    }

    return $prod !== [] ? $prod : $rows;
}

function prod_save_mixing_entry(PDO $pdo, array $data): int
{
    prod_validate_entry_common($pdo, $data, true);
    $tyre = trim((string)($data['tyre_type'] ?? ''));
    if ($tyre === '') {
        throw new InvalidArgumentException('Tyre type is required.');
    }
    $produced = max(0, (float)($data['produced_qty'] ?? 0));
    if ($produced <= 0) {
        throw new InvalidArgumentException('Compound produced (kg) is required.');
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'INSERT INTO mixing_entries (production_date, shift, machine_id, operator_id, tyre_type, produced_qty, rejected_qty, remarks)
             VALUES (:d, :sh, :mid, :oid, :tt, :pq, :rq, :rm)'
        );
        $st->execute([
            'd' => $data['production_date'],
            'sh' => $data['shift'] ?? 'Morning',
            'mid' => (int)$data['machine_id'],
            'oid' => (int)($data['operator_id'] ?? 0) ?: null,
            'tt' => $tyre,
            'pq' => $produced,
            'rq' => max(0, (float)($data['rejected_qty'] ?? 0)),
            'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
        ]);
        $entryId = (int)$pdo->lastInsertId();
        inv_apply_production_usage($pdo, 'mixing', $entryId, $produced, (string)$data['production_date']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $entryId;
}

function prod_save_building_entry(PDO $pdo, array $data): int
{
    prod_validate_entry_common($pdo, $data, true);
    $tyre = trim((string)($data['tyre_type'] ?? ''));
    if ($tyre === '') {
        throw new InvalidArgumentException('Tyre type is required.');
    }
    $produced = max(0, (int)($data['produced_qty'] ?? 0));
    if ($produced < 1) {
        throw new InvalidArgumentException('Green tyres built is required.');
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'INSERT INTO building_entries (production_date, shift, machine_id, operator_id, tyre_type, produced_qty, rejected_qty, remarks)
             VALUES (:d, :sh, :mid, :oid, :tt, :pq, :rq, :rm)'
        );
        $st->execute([
            'd' => $data['production_date'],
            'sh' => $data['shift'] ?? 'Morning',
            'mid' => (int)$data['machine_id'],
            'oid' => (int)($data['operator_id'] ?? 0) ?: null,
            'tt' => $tyre,
            'pq' => $produced,
            'rq' => max(0, (int)($data['rejected_qty'] ?? 0)),
            'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
        ]);
        $entryId = (int)$pdo->lastInsertId();
        inv_apply_production_usage($pdo, 'building', $entryId, (float)$produced, (string)$data['production_date']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $entryId;
}

function prod_save_curing_entry(PDO $pdo, array $data): int
{
    prod_validate_entry_common($pdo, $data, true);
    $produced = max(0, (int)($data['produced_qty'] ?? 0));
    if ($produced < 1) {
        throw new InvalidArgumentException('Cured quantity is required.');
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'INSERT INTO curing_entries (production_date, shift, machine_id, operator_id, tyre_type, produced_qty, rejected_qty, downtime_minutes, remarks)
             VALUES (:d, :sh, :mid, :oid, :tt, :pq, :rq, :dt, :rm)'
        );
        $st->execute([
            'd' => $data['production_date'],
            'sh' => $data['shift'] ?? 'Morning',
            'mid' => (int)$data['machine_id'],
            'oid' => (int)($data['operator_id'] ?? 0) ?: null,
            'tt' => trim((string)($data['tyre_type'] ?? 'Tyre')) ?: 'Tyre',
            'pq' => $produced,
            'rq' => max(0, (int)($data['rejected_qty'] ?? 0)),
            'dt' => max(0, (int)($data['downtime_minutes'] ?? 0)),
            'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
        ]);
        $entryId = (int)$pdo->lastInsertId();
        inv_apply_production_usage($pdo, 'curing', $entryId, (float)$produced, (string)$data['production_date']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $entryId;
}

function prod_save_qc_entry(PDO $pdo, array $data): int
{
    $date = (string)($data['entry_date'] ?? $data['production_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid date is required.');
    }
    $inspector = trim((string)($data['inspector_name'] ?? ''));
    $tyre = trim((string)($data['tyre_type'] ?? ''));
    if ($inspector === '' || $tyre === '') {
        throw new InvalidArgumentException('Inspector and tyre type are required.');
    }
    $checked = max(0, (int)($data['checked_qty'] ?? 0));
    $passed = max(0, (int)($data['passed_qty'] ?? 0));
    $failed = max(0, (int)($data['failed_qty'] ?? 0));
    if ($checked < 1) {
        throw new InvalidArgumentException('Checked quantity is required.');
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'INSERT INTO qc_entries (entry_date, shift, inspector_name, tyre_type, checked_qty, passed_qty, failed_qty, defect_type, remarks)
             VALUES (:d, :sh, :ins, :tt, :cq, :pq, :fq, :def, :rm)'
        );
        $st->execute([
            'd' => $date,
            'sh' => in_array($data['shift'] ?? '', PRODUCTION_SHIFTS, true) ? $data['shift'] : 'Morning',
            'ins' => $inspector,
            'tt' => $tyre,
            'cq' => $checked,
            'pq' => $passed,
            'fq' => $failed,
            'def' => trim((string)($data['defect_type'] ?? '')) ?: null,
            'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
        ]);
        $id = (int)$pdo->lastInsertId();

        if ($passed > 0) {
            prod_entry_add_inventory($pdo, $tyre, 'QC-' . date('Ymd') . '-' . $id, $passed);
        }

        $pdo->commit();

        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** @return list<array<string, mixed>> */
function prod_list_department_entries(PDO $pdo, string $department, int $limit = 50): array
{
    $table = prod_entry_table($department);
    $dateCol = prod_entry_date_field($department);
    $sql = match ($department) {
        PROD_ENTRY_QC => "SELECT e.*, NULL AS machine_code, e.inspector_name AS operator_name
            FROM qc_entries e ORDER BY e.id DESC LIMIT " . max(1, min(200, $limit)),
        default => "SELECT e.*, m.machine_code, op.full_name AS operator_name
            FROM {$table} e
            LEFT JOIN machines m ON m.id = e.machine_id
            LEFT JOIN employees op ON op.id = e.operator_id
            ORDER BY e.id DESC LIMIT " . max(1, min(200, $limit)),
    };

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Dashboard operational stats. */
function prod_entry_dashboard(PDO $pdo): array
{
    $mixing = (float)$pdo->query(
        'SELECT COALESCE(SUM(produced_qty),0) FROM mixing_entries WHERE production_date = CURDATE()'
    )->fetchColumn();
    $building = (int)$pdo->query(
        'SELECT COALESCE(SUM(produced_qty),0) FROM building_entries WHERE production_date = CURDATE()'
    )->fetchColumn();
    $curing = (int)$pdo->query(
        'SELECT COALESCE(SUM(produced_qty),0) FROM curing_entries WHERE production_date = CURDATE()'
    )->fetchColumn();
    $qcPass = (int)$pdo->query(
        'SELECT COALESCE(SUM(passed_qty),0) FROM qc_entries WHERE entry_date = CURDATE()'
    )->fetchColumn();
    $rejected = (int)$pdo->query(
        "SELECT
            (SELECT COALESCE(SUM(rejected_qty),0) FROM mixing_entries WHERE production_date = CURDATE()) +
            (SELECT COALESCE(SUM(rejected_qty),0) FROM building_entries WHERE production_date = CURDATE()) +
            (SELECT COALESCE(SUM(rejected_qty),0) FROM curing_entries WHERE production_date = CURDATE()) +
            (SELECT COALESCE(SUM(failed_qty),0) FROM qc_entries WHERE entry_date = CURDATE())"
    )->fetchColumn();
    $downtime = (int)$pdo->query(
        'SELECT COALESCE(SUM(downtime_minutes),0) FROM curing_entries WHERE production_date = CURDATE()'
    )->fetchColumn();

    $running = 0;
    $maint = 0;
    $alerts = [];
    foreach (production_list_machines($pdo) as $m) {
        $s = production_normalize_machine_status((string)($m['status'] ?? ''));
        if ($s === MACHINE_STATUS_RUNNING) {
            $running++;
        }
        if (in_array($s, [MACHINE_STATUS_MAINTENANCE, MACHINE_STATUS_BREAKDOWN], true)) {
            $maint++;
            $alerts[] = $m['machine_code'] . ' — ' . $s;
        }
    }

    return [
        'mixing_today' => $mixing,
        'building_today' => $building,
        'curing_today' => $curing,
        'qc_passed_today' => $qcPass,
        'rejected_today' => $rejected,
        'downtime_today' => $downtime,
        'running_machines' => $running,
        'maint_machines' => $maint,
        'machine_alerts' => $alerts,
        'recent' => prod_entry_recent_feed($pdo, 15),
    ];
}

/** @return list<array<string, mixed>> */
function prod_entry_recent_feed(PDO $pdo, int $limit): array
{
    $items = [];
    $queries = [
        ['Mixing', 'SELECT production_date AS dt, shift, tyre_type, produced_qty, rejected_qty, m.machine_code, e.full_name AS op
            FROM mixing_entries x LEFT JOIN machines m ON m.id=x.machine_id LEFT JOIN employees e ON e.id=x.operator_id'],
        ['Building', 'SELECT production_date AS dt, shift, tyre_type, produced_qty, rejected_qty, m.machine_code, e.full_name AS op
            FROM building_entries x LEFT JOIN machines m ON m.id=x.machine_id LEFT JOIN employees e ON e.id=x.operator_id'],
        ['Curing', 'SELECT production_date AS dt, shift, tyre_type, produced_qty, rejected_qty, downtime_minutes, m.machine_code, e.full_name AS op
            FROM curing_entries x LEFT JOIN machines m ON m.id=x.machine_id LEFT JOIN employees e ON e.id=x.operator_id'],
        ['QC', 'SELECT entry_date AS dt, shift, tyre_type, passed_qty AS produced_qty, failed_qty AS rejected_qty, NULL AS machine_code, inspector_name AS op FROM qc_entries'],
    ];
    foreach ($queries as [$dept, $sql]) {
        $order = $dept === 'QC' ? ' ORDER BY id DESC LIMIT 5' : ' ORDER BY x.id DESC LIMIT 5';
        foreach ($pdo->query($sql . $order)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $r['department'] = $dept;
            $items[] = $r;
        }
    }
    usort($items, static fn($a, $b) => strcmp((string)$b['dt'], (string)$a['dt']));

    return array_slice($items, 0, $limit);
}

function prod_entry_add_inventory(PDO $pdo, string $productName, string $batchRef, int $qty): void
{
    if ($qty < 1) {
        return;
    }
    $exists = $pdo->prepare('SELECT id FROM inventory WHERE batch_ref = :b LIMIT 1');
    $exists->execute(['b' => $batchRef]);
    $row = $exists->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare('UPDATE inventory SET qty = qty + :q WHERE id = :id')->execute(['q' => $qty, 'id' => (int)$row['id']]);
    } else {
        $pdo->prepare('INSERT INTO inventory (product_name, batch_ref, qty, reorder_level, warehouse_location) VALUES (:n,:b,:q,50,:w)')
            ->execute(['n' => $productName, 'b' => $batchRef, 'q' => $qty, 'w' => 'FG-A1']);
    }
}

/**
 * Unified report rows.
 *
 * @return array{rows: list<array>, summary: array<string, float|int>}
 */
function prod_entry_report(PDO $pdo, string $from, string $to, string $department, string $shift, int $machineId): array
{
    $parts = [];
    $depts = $department === 'all' ? prod_entry_departments() : [$department];

    foreach ($depts as $dept) {
        $parts = array_merge($parts, prod_entry_report_department($pdo, $dept, $from, $to, $shift, $machineId));
    }

    usort($parts, static fn($a, $b) => strcmp((string)$b['entry_date'], (string)$a['entry_date']));

    $totalProduced = 0.0;
    $totalRejected = 0.0;
    $qcChecked = 0;
    $qcPassed = 0;
    foreach ($parts as $r) {
        $totalProduced += (float)$r['produced'];
        $totalRejected += (float)$r['rejected'];
        if ($r['department'] === PROD_ENTRY_QC) {
            $qcChecked += (int)$r['produced'] + (int)$r['rejected'];
            $qcPassed += (int)$r['produced'];
        }
    }
    $qcPct = $qcChecked > 0 ? round(($qcPassed / $qcChecked) * 100, 1) : 0.0;

    $dtSt = $pdo->prepare('SELECT COALESCE(SUM(downtime_minutes),0) FROM curing_entries WHERE production_date >= :f AND production_date <= :t');
    $dtSt->execute(['f' => $from, 't' => $to]);
    $downtime = (int)$dtSt->fetchColumn();

    $running = 0;
    foreach (production_list_machines($pdo) as $m) {
        if (($m['status'] ?? '') === MACHINE_STATUS_RUNNING) {
            $running++;
        }
    }

    return [
        'rows' => $parts,
        'summary' => [
            'total_produced' => $totalProduced,
            'total_rejected' => $totalRejected,
            'qc_pass_pct' => $qcPct,
            'downtime' => $downtime,
            'active_machines' => $running,
        ],
    ];
}

/** @return list<array<string, mixed>> */
function prod_entry_report_department(
    PDO $pdo,
    string $department,
    string $from,
    string $to,
    string $shift,
    int $machineId
): array {
    if ($department === PROD_ENTRY_QC) {
        $sql = 'SELECT e.entry_date, e.shift, e.tyre_type, e.passed_qty, e.failed_qty, e.inspector_name AS operator_name, NULL AS machine_code
            FROM qc_entries e WHERE e.entry_date >= :f AND e.entry_date <= :t';
        $params = ['f' => $from, 't' => $to];
        if ($shift !== '') {
            $sql .= ' AND e.shift = :sh';
            $params['sh'] = $shift;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = [
                'entry_date' => $r['entry_date'],
                'shift' => $r['shift'],
                'department' => PROD_ENTRY_QC,
                'machine' => '—',
                'tyre_type' => $r['tyre_type'],
                'produced' => (int)$r['passed_qty'],
                'rejected' => (int)$r['failed_qty'],
                'operator' => $r['operator_name'],
            ];
        }

        return $out;
    }

    $table = prod_entry_table($department);
    $sql = "SELECT e.production_date AS entry_date, e.shift, e.tyre_type, e.produced_qty, e.rejected_qty,
            m.machine_code, op.full_name AS operator_name
        FROM {$table} e
        LEFT JOIN machines m ON m.id = e.machine_id
        LEFT JOIN employees op ON op.id = e.operator_id
        WHERE e.production_date >= :f AND e.production_date <= :t";
    $params = ['f' => $from, 't' => $to];
    if ($shift !== '') {
        $sql .= ' AND e.shift = :sh';
        $params['sh'] = $shift;
    }
    if ($machineId > 0) {
        $sql .= ' AND e.machine_id = :mid';
        $params['mid'] = $machineId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $out[] = [
            'entry_date' => $r['entry_date'],
            'shift' => $r['shift'],
            'department' => $department,
            'machine' => $r['machine_code'] ?? '—',
            'tyre_type' => $r['tyre_type'],
            'produced' => $r['produced_qty'],
            'rejected' => $r['rejected_qty'],
            'operator' => $r['operator_name'] ?? '—',
        ];
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function prod_machines_for_dept(PDO $pdo, string $department): array
{
    $all = production_list_machines($pdo);
    $dept = strtolower($department);

    return array_values(array_filter($all, static function (array $m) use ($dept): bool {
        $d = strtolower((string)($m['department'] ?? ''));
        $t = strtolower((string)($m['machine_type'] ?? ''));

        return ($d !== '' && str_contains($d, $dept)) || ($t !== '' && str_contains($t, $dept));
    })) ?: $all;
}
