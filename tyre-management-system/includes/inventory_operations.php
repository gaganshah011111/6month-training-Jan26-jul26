<?php
declare(strict_types=1);

const INV_ADJUST_REASONS = [
    'counting_correction' => 'Physical count correction',
    'damaged_stock' => 'Damaged stock',
    'wastage' => 'Wastage',
    'scrap_removal' => 'Scrap removal',
    'other' => 'Other',
];

const INV_ISSUE_REASONS = [
    'damaged' => 'Damaged stock',
    'rejected' => 'Rejected material',
    'scrap' => 'Scrap / waste',
    'wastage' => 'Wastage',
];

function inv_current_user_name(): string
{
    $u = function_exists('current_user') ? current_user() : null;

    return trim((string)($u['full_name'] ?? $u['username'] ?? 'System'));
}

function inv_usage_reason_label(string $code): string
{
    return INV_ISSUE_REASONS[$code] ?? INV_ADJUST_REASONS[$code] ?? ucfirst(str_replace('_', ' ', $code));
}

/** @return list<array<string, mixed>> */
function inv_material_history(PDO $pdo, int $materialId, int $limit = 80): array
{
    $rows = [];

    $in = $pdo->prepare(
        "SELECT i.inward_date AS dt, i.created_at AS ts, 'Added' AS txn_type, i.quantity AS qty,
            i.received_by AS operator_name, i.remarks, i.batch_no, NULL AS department
         FROM stock_inward i WHERE i.material_id = :id"
    );
    $in->execute(['id' => $materialId]);
    foreach ($in->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $r['qty_signed'] = (float)$r['qty'];
        $rows[] = $r;
    }

    $us = $pdo->prepare(
        "SELECT u.usage_date AS dt, u.created_at AS ts,
            CASE WHEN u.usage_reason IN ('damaged','rejected','scrap','wastage') THEN 'Issued' ELSE 'Used' END AS txn_type,
            u.quantity AS qty, u.created_by AS operator_name, u.remarks, u.department, u.usage_reason
         FROM stock_usage u WHERE u.material_id = :id"
    );
    $us->execute(['id' => $materialId]);
    foreach ($us->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $r['qty_signed'] = -(float)$r['qty'];
        $rows[] = $r;
    }

    if (inv_table_exists($pdo, 'stock_adjustments')) {
        $adj = $pdo->prepare(
            "SELECT a.adjust_date AS dt, a.created_at AS ts, 'Adjusted' AS txn_type,
                a.difference_qty AS qty_signed, a.actual_qty, a.previous_qty, a.reason, a.operator_name, a.remarks
             FROM stock_adjustments a WHERE a.material_id = :id"
        );
        $adj->execute(['id' => $materialId]);
        foreach ($adj->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $r['qty'] = abs((float)$r['qty_signed']);
            $rows[] = $r;
        }
    }

    usort($rows, static fn($a, $b) => strcmp((string)($b['ts'] ?? $b['dt']), (string)($a['ts'] ?? $a['dt'])));

    return array_slice($rows, 0, $limit);
}

function inv_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
    $st->execute(['t' => $table]);

    return (int)$st->fetchColumn() > 0;
}

function inv_save_stock_adjustment(PDO $pdo, array $data): int
{
    $materialId = (int)($data['material_id'] ?? 0);
    $actual = (float)($data['actual_stock'] ?? -1);
    $reason = trim((string)($data['reason'] ?? ''));
    $date = (string)($data['adjust_date'] ?? date('Y-m-d'));

    if ($materialId < 1 || $actual < 0) {
        throw new InvalidArgumentException('Material and actual stock are required.');
    }
    if ($reason === '' || !isset(INV_ADJUST_REASONS[$reason])) {
        throw new InvalidArgumentException('Select a valid adjustment reason.');
    }

    $mat = inv_get_material_row($pdo, $materialId);
    if (!$mat) {
        throw new InvalidArgumentException('Material not found.');
    }

    $previous = (float)$mat['stock_qty'];
    $diff = round($actual - $previous, 2);
    if (abs($diff) < 0.0001) {
        throw new InvalidArgumentException('Actual stock matches system stock — no adjustment needed.');
    }

    $operator = inv_current_user_name();
    $remarks = trim((string)($data['remarks'] ?? ''));

    $pdo->prepare(
        'INSERT INTO stock_adjustments (adjust_date, material_id, previous_qty, actual_qty, difference_qty, reason, operator_name, remarks)
         VALUES (:d,:mid,:pq,:aq,:df,:rs,:op,:rm)'
    )->execute([
        'd' => $date,
        'mid' => $materialId,
        'pq' => $previous,
        'aq' => $actual,
        'df' => $diff,
        'rs' => $reason,
        'op' => $operator,
        'rm' => $remarks ?: null,
    ]);
    $adjId = (int)$pdo->lastInsertId();

    $label = INV_ADJUST_REASONS[$reason];
    if ($diff > 0) {
        inv_increase_stock($pdo, $materialId, $diff, 'adjustment', 'Stock adjusted +' . $diff . ' ' . $mat['unit'] . ' (' . $label . ')');
    } else {
        inv_decrease_stock($pdo, $materialId, abs($diff), 'adjustment', 'Stock adjusted ' . $diff . ' ' . $mat['unit'] . ' (' . $label . ')');
    }

    return $adjId;
}

/** @return list<array{department: string, total: float, unit: string, label: string}> */
function inv_today_dept_summary(PDO $pdo): array
{
    $today = date('Y-m-d');
    $items = [];

    $st = $pdo->prepare(
        "SELECT u.department, SUM(u.quantity) AS total, rm.unit
         FROM stock_usage u
         JOIN raw_materials rm ON rm.id = u.material_id
         WHERE u.usage_date = :d
         GROUP BY u.department, rm.unit
         ORDER BY total DESC"
    );
    $st->execute(['d' => $today]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $dept = (string)($r['department'] ?? 'Other');
        $items[] = [
            'department' => $dept,
            'total' => (float)$r['total'],
            'unit' => (string)$r['unit'],
            'label' => $dept . ' used ' . inv_format_qty((float)$r['total'], (string)$r['unit']),
        ];
    }

    try {
        $qc = $pdo->prepare('SELECT COALESCE(SUM(failed_qty),0) FROM qc_entries WHERE entry_date = :d');
        $qc->execute(['d' => $today]);
        $failed = (int)$qc->fetchColumn();
        if ($failed > 0) {
            $items[] = [
                'department' => 'QC',
                'total' => (float)$failed,
                'unit' => 'tyres',
                'label' => 'QC rejected ' . $failed . ' tyres',
            ];
        }
    } catch (Throwable) {
    }

    return $items;
}

/** @return list<array{message: string, code: string, material_name: string}> */
function inv_low_stock_alert_messages(PDO $pdo, int $limit = 8): array
{
    $rows = $pdo->query(
        "SELECT material_name, stock_qty, reorder_level, unit
         FROM raw_materials
         WHERE status = 'Active' AND reorder_level > 0 AND stock_qty <= reorder_level
         ORDER BY stock_qty ASC LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'material_name' => (string)$r['material_name'],
            'code' => (float)$r['stock_qty'] <= 0 ? 'out' : 'low',
            'message' => (float)$r['stock_qty'] <= 0
                ? (string)$r['material_name'] . ' is out of stock. Production may stop soon.'
                : (string)$r['material_name'] . ' is low (' . inv_format_qty((float)$r['stock_qty'], (string)$r['unit']) . ' left).',
        ];
    }

    return $out;
}

function inv_render_low_stock_banner(PDO $pdo): void
{
    $alerts = inv_low_stock_alert_messages($pdo, 5);
    if ($alerts === []) {
        return;
    }
    echo '<div class="inv-prod-alert" role="alert">';
    echo '<div class="inv-prod-alert__title">⚠ Inventory — low stock</div><ul class="inv-prod-alert__list">';
    foreach ($alerts as $a) {
        echo '<li>' . htmlspecialchars($a['message'], ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul></div>';
}

/** @return list<array<string, mixed>> */
function inv_expiring_batches(PDO $pdo, int $withinDays = 45): array
{
    if (!inv_table_exists($pdo, 'stock_inward')) {
        return [];
    }
    $st = $pdo->prepare(
        "SELECT i.expiry_date, i.batch_no, i.quantity, rm.material_name, rm.unit
         FROM stock_inward i
         JOIN raw_materials rm ON rm.id = i.material_id
         WHERE i.expiry_date IS NOT NULL
           AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :d DAY)
           AND i.expiry_date >= CURDATE()
         ORDER BY i.expiry_date ASC LIMIT 10"
    );
    $st->execute(['d' => $withinDays]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function inv_search_materials_master(PDO $pdo, string $search, string $filter): array
{
    $sql = 'SELECT rm.* FROM raw_materials rm WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (rm.material_name LIKE :q OR rm.material_code LIKE :q OR rm.storage_location LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }

    if ($filter === 'low') {
        $sql .= " AND rm.status = 'Active' AND rm.reorder_level > 0 AND rm.stock_qty > 0 AND rm.stock_qty <= rm.reorder_level";
    } elseif ($filter === 'out') {
        $sql .= " AND rm.status = 'Active' AND rm.stock_qty <= 0";
    } elseif ($filter === 'recent') {
        $sql .= ' AND rm.id IN (SELECT DISTINCT material_id FROM stock_usage WHERE usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))';
    }

    $sql .= ' ORDER BY rm.material_name ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function inv_report_transactions(PDO $pdo, string $from, string $to, int $materialId): array
{
    $all = [];
    if ($materialId > 0) {
        foreach (inv_material_history($pdo, $materialId, 500) as $h) {
            $dt = (string)($h['dt'] ?? '');
            if ($dt >= $from && $dt <= $to) {
                $all[] = $h;
            }
        }

        return $all;
    }

    $ids = $pdo->query("SELECT id FROM raw_materials WHERE status = 'Active'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($ids as $mid) {
        foreach (inv_material_history($pdo, (int)$mid, 30) as $h) {
            $dt = (string)($h['dt'] ?? '');
            if ($dt >= $from && $dt <= $to) {
                $all[] = $h;
            }
        }
    }
    usort($all, static fn($a, $b) => strcmp((string)($b['dt'] ?? ''), (string)($a['dt'] ?? '')));

    return array_slice($all, 0, 300);
}
