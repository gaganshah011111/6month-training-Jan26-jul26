<?php
declare(strict_types=1);

/** Rule-based production consumption (simple factory ratios). */
const INV_MIXING_RULES = [
    'Natural Rubber' => 0.45,
    'Carbon Black' => 0.25,
    'Chemicals' => 0.05,
];

const INV_BUILDING_RULES = [
    'Fabric' => 1.0,
    'Steel Wire' => 0.5,
];

const INV_CURING_RULES = [
    'Packaging' => 0.1,
];

const INV_CATEGORIES = ['Rubber', 'Fillers', 'Reinforcement', 'Chemicals', 'Packaging', 'General'];

require_once __DIR__ . '/inventory_operations.php';

/** Human-readable quantity (no excessive decimals). */
function inv_format_qty(float $qty, string $unit = ''): string
{
    if ($qty >= 100) {
        $s = number_format($qty, 0, '.', ',');
    } elseif (abs($qty - round($qty)) < 0.001) {
        $s = number_format($qty, 0, '.', ',');
    } else {
        $s = rtrim(rtrim(number_format($qty, 2, '.', ','), '0'), '.');
    }

    return $unit !== '' ? $s . ' ' . $unit : $s;
}

function inv_time_ago(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return (int)floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return (int)floor($diff / 3600) . 'h ago';
    }
    if ($diff < 172800) {
        return 'Yesterday';
    }

    return date('d M', $ts);
}

/** @return array{icon: string, tone: string} */
function inv_activity_timeline_meta(string $type): array
{
    return match ($type) {
        'inward' => ['icon' => '+', 'tone' => 'in'],
        'usage' => ['icon' => '−', 'tone' => 'out'],
        'low_stock' => ['icon' => '⚠', 'tone' => 'warn'],
        'adjustment' => ['icon' => '⚖', 'tone' => 'neutral'],
        default => ['icon' => '•', 'tone' => 'neutral'],
    };
}

function inv_log_activity(PDO $pdo, string $type, ?int $materialId, float $qtyChange, string $message): void
{
    $pdo->prepare(
        'INSERT INTO inventory_activity (activity_type, material_id, qty_change, message) VALUES (:t, :mid, :q, :m)'
    )->execute([
        't' => $type,
        'mid' => $materialId,
        'q' => $qtyChange,
        'm' => mb_substr($message, 0, 500),
    ]);
}

function inv_material_by_name(PDO $pdo, string $name): ?array
{
    $key = strtolower(trim($name));
    if ($key === '') {
        return null;
    }
    $st = $pdo->prepare(
        "SELECT * FROM raw_materials WHERE status = 'Active'
         AND (LOWER(material_name) = :n OR LOWER(material_code) = :n)
         LIMIT 1"
    );
    $st->execute(['n' => $key]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function inv_get_material_row(PDO $pdo, int $materialId): ?array
{
    $st = $pdo->prepare('SELECT * FROM raw_materials WHERE id = :id LIMIT 1');
    $st->execute(['id' => $materialId]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function inv_available_qty(PDO $pdo, int $materialId): float
{
    $m = inv_get_material_row($pdo, $materialId);

    return $m ? max(0, (float)$m['stock_qty']) : 0.0;
}

/** @return array{code: string, label: string, badge: string} */
function inv_stock_status_meta(float $qty, float $minimum): array
{
    if ($qty <= 0) {
        return ['code' => 'out', 'label' => 'Out Of Stock', 'badge' => 'out', 'icon' => '🔴'];
    }
    if ($minimum > 0 && $qty <= $minimum) {
        return ['code' => 'warning', 'label' => 'Warning', 'badge' => 'low', 'icon' => '⚠'];
    }

    return ['code' => 'normal', 'label' => 'Normal', 'badge' => 'ok', 'icon' => ''];
}

function inv_stock_status(float $qty, float $reorder): string
{
    return inv_stock_status_meta($qty, $reorder)['code'];
}

function inv_check_low_stock_alert(PDO $pdo, int $materialId): void
{
    $m = inv_get_material_row($pdo, $materialId);
    if (!$m) {
        return;
    }
    $stock = (float)$m['stock_qty'];
    $reorder = (float)$m['reorder_level'];
    if ($stock <= 0) {
        inv_log_activity($pdo, 'low_stock', $materialId, 0, $m['material_name'] . ' — out of stock');
    } elseif ($reorder > 0 && $stock <= $reorder) {
        inv_log_activity($pdo, 'low_stock', $materialId, 0, $m['material_name'] . ' — below minimum stock');
    }
}

function inv_increase_stock(PDO $pdo, int $materialId, float $qty, string $activityType, string $message): void
{
    if ($qty <= 0) {
        throw new InvalidArgumentException('Quantity must be greater than zero.');
    }
    $pdo->prepare('UPDATE raw_materials SET stock_qty = stock_qty + :q WHERE id = :id')
        ->execute(['q' => $qty, 'id' => $materialId]);
    inv_log_activity($pdo, $activityType, $materialId, $qty, $message);
    inv_check_low_stock_alert($pdo, $materialId);
}

function inv_decrease_stock(PDO $pdo, int $materialId, float $qty, string $activityType, string $message): void
{
    if ($qty <= 0) {
        throw new InvalidArgumentException('Quantity must be greater than zero.');
    }
    $available = inv_available_qty($pdo, $materialId);
    if ($available < $qty) {
        throw new InvalidArgumentException('Insufficient stock available.');
    }
    $pdo->prepare('UPDATE raw_materials SET stock_qty = stock_qty - :q WHERE id = :id')
        ->execute(['q' => $qty, 'id' => $materialId]);
    inv_log_activity($pdo, $activityType, $materialId, -$qty, $message);
    inv_check_low_stock_alert($pdo, $materialId);
}

function inv_save_material(PDO $pdo, array $data, ?int $id = null): int
{
    $code = trim((string)($data['material_code'] ?? ''));
    $name = trim((string)($data['material_name'] ?? ''));
    $unit = trim((string)($data['unit'] ?? 'kg'));
    if ($code === '' || $name === '' || $unit === '') {
        throw new InvalidArgumentException('Material code, name and unit are required.');
    }

    $params = [
        'c' => $code,
        'n' => $name,
        'u' => $unit,
        'sid' => (int)($data['supplier_id'] ?? 0) ?: null,
        'loc' => trim((string)($data['storage_location'] ?? '')) ?: null,
        'st' => in_array(($data['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
    ];

    if ($id) {
        $pdo->prepare(
            'UPDATE raw_materials SET material_code=:c, material_name=:n, unit=:u,
             supplier_id=:sid, storage_location=:loc, status=:st WHERE id=:id'
        )->execute($params + ['id' => $id]);

        return $id;
    }

    $pdo->prepare(
        'INSERT INTO raw_materials (material_code, material_name, category, unit, stock_qty, reorder_level, max_stock_level, supplier_id, storage_location, status)
         VALUES (:c,:n,\'General\',:u,0,0,0,:sid,:loc,:st)'
    )->execute($params);
    $newId = (int)$pdo->lastInsertId();
    inv_log_activity($pdo, 'adjustment', $newId, 0, 'Material registered: ' . $name);

    return $newId;
}

function inv_material_inward_count(PDO $pdo, int $materialId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM stock_inward WHERE material_id = :id');
    $st->execute(['id' => $materialId]);

    return (int)$st->fetchColumn();
}

function inv_material_limits_unset(array $material): bool
{
    return (float)($material['reorder_level'] ?? 0) <= 0
        && (float)($material['max_stock_level'] ?? 0) <= 0;
}

function inv_apply_material_limits(PDO $pdo, int $materialId, array $data, bool $isFirstInward): void
{
    $setMin = array_key_exists('minimum_stock', $data) && $data['minimum_stock'] !== '';
    $setMax = array_key_exists('maximum_stock', $data) && $data['maximum_stock'] !== '';
    $force = !empty($data['update_limits']);

    if (!$setMin && !$setMax && !$force) {
        return;
    }

    if (!$isFirstInward && !$force && !$setMin && !$setMax) {
        return;
    }

    $row = inv_get_material_row($pdo, $materialId);
    if (!$row) {
        return;
    }

    $min = $setMin ? max(0, (float)$data['minimum_stock']) : (float)$row['reorder_level'];
    $max = $setMax ? max(0, (float)$data['maximum_stock']) : (float)($row['max_stock_level'] ?? 0);

    if ($isFirstInward && !$setMin && !$setMax) {
        return;
    }

    $pdo->prepare('UPDATE raw_materials SET reorder_level = :min, max_stock_level = :max WHERE id = :id')
        ->execute(['min' => $min, 'max' => $max, 'id' => $materialId]);

    inv_log_activity(
        $pdo,
        'adjustment',
        $materialId,
        0,
        'Stock limits set for ' . $row['material_name'] . ' (min ' . $min . ', max ' . $max . ')'
    );
}

/** Material master only — no stock totals. */
function inv_list_materials_master(PDO $pdo): array
{
    return $pdo->query(
        'SELECT rm.*, s.name AS supplier_name
         FROM raw_materials rm
         LEFT JOIN suppliers s ON s.id = rm.supplier_id
         ORDER BY rm.material_name ASC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Stock analytics per material — for dashboard and reports only. */
function inv_list_stock_analytics(PDO $pdo, bool $activeOnly = true): array
{
    $where = $activeOnly ? "WHERE rm.status = 'Active'" : '';
    return $pdo->query(
        "SELECT rm.*, s.name AS supplier_name,
            COALESCE((SELECT SUM(i.quantity) FROM stock_inward i WHERE i.material_id = rm.id), 0) AS total_added,
            COALESCE((SELECT SUM(u.quantity) FROM stock_usage u WHERE u.material_id = rm.id), 0) AS total_used,
            rm.stock_qty AS current_stock,
            rm.stock_qty AS remaining_stock
         FROM raw_materials rm
         LEFT JOIN suppliers s ON s.id = rm.supplier_id
         {$where}
         ORDER BY rm.material_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @deprecated Use inv_list_materials_master() or inv_list_stock_analytics(). */
function inv_list_materials(PDO $pdo): array
{
    return inv_list_stock_analytics($pdo, false);
}

/** Add stock entry (warehouse receipt). */
function inv_save_add_stock(PDO $pdo, array $data): int
{
    return inv_save_inward($pdo, $data);
}

function inv_save_inward(PDO $pdo, array $data): int
{
    $date = (string)($data['inward_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid inward date is required.');
    }
    $materialId = (int)($data['material_id'] ?? 0);
    $qty = (float)($data['quantity'] ?? 0);
    if ($materialId < 1 || $qty <= 0) {
        throw new InvalidArgumentException('Material and quantity are required.');
    }

    $isFirstInward = inv_material_inward_count($pdo, $materialId) === 0;

    $mat = $pdo->prepare('SELECT material_name, unit FROM raw_materials WHERE id = :id');
    $mat->execute(['id' => $materialId]);
    $material = $mat->fetch(PDO::FETCH_ASSOC);
    if (!$material) {
        throw new InvalidArgumentException('Material not found.');
    }

    $batchNo = trim((string)($data['batch_no'] ?? '')) ?: null;
    $expiry = (string)($data['expiry_date'] ?? '');
    $expiryDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry) ? $expiry : null;

    $pdo->prepare(
        'INSERT INTO stock_inward (inward_date, supplier_id, invoice_no, material_id, batch_no, expiry_date, quantity, rate, received_by, remarks)
         VALUES (:d,:sid,:inv,:mid,:bn,:ex,:q,:rate,:rb,:rm)'
    )->execute([
        'd' => $date,
        'sid' => (int)($data['supplier_id'] ?? 0) ?: null,
        'inv' => trim((string)($data['invoice_no'] ?? '')) ?: null,
        'mid' => $materialId,
        'bn' => $batchNo,
        'ex' => $expiryDate,
        'q' => $qty,
        'rate' => 0,
        'rb' => inv_current_user_name(),
        'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
    ]);
    $inwardId = (int)$pdo->lastInsertId();
    $unit = (string)($material['unit'] ?? '');
    inv_increase_stock(
        $pdo,
        $materialId,
        $qty,
        'inward',
        $material['material_name'] . ' stock added +' . $qty . ' ' . $unit
    );

    inv_apply_material_limits($pdo, $materialId, $data, $isFirstInward);

    return $inwardId;
}

/** Use stock entry (manual consumption). */
function inv_save_use_stock(PDO $pdo, array $data): int
{
    return inv_save_manual_usage($pdo, $data);
}

function inv_save_manual_usage(PDO $pdo, array $data): int
{
    $date = (string)($data['usage_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid usage date is required.');
    }
    $materialId = (int)($data['material_id'] ?? 0);
    $qty = (float)($data['quantity'] ?? 0);
    if ($materialId < 1 || $qty <= 0) {
        throw new InvalidArgumentException('Material and quantity are required.');
    }

    $mat = $pdo->prepare('SELECT material_name, unit FROM raw_materials WHERE id = :id');
    $mat->execute(['id' => $materialId]);
    $material = $mat->fetch(PDO::FETCH_ASSOC);
    if (!$material) {
        throw new InvalidArgumentException('Material not found.');
    }

    $dept = trim((string)($data['department'] ?? 'Manual'));
    $reason = trim((string)($data['usage_reason'] ?? 'production_use'));
    $remarks = trim((string)($data['remarks'] ?? ''));
    $by = trim((string)($data['created_by'] ?? '')) ?: inv_current_user_name();
    $reasonLabel = inv_usage_reason_label($reason);

    $pdo->prepare(
        'INSERT INTO stock_usage (usage_date, material_id, quantity, usage_type, department, usage_reason, remarks, created_by)
         VALUES (:d,:mid,:q,\'manual\',:dept,:ur,:rm,:by)'
    )->execute([
        'd' => $date,
        'mid' => $materialId,
        'q' => $qty,
        'dept' => $dept,
        'ur' => $reason,
        'rm' => $remarks ?: null,
        'by' => $by,
    ]);
    $usageId = (int)$pdo->lastInsertId();
    inv_decrease_stock(
        $pdo,
        $materialId,
        $qty,
        'usage',
        $dept . ' — ' . $reasonLabel . ' ' . $qty . ' ' . $material['unit'] . ' ' . $material['material_name']
    );

    return $usageId;
}

/**
 * Auto-deduct materials when production entry is saved. Throws if stock insufficient.
 */
function inv_apply_production_usage(PDO $pdo, string $department, int $entryId, float $outputQty, string $date): void
{
    if ($outputQty <= 0) {
        return;
    }

    $rules = match (strtolower($department)) {
        'mixing' => INV_MIXING_RULES,
        'building' => INV_BUILDING_RULES,
        'curing' => INV_CURING_RULES,
        default => [],
    };

    if ($rules === []) {
        return;
    }

    $plan = [];
    foreach ($rules as $materialName => $ratio) {
        $mat = inv_material_by_name($pdo, $materialName);
        if (!$mat) {
            continue;
        }
        $deduct = round($outputQty * (float)$ratio, 2);
        if ($deduct <= 0) {
            continue;
        }
        $available = inv_available_qty($pdo, (int)$mat['id']);
        if ($available < $deduct) {
            throw new InvalidArgumentException(
                'Insufficient stock available for ' . $mat['material_name']
                . ' (need ' . $deduct . ' ' . $mat['unit'] . ', have ' . $available . ').'
            );
        }
        $plan[] = ['mat' => $mat, 'qty' => $deduct];
    }

    $deptLabel = ucfirst($department);
    foreach ($plan as $item) {
        $mat = $item['mat'];
        $deduct = $item['qty'];
        $pdo->prepare(
            'INSERT INTO stock_usage (usage_date, material_id, quantity, usage_type, department, usage_reason, reference_id, remarks, created_by)
             VALUES (:d,:mid,:q,\'production\',:dept,\'production\',:ref,:rm,\'System\')'
        )->execute([
            'd' => $date,
            'mid' => (int)$mat['id'],
            'q' => $deduct,
            'dept' => $deptLabel,
            'ref' => $entryId,
            'rm' => 'Auto from ' . $department . ' entry #' . $entryId,
        ]);
        inv_decrease_stock(
            $pdo,
            (int)$mat['id'],
            $deduct,
            'usage',
            $deptLabel . ' used ' . $deduct . ' ' . $mat['unit'] . ' ' . $mat['material_name']
        );
    }
}

function inv_dashboard(PDO $pdo): array
{
    $today = date('Y-m-d');

    $totalMaterials = (int)$pdo->query("SELECT COUNT(*) FROM raw_materials WHERE status = 'Active'")->fetchColumn();
    $totalStock = (float)$pdo->query("SELECT COALESCE(SUM(stock_qty),0) FROM raw_materials WHERE status = 'Active'")->fetchColumn();

    $lowRows = $pdo->query(
        "SELECT rm.*, s.name AS supplier_name
         FROM raw_materials rm
         LEFT JOIN suppliers s ON s.id = rm.supplier_id
         WHERE rm.status = 'Active' AND rm.stock_qty > 0 AND rm.stock_qty <= rm.reorder_level
         ORDER BY rm.stock_qty ASC LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $outRows = $pdo->query(
        "SELECT rm.*, s.name AS supplier_name
         FROM raw_materials rm
         LEFT JOIN suppliers s ON s.id = rm.supplier_id
         WHERE rm.status = 'Active' AND rm.stock_qty <= 0
         ORDER BY rm.material_name ASC LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stIn = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM stock_inward WHERE inward_date = :d');
    $stIn->execute(['d' => $today]);
    $todayInward = (float)$stIn->fetchColumn();

    $stUse = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM stock_usage WHERE usage_date = :d');
    $stUse->execute(['d' => $today]);
    $todayUsed = (float)$stUse->fetchColumn();

    $recentUsed = $pdo->query(
        "SELECT u.usage_date, u.department, u.quantity, rm.material_name, rm.unit
         FROM stock_usage u
         JOIN raw_materials rm ON rm.id = u.material_id
         ORDER BY u.id DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $activeSuppliers = (int)$pdo->query("SELECT COUNT(*) FROM suppliers WHERE status = 'Active' OR status IS NULL")->fetchColumn();

    $recent = $pdo->query(
        'SELECT activity_at, activity_type, message, qty_change
         FROM inventory_activity ORDER BY id DESC LIMIT 15'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stockRows = inv_list_stock_analytics($pdo, true);
    $totalAdded = 0.0;
    $totalUsed = 0.0;
    foreach ($stockRows as $row) {
        $totalAdded += (float)$row['total_added'];
        $totalUsed += (float)$row['total_used'];
    }

    return [
        'total_materials' => $totalMaterials,
        'total_stock' => $totalStock,
        'total_added' => $totalAdded,
        'total_used' => $totalUsed,
        'total_remaining' => $totalStock,
        'low_count' => count($lowRows),
        'out_count' => count($outRows),
        'today_added' => $todayInward,
        'today_used' => $todayUsed,
        'active_suppliers' => $activeSuppliers,
        'low_rows' => $lowRows,
        'out_rows' => $outRows,
        'stock_rows' => $stockRows,
        'recent' => $recent,
        'recent_used' => $recentUsed,
        'dept_today' => inv_today_dept_summary($pdo),
        'expiring' => inv_expiring_batches($pdo, 45),
    ];
}

function inv_list_inward(PDO $pdo, int $limit = 50): array
{
    return $pdo->query(
        "SELECT i.*, rm.material_name, rm.unit, s.name AS supplier_name
         FROM stock_inward i
         JOIN raw_materials rm ON rm.id = i.material_id
         LEFT JOIN suppliers s ON s.id = i.supplier_id
         ORDER BY i.id DESC LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function inv_list_usage(PDO $pdo, int $limit = 50): array
{
    return $pdo->query(
        "SELECT u.*, rm.material_name, rm.unit
         FROM stock_usage u
         JOIN raw_materials rm ON rm.id = u.material_id
         ORDER BY u.id DESC LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function inv_save_supplier(PDO $pdo, array $data, ?int $id = null): int
{
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Supplier name is required.');
    }
    $params = [
        'n' => $name,
        'c' => trim((string)($data['contact_person'] ?? '')) ?: null,
        'p' => trim((string)($data['phone'] ?? '')) ?: null,
        'e' => trim((string)($data['email'] ?? '')) ?: null,
        'a' => trim((string)($data['address'] ?? '')) ?: null,
        'ms' => trim((string)($data['materials_supplied'] ?? '')) ?: null,
        'st' => in_array(($data['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
    ];

    if ($id) {
        $pdo->prepare(
            'UPDATE suppliers SET name=:n, contact_person=:c, phone=:p, email=:e, address=:a, materials_supplied=:ms, status=:st WHERE id=:id'
        )->execute($params + ['id' => $id]);

        return $id;
    }

    $pdo->prepare(
        'INSERT INTO suppliers (name, contact_person, phone, email, address, materials_supplied, status)
         VALUES (:n,:c,:p,:e,:a,:ms,:st)'
    )->execute($params);

    return (int)$pdo->lastInsertId();
}

function inv_list_suppliers(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM suppliers ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function inv_supplier_recent_inward(PDO $pdo, int $supplierId, int $limit = 10): array
{
    $st = $pdo->prepare(
        "SELECT i.inward_date, i.invoice_no, i.quantity, rm.material_name
         FROM stock_inward i
         JOIN raw_materials rm ON rm.id = i.material_id
         WHERE i.supplier_id = :sid
         ORDER BY i.id DESC LIMIT {$limit}"
    );
    $st->execute(['sid' => $supplierId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array{summary: array, inward: list, usage: list, low: list, supplier_stock: list}
 */
function inv_report(PDO $pdo, string $from, string $to, int $materialId, int $supplierId): array
{
    $summary = [
        'materials' => (int)$pdo->query("SELECT COUNT(*) FROM raw_materials WHERE status='Active'")->fetchColumn(),
        'total_stock' => (float)$pdo->query("SELECT COALESCE(SUM(stock_qty),0) FROM raw_materials WHERE status='Active'")->fetchColumn(),
        'low' => (int)$pdo->query("SELECT COUNT(*) FROM raw_materials WHERE status='Active' AND stock_qty > 0 AND stock_qty <= reorder_level")->fetchColumn(),
        'out' => (int)$pdo->query("SELECT COUNT(*) FROM raw_materials WHERE status='Active' AND stock_qty <= 0")->fetchColumn(),
    ];

    $inSql = 'SELECT i.inward_date, i.invoice_no, i.quantity, i.rate, i.received_by, rm.material_name, s.name AS supplier_name
        FROM stock_inward i JOIN raw_materials rm ON rm.id = i.material_id LEFT JOIN suppliers s ON s.id = i.supplier_id
        WHERE i.inward_date >= :f AND i.inward_date <= :t';
    $inParams = ['f' => $from, 't' => $to];
    if ($materialId > 0) {
        $inSql .= ' AND i.material_id = :mid';
        $inParams['mid'] = $materialId;
    }
    if ($supplierId > 0) {
        $inSql .= ' AND i.supplier_id = :sid';
        $inParams['sid'] = $supplierId;
    }
    $inSql .= ' ORDER BY i.inward_date DESC, i.id DESC';
    $inSt = $pdo->prepare($inSql);
    $inSt->execute($inParams);
    $inward = $inSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $usSql = 'SELECT u.usage_date, u.quantity, u.usage_type, u.department, u.usage_reason, u.remarks, u.created_by, rm.material_name, rm.unit
        FROM stock_usage u JOIN raw_materials rm ON rm.id = u.material_id
        WHERE u.usage_date >= :f AND u.usage_date <= :t';
    $usParams = ['f' => $from, 't' => $to];
    if ($materialId > 0) {
        $usSql .= ' AND u.material_id = :mid';
        $usParams['mid'] = $materialId;
    }
    $usSql .= ' ORDER BY u.usage_date DESC, u.id DESC';
    $usSt = $pdo->prepare($usSql);
    $usSt->execute($usParams);
    $usage = $usSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $low = $pdo->query(
        "SELECT rm.material_code, rm.material_name, rm.category, rm.unit, rm.stock_qty, rm.reorder_level, rm.storage_location, s.name AS supplier_name
         FROM raw_materials rm LEFT JOIN suppliers s ON s.id = rm.supplier_id
         WHERE rm.status = 'Active' AND (rm.stock_qty <= 0 OR rm.stock_qty <= rm.reorder_level)
         ORDER BY rm.stock_qty ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $supSql = 'SELECT s.name AS supplier_name, rm.material_name, rm.stock_qty, rm.unit
        FROM raw_materials rm
        LEFT JOIN suppliers s ON s.id = rm.supplier_id
        WHERE rm.status = \'Active\'';
    $supParams = [];
    if ($supplierId > 0) {
        $supSql .= ' AND rm.supplier_id = :sid';
        $supParams['sid'] = $supplierId;
    }
    if ($materialId > 0) {
        $supSql .= ' AND rm.id = :mid';
        $supParams['mid'] = $materialId;
    }
    $supSql .= ' ORDER BY s.name, rm.material_name';
    $supSt = $pdo->prepare($supSql);
    $supSt->execute($supParams);
    $supplierStock = $supSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stockSummary = $pdo->query(
        "SELECT rm.material_code, rm.material_name, rm.category, rm.unit, rm.stock_qty, rm.reorder_level, rm.storage_location,
            COALESCE((SELECT SUM(i.quantity) FROM stock_inward i WHERE i.material_id = rm.id), 0) AS total_added,
            COALESCE((SELECT SUM(u.quantity) FROM stock_usage u WHERE u.material_id = rm.id), 0) AS total_used
         FROM raw_materials rm WHERE rm.status = 'Active' ORDER BY rm.material_name"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'summary' => $summary,
        'stock_summary' => $stockSummary,
        'inward' => $inward,
        'usage' => $usage,
        'low' => $low,
        'supplier_stock' => $supplierStock,
    ];
}
