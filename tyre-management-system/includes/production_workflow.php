<?php
declare(strict_types=1);

require_once __DIR__ . '/production_service.php';

const PROD_ORDER_PENDING = 'Pending';
const PROD_ORDER_IN_PROGRESS = 'In Progress';
const PROD_ORDER_QC_PENDING = 'QC Pending';
const PROD_ORDER_FINISHED = 'Finished';
const PROD_ORDER_CANCELLED = 'Cancelled';

const PROD_STAGE_PENDING = 'Pending';
const PROD_STAGE_RUNNING = 'Running';
const PROD_STAGE_PAUSED = 'Paused';
const PROD_STAGE_COMPLETED = 'Completed';

/** @return list<string> */
function production_stage_names(): array
{
    return ['Mixing', 'Building', 'Curing', 'QC', 'Finished'];
}

function production_is_production_department(?string $dept): bool
{
    $d = strtolower(trim((string)$dept));

    return $d !== '' && (str_contains($d, 'production') || str_contains($d, 'plant') || str_contains($d, 'manufacturing'));
}

/**
 * Production department employees for operator assignment.
 *
 * @return list<array<string, mixed>>
 */
function production_department_operators(PDO $pdo, string $dateYmd): array
{
    $sql = "SELECT e.id, e.full_name, e.employee_code, e.department, e.designation,
            a.status AS att_status,
            CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END AS is_absent
        FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = :d
        WHERE COALESCE(e.status, 'active') = 'active'
        ORDER BY e.full_name ASC";
    $st = $pdo->prepare($sql);
    $st->execute(['d' => $dateYmd]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $dept = (string)($r['department'] ?? '');
        if (!production_is_production_department($dept)) {
            continue;
        }
        $out[] = $r;
    }

    return $out;
}

function production_operator_absent_warning(?array $operatorRow): string
{
    if (!$operatorRow) {
        return '';
    }
    if ((int)($operatorRow['is_absent'] ?? 0) === 1 || ($operatorRow['att_status'] ?? '') === 'Absent') {
        return 'Assigned operator absent today.';
    }

    return '';
}

function production_generate_order_code(PDO $pdo): string
{
    $prefix = 'PRD-' . date('Ymd') . '-';
    $st = $pdo->prepare('SELECT COUNT(*) FROM production_orders WHERE order_code LIKE :p');
    $st->execute(['p' => $prefix . '%']);
    $seq = (int)$st->fetchColumn() + 1;

    return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

/** @param array<string, mixed> $data */
function production_create_order(PDO $pdo, array $data, ?int $userId = null): int
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
        throw new InvalidArgumentException('Invalid deadline date.');
    }
    if (!in_array($priority, ['Low', 'Normal', 'High', 'Urgent'], true)) {
        $priority = 'Normal';
    }

    $code = production_generate_order_code($pdo);

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO production_orders (order_code, tyre_type, target_qty, deadline, priority, status, current_stage, remarks, created_by_user_id)
             VALUES (:c, :t, :q, :dl, :p, :st, :cs, :rm, :uid)'
        );
        $ins->execute([
            'c' => $code,
            't' => $tyreType,
            'q' => $target,
            'dl' => $deadline !== '' ? $deadline : null,
            'p' => $priority,
            'st' => PROD_ORDER_PENDING,
            'cs' => 'Mixing',
            'rm' => $remarks !== '' ? $remarks : null,
            'uid' => $userId,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $stageIns = $pdo->prepare(
            'INSERT INTO production_stages (order_id, stage_name, stage_order, status)
             VALUES (:oid, :sn, :so, :st)'
        );
        $order = 1;
        foreach (production_stage_names() as $name) {
            $stageIns->execute([
                'oid' => $orderId,
                'sn' => $name,
                'so' => $order++,
                'st' => PROD_STAGE_PENDING,
            ]);
        }

        production_log($pdo, $orderId, null, 'order_created', 'Production job ticket ' . $code . ' created — production not started yet.', $userId);
        $pdo->commit();

        return $orderId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** @return list<array<string, mixed>> */
function production_list_orders(PDO $pdo, ?string $statusFilter = null, int $limit = 100): array
{
    $sql = 'SELECT o.* FROM production_orders o WHERE 1=1';
    $params = [];
    if ($statusFilter !== null && $statusFilter !== '') {
        $sql .= ' AND o.status = :st';
        $params['st'] = $statusFilter;
    }
    $sql .= ' ORDER BY o.id DESC LIMIT ' . max(1, min(500, $limit));
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array{order: array, stages: list<array>, logs: list<array>}|null */
function production_fetch_order(PDO $pdo, int $orderId): ?array
{
    $st = $pdo->prepare('SELECT * FROM production_orders WHERE id = :id LIMIT 1');
    $st->execute(['id' => $orderId]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return null;
    }

    $stg = $pdo->prepare(
        'SELECT s.*, m.machine_code, m.machine_name, e.full_name AS operator_name, e.employee_code
         FROM production_stages s
         LEFT JOIN machines m ON m.id = s.machine_id
         LEFT JOIN employees e ON e.id = s.operator_id
         WHERE s.order_id = :id ORDER BY s.stage_order ASC'
    );
    $stg->execute(['id' => $orderId]);
    $stages = $stg->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $logSt = $pdo->prepare(
        'SELECT l.*, u.full_name AS user_name FROM production_logs l
         LEFT JOIN users u ON u.id = l.user_id
         WHERE l.order_id = :id ORDER BY l.id DESC LIMIT 50'
    );
    $logSt->execute(['id' => $orderId]);
    $logs = $logSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return ['order' => $order, 'stages' => $stages, 'logs' => $logs];
}

function production_log(PDO $pdo, int $orderId, ?int $stageId, string $action, string $message, ?int $userId): void
{
    $st = $pdo->prepare(
        'INSERT INTO production_logs (order_id, stage_id, action, message, user_id) VALUES (:o, :s, :a, :m, :u)'
    );
    $st->execute([
        'o' => $orderId,
        's' => $stageId,
        'a' => $action,
        'm' => $message,
        'u' => $userId,
    ]);
}

function production_write_stage_log(PDO $pdo, int $orderId, ?int $stageId, string $stageName, string $actionType, array $stageRow, ?int $userId): void
{
    if (!production_table_exists($pdo, 'production_stage_logs')) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO production_stage_logs (
            order_id, stage_id, stage_name, machine_id, operator_id, shift, status,
            produced_qty, rejected_qty, downtime_minutes, started_at, completed_at, remarks, action_type, user_id
        ) VALUES (
            :oid, :sid, :sn, :mid, :op, :sh, :st, :pq, :rq, :dt, :sa, :ea, :rm, :act, :uid
        )'
    )->execute([
        'oid' => $orderId,
        'sid' => $stageId,
        'sn' => $stageName,
        'mid' => $stageRow['machine_id'] ?? null,
        'op' => $stageRow['operator_id'] ?? null,
        'sh' => $stageRow['shift'] ?? null,
        'st' => $stageRow['status'] ?? 'Pending',
        'pq' => (int)($stageRow['produced_qty'] ?? 0),
        'rq' => (int)($stageRow['rejected_qty'] ?? 0),
        'dt' => (int)($stageRow['downtime_minutes'] ?? 0),
        'sa' => $stageRow['started_at'] ?? null,
        'ea' => $stageRow['ended_at'] ?? null,
        'rm' => $stageRow['remarks'] ?? null,
        'act' => $actionType,
        'uid' => $userId,
    ]);
}

function production_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
    $st->execute(['t' => $table]);

    return (int)$st->fetchColumn() > 0;
}

/** @param array<string, mixed> $data */
function production_update_stage(PDO $pdo, int $stageId, array $data, ?int $userId = null, bool $allowStatusChange = false): void
{
    $st = $pdo->prepare('SELECT s.*, o.status AS order_status, o.id AS order_id, o.order_code, o.inventory_deducted
        FROM production_stages s INNER JOIN production_orders o ON o.id = s.order_id WHERE s.id = :id');
    $st->execute(['id' => $stageId]);
    $stage = $st->fetch(PDO::FETCH_ASSOC);
    if (!$stage) {
        throw new RuntimeException('Stage not found.');
    }

    $orderId = (int)$stage['order_id'];
    $stageName = (string)$stage['stage_name'];
    $oldStatus = (string)$stage['status'];
    $newStatus = $allowStatusChange ? (string)($data['status'] ?? $oldStatus) : $oldStatus;
    $machineId = (int)($data['machine_id'] ?? 0) ?: null;
    $operatorId = (int)($data['operator_id'] ?? 0) ?: null;
    $shift = (string)($data['shift'] ?? $stage['shift'] ?? 'Morning');
    $produced = max(0, (int)($data['produced_qty'] ?? $stage['produced_qty'] ?? 0));
    $rejected = max(0, (int)($data['rejected_qty'] ?? $stage['rejected_qty'] ?? 0));
    $downtime = max(0, (int)($data['downtime_minutes'] ?? $stage['downtime_minutes'] ?? 0));
    $remarks = trim((string)($data['remarks'] ?? $stage['remarks'] ?? ''));

    if (!in_array($newStatus, [PROD_STAGE_PENDING, PROD_STAGE_RUNNING, PROD_STAGE_PAUSED, PROD_STAGE_COMPLETED], true)) {
        throw new InvalidArgumentException('Invalid stage status.');
    }
    if (!in_array($shift, PRODUCTION_SHIFTS, true)) {
        $shift = 'Morning';
    }

    if (!$allowStatusChange && $newStatus !== $oldStatus) {
        throw new RuntimeException('Stage status can only change via Start / Pause / Complete actions.');
    }

    if (!$allowStatusChange && production_is_shop_stage($stageName)
        && !in_array($oldStatus, [PROD_STAGE_RUNNING, PROD_STAGE_PAUSED], true)) {
        throw new RuntimeException('Start ' . $stageName . ' before entering production quantities.');
    }

    $bundle = production_fetch_order($pdo, $orderId);

    if ($allowStatusChange && production_is_shop_stage($stageName)) {
        if ($bundle && production_stage_is_locked($stageName, $stage, $bundle['order'], $bundle['stages'])) {
            throw new RuntimeException($stageName . ' is locked. Complete the previous stage first.');
        }
        if ($newStatus === PROD_STAGE_RUNNING && !production_previous_stage_completed($stageName, $bundle['stages'] ?? [])) {
            throw new RuntimeException('Previous stage must be completed before starting ' . $stageName . '.');
        }
    }

    if (in_array($stageName, ['QC', 'Finished'], true) && $newStatus === PROD_STAGE_RUNNING) {
        throw new RuntimeException($stageName . ' stage is updated by workflow actions, not manual start.');
    }

    if ($machineId !== null && $machineId > 0) {
        $mSt = $pdo->prepare('SELECT status FROM machines WHERE id = :id');
        $mSt->execute(['id' => $machineId]);
        $m = $mSt->fetch(PDO::FETCH_ASSOC);
        if (!$m || !production_machine_can_run(production_normalize_machine_status((string)$m['status']))) {
            throw new RuntimeException('Machine must be in Running status.');
        }
    }

    if ($operatorId !== null && $operatorId > 0) {
        $ops = production_department_operators($pdo, date('Y-m-d'));
        $found = false;
        foreach ($ops as $op) {
            if ((int)$op['id'] === $operatorId) {
                $found = true;
                if ((int)($op['is_absent'] ?? 0) === 1) {
                    throw new RuntimeException('Assigned operator absent today.');
                }
                break;
            }
        }
        if (!$found) {
            throw new RuntimeException('Operator must be from Production department.');
        }
    }

    $startedAt = $stage['started_at'];
    $endedAt = $stage['ended_at'];
    if ($newStatus === PROD_STAGE_RUNNING && ($startedAt === null || $startedAt === '')) {
        $startedAt = date('Y-m-d H:i:s');
    }
    if ($newStatus === PROD_STAGE_COMPLETED && ($endedAt === null || $endedAt === '')) {
        $endedAt = date('Y-m-d H:i:s');
    }

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare(
            'UPDATE production_stages SET machine_id = :mid, operator_id = :oid, shift = :sh, status = :st,
             produced_qty = :pq, rejected_qty = :rq, downtime_minutes = :dt, started_at = :sa, ended_at = :ea, remarks = :rm
             WHERE id = :id'
        );
        $upd->execute([
            'mid' => $machineId,
            'oid' => $operatorId,
            'sh' => $shift,
            'st' => $newStatus,
            'pq' => $produced,
            'rq' => $rejected,
            'dt' => $downtime,
            'sa' => $startedAt,
            'ea' => $endedAt,
            'rm' => $remarks !== '' ? $remarks : null,
            'id' => $stageId,
        ]);

        if ($machineId !== null && $machineId > 0) {
            $pdo->prepare(
                'INSERT INTO machine_assignments (order_id, stage_id, machine_id) VALUES (:o, :s, :m)'
            )->execute(['o' => $orderId, 's' => $stageId, 'm' => $machineId]);
        }

        if ($downtime > 0) {
            $pdo->prepare(
                'INSERT INTO production_downtime (order_id, stage_id, minutes, reason) VALUES (:o, :s, :m, :r)'
            )->execute([
                'o' => $orderId,
                's' => $stageId,
                'm' => $downtime,
                'r' => $remarks !== '' ? $remarks : 'Downtime logged',
            ]);
        }

        if ($newStatus === PROD_STAGE_RUNNING && (int)($stage['inventory_deducted'] ?? 0) === 0 && $stageName === 'Mixing') {
            production_apply_bom_deduction($pdo, $orderId);
            $pdo->prepare('UPDATE production_orders SET inventory_deducted = 1 WHERE id = :id')->execute(['id' => $orderId]);
        }

        production_recalculate_order_totals($pdo, $orderId);
        production_sync_order_progress($pdo, $orderId);

        $logAction = $allowStatusChange && $newStatus !== $oldStatus
            ? match ($newStatus) {
                PROD_STAGE_RUNNING => 'started',
                PROD_STAGE_PAUSED => 'paused',
                PROD_STAGE_COMPLETED => 'completed',
                default => 'updated',
            }
            : 'updated';

        $logMsg = match ($logAction) {
            'started' => $stageName . ' started',
            'paused' => $stageName . ' paused',
            'completed' => $stageName . ' completed',
            default => $stageName . ' details saved',
        };
        production_log($pdo, $orderId, $stageId, 'stage_' . $logAction, $logMsg, $userId);
        production_write_stage_log($pdo, $orderId, $stageId, $stageName, $logAction, [
            'machine_id' => $machineId,
            'operator_id' => $operatorId,
            'shift' => $shift,
            'status' => $newStatus,
            'produced_qty' => $produced,
            'rejected_qty' => $rejected,
            'downtime_minutes' => $downtime,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'remarks' => $remarks !== '' ? $remarks : null,
        ], $userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function production_recalculate_order_totals(PDO $pdo, int $orderId): void
{
    $bundle = production_fetch_order($pdo, $orderId);
    if (!$bundle) {
        return;
    }
    $metrics = production_order_display_metrics($bundle['order'], $bundle['stages']);
    $dtSt = $pdo->prepare(
        "SELECT COALESCE(SUM(downtime_minutes),0) FROM production_stages WHERE order_id = :id AND stage_name IN ('Mixing','Building','Curing')"
    );
    $dtSt->execute(['id' => $orderId]);
    $downtime = (int)$dtSt->fetchColumn();
    $pdo->prepare('UPDATE production_orders SET total_produced = :p, total_rejected = :r, total_downtime = :d WHERE id = :id')
        ->execute(['p' => $metrics['produced'], 'r' => $metrics['rejected'], 'd' => $downtime, 'id' => $orderId]);
}

function production_sync_order_progress(PDO $pdo, int $orderId): void
{
    $st = $pdo->prepare('SELECT * FROM production_stages WHERE order_id = :id ORDER BY stage_order ASC');
    $st->execute(['id' => $orderId]);
    $stages = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $ordSt = $pdo->prepare('SELECT status FROM production_orders WHERE id = :id');
    $ordSt->execute(['id' => $orderId]);
    $existing = (string)$ordSt->fetchColumn();

    $currentStage = 'Mixing';
    $orderStatus = PROD_ORDER_PENDING;
    $allShopFloorDone = true;

    foreach ($stages as $s) {
        $name = (string)$s['stage_name'];
        if (in_array($name, ['Mixing', 'Building', 'Curing'], true)) {
            if ($s['status'] !== PROD_STAGE_COMPLETED) {
                $allShopFloorDone = false;
                $currentStage = $name;
                if ($s['status'] === PROD_STAGE_RUNNING || $s['status'] === PROD_STAGE_PAUSED) {
                    $orderStatus = PROD_ORDER_IN_PROGRESS;
                }
                break;
            }
        }
    }

    if ($allShopFloorDone && $existing !== PROD_ORDER_QC_PENDING && $existing !== PROD_ORDER_FINISHED) {
        $currentStage = 'QC';
        $orderStatus = PROD_ORDER_IN_PROGRESS;
    }

    if ($existing === PROD_ORDER_QC_PENDING) {
        $orderStatus = PROD_ORDER_QC_PENDING;
        $currentStage = 'QC';
    } elseif ($existing === PROD_ORDER_FINISHED) {
        $orderStatus = PROD_ORDER_FINISHED;
        $currentStage = 'Finished';
    } elseif ($orderStatus === PROD_ORDER_PENDING) {
        foreach ($stages as $s) {
            if (in_array($s['stage_name'], ['Mixing', 'Building', 'Curing'], true)
                && in_array($s['status'], [PROD_STAGE_RUNNING, PROD_STAGE_PAUSED, PROD_STAGE_COMPLETED], true)) {
                $orderStatus = PROD_ORDER_IN_PROGRESS;
                break;
            }
        }
    }

    $pdo->prepare('UPDATE production_orders SET status = :st, current_stage = :cs, updated_at = NOW() WHERE id = :id')
        ->execute(['st' => $orderStatus, 'cs' => $currentStage, 'id' => $orderId]);
}

/** Submit order to Quality after Curing complete. */
function production_submit_to_qc(PDO $pdo, int $orderId, ?int $userId = null): void
{
    $bundle = production_fetch_order($pdo, $orderId);
    if (!$bundle) {
        throw new RuntimeException('Order not found.');
    }

    foreach ($bundle['stages'] as $s) {
        if ($s['stage_name'] === 'Curing' && $s['status'] !== PROD_STAGE_COMPLETED) {
            throw new RuntimeException('Complete Curing stage before sending to QC.');
        }
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE production_orders SET status = :st, current_stage = 'QC' WHERE id = :id")
            ->execute(['st' => PROD_ORDER_QC_PENDING, 'id' => $orderId]);
        $pdo->prepare("UPDATE production_stages SET status = :run WHERE order_id = :id AND stage_name = 'QC'")
            ->execute(['run' => PROD_STAGE_RUNNING, 'id' => $orderId]);
        production_log($pdo, $orderId, null, 'submit_qc', 'Order submitted to Quality Control.', $userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** QC approve — finished goods ready for dispatch pipeline. */
function production_qc_approve_order(PDO $pdo, int $orderId, int $passedQty, int $failedQty, string $inspector, string $defects, string $warehouse, ?int $userId = null): void
{
    $bundle = production_fetch_order($pdo, $orderId);
    if (!$bundle || $bundle['order']['status'] !== PROD_ORDER_QC_PENDING) {
        throw new RuntimeException('Order is not pending QC.');
    }

    $pdo->beginTransaction();
    try {
        $legacyId = production_create_legacy_batch_from_order($pdo, $bundle['order'], $passedQty, $failedQty);

        $qc = $pdo->prepare(
            'INSERT INTO quality_checks (production_id, production_order_id, inspection_date, inspector_name, passed_qty, failed_qty, quality_status, defects)
             VALUES (:p, :po, CURDATE(), :i, :pa, :f, :qs, :de)'
        );
        $status = $failedQty > 0 ? 'Fail' : 'Pass';
        $qc->execute([
            'p' => $legacyId,
            'po' => $orderId,
            'i' => $inspector,
            'pa' => $passedQty,
            'f' => $failedQty,
            'qs' => $status,
            'de' => $defects !== '' ? $defects : null,
        ]);
        $qcId = (int)$pdo->lastInsertId();

        $batchRef = 'ORD-' . $bundle['order']['order_code'];
        production_upsert_finished_inventory($pdo, $bundle['order']['tyre_type'], $batchRef, $passedQty, $warehouse);

        if ($failedQty > 0) {
            $pdo->prepare('INSERT INTO defect_logs (quality_check_id, production_id, failed_qty, defect_notes) VALUES (:q,:p,:f,:d)')
                ->execute(['q' => $qcId, 'p' => $legacyId, 'f' => $failedQty, 'd' => $defects]);
        }

        $pdo->prepare("UPDATE production_stages SET status = :c, produced_qty = :pq, ended_at = NOW() WHERE order_id = :id AND stage_name = 'QC'")
            ->execute(['c' => PROD_STAGE_COMPLETED, 'pq' => $passedQty, 'id' => $orderId]);
        $pdo->prepare("UPDATE production_stages SET status = :c, ended_at = NOW() WHERE order_id = :id AND stage_name = 'Finished'")
            ->execute(['c' => PROD_STAGE_COMPLETED, 'id' => $orderId]);
        $pdo->prepare('UPDATE production_orders SET status = :st, current_stage = :cs, qc_passed_qty = :p, qc_failed_qty = :f WHERE id = :id')
            ->execute([
                'st' => PROD_ORDER_FINISHED,
                'cs' => 'Finished',
                'p' => $passedQty,
                'f' => $failedQty,
                'id' => $orderId,
            ]);

        production_log($pdo, $orderId, null, 'qc_approved', 'QC approved. Passed: ' . $passedQty, $userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function production_create_legacy_batch_from_order(PDO $pdo, array $order, int $passed, int $failed): int
{
    $mid = (int)$pdo->query("SELECT id FROM machines ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($mid < 1) {
        $pdo->exec("INSERT INTO machines (machine_code, machine_name, status) VALUES ('SYS-01','System','Running')");
        $mid = (int)$pdo->lastInsertId();
    }
    $st = $pdo->prepare(
        'INSERT INTO production (production_date, machine_id, shift, tyre_type, planned_quantity, output_quantity, rejected_quantity, entry_status, inventory_deducted)
         VALUES (CURDATE(), :mid, :sh, :tt, :plan, :out, :rej, :es, 1)'
    );
    $st->execute([
        'mid' => $mid,
        'sh' => 'Morning',
        'tt' => $order['tyre_type'],
        'plan' => $order['target_qty'],
        'out' => $passed,
        'rej' => $failed,
        'es' => 'Completed',
    ]);

    return (int)$pdo->lastInsertId();
}

/** QC reject — return order to shop floor for rework. */
function production_qc_reject_order(PDO $pdo, int $orderId, int $failedQty, string $defects, ?int $userId = null): void
{
    $bundle = production_fetch_order($pdo, $orderId);
    if (!$bundle || $bundle['order']['status'] !== PROD_ORDER_QC_PENDING) {
        throw new RuntimeException('Order is not pending QC.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE production_orders SET status = :st, current_stage = :cs, qc_failed_qty = :f, total_rejected = total_rejected + :f WHERE id = :id')
            ->execute(['st' => PROD_ORDER_IN_PROGRESS, 'cs' => 'Curing', 'f' => $failedQty, 'id' => $orderId]);
        $pdo->prepare("UPDATE production_stages SET status = :p WHERE order_id = :id AND stage_name = 'QC'")
            ->execute(['p' => PROD_STAGE_PENDING, 'id' => $orderId]);
        $pdo->prepare("UPDATE production_stages SET status = :r, ended_at = NULL WHERE order_id = :id AND stage_name = 'Curing'")
            ->execute(['r' => PROD_STAGE_RUNNING, 'id' => $orderId]);
        production_log($pdo, $orderId, null, 'qc_rejected', 'QC rejected: ' . $defects, $userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function production_order_ready_for_qc(array $bundle): bool
{
    if ($bundle['order']['status'] === PROD_ORDER_QC_PENDING || $bundle['order']['status'] === PROD_ORDER_FINISHED) {
        return false;
    }
    foreach ($bundle['stages'] as $s) {
        if ($s['stage_name'] === 'Curing') {
            return $s['status'] === PROD_STAGE_COMPLETED;
        }
    }

    return false;
}

/** @return list<array<string, mixed>> */
function production_report_daily(PDO $pdo, string $from, string $to): array
{
    $st = $pdo->prepare(
        "SELECT DATE(updated_at) AS prod_date, COUNT(*) AS orders,
         COALESCE(SUM(total_produced),0) AS produced, COALESCE(SUM(total_rejected),0) AS rejected,
         COALESCE(SUM(total_downtime),0) AS downtime
         FROM production_orders WHERE DATE(updated_at) BETWEEN :f AND :t
         GROUP BY DATE(updated_at) ORDER BY prod_date DESC"
    );
    $st->execute(['f' => $from, 't' => $to]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function production_report_shift(PDO $pdo, string $from, string $to): array
{
    $st = $pdo->prepare(
        "SELECT s.shift, COALESCE(SUM(s.produced_qty),0) AS produced, COALESCE(SUM(s.rejected_qty),0) AS rejected
         FROM production_stages s
         INNER JOIN production_orders o ON o.id = s.order_id
         WHERE s.stage_name IN ('Mixing','Building','Curing') AND DATE(o.updated_at) BETWEEN :f AND :t
         GROUP BY s.shift ORDER BY s.shift"
    );
    $st->execute(['f' => $from, 't' => $to]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function production_report_machine_efficiency(PDO $pdo, string $from, string $to): array
{
    $st = $pdo->prepare(
        "SELECT m.machine_code, m.machine_name, COUNT(DISTINCT s.order_id) AS orders,
         COALESCE(SUM(s.produced_qty),0) AS produced, COALESCE(SUM(s.downtime_minutes),0) AS downtime
         FROM production_stages s
         INNER JOIN machines m ON m.id = s.machine_id
         INNER JOIN production_orders o ON o.id = s.order_id
         WHERE DATE(o.updated_at) BETWEEN :f AND :t AND s.machine_id IS NOT NULL
         GROUP BY m.id ORDER BY produced DESC"
    );
    $st->execute(['f' => $from, 't' => $to]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function production_report_operator_productivity(PDO $pdo, string $from, string $to): array
{
    $st = $pdo->prepare(
        "SELECT e.full_name, e.employee_code, COALESCE(SUM(s.produced_qty),0) AS produced,
         COALESCE(SUM(s.rejected_qty),0) AS rejected
         FROM production_stages s
         INNER JOIN employees e ON e.id = s.operator_id
         INNER JOIN production_orders o ON o.id = s.order_id
         WHERE DATE(o.updated_at) BETWEEN :f AND :t AND s.operator_id IS NOT NULL
         GROUP BY e.id ORDER BY produced DESC"
    );
    $st->execute(['f' => $from, 't' => $to]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function production_report_downtime(PDO $pdo, string $from, string $to): array
{
    $st = $pdo->prepare(
        'SELECT d.*, o.order_code, s.stage_name FROM production_downtime d
         INNER JOIN production_orders o ON o.id = d.order_id
         LEFT JOIN production_stages s ON s.id = d.stage_id
         WHERE DATE(d.logged_at) BETWEEN :f AND :t ORDER BY d.id DESC LIMIT 500'
    );
    $st->execute(['f' => $from, 't' => $to]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function production_upsert_finished_inventory(PDO $pdo, string $tyreType, string $batchRef, int $qty, string $warehouse): void
{
    if ($qty < 1) {
        return;
    }
    $exists = $pdo->prepare('SELECT id FROM inventory WHERE batch_ref = :b LIMIT 1');
    $exists->execute(['b' => $batchRef]);
    $row = $exists->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare('UPDATE inventory SET qty = qty + :q, warehouse_location = :w WHERE id = :id')
            ->execute(['q' => $qty, 'w' => $warehouse, 'id' => (int)$row['id']]);
    } else {
        $pdo->prepare('INSERT INTO inventory (product_name, batch_ref, qty, reorder_level, warehouse_location) VALUES (:n,:b,:q,50,:w)')
            ->execute(['n' => $tyreType, 'b' => $batchRef, 'q' => $qty, 'w' => $warehouse]);
    }
}

/** BOM deduction when Mixing starts. */
function production_apply_bom_deduction(PDO $pdo, int $orderId): void
{
    $st = $pdo->prepare('SELECT tyre_type, target_qty FROM production_orders WHERE id = :id');
    $st->execute(['id' => $orderId]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return;
    }

    $bom = $pdo->prepare(
        'SELECT b.raw_material_id, b.qty_per_unit, r.material_name, r.stock_qty, r.unit
         FROM production_bom b
         INNER JOIN raw_materials r ON r.id = b.raw_material_id
         WHERE b.tyre_type = :t'
    );
    $bom->execute(['t' => $order['tyre_type']]);
    $lines = $bom->fetchAll(PDO::FETCH_ASSOC);
    if (!$lines) {
        production_log($pdo, $orderId, null, 'inventory_skip', 'No BOM defined for tyre type — configure production_bom.', null);
        return;
    }

    $target = (int)$order['target_qty'];
    foreach ($lines as $line) {
        $need = round((float)$line['qty_per_unit'] * $target, 2);
        if ($need <= 0) {
            continue;
        }
        if ((float)$line['stock_qty'] < $need) {
            throw new RuntimeException('Insufficient ' . $line['material_name'] . ' (need ' . $need . ' ' . $line['unit'] . ').');
        }
        $pdo->prepare('UPDATE raw_materials SET stock_qty = stock_qty - :q WHERE id = :id')
            ->execute(['q' => $need, 'id' => (int)$line['raw_material_id']]);
        production_log($pdo, $orderId, null, 'inventory_deduct', 'Deducted ' . $need . ' ' . $line['unit'] . ' ' . $line['material_name'], null);
    }
}

function production_workflow_dashboard(PDO $pdo): array
{
    $activeOrders = (int)$pdo->query(
        "SELECT COUNT(*) FROM production_orders WHERE status IN ('Pending','In Progress')"
    )->fetchColumn();
    $qcPending = (int)$pdo->query(
        "SELECT COUNT(*) FROM production_orders WHERE status = 'QC Pending'"
    )->fetchColumn();
    $todayProd = (int)$pdo->query(
        "SELECT COALESCE(SUM(total_produced),0) FROM production_orders WHERE DATE(updated_at) = CURDATE()"
    )->fetchColumn();
    $todayRej = (int)$pdo->query(
        "SELECT COALESCE(SUM(total_rejected),0) FROM production_orders WHERE DATE(updated_at) = CURDATE()"
    )->fetchColumn();
    $todayDown = (int)$pdo->query(
        'SELECT COALESCE(SUM(minutes),0) FROM production_downtime WHERE DATE(logged_at) = CURDATE()'
    )->fetchColumn();

    $runningMachines = 0;
    foreach (production_list_machines($pdo) as $m) {
        if (($m['status'] ?? '') === MACHINE_STATUS_RUNNING) {
            $runningMachines++;
        }
    }

    return [
        'active_orders' => $activeOrders,
        'running_machines' => $runningMachines,
        'qc_pending' => $qcPending,
        'today_produced' => $todayProd,
        'today_rejected' => $todayRej,
        'today_downtime' => $todayDown,
        'running_orders' => $pdo->query(
            "SELECT * FROM production_orders WHERE status IN ('Pending','In Progress') ORDER BY FIELD(priority,'Urgent','High','Normal','Low'), id DESC LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'recent_logs' => production_recent_logs($pdo, 12),
    ];
}

/** @return list<array<string, mixed>> */
function production_recent_logs(PDO $pdo, int $limit): array
{
    $st = $pdo->query(
        'SELECT l.*, o.order_code, u.full_name AS user_name FROM production_logs l
         INNER JOIN production_orders o ON o.id = l.order_id
         LEFT JOIN users u ON u.id = l.user_id
         ORDER BY l.id DESC LIMIT ' . max(1, min(100, $limit))
    );

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function production_order_status_badge(string $status): array
{
    return match ($status) {
        PROD_ORDER_IN_PROGRESS => ['class' => 'prod-badge prod-badge--run', 'label' => 'In Progress'],
        PROD_ORDER_QC_PENDING => ['class' => 'prod-badge prod-badge--maint', 'label' => 'QC Pending'],
        PROD_ORDER_FINISHED => ['class' => 'prod-badge prod-badge--run', 'label' => 'Finished'],
        PROD_ORDER_CANCELLED => ['class' => 'prod-badge prod-badge--down', 'label' => 'Cancelled'],
        default => ['class' => 'prod-badge prod-badge--idle', 'label' => $status ?: 'Pending'],
    };
}

function production_stage_status_badge(string $status): array
{
    return match ($status) {
        PROD_STAGE_RUNNING => ['class' => 'pw-badge pw-badge--running', 'label' => 'Running'],
        PROD_STAGE_PAUSED => ['class' => 'pw-badge pw-badge--paused', 'label' => 'Paused'],
        PROD_STAGE_COMPLETED => ['class' => 'pw-badge pw-badge--completed', 'label' => 'Completed'],
        default => ['class' => 'pw-badge pw-badge--pending', 'label' => 'Pending'],
    };
}

/** Whether any shop-floor stage has left Pending (production execution begun). */
function production_has_execution_started(array $stages): bool
{
    foreach ($stages as $s) {
        if (!production_is_shop_stage((string)($s['stage_name'] ?? ''))) {
            continue;
        }
        if (($s['status'] ?? PROD_STAGE_PENDING) !== PROD_STAGE_PENDING) {
            return true;
        }
    }

    return false;
}

/**
 * Display output from active/completed shop stage (not summed across stages).
 *
 * @param array<string, mixed> $order
 * @param list<array<string, mixed>> $stages
 * @return array{produced: int, rejected: int}
 */
function production_order_display_metrics(array $order, array $stages): array
{
    if (($order['status'] ?? '') === PROD_ORDER_FINISHED) {
        return [
            'produced' => (int)($order['qc_passed_qty'] ?? 0),
            'rejected' => (int)($order['total_rejected'] ?? 0) + (int)($order['qc_failed_qty'] ?? 0),
        ];
    }

    $produced = 0;
    $rejected = 0;
    foreach (['Curing', 'Building', 'Mixing'] as $name) {
        $s = production_find_stage_by_name($stages, $name);
        if (!$s) {
            continue;
        }
        $st = (string)($s['status'] ?? '');
        if (in_array($st, [PROD_STAGE_RUNNING, PROD_STAGE_PAUSED, PROD_STAGE_COMPLETED], true)) {
            $produced = max($produced, (int)($s['produced_qty'] ?? 0));
            $rejected = max($rejected, (int)($s['rejected_qty'] ?? 0));
        }
    }

    return ['produced' => $produced, 'rejected' => $rejected];
}

/** Workflow progress through stages (not quantity). */
function production_order_workflow_pct(array $order, array $stages): int
{
    if (($order['status'] ?? '') === PROD_ORDER_FINISHED) {
        return 100;
    }

    $completed = 0;
    foreach (['Mixing', 'Building', 'Curing'] as $name) {
        $s = production_find_stage_by_name($stages, $name);
        if ($s && ($s['status'] ?? '') === PROD_STAGE_COMPLETED) {
            $completed++;
        }
    }
    $pct = $completed * 20;
    if (($order['status'] ?? '') === PROD_ORDER_QC_PENDING) {
        $pct = max($pct, 80);
    }

    return min(100, $pct);
}

/** Quantity progress vs target. */
function production_order_quantity_pct(array $order, array $stages): int
{
    $target = max(1, (int)($order['target_qty'] ?? 0));
    $m = production_order_display_metrics($order, $stages);

    return min(100, max(0, (int)round(($m['produced'] / $target) * 100)));
}

/** @deprecated Use production_order_quantity_pct */
function production_order_completion_pct(array $order, array $stages = []): int
{
    return $stages !== [] ? production_order_quantity_pct($order, $stages) : 0;
}

/** Central stage lock rules — previous stage must complete before next starts. */
function production_stage_is_locked(string $stageName, array $stage, array $order, array $stages): bool
{
    $orderStatus = (string)($order['status'] ?? '');
    $status = (string)($stage['status'] ?? PROD_STAGE_PENDING);

    if ($stageName === 'Finished') {
        return $orderStatus !== PROD_ORDER_FINISHED;
    }

    if ($stageName === 'QC') {
        if ($orderStatus === PROD_ORDER_FINISHED) {
            return false;
        }
        if ($orderStatus === PROD_ORDER_QC_PENDING) {
            return false;
        }
        $curing = production_find_stage_by_name($stages, 'Curing');

        return !$curing || ($curing['status'] ?? '') !== PROD_STAGE_COMPLETED;
    }

    if (production_is_shop_stage($stageName)) {
        if (in_array($orderStatus, [PROD_ORDER_QC_PENDING, PROD_ORDER_FINISHED], true)) {
            return true;
        }
        if ($status === PROD_STAGE_COMPLETED) {
            return false;
        }

        return !production_previous_stage_completed($stageName, $stages);
    }

    return false;
}

/** @return array<string, mixed>|null */
function production_find_stage_by_name(array $stages, string $name): ?array
{
    foreach ($stages as $s) {
        if (($s['stage_name'] ?? '') === $name) {
            return $s;
        }
    }

    return null;
}

function production_previous_stage_name(string $stageName): ?string
{
    $names = production_stage_names();
    $idx = array_search($stageName, $names, true);
    if ($idx === false || $idx < 1) {
        return null;
    }

    return $names[$idx - 1];
}

function production_is_shop_stage(string $name): bool
{
    return in_array($name, ['Mixing', 'Building', 'Curing'], true);
}

/** Whether the stage before this one (in flow) is completed. */
function production_previous_stage_completed(string $stageName, array $stages): bool
{
    $prev = production_previous_stage_name($stageName);
    if ($prev === null) {
        return true;
    }
    $ps = production_find_stage_by_name($stages, $prev);

    return $ps !== null && ($ps['status'] ?? '') === PROD_STAGE_COMPLETED;
}

/**
 * Visual + permission metadata for workflow UI.
 *
 * @param array<string, mixed> $stage
 * @param array<string, mixed> $order
 * @param list<array<string, mixed>> $stages
 * @return array<string, mixed>
 */
function production_stage_visual_meta(array $stage, array $order, array $stages): array
{
    $name = (string)($stage['stage_name'] ?? '');
    $status = (string)($stage['status'] ?? PROD_STAGE_PENDING);
    $rejected = (int)($stage['rejected_qty'] ?? 0);
    $orderStatus = (string)($order['status'] ?? '');
    $isLocked = production_stage_is_locked($name, $stage, $order, $stages);
    $isCurrent = ($order['current_stage'] ?? '') === $name
        || ($status === PROD_STAGE_RUNNING)
        || ($status === PROD_STAGE_PAUSED);

    $visual = 'pending';
    if ($isLocked && $status !== PROD_STAGE_COMPLETED && $name !== 'QC') {
        $visual = 'locked';
    } elseif ($isLocked && $name === 'QC' && $orderStatus !== PROD_ORDER_QC_PENDING && $orderStatus !== PROD_ORDER_FINISHED) {
        $visual = 'locked';
    } elseif ($rejected > 0 && $status === PROD_STAGE_COMPLETED) {
        $visual = 'rejected';
    } elseif ($status === PROD_STAGE_COMPLETED || ($name === 'Finished' && $orderStatus === PROD_ORDER_FINISHED)) {
        $visual = 'completed';
    } elseif ($status === PROD_STAGE_RUNNING || ($name === 'QC' && $orderStatus === PROD_ORDER_QC_PENDING)) {
        $visual = 'running';
    } elseif ($status === PROD_STAGE_PAUSED) {
        $visual = 'paused';
    } elseif ($name === 'QC' && $orderStatus === PROD_ORDER_FINISHED) {
        $visual = 'completed';
    } elseif ($name === 'QC' && !$isLocked && $status === PROD_STAGE_PENDING) {
        $visual = 'pending';
    }

    $icon = match ($visual) {
        'completed' => 'bi-check-lg',
        'running' => 'bi-arrow-repeat',
        'rejected' => 'bi-x-lg',
        'paused' => 'bi-pause-fill',
        'locked' => 'bi-lock-fill',
        default => 'bi-hourglass',
    };

    $label = match ($visual) {
        'completed' => 'Completed',
        'running' => $name === 'QC' && $orderStatus === PROD_ORDER_QC_PENDING ? 'At QC' : 'Running',
        'rejected' => 'Rejected',
        'paused' => 'Paused',
        'locked' => 'Locked',
        default => 'Pending',
    };

    $canStart = !$isLocked
        && production_is_shop_stage($name)
        && $status === PROD_STAGE_PENDING
        && production_previous_stage_completed($name, $stages);
    $canPause = !$isLocked && $status === PROD_STAGE_RUNNING;
    $canComplete = !$isLocked
        && production_is_shop_stage($name)
        && in_array($status, [PROD_STAGE_RUNNING, PROD_STAGE_PAUSED], true);
    $canSaveFields = !$isLocked
        && production_is_shop_stage($name)
        && in_array($status, [PROD_STAGE_RUNNING, PROD_STAGE_PAUSED], true);
    $qcReady = $name === 'QC'
        && !$isLocked
        && $orderStatus !== PROD_ORDER_QC_PENDING
        && $orderStatus !== PROD_ORDER_FINISHED;

    return [
        'visual' => $visual,
        'icon' => $icon,
        'pill_class' => 'pw-pill pw-pill--' . $visual,
        'badge_class' => 'pw-badge pw-badge--' . $visual,
        'label' => $label,
        'is_current' => $isCurrent,
        'can_start' => $canStart,
        'can_pause' => $canPause,
        'can_complete' => $canComplete,
        'can_save_fields' => $canSaveFields,
        'locked' => $isLocked && $visual === 'locked',
        'qc_ready' => $qcReady,
    ];
}

/** Orders with nested stages for workflow board. */
function production_list_board_orders(PDO $pdo, ?string $statusFilter = null, int $limit = 60): array
{
    $orders = production_list_orders($pdo, $statusFilter, $limit);
    if ($orders === []) {
        return [];
    }
    $ids = array_map(static fn(array $o): int => (int)$o['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare(
        "SELECT s.*, m.machine_code, m.machine_name, m.status AS machine_status,
                e.full_name AS operator_name
         FROM production_stages s
         LEFT JOIN machines m ON m.id = s.machine_id
         LEFT JOIN employees e ON e.id = s.operator_id
         WHERE s.order_id IN ($placeholders)
         ORDER BY s.order_id, s.stage_order ASC"
    );
    $st->execute($ids);
    $byOrder = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $byOrder[(int)$row['order_id']][] = $row;
    }
    foreach ($orders as &$o) {
        $o['stages'] = $byOrder[(int)$o['id']] ?? [];
        $o['workflow_pct'] = production_order_workflow_pct($o, $o['stages']);
        $o['completion_pct'] = production_order_quantity_pct($o, $o['stages']);
        $m = production_order_display_metrics($o, $o['stages']);
        $o['display_produced'] = $m['produced'];
        $o['display_rejected'] = $m['rejected'];
        $o['execution_started'] = production_has_execution_started($o['stages']);
    }
    unset($o);

    return $orders;
}

function production_format_workflow_time(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);

    return $ts ? date('g:i A', $ts) : '—';
}

/** Stage workflow action: start | pause | complete */
function production_stage_action(PDO $pdo, int $stageId, string $action, array $fields, ?int $userId = null): void
{
    $st = $pdo->prepare(
        'SELECT s.*, o.status AS order_status, o.order_code FROM production_stages s
         INNER JOIN production_orders o ON o.id = s.order_id WHERE s.id = :id'
    );
    $st->execute(['id' => $stageId]);
    $stage = $st->fetch(PDO::FETCH_ASSOC);
    if (!$stage) {
        throw new RuntimeException('Stage not found.');
    }

    $bundle = production_fetch_order($pdo, (int)$stage['order_id']);
    if (!$bundle) {
        throw new RuntimeException('Order not found.');
    }

    $stageName = (string)$stage['stage_name'];
    if (!production_is_shop_stage($stageName)) {
        throw new RuntimeException('Use workflow buttons only on Mixing, Building, or Curing.');
    }

    $allStages = $bundle['stages'];
    $data = [
        'machine_id' => (int)($fields['machine_id'] ?? $stage['machine_id'] ?? 0),
        'operator_id' => (int)($fields['operator_id'] ?? $stage['operator_id'] ?? 0),
        'shift' => (string)($fields['shift'] ?? $stage['shift'] ?? 'Morning'),
        'produced_qty' => (int)($fields['produced_qty'] ?? $stage['produced_qty'] ?? 0),
        'rejected_qty' => (int)($fields['rejected_qty'] ?? $stage['rejected_qty'] ?? 0),
        'downtime_minutes' => (int)($fields['downtime_minutes'] ?? $stage['downtime_minutes'] ?? 0),
        'remarks' => (string)($fields['remarks'] ?? $stage['remarks'] ?? ''),
    ];

    if ($action === 'start') {
        if (!production_previous_stage_completed($stageName, $allStages)) {
            throw new RuntimeException('Complete the previous stage before starting ' . $stageName . '.');
        }
        if ($data['machine_id'] < 1 || $data['operator_id'] < 1) {
            throw new RuntimeException('Assign machine and operator before starting the stage.');
        }
        $data['status'] = PROD_STAGE_RUNNING;
    } elseif ($action === 'pause') {
        if (($stage['status'] ?? '') !== PROD_STAGE_RUNNING) {
            throw new RuntimeException('Only a running stage can be paused.');
        }
        $data['status'] = PROD_STAGE_PAUSED;
    } elseif ($action === 'complete') {
        if (!in_array($stage['status'] ?? '', [PROD_STAGE_RUNNING, PROD_STAGE_PAUSED], true)) {
            throw new RuntimeException('Start the stage before completing it.');
        }
        if ((int)($data['produced_qty'] ?? 0) < 1) {
            throw new RuntimeException('Enter produced quantity before completing ' . $stageName . '.');
        }
        $data['status'] = PROD_STAGE_COMPLETED;
    } else {
        throw new InvalidArgumentException('Unknown stage action.');
    }

    production_update_stage($pdo, $stageId, $data, $userId, true);
}

/** @return list<array<string, mixed>> */
function production_fetch_timeline(PDO $pdo, int $orderId): array
{
    $logSt = $pdo->prepare(
        'SELECT created_at, action, message FROM production_logs WHERE order_id = :id ORDER BY id DESC LIMIT 80'
    );
    $logSt->execute(['id' => $orderId]);
    $items = [];
    foreach ($logSt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $items[] = [
            'at' => $row['created_at'],
            'title' => ucfirst(str_replace('_', ' ', (string)$row['action'])),
            'message' => (string)$row['message'],
        ];
    }

    return $items;
}

/** Shift-wise qty summary for an order. @return array<string, array{produced: int, rejected: int}> */
function production_order_shift_summary(array $stages): array
{
    $out = ['Morning' => ['produced' => 0, 'rejected' => 0], 'Evening' => ['produced' => 0, 'rejected' => 0], 'Night' => ['produced' => 0, 'rejected' => 0]];
    foreach ($stages as $s) {
        if (!production_is_shop_stage((string)($s['stage_name'] ?? ''))) {
            continue;
        }
        $sh = (string)($s['shift'] ?? 'Morning');
        if (!isset($out[$sh])) {
            $out[$sh] = ['produced' => 0, 'rejected' => 0];
        }
        $out[$sh]['produced'] += (int)($s['produced_qty'] ?? 0);
        $out[$sh]['rejected'] += (int)($s['rejected_qty'] ?? 0);
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function production_report_stage_wise(PDO $pdo, string $from, string $to): array
{
    $st = $pdo->prepare(
        "SELECT s.stage_name,
         COALESCE(SUM(s.produced_qty),0) AS produced,
         COALESCE(SUM(s.rejected_qty),0) AS rejected,
         COALESCE(SUM(s.downtime_minutes),0) AS downtime
         FROM production_stages s
         INNER JOIN production_orders o ON o.id = s.order_id
         WHERE s.stage_name IN ('Mixing','Building','Curing','QC')
           AND DATE(o.updated_at) BETWEEN :f AND :t
         GROUP BY s.stage_name ORDER BY FIELD(s.stage_name,'Mixing','Building','Curing','QC')"
    );
    $st->execute(['f' => $from, 't' => $to]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Seed default BOM if empty. */
function production_seed_default_bom(PDO $pdo): void
{
    $cnt = (int)$pdo->query('SELECT COUNT(*) FROM production_bom')->fetchColumn();
    if ($cnt > 0) {
        return;
    }
    $materials = $pdo->query('SELECT id, material_name FROM raw_materials LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    if (!$materials) {
        return;
    }
    $defaults = [
        'TBR Truck' => [0.5, 0.2, 0.1],
        'PCR Car' => [0.4, 0.15, 0.08],
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO production_bom (tyre_type, raw_material_id, qty_per_unit) VALUES (:t,:r,:q)');
    foreach ($defaults as $tyre => $qtys) {
        foreach ($qtys as $i => $q) {
            if (!isset($materials[$i])) {
                break;
            }
            $ins->execute(['t' => $tyre, 'r' => (int)$materials[$i]['id'], 'q' => $q]);
        }
    }
}
