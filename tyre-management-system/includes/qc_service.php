<?php
declare(strict_types=1);

require_once __DIR__ . '/production_service.php';

const QC_STATUS_PENDING = 'Pending';
const QC_STATUS_INSPECTING = 'Inspecting';
const QC_STATUS_PASSED = 'Passed';
const QC_STATUS_PARTIAL = 'Partial';
const QC_STATUS_REJECTED = 'Rejected';
const QC_STATUS_REWORK = 'Rework';

const QC_STOCK_DISPATCH = 'dispatch_ready';
const QC_STOCK_REWORK = 'rework';
const QC_STOCK_SCRAP = 'scrap';

/** @var array<string, string> */
const QC_DEFECT_TYPES = [
    'bubble' => 'Bubble',
    'crack' => 'Crack',
    'sidewall' => 'Sidewall defect',
    'tread_cut' => 'Tread cut',
    'air_leak' => 'Air leak',
    'shape' => 'Shape issue',
    'bead' => 'Bead damage',
    'other' => 'Other',
];

function qc_current_inspector_default(): string
{
    $u = function_exists('current_user') ? current_user() : null;

    return trim((string)($u['full_name'] ?? $u['username'] ?? ''));
}

function qc_dispatch_stock_sql(): string
{
    return "(stock_category IS NULL OR stock_category = '" . QC_STOCK_DISPATCH . "')";
}

function qc_format_qty(int $qty): string
{
    return number_format($qty, 0, '.', ',');
}

function qc_status_badge(string $status): string
{
    return match ($status) {
        QC_STATUS_PASSED => 'passed',
        QC_STATUS_PARTIAL => 'partial',
        QC_STATUS_REJECTED => 'rejected',
        QC_STATUS_REWORK => 'rework',
        QC_STATUS_INSPECTING => 'inspecting',
        default => 'pending',
    };
}

function qc_batch_code_for_curing(int $curingId, string $dateYmd): string
{
    return 'CUR-' . str_replace('-', '', $dateYmd) . '-' . str_pad((string)$curingId, 4, '0', STR_PAD_LEFT);
}

/** Mark curing output ready for QC after production save. */
function qc_queue_curing_batch(PDO $pdo, int $curingEntryId): void
{
    $st = $pdo->prepare('SELECT id, production_date, produced_qty FROM curing_entries WHERE id = :id');
    $st->execute(['id' => $curingEntryId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }
    $code = qc_batch_code_for_curing($curingEntryId, (string)$row['production_date']);
    $pdo->prepare(
        "UPDATE curing_entries SET batch_code = :c, qc_status = :st WHERE id = :id"
    )->execute(['c' => $code, 'st' => QC_STATUS_PENDING, 'id' => $curingEntryId]);
}

/** @return array<string, mixed>|null */
function qc_get_curing_batch(PDO $pdo, int $curingId): ?array
{
    $st = $pdo->prepare(
        "SELECT c.*, m.machine_code, m.machine_name
         FROM curing_entries c
         LEFT JOIN machines m ON m.id = c.machine_id
         WHERE c.id = :id"
    );
    $st->execute(['id' => $curingId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function qc_list_pending_batches(PDO $pdo, int $limit = 100): array
{
    $sql = "SELECT c.id, c.batch_code, c.tyre_type, c.production_date, c.shift,
            c.produced_qty, c.rejected_qty, c.qc_status, c.created_at,
            m.machine_code
            FROM curing_entries c
            LEFT JOIN machines m ON m.id = c.machine_id
            WHERE c.qc_status IN ('Pending','Inspecting')
            AND c.produced_qty > 0
            ORDER BY c.production_date DESC, c.id DESC
            LIMIT " . max(1, min(500, $limit));

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function qc_start_inspection(PDO $pdo, int $curingId): void
{
    $batch = qc_get_curing_batch($pdo, $curingId);
    if (!$batch) {
        throw new InvalidArgumentException('Curing batch not found.');
    }
    if (!in_array((string)($batch['qc_status'] ?? ''), [QC_STATUS_PENDING, QC_STATUS_INSPECTING], true)) {
        throw new InvalidArgumentException('This batch is not available for inspection.');
    }
    $pdo->prepare("UPDATE curing_entries SET qc_status = :st WHERE id = :id")
        ->execute(['st' => QC_STATUS_INSPECTING, 'id' => $curingId]);
}

/** @return array<string, mixed>|null */
function qc_get_inspection_by_curing(PDO $pdo, int $curingId): ?array
{
    $st = $pdo->prepare('SELECT * FROM qc_inspections WHERE curing_entry_id = :id LIMIT 1');
    $st->execute(['id' => $curingId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['defect_lines'] = qc_defect_lines_for_inspection($pdo, (int)$row['id']);
    }

    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function qc_defect_lines_for_inspection(PDO $pdo, int $inspectionId): array
{
    $st = $pdo->prepare('SELECT * FROM qc_defect_lines WHERE inspection_id = :id ORDER BY id ASC');
    $st->execute(['id' => $inspectionId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param array<string, mixed> $data
 * @param list<array{defect_type: string, qty: int}> $defectLines
 */
function qc_save_inspection(PDO $pdo, int $curingId, array $data, array $defectLines, string $finalAction): int
{
    $batch = qc_get_curing_batch($pdo, $curingId);
    if (!$batch) {
        throw new InvalidArgumentException('Curing batch not found.');
    }

    $produced = (int)$batch['produced_qty'];
    $inspected = max(0, (int)($data['inspected_qty'] ?? 0));
    $passed = max(0, (int)($data['passed_qty'] ?? 0));
    $rejected = max(0, (int)($data['rejected_qty'] ?? 0));
    $rework = max(0, (int)($data['rework_qty'] ?? 0));
    $inspector = trim((string)($data['inspector_name'] ?? ''));
    $inspDate = (string)($data['inspection_date'] ?? date('Y-m-d'));
    $shift = in_array($data['shift'] ?? '', PRODUCTION_SHIFTS, true) ? $data['shift'] : (string)$batch['shift'];

    if ($inspector === '') {
        throw new InvalidArgumentException('Inspector name is required.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inspDate)) {
        throw new InvalidArgumentException('Valid inspection date is required.');
    }
    if ($inspected < 1) {
        throw new InvalidArgumentException('Inspected quantity must be at least 1.');
    }
    if ($inspected > $produced) {
        throw new InvalidArgumentException('Inspected quantity cannot exceed produced quantity (' . $produced . ').');
    }
    if ($passed + $rejected + $rework > $inspected) {
        throw new InvalidArgumentException('Pass + Reject + Rework cannot exceed inspected quantity.');
    }

    $qcStatus = match ($finalAction) {
        'rework' => QC_STATUS_REWORK,
        'approve' => ($rejected + $rework) > 0 && $passed > 0 ? QC_STATUS_PARTIAL : (($passed > 0) ? QC_STATUS_PASSED : QC_STATUS_REJECTED),
        default => ($rejected + $rework) > 0 && $passed > 0 ? QC_STATUS_PARTIAL : (($passed > 0) ? QC_STATUS_PASSED : QC_STATUS_REJECTED),
    };

    if ($finalAction === 'rework' && $rework < 1 && $rejected < 1) {
        throw new InvalidArgumentException('Enter rework or reject quantity to send batch to rework.');
    }

    $batchCode = (string)($batch['batch_code'] ?? qc_batch_code_for_curing($curingId, (string)$batch['production_date']));
    $tyreType = trim((string)$batch['tyre_type']);
    $remarks = trim((string)($data['remarks'] ?? '')) ?: null;

    if (!in_array((string)($batch['qc_status'] ?? ''), [QC_STATUS_PENDING, QC_STATUS_INSPECTING], true)) {
        throw new InvalidArgumentException('This batch has already been inspected.');
    }

    $pdo->beginTransaction();
    try {
        $existing = qc_get_inspection_by_curing($pdo, $curingId);
        if ($existing) {
            qc_rollback_batch_inventory($pdo, $batchCode);
            $inspectionId = (int)$existing['id'];
            $pdo->prepare(
                'UPDATE qc_inspections SET inspected_qty=:iq, passed_qty=:pq, rejected_qty=:rq, rework_qty=:wq,
                 inspector_name=:ins, inspection_date=:dt, inspection_shift=:sh, qc_status=:st, remarks=:rm
                 WHERE id=:id'
            )->execute([
                'iq' => $inspected,
                'pq' => $passed,
                'rq' => $rejected,
                'wq' => $rework,
                'ins' => $inspector,
                'dt' => $inspDate,
                'sh' => $shift,
                'st' => $qcStatus,
                'rm' => $remarks,
                'id' => $inspectionId,
            ]);
            $pdo->prepare('DELETE FROM qc_defect_lines WHERE inspection_id = :id')->execute(['id' => $inspectionId]);
        } else {
            $pdo->prepare(
                'INSERT INTO qc_inspections (
                    curing_entry_id, batch_code, tyre_type, machine_id, production_date, production_shift,
                    produced_qty, inspected_qty, passed_qty, rejected_qty, rework_qty,
                    inspector_name, inspection_date, inspection_shift, qc_status, remarks
                ) VALUES (
                    :cid, :bc, :tt, :mid, :pd, :ps, :prod, :iq, :pq, :rq, :wq,
                    :ins, :idt, :ish, :st, :rm
                )'
            )->execute([
                'cid' => $curingId,
                'bc' => $batchCode,
                'tt' => $tyreType,
                'mid' => (int)($batch['machine_id'] ?? 0) ?: null,
                'pd' => $batch['production_date'],
                'ps' => $batch['shift'],
                'prod' => $produced,
                'iq' => $inspected,
                'pq' => $passed,
                'rq' => $rejected,
                'wq' => $rework,
                'ins' => $inspector,
                'idt' => $inspDate,
                'ish' => $shift,
                'st' => $qcStatus,
                'rm' => $remarks,
            ]);
            $inspectionId = (int)$pdo->lastInsertId();
        }

        foreach ($defectLines as $line) {
            $dtype = (string)($line['defect_type'] ?? '');
            $dq = max(0, (int)($line['qty'] ?? 0));
            if ($dtype === '' || $dq < 1 || !isset(QC_DEFECT_TYPES[$dtype])) {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO qc_defect_lines (inspection_id, defect_type, defect_label, qty) VALUES (:i,:t,:l,:q)'
            )->execute([
                'i' => $inspectionId,
                't' => $dtype,
                'l' => QC_DEFECT_TYPES[$dtype],
                'q' => $dq,
            ]);
        }

        $pdo->prepare('UPDATE curing_entries SET qc_status = :st, qc_inspection_id = :qid WHERE id = :id')
            ->execute(['st' => $qcStatus, 'qid' => $inspectionId, 'id' => $curingId]);

        if ($passed > 0) {
            qc_add_inventory($pdo, $tyreType, 'QC-PASS-' . $batchCode, $passed, QC_STOCK_DISPATCH);
        }
        if ($rework > 0) {
            qc_add_inventory($pdo, $tyreType, 'QC-REWORK-' . $batchCode, $rework, QC_STOCK_REWORK);
        }
        if ($rejected > 0) {
            qc_add_inventory($pdo, $tyreType, 'QC-SCRAP-' . $batchCode, $rejected, QC_STOCK_SCRAP);
        }

        $pdo->commit();

        return $inspectionId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function qc_rollback_batch_inventory(PDO $pdo, string $batchCode): void
{
    foreach (['QC-PASS-', 'QC-REWORK-', 'QC-SCRAP-'] as $prefix) {
        $pdo->prepare('DELETE FROM inventory WHERE batch_ref = :b')->execute(['b' => $prefix . $batchCode]);
    }
}

function qc_add_inventory(PDO $pdo, string $productName, string $batchRef, int $qty, string $stockCategory): void
{
    if ($qty < 1) {
        return;
    }
    $warehouse = match ($stockCategory) {
        QC_STOCK_REWORK => 'QC-REWORK',
        QC_STOCK_SCRAP => 'QC-SCRAP',
        default => 'FG-A1',
    };
    $exists = $pdo->prepare('SELECT id, qty FROM inventory WHERE batch_ref = :b LIMIT 1');
    $exists->execute(['b' => $batchRef]);
    $row = $exists->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare('UPDATE inventory SET qty = qty + :q, stock_category = :sc WHERE id = :id')
            ->execute(['q' => $qty, 'sc' => $stockCategory, 'id' => (int)$row['id']]);
    } else {
        $pdo->prepare(
            'INSERT INTO inventory (product_name, batch_ref, qty, reorder_level, warehouse_location, stock_category)
             VALUES (:n,:b,:q,50,:w,:sc)'
        )->execute([
            'n' => $productName,
            'b' => $batchRef,
            'q' => $qty,
            'w' => $warehouse,
            'sc' => $stockCategory,
        ]);
    }
    if ($stockCategory === QC_STOCK_DISPATCH && dh_table_exists($pdo, 'sales_orders')) {
        require_once __DIR__ . '/sales_service.php';
        sales_on_inventory_changed($pdo, $productName);
    }
}

/** @return array<string, mixed> */
function qc_dashboard(PDO $pdo): array
{
    $pending = (int)$pdo->query(
        "SELECT COUNT(*) FROM curing_entries WHERE qc_status IN ('Pending','Inspecting') AND produced_qty > 0"
    )->fetchColumn();

    $todayInspected = (int)$pdo->query(
        'SELECT COUNT(*) FROM qc_inspections WHERE inspection_date = CURDATE()'
    )->fetchColumn();

    $todayPassed = (int)$pdo->query(
        'SELECT COALESCE(SUM(passed_qty),0) FROM qc_inspections WHERE inspection_date = CURDATE()'
    )->fetchColumn();

    $todayRejected = (int)$pdo->query(
        'SELECT COALESCE(SUM(rejected_qty),0) FROM qc_inspections WHERE inspection_date = CURDATE()'
    )->fetchColumn();

    $reworkPending = (int)$pdo->query(
        "SELECT COALESCE(SUM(qty),0) FROM inventory WHERE stock_category = '" . QC_STOCK_REWORK . "' AND qty > 0"
    )->fetchColumn();

    $inspectedToday = (int)$pdo->query(
        'SELECT COALESCE(SUM(inspected_qty),0) FROM qc_inspections WHERE inspection_date = CURDATE()'
    )->fetchColumn();

    $passPct = $inspectedToday > 0 ? round(($todayPassed / $inspectedToday) * 100, 1) : 0.0;

    $majorDefect = $pdo->query(
        "SELECT defect_label, SUM(qty) AS total
         FROM qc_defect_lines d
         JOIN qc_inspections i ON i.id = d.inspection_id
         WHERE i.inspection_date = CURDATE()
         GROUP BY defect_label ORDER BY total DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC) ?: null;

    $recent = $pdo->query(
        "SELECT i.batch_code, i.tyre_type, i.inspection_date, i.inspected_qty, i.passed_qty,
                i.rejected_qty, i.rework_qty, i.qc_status, i.inspector_name, m.machine_code
         FROM qc_inspections i
         LEFT JOIN machines m ON m.id = i.machine_id
         ORDER BY i.id DESC LIMIT 12"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $defectSummary = $pdo->query(
        "SELECT defect_label, SUM(qty) AS total
         FROM qc_defect_lines d
         JOIN qc_inspections i ON i.id = d.inspection_id
         WHERE i.inspection_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY defect_label ORDER BY total DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $shiftStats = $pdo->query(
        "SELECT inspection_shift AS shift, COUNT(*) AS inspections,
                COALESCE(SUM(passed_qty),0) AS passed, COALESCE(SUM(rejected_qty),0) AS rejected
         FROM qc_inspections WHERE inspection_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY inspection_shift ORDER BY inspections DESC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $machineRejects = $pdo->query(
        "SELECT COALESCE(m.machine_code, CONCAT('M-', i.machine_id)) AS machine_code,
                COALESCE(SUM(i.rejected_qty),0) AS rejected
         FROM qc_inspections i
         LEFT JOIN machines m ON m.id = i.machine_id
         WHERE i.inspection_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY i.machine_id, m.machine_code
         HAVING rejected > 0
         ORDER BY rejected DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $fgDispatch = (int)$pdo->query(
        'SELECT COALESCE(SUM(qty),0) FROM inventory WHERE ' . qc_dispatch_stock_sql() . ' AND qty > 0'
    )->fetchColumn();

    $fgRework = (int)$pdo->query(
        "SELECT COALESCE(SUM(qty),0) FROM inventory WHERE stock_category = '" . QC_STOCK_REWORK . "' AND qty > 0"
    )->fetchColumn();

    $fgScrap = (int)$pdo->query(
        "SELECT COALESCE(SUM(qty),0) FROM inventory WHERE stock_category = '" . QC_STOCK_SCRAP . "' AND qty > 0"
    )->fetchColumn();

    return [
        'pending' => $pending,
        'today_inspected' => $todayInspected,
        'today_passed' => $todayPassed,
        'today_rejected' => $todayRejected,
        'rework_pending' => $reworkPending,
        'pass_pct' => $passPct,
        'major_defect' => $majorDefect,
        'recent' => $recent,
        'defect_summary' => $defectSummary,
        'shift_stats' => $shiftStats,
        'machine_rejects' => $machineRejects,
        'fg_dispatch' => $fgDispatch,
        'fg_rework' => $fgRework,
        'fg_scrap' => $fgScrap,
    ];
}

/** @return array<string, mixed> */
function qc_defect_analytics(PDO $pdo, string $from, string $to): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = date('Y-m-d');
    }

    $defects = $pdo->prepare(
        "SELECT d.defect_label, SUM(d.qty) AS total
         FROM qc_defect_lines d
         JOIN qc_inspections i ON i.id = d.inspection_id
         WHERE i.inspection_date BETWEEN :f AND :t
         GROUP BY d.defect_type, d.defect_label ORDER BY total DESC"
    );
    $defects->execute(['f' => $from, 't' => $to]);
    $topDefects = $defects->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $byMachine = $pdo->prepare(
        "SELECT COALESCE(m.machine_code,'—') AS machine_code, SUM(d.qty) AS defects
         FROM qc_defect_lines d
         JOIN qc_inspections i ON i.id = d.inspection_id
         LEFT JOIN machines m ON m.id = i.machine_id
         WHERE i.inspection_date BETWEEN :f AND :t
         GROUP BY i.machine_id, m.machine_code ORDER BY defects DESC LIMIT 12"
    );
    $byMachine->execute(['f' => $from, 't' => $to]);

    $byShift = $pdo->prepare(
        "SELECT inspection_shift AS shift,
                COALESCE(SUM(inspected_qty),0) AS inspected,
                COALESCE(SUM(rejected_qty),0) AS rejected
         FROM qc_inspections WHERE inspection_date BETWEEN :f AND :t
         GROUP BY inspection_shift"
    );
    $byShift->execute(['f' => $from, 't' => $to]);

    $byTyre = $pdo->prepare(
        "SELECT tyre_type, COALESCE(SUM(inspected_qty),0) AS inspected,
                COALESCE(SUM(rejected_qty),0) AS rejected
         FROM qc_inspections WHERE inspection_date BETWEEN :f AND :t
         GROUP BY tyre_type ORDER BY inspected DESC"
    );
    $byTyre->execute(['f' => $from, 't' => $to]);

    $summary = $pdo->prepare(
        'SELECT COALESCE(SUM(inspected_qty),0) AS inspected,
                COALESCE(SUM(passed_qty),0) AS passed,
                COALESCE(SUM(rejected_qty),0) AS rejected,
                COUNT(*) AS inspections
         FROM qc_inspections WHERE inspection_date BETWEEN :f AND :t'
    );
    $summary->execute(['f' => $from, 't' => $to]);
    $sum = $summary->fetch(PDO::FETCH_ASSOC) ?: [];
    $inspected = (int)($sum['inspected'] ?? 0);
    $rejectPct = $inspected > 0 ? round(((int)($sum['rejected'] ?? 0) / $inspected) * 100, 1) : 0.0;

    return [
        'from' => $from,
        'to' => $to,
        'summary' => $sum,
        'reject_pct' => $rejectPct,
        'top_defects' => $topDefects,
        'by_machine' => $byMachine->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'by_shift' => $byShift->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'by_tyre' => $byTyre->fetchAll(PDO::FETCH_ASSOC) ?: [],
    ];
}

/**
 * @return array{rows: list<array>, summary: array}
 */
function qc_report(PDO $pdo, string $from, string $to, string $reportType, string $shift, int $machineId): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = date('Y-m-d');
    }

    $sql = "SELECT i.*, m.machine_code
            FROM qc_inspections i
            LEFT JOIN machines m ON m.id = i.machine_id
            WHERE i.inspection_date BETWEEN :f AND :t";
    $params = ['f' => $from, 't' => $to];

    if ($shift !== '') {
        $sql .= ' AND i.inspection_shift = :sh';
        $params['sh'] = $shift;
    }
    if ($machineId > 0) {
        $sql .= ' AND i.machine_id = :mid';
        $params['mid'] = $machineId;
    }

    if ($reportType === 'reject') {
        $sql .= ' AND (i.rejected_qty > 0 OR i.rework_qty > 0)';
    }

    $sql .= ' ORDER BY i.inspection_date DESC, i.id DESC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $inspected = 0;
    $passed = 0;
    $rejected = 0;
    foreach ($rows as $r) {
        $inspected += (int)$r['inspected_qty'];
        $passed += (int)$r['passed_qty'];
        $rejected += (int)$r['rejected_qty'];
    }

    return [
        'rows' => $rows,
        'summary' => [
            'count' => count($rows),
            'inspected' => $inspected,
            'passed' => $passed,
            'rejected' => $rejected,
            'pass_pct' => $inspected > 0 ? round(($passed / $inspected) * 100, 1) : 0,
        ],
    ];
}

/** @return list<array<string, mixed>> */
function qc_rework_stock(PDO $pdo): array
{
    return $pdo->query(
        "SELECT product_name, batch_ref, qty, warehouse_location, stock_category, updated_at
         FROM inventory
         WHERE stock_category IN ('" . QC_STOCK_REWORK . "','" . QC_STOCK_SCRAP . "') AND qty > 0
         ORDER BY stock_category, product_name, id DESC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function qc_inspector_stats(PDO $pdo, string $from, string $to): array
{
    $st = $pdo->prepare(
        "SELECT inspector_name, COUNT(*) AS inspections,
                COALESCE(SUM(passed_qty),0) AS passed,
                COALESCE(SUM(rejected_qty),0) AS rejected
         FROM qc_inspections WHERE inspection_date BETWEEN :f AND :t
         GROUP BY inspector_name ORDER BY inspections DESC"
    );
    $st->execute(['f' => $from, 't' => $to]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
