<?php
declare(strict_types=1);

require_once __DIR__ . '/production_service.php';
require_once __DIR__ . '/production_workflow.php';

const PROD_DEPT_MIXING = 'Mixing';
const PROD_DEPT_BUILDING = 'Building';
const PROD_DEPT_CURING = 'Curing';
const PROD_ORDER_OPEN = 'Open';

/** @return list<string> */
function prod_departments(): array
{
    return [PROD_DEPT_MIXING, PROD_DEPT_BUILDING, PROD_DEPT_CURING, 'QC'];
}

function prod_batch_prefix(string $dept): string
{
    return match ($dept) {
        PROD_DEPT_MIXING => 'CMP',
        PROD_DEPT_BUILDING => 'GBT',
        PROD_DEPT_CURING => 'CUR',
        default => 'BAT',
    };
}

function prod_generate_batch_code(PDO $pdo, string $dept): string
{
    $prefix = prod_batch_prefix($dept) . '-' . date('Ymd') . '-';
    $table = match ($dept) {
        PROD_DEPT_MIXING => 'mixing_batches',
        PROD_DEPT_BUILDING => 'building_batches',
        PROD_DEPT_CURING => 'curing_batches',
        default => 'mixing_batches',
    };
    $st = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE batch_code LIKE :p");
    $st->execute(['p' => $prefix . '%']);
    $seq = (int)$st->fetchColumn() + 1;

    return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

/** Master production order — target only, no stage chain. */
function prod_create_master_order(PDO $pdo, array $data, ?int $userId = null): int
{
    $tyreType = trim((string)($data['tyre_type'] ?? ''));
    $target = max(1, (int)($data['target_qty'] ?? 0));
    $deadline = trim((string)($data['deadline'] ?? ''));
    $priority = trim((string)($data['priority'] ?? 'Normal'));
    $remarks = trim((string)($data['remarks'] ?? ''));

    if ($tyreType === '') {
        throw new InvalidArgumentException('Tyre type is required.');
    }
    if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
        throw new InvalidArgumentException('Invalid deadline.');
    }

    $code = production_generate_order_code($pdo);
    $pdo->prepare(
        'INSERT INTO production_orders (order_code, tyre_type, target_qty, deadline, priority, status, current_stage, remarks, created_by_user_id)
         VALUES (:c, :t, :q, :dl, :p, :st, :cs, :rm, :uid)'
    )->execute([
        'c' => $code,
        't' => $tyreType,
        'q' => $target,
        'dl' => $deadline !== '' ? $deadline : null,
        'p' => in_array($priority, ['Low', 'Normal', 'High', 'Urgent'], true) ? $priority : 'Normal',
        'st' => PROD_ORDER_OPEN,
        'cs' => 'Plant',
        'rm' => $remarks !== '' ? $remarks : null,
        'uid' => $userId,
    ]);
    $id = (int)$pdo->lastInsertId();
    production_log($pdo, $id, null, 'order_created', 'Master production target ' . $code . ' — departments work independently.', $userId);

    return $id;
}

/** @return list<array<string, mixed>> */
function prod_list_master_orders(PDO $pdo, int $limit = 100): array
{
    $orders = $pdo->query(
        'SELECT * FROM production_orders ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($orders as &$o) {
        $oid = (int)$o['id'];
        $o['mixing_output'] = prod_sum_mixing_today_for_order($pdo, $oid);
        $o['building_output'] = prod_sum_column($pdo, 'building_batches', 'produced_qty', $oid);
        $o['curing_output'] = prod_sum_column($pdo, 'curing_batches', 'cured_qty', $oid);
        $o['qc_passed'] = prod_sum_column($pdo, 'production_qc_entries', 'passed_qty', $oid);
    }
    unset($o);

    return $orders;
}

function prod_sum_column(PDO $pdo, string $table, string $col, int $orderId): int
{
    if (!prod_table_exists($pdo, $table)) {
        return 0;
    }
    $st = $pdo->prepare("SELECT COALESCE(SUM({$col}),0) FROM {$table} WHERE order_id = :id");
    $st->execute(['id' => $orderId]);

    return (int)$st->fetchColumn();
}

function prod_sum_mixing_today_for_order(PDO $pdo, int $orderId): float
{
    $st = $pdo->prepare('SELECT COALESCE(SUM(produced_qty),0) FROM mixing_batches WHERE order_id = :id');
    $st->execute(['id' => $orderId]);

    return (float)$st->fetchColumn();
}

function prod_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
    $st->execute(['t' => $table]);

    return (int)$st->fetchColumn() > 0;
}

/** @return list<array<string, mixed>> */
function prod_machines_for_department(PDO $pdo, string $department): array
{
    $all = production_list_machines($pdo);
    $dept = strtolower($department);

    return array_values(array_filter($all, static function (array $m) use ($dept): bool {
        $d = strtolower((string)($m['department'] ?? ''));
        $t = strtolower((string)($m['machine_type'] ?? ''));
        if ($d !== '' && str_contains($d, $dept)) {
            return true;
        }

        return $t !== '' && str_contains($t, $dept);
    }));
}

function prod_validate_machine_operator(PDO $pdo, int $machineId, int $operatorId, string $date): void
{
    if ($machineId > 0) {
        $mSt = $pdo->prepare('SELECT status FROM machines WHERE id = :id');
        $mSt->execute(['id' => $machineId]);
        $m = $mSt->fetch(PDO::FETCH_ASSOC);
        if (!$m || !production_machine_can_run(production_normalize_machine_status((string)$m['status']))) {
            throw new RuntimeException('Machine must be in Running status.');
        }
    }
    if ($operatorId > 0) {
        $found = false;
        foreach (production_department_operators($pdo, $date) as $op) {
            if ((int)$op['id'] === $operatorId) {
                $found = true;
                if ((int)($op['is_absent'] ?? 0) === 1) {
                    throw new RuntimeException('Assigned operator absent today.');
                }
                break;
            }
        }
        if (!$found) {
            throw new RuntimeException('Invalid production operator.');
        }
    }
}

/** @param array<string, mixed> $data */
function prod_save_mixing_batch(PDO $pdo, array $data, ?int $userId = null): int
{
    $date = (string)($data['production_date'] ?? date('Y-m-d'));
    $produced = max(0, (float)($data['produced_qty'] ?? 0));
    $wastage = max(0, (float)($data['wastage_qty'] ?? 0));
    $machineId = (int)($data['machine_id'] ?? 0) ?: null;
    $operatorId = (int)($data['operator_id'] ?? 0) ?: null;
    $orderId = (int)($data['order_id'] ?? 0) ?: null;
    prod_validate_machine_operator($pdo, (int)$machineId, (int)$operatorId, $date);

    $code = trim((string)($data['batch_code'] ?? ''));
    if ($code === '') {
        $code = prod_generate_batch_code($pdo, PROD_DEPT_MIXING);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO mixing_batches (order_id, batch_code, compound_name, produced_qty, wastage_qty, unit, machine_id, operator_id, shift, production_date, status, notes)
             VALUES (:oid, :c, :cn, :pq, :wq, :u, :mid, :op, :sh, :d, :st, :n)'
        )->execute([
            'oid' => $orderId,
            'c' => $code,
            'cn' => trim((string)($data['compound_name'] ?? 'Rubber Compound')),
            'pq' => $produced,
            'wq' => $wastage,
            'u' => trim((string)($data['unit'] ?? 'kg')),
            'mid' => $machineId,
            'op' => $operatorId,
            'sh' => in_array($data['shift'] ?? '', PRODUCTION_SHIFTS, true) ? $data['shift'] : 'Morning',
            'd' => $date,
            'st' => trim((string)($data['status'] ?? 'Ready')),
            'n' => trim((string)($data['notes'] ?? '')) ?: null,
        ]);
        $id = (int)$pdo->lastInsertId();

        if ($orderId && $produced > 0) {
            prod_deduct_bom_for_order($pdo, $orderId);
            $pdo->prepare('UPDATE mixing_batches SET inventory_deducted = 1 WHERE id = :id')->execute(['id' => $id]);
        }

        production_log($pdo, $orderId ?? 0, null, 'mixing_batch', 'Mixing batch ' . $code . ' — ' . $produced . ' kg compound.', $userId);
        $pdo->commit();

        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function prod_deduct_bom_for_order(PDO $pdo, int $orderId): void
{
    $st = $pdo->prepare('SELECT tyre_type, target_qty FROM production_orders WHERE id = :id');
    $st->execute(['id' => $orderId]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return;
    }
    production_apply_bom_deduction($pdo, $orderId);
}

/** @return list<array<string, mixed>> */
function prod_list_mixing_batches(PDO $pdo, ?string $from = null, ?string $to = null, int $limit = 100): array
{
    $sql = 'SELECT b.*, o.order_code, o.tyre_type, m.machine_code, e.full_name AS operator_name
        FROM mixing_batches b
        LEFT JOIN production_orders o ON o.id = b.order_id
        LEFT JOIN machines m ON m.id = b.machine_id
        LEFT JOIN employees e ON e.id = b.operator_id WHERE 1=1';
    $params = [];
    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $sql .= ' AND b.production_date >= :f';
        $params['f'] = $from;
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $sql .= ' AND b.production_date <= :t';
        $params['t'] = $to;
    }
    $sql .= ' ORDER BY b.id DESC LIMIT ' . max(1, min(300, $limit));
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function prod_list_mixing_batches_ready(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id, batch_code, compound_name, produced_qty, unit, production_date
         FROM mixing_batches WHERE status IN ('Ready','Completed') ORDER BY id DESC LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @param array<string, mixed> $data */
function prod_save_building_batch(PDO $pdo, array $data, ?int $userId = null): int
{
    $date = (string)($data['production_date'] ?? date('Y-m-d'));
    $produced = max(0, (int)($data['produced_qty'] ?? 0));
    $rejected = max(0, (int)($data['rejected_qty'] ?? 0));
    $machineId = (int)($data['machine_id'] ?? 0) ?: null;
    $operatorId = (int)($data['operator_id'] ?? 0) ?: null;
    $mixingId = (int)($data['mixing_batch_id'] ?? 0) ?: null;
    $orderId = (int)($data['order_id'] ?? 0) ?: null;
    prod_validate_machine_operator($pdo, (int)$machineId, (int)$operatorId, $date);

    if ($mixingId) {
        $mSt = $pdo->prepare('SELECT order_id FROM mixing_batches WHERE id = :id');
        $mSt->execute(['id' => $mixingId]);
        $mixOrder = $mSt->fetchColumn();
        if ($mixOrder && !$orderId) {
            $orderId = (int)$mixOrder;
        }
    }

    $code = trim((string)($data['batch_code'] ?? '')) ?: prod_generate_batch_code($pdo, PROD_DEPT_BUILDING);

    $pdo->prepare(
        'INSERT INTO building_batches (order_id, mixing_batch_id, batch_code, produced_qty, rejected_qty, machine_id, operator_id, shift, production_date, status, notes)
         VALUES (:oid, :mid, :c, :pq, :rq, :mc, :op, :sh, :d, :st, :n)'
    )->execute([
        'oid' => $orderId,
        'mid' => $mixingId,
        'c' => $code,
        'pq' => $produced,
        'rq' => $rejected,
        'mc' => $machineId,
        'op' => $operatorId,
        'sh' => in_array($data['shift'] ?? '', PRODUCTION_SHIFTS, true) ? $data['shift'] : 'Morning',
        'd' => $date,
        'st' => trim((string)($data['status'] ?? 'In Progress')),
        'n' => trim((string)($data['notes'] ?? '')) ?: null,
    ]);
    $id = (int)$pdo->lastInsertId();
    production_log($pdo, $orderId ?? 0, null, 'building_batch', 'Building batch ' . $code . ' — ' . $produced . ' green tyres.', $userId);

    return $id;
}

/** @return list<array<string, mixed>> */
function prod_list_building_batches(PDO $pdo, ?string $from = null, ?string $to = null, int $limit = 100): array
{
    $sql = 'SELECT b.*, o.order_code, mb.batch_code AS mixing_code, m.machine_code, e.full_name AS operator_name
        FROM building_batches b
        LEFT JOIN production_orders o ON o.id = b.order_id
        LEFT JOIN mixing_batches mb ON mb.id = b.mixing_batch_id
        LEFT JOIN machines m ON m.id = b.machine_id
        LEFT JOIN employees e ON e.id = b.operator_id WHERE 1=1';
    $params = [];
    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $sql .= ' AND b.production_date >= :f';
        $params['f'] = $from;
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $sql .= ' AND b.production_date <= :t';
        $params['t'] = $to;
    }
    $sql .= ' ORDER BY b.id DESC LIMIT ' . max(1, min(300, $limit));
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function prod_list_building_batches_ready(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, batch_code, produced_qty, production_date FROM building_batches ORDER BY id DESC LIMIT 200'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @param array<string, mixed> $data */
function prod_save_curing_batch(PDO $pdo, array $data, ?int $userId = null): int
{
    $date = (string)($data['production_date'] ?? date('Y-m-d'));
    $cured = max(0, (int)($data['cured_qty'] ?? 0));
    $rejected = max(0, (int)($data['rejected_qty'] ?? 0));
    $machineId = (int)($data['machine_id'] ?? 0) ?: null;
    $operatorId = (int)($data['operator_id'] ?? 0) ?: null;
    $buildingId = (int)($data['building_batch_id'] ?? 0) ?: null;
    $orderId = (int)($data['order_id'] ?? 0) ?: null;
    prod_validate_machine_operator($pdo, (int)$machineId, (int)$operatorId, $date);

    if ($buildingId) {
        $bSt = $pdo->prepare('SELECT order_id FROM building_batches WHERE id = :id');
        $bSt->execute(['id' => $buildingId]);
        $bo = $bSt->fetchColumn();
        if ($bo && !$orderId) {
            $orderId = (int)$bo;
        }
    }

    $code = trim((string)($data['batch_code'] ?? '')) ?: prod_generate_batch_code($pdo, PROD_DEPT_CURING);

    $pdo->prepare(
        'INSERT INTO curing_batches (order_id, building_batch_id, batch_code, cured_qty, rejected_qty, cycle_time_min, downtime_min, machine_id, operator_id, shift, production_date, status, notes)
         VALUES (:oid, :bid, :c, :cq, :rq, :ct, :dt, :mc, :op, :sh, :d, :st, :n)'
    )->execute([
        'oid' => $orderId,
        'bid' => $buildingId,
        'c' => $code,
        'cq' => $cured,
        'rq' => $rejected,
        'ct' => max(0, (int)($data['cycle_time_min'] ?? 0)),
        'dt' => max(0, (int)($data['downtime_min'] ?? 0)),
        'mc' => $machineId,
        'op' => $operatorId,
        'sh' => in_array($data['shift'] ?? '', PRODUCTION_SHIFTS, true) ? $data['shift'] : 'Morning',
        'd' => $date,
        'st' => trim((string)($data['status'] ?? 'Completed')),
        'n' => trim((string)($data['notes'] ?? '')) ?: null,
    ]);
    $id = (int)$pdo->lastInsertId();
    production_log($pdo, $orderId ?? 0, null, 'curing_batch', 'Curing batch ' . $code . ' — ' . $cured . ' tyres cured.', $userId);

    return $id;
}

/** @return list<array<string, mixed>> */
function prod_list_curing_batches(PDO $pdo, ?string $from = null, ?string $to = null, int $limit = 100): array
{
    $sql = 'SELECT b.*, o.order_code, bb.batch_code AS building_code, m.machine_code, e.full_name AS operator_name
        FROM curing_batches b
        LEFT JOIN production_orders o ON o.id = b.order_id
        LEFT JOIN building_batches bb ON bb.id = b.building_batch_id
        LEFT JOIN machines m ON m.id = b.machine_id
        LEFT JOIN employees e ON e.id = b.operator_id WHERE 1=1';
    $params = [];
    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $sql .= ' AND b.production_date >= :f';
        $params['f'] = $from;
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $sql .= ' AND b.production_date <= :t';
        $params['t'] = $to;
    }
    $sql .= ' ORDER BY b.id DESC LIMIT ' . max(1, min(300, $limit));
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @param array<string, mixed> $data */
function prod_save_qc_entry(PDO $pdo, array $data, ?int $userId = null): int
{
    $date = (string)($data['inspection_date'] ?? date('Y-m-d'));
    $inspected = max(0, (int)($data['inspected_qty'] ?? 0));
    $passed = max(0, (int)($data['passed_qty'] ?? 0));
    $failed = max(0, (int)($data['rejected_qty'] ?? 0));
    $orderId = (int)($data['order_id'] ?? 0) ?: null;
    $curingId = (int)($data['curing_batch_id'] ?? 0) ?: null;
    $warehouse = trim((string)($data['warehouse_location'] ?? 'FG-A1'));
    $tyreType = 'Tyre';

    if ($curingId) {
        $cSt = $pdo->prepare('SELECT b.order_id, o.tyre_type FROM curing_batches b LEFT JOIN production_orders o ON o.id = b.order_id WHERE b.id = :id');
        $cSt->execute(['id' => $curingId]);
        $row = $cSt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (!$orderId && $row['order_id']) {
                $orderId = (int)$row['order_id'];
            }
            $tyreType = (string)($row['tyre_type'] ?? 'Tyre');
        }
    }

    $batchRef = trim((string)($data['batch_ref'] ?? ''));
    if ($batchRef === '' && $curingId) {
        $cb = $pdo->prepare('SELECT batch_code FROM curing_batches WHERE id = :id');
        $cb->execute(['id' => $curingId]);
        $batchRef = (string)$cb->fetchColumn();
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO production_qc_entries (order_id, curing_batch_id, batch_ref, inspection_date, inspected_qty, passed_qty, rejected_qty, defect_type, inspector_name, remarks, warehouse_location)
             VALUES (:oid, :cid, :br, :d, :iq, :pq, :rq, :def, :ins, :rm, :wh)'
        )->execute([
            'oid' => $orderId,
            'cid' => $curingId,
            'br' => $batchRef !== '' ? $batchRef : null,
            'd' => $date,
            'iq' => $inspected,
            'pq' => $passed,
            'rq' => $failed,
            'def' => trim((string)($data['defect_type'] ?? '')) ?: null,
            'ins' => trim((string)($data['inspector_name'] ?? '')),
            'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
            'wh' => $warehouse,
        ]);
        $id = (int)$pdo->lastInsertId();

        if ($passed > 0) {
            $invRef = $orderId ? 'ORD-' . $orderId . '-' . $batchRef : $batchRef;
            production_upsert_finished_inventory($pdo, $tyreType, $invRef, $passed, $warehouse);
            $pdo->prepare('UPDATE production_qc_entries SET inventory_added = 1 WHERE id = :id')->execute(['id' => $id]);
        }

        production_log($pdo, $orderId ?? 0, null, 'qc_entry', 'QC: passed ' . $passed . ', rejected ' . $failed . ' (' . $batchRef . ').', $userId);
        $pdo->commit();

        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** @return list<array<string, mixed>> */
function prod_list_qc_entries(PDO $pdo, ?string $from = null, ?string $to = null, int $limit = 100): array
{
    $sql = 'SELECT q.*, o.order_code, o.tyre_type, c.batch_code AS curing_code
        FROM production_qc_entries q
        LEFT JOIN production_orders o ON o.id = q.order_id
        LEFT JOIN curing_batches c ON c.id = q.curing_batch_id WHERE 1=1';
    $params = [];
    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $sql .= ' AND q.inspection_date >= :f';
        $params['f'] = $from;
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $sql .= ' AND q.inspection_date <= :t';
        $params['t'] = $to;
    }
    $sql .= ' ORDER BY q.id DESC LIMIT ' . max(1, min(300, $limit));
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Department dashboard stats for today. */
function prod_department_dashboard(PDO $pdo): array
{
    $today = date('Y-m-d');

    $mixing = (float)$pdo->query(
        "SELECT COALESCE(SUM(produced_qty),0) FROM mixing_batches WHERE production_date = CURDATE()"
    )->fetchColumn();
    $building = (int)$pdo->query(
        "SELECT COALESCE(SUM(produced_qty),0) FROM building_batches WHERE production_date = CURDATE()"
    )->fetchColumn();
    $curing = (int)$pdo->query(
        "SELECT COALESCE(SUM(cured_qty),0) FROM curing_batches WHERE production_date = CURDATE()"
    )->fetchColumn();
    $qcPass = (int)$pdo->query(
        "SELECT COALESCE(SUM(passed_qty),0) FROM production_qc_entries WHERE inspection_date = CURDATE()"
    )->fetchColumn();
    $qcRej = (int)$pdo->query(
        "SELECT COALESCE(SUM(rejected_qty),0) FROM production_qc_entries WHERE inspection_date = CURDATE()"
    )->fetchColumn();
    $downtime = (int)$pdo->query(
        "SELECT COALESCE(SUM(downtime_min),0) FROM curing_batches WHERE production_date = CURDATE()"
    )->fetchColumn();

    $openOrders = (int)$pdo->query(
        "SELECT COUNT(*) FROM production_orders WHERE status IN ('Open','Pending','In Progress')"
    )->fetchColumn();

    $runningMachines = 0;
    foreach (production_list_machines($pdo) as $m) {
        if (($m['status'] ?? '') === MACHINE_STATUS_RUNNING) {
            $runningMachines++;
        }
    }

    return [
        'mixing_today' => $mixing,
        'building_today' => $building,
        'curing_today' => $curing,
        'qc_passed_today' => $qcPass,
        'qc_rejected_today' => $qcRej,
        'downtime_today' => $downtime,
        'open_orders' => $openOrders,
        'running_machines' => $runningMachines,
    ];
}

/** Order traceability summary. */
function prod_order_traceability(PDO $pdo, int $orderId): array
{
    $order = $pdo->prepare('SELECT * FROM production_orders WHERE id = :id');
    $order->execute(['id' => $orderId]);
    $o = $order->fetch(PDO::FETCH_ASSOC);
    if (!$o) {
        return [];
    }

    $mix = $pdo->prepare('SELECT * FROM mixing_batches WHERE order_id = :id ORDER BY id DESC');
    $mix->execute(['id' => $orderId]);
    $bld = $pdo->prepare('SELECT b.*, mb.batch_code AS mixing_code FROM building_batches b LEFT JOIN mixing_batches mb ON mb.id = b.mixing_batch_id WHERE b.order_id = :id ORDER BY b.id DESC');
    $bld->execute(['id' => $orderId]);
    $cur = $pdo->prepare('SELECT c.*, bb.batch_code AS building_code FROM curing_batches c LEFT JOIN building_batches bb ON bb.id = c.building_batch_id WHERE c.order_id = :id ORDER BY c.id DESC');
    $cur->execute(['id' => $orderId]);
    $qc = $pdo->prepare('SELECT * FROM production_qc_entries WHERE order_id = :id ORDER BY id DESC');
    $qc->execute(['id' => $orderId]);
    $qcRows = $qc->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $target = max(1, (int)$o['target_qty']);
    $qcPass = array_sum(array_map(static fn(array $r): int => (int)$r['passed_qty'], $qcRows));

    return [
        'order' => $o,
        'mixing' => $mix->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'building' => $bld->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'curing' => $cur->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'qc' => $qcRows,
        'achieved_pct' => min(100, (int)round(($qcPass / $target) * 100)),
    ];
}

/** @return list<array<string, mixed>> */
function prod_open_orders_dropdown(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id, order_code, tyre_type, target_qty FROM production_orders
         WHERE status NOT IN ('Cancelled','Finished') ORDER BY order_code DESC LIMIT 100"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
