<?php
declare(strict_types=1);

function inv_purchase_tolerance(): float
{
    return 0.02;
}

function inv_purchase_ensure_schema(PDO $pdo): void
{
    // Purchase columns are applied via Database::connection() → migrateInventoryWarehouse().
    inv_purchase_ensure_payment_schema($pdo);
}

function inv_purchase_ensure_payment_schema(PDO $pdo): void
{
    if (!function_exists('inv_table_exists') || !inv_table_exists($pdo, 'purchase_payments')) {
        return;
    }
    $tol = inv_purchase_tolerance();
    $pdo->exec(
        "INSERT INTO purchase_payments (inward_id, payment_date, amount, payment_mode, payment_ref, notes, recorded_by)
         SELECT i.id, i.inward_date, i.paid_amount, i.payment_mode, i.payment_ref, 'Opening balance (migrated)', i.received_by
         FROM stock_inward i
         WHERE i.paid_amount > {$tol}
           AND NOT EXISTS (SELECT 1 FROM purchase_payments p WHERE p.inward_id = i.id)"
    );
}

function inv_purchase_resolve_status(float $total, float $paid): string
{
    $tol = inv_purchase_tolerance();
    $pending = max(0, round($total - $paid, 2));
    if ($pending <= $tol) {
        return 'Paid';
    }
    if ($paid > $tol) {
        return 'Partial';
    }

    return 'Unpaid';
}

/** Recalculate stock_inward paid_amount + payment_status from payment rows. */
function inv_purchase_sync_inward_payment(PDO $pdo, int $inwardId): void
{
    inv_purchase_ensure_payment_schema($pdo);
    $row = inv_purchase_get($pdo, $inwardId);
    if (!$row) {
        return;
    }
    $total = (float)($row['total_amount'] ?? 0);
    $paid = 0.0;
    if (inv_table_exists($pdo, 'purchase_payments')) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE inward_id = :id');
        $st->execute(['id' => $inwardId]);
        $paid = (float)$st->fetchColumn();
    } else {
        $paid = (float)($row['paid_amount'] ?? 0);
    }
    $paid = round($paid, 2);
    $status = inv_purchase_resolve_status($total, $paid);
    $pdo->prepare('UPDATE stock_inward SET paid_amount = :p, payment_status = :s WHERE id = :id')
        ->execute(['p' => $paid, 's' => $status, 'id' => $inwardId]);
}

/**
 * Append a payment (never overwrites prior rows).
 *
 * @return int payment row id
 */
function inv_purchase_add_payment(PDO $pdo, int $inwardId, array $data): int
{
    inv_purchase_ensure_payment_schema($pdo);
    if (!inv_table_exists($pdo, 'purchase_payments')) {
        throw new RuntimeException('Payment module is not available. Reload the application once.');
    }

    $row = inv_purchase_get($pdo, $inwardId);
    if (!$row) {
        throw new InvalidArgumentException('Purchase entry not found.');
    }

    $amount = round((float)($data['amount'] ?? $data['payment_amount'] ?? 0), 2);
    if ($amount <= 0) {
        throw new InvalidArgumentException('Payment amount must be greater than zero.');
    }

    $total = (float)($row['total_amount'] ?? 0);
    $alreadyPaid = (float)($row['paid_amount'] ?? 0);
    $pending = round(max(0, $total - $alreadyPaid), 2);
    $tol = inv_purchase_tolerance();
    if ($amount > $pending + $tol) {
        throw new InvalidArgumentException('Payment exceeds remaining balance (₹' . number_format($pending, 2) . ').');
    }

    $payDate = (string)($data['payment_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payDate)) {
        throw new InvalidArgumentException('Valid payment date is required.');
    }

    $mode = trim((string)($data['payment_mode'] ?? '')) ?: null;
    $allowedModes = ['Cash', 'UPI', 'Bank', 'Credit'];
    if ($mode !== null && !in_array($mode, $allowedModes, true)) {
        $mode = null;
    }

    $ref = trim((string)($data['payment_ref'] ?? $data['transaction_reference'] ?? '')) ?: null;
    $notes = trim((string)($data['notes'] ?? '')) ?: null;
    $user = inv_current_user_name();

    $pdo->prepare(
        'INSERT INTO purchase_payments (inward_id, payment_date, amount, payment_mode, payment_ref, notes, recorded_by)
         VALUES (:iid,:d,:amt,:mode,:ref,:notes,:user)'
    )->execute([
        'iid' => $inwardId,
        'd' => $payDate,
        'amt' => $amount,
        'mode' => $mode,
        'ref' => $ref,
        'notes' => $notes,
        'user' => $user,
    ]);
    $paymentId = (int)$pdo->lastInsertId();
    inv_purchase_sync_inward_payment($pdo, $inwardId);

    $pinv = (string)($row['pinv_no'] ?? 'PINV-' . $inwardId);
    inv_log_activity(
        $pdo,
        'adjustment',
        (int)($row['material_id'] ?? 0) ?: null,
        0,
        $pinv . ': supplier payment ₹' . number_format($amount, 2) . ' (' . ($mode ?? '—') . ')'
    );

    return $paymentId;
}

/** @return list<array<string, mixed>> */
function inv_purchase_list_payments(PDO $pdo, ?int $inwardId = null, array $filters = []): array
{
    inv_purchase_ensure_payment_schema($pdo);
    if (!inv_table_exists($pdo, 'purchase_payments')) {
        return [];
    }

    $sql = "SELECT p.*, i.pinv_no, i.inward_date, i.total_amount, s.name AS supplier_name
            FROM purchase_payments p
            JOIN stock_inward i ON i.id = p.inward_id
            LEFT JOIN suppliers s ON s.id = i.supplier_id
            WHERE 1=1";
    $params = [];
    if ($inwardId !== null && $inwardId > 0) {
        $sql .= ' AND p.inward_id = :iid';
        $params['iid'] = $inwardId;
    }
    if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['from'])) {
        $sql .= ' AND p.payment_date >= :df';
        $params['df'] = $filters['from'];
    }
    if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['to'])) {
        $sql .= ' AND p.payment_date <= :dt';
        $params['dt'] = $filters['to'];
    }
    if ((int)($filters['supplier_id'] ?? 0) > 0) {
        $sql .= ' AND i.supplier_id = :sid';
        $params['sid'] = (int)$filters['supplier_id'];
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $sql .= ' AND (i.pinv_no LIKE :q OR s.name LIKE :q OR p.payment_ref LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY p.payment_date DESC, p.id DESC';
    $limit = (int)($filters['limit'] ?? 0);
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Update non-stock purchase metadata only. */
function inv_purchase_update_meta(PDO $pdo, int $inwardId, array $data): void
{
    $row = inv_purchase_get($pdo, $inwardId);
    if (!$row) {
        throw new InvalidArgumentException('Purchase entry not found.');
    }
    $due = (string)($data['due_date'] ?? '');
    $dueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;
    $pdo->prepare(
        'UPDATE stock_inward SET invoice_no = :inv, challan_no = :ch, due_date = :due,
         warehouse_location = :wh, remarks = :rm WHERE id = :id'
    )->execute([
        'inv' => trim((string)($data['supplier_invoice_no'] ?? $data['invoice_no'] ?? '')) ?: null,
        'ch' => trim((string)($data['challan_no'] ?? '')) ?: null,
        'due' => $dueDate,
        'wh' => trim((string)($data['warehouse_location'] ?? '')) ?: null,
        'rm' => trim((string)($data['notes'] ?? $data['remarks'] ?? '')) ?: null,
        'id' => $inwardId,
    ]);
}

/** @return array{subtotal: float, gst_amount: float, extra: float, total: float, pending: float, payment_status: string} */
function inv_purchase_calculate(array $data): array
{
    $qty = max(0, (float)($data['quantity'] ?? 0));
    $rate = max(0, (float)($data['rate'] ?? $data['purchase_rate'] ?? 0));
    $gstPct = max(0, min(100, (float)($data['gst_percent'] ?? 0)));
    $transport = max(0, (float)($data['transport_charges'] ?? 0));
    $loading = max(0, (float)($data['loading_charges'] ?? 0));
    $other = max(0, (float)($data['other_charges'] ?? 0));
    $discount = max(0, (float)($data['discount_amount'] ?? $data['discount'] ?? 0));
    $paid = max(0, (float)($data['paid_amount'] ?? 0));

    $subtotal = round($qty * $rate, 2);
    $gstAmount = round($subtotal * ($gstPct / 100), 2);
    $extra = round($transport + $loading + $other, 2);
    $total = round($subtotal + $gstAmount + $extra - $discount, 2);
    if ($total < 0) {
        $total = 0;
    }
    $pending = round(max(0, $total - $paid), 2);
    $tol = inv_purchase_tolerance();

    $status = (string)($data['payment_status'] ?? '');
    if (!in_array($status, ['Paid', 'Partial', 'Unpaid'], true)) {
        if ($total <= $tol || $pending <= $tol) {
            $status = 'Paid';
            $paid = $total;
            $pending = 0;
        } elseif ($paid > $tol) {
            $status = 'Partial';
        } else {
            $status = 'Unpaid';
        }
    } elseif ($status === 'Paid' && $paid < $total - $tol) {
        $paid = $total;
        $pending = 0;
    }

    return [
        'subtotal' => $subtotal,
        'gst_amount' => $gstAmount,
        'extra' => $extra,
        'total' => $total,
        'paid' => $paid,
        'pending' => $pending,
        'payment_status' => $status,
    ];
}

function inv_purchase_next_pinv(PDO $pdo, string $date): string
{
    $ymd = date('Ymd', strtotime($date) ?: time());
    $prefix = 'PINV-' . $ymd . '-';
    $st = $pdo->prepare('SELECT pinv_no FROM stock_inward WHERE pinv_no LIKE :pfx ORDER BY id DESC LIMIT 1');
    $st->execute(['pfx' => $prefix . '%']);
    $last = (string)($st->fetchColumn() ?: '');
    $seq = 1;
    if ($last !== '' && preg_match('/-(\d+)$/', $last, $m)) {
        $seq = (int)$m[1] + 1;
    }

    return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

function inv_purchase_update_avg_rate(PDO $pdo, int $materialId, float $inwardQty, float $rate): void
{
    if ($materialId < 1 || $inwardQty <= 0 || $rate < 0) {
        return;
    }
    $row = inv_get_material_row($pdo, $materialId);
    if (!$row) {
        return;
    }
    $oldQty = max(0, (float)($row['stock_qty'] ?? 0) - $inwardQty);
    $oldAvg = (float)($row['avg_purchase_rate'] ?? 0);
    if ($oldQty <= 0) {
        $newAvg = $rate;
    } else {
        $newAvg = (($oldQty * $oldAvg) + ($inwardQty * $rate)) / ($oldQty + $inwardQty);
    }
    $pdo->prepare('UPDATE raw_materials SET avg_purchase_rate = :a WHERE id = :id')
        ->execute(['a' => round($newAvg, 2), 'id' => $materialId]);
}

/** Save purchase inward: stock, ledger, payables via stock_inward row. */
function inv_purchase_save(PDO $pdo, array $data): int
{
    inv_purchase_ensure_schema($pdo);

    $date = (string)($data['inward_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid purchase date is required.');
    }
    $supplierId = (int)($data['supplier_id'] ?? 0);
    if ($supplierId < 1) {
        throw new InvalidArgumentException('Supplier is required.');
    }
    $materialId = (int)($data['material_id'] ?? 0);
    $qty = (float)($data['quantity'] ?? 0);
    if ($materialId < 1 || $qty <= 0) {
        throw new InvalidArgumentException('Material and quantity are required.');
    }

    $rate = (float)($data['purchase_rate'] ?? $data['rate'] ?? 0);
    if ($rate < 0) {
        throw new InvalidArgumentException('Purchase rate cannot be negative.');
    }

    $calc = inv_purchase_calculate($data + ['rate' => $rate]);
    $isFirstInward = inv_material_inward_count($pdo, $materialId) === 0;

    $mat = $pdo->prepare('SELECT material_name, unit FROM raw_materials WHERE id = :id');
    $mat->execute(['id' => $materialId]);
    $material = $mat->fetch(PDO::FETCH_ASSOC);
    if (!$material) {
        throw new InvalidArgumentException('Material not found.');
    }

    $pinv = trim((string)($data['pinv_no'] ?? ''));
    if ($pinv === '') {
        $pinv = inv_purchase_next_pinv($pdo, $date);
    }

    $batchNo = trim((string)($data['batch_no'] ?? '')) ?: null;
    $expiry = (string)($data['expiry_date'] ?? '');
    $expiryDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry) ? $expiry : null;
    $due = (string)($data['due_date'] ?? '');
    $dueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;
    $paymentMode = trim((string)($data['payment_mode'] ?? '')) ?: null;
    $warehouse = trim((string)($data['warehouse_location'] ?? '')) ?: null;
    $supplierInvoice = trim((string)($data['supplier_invoice_no'] ?? $data['invoice_no'] ?? '')) ?: null;
    $challan = trim((string)($data['challan_no'] ?? '')) ?: null;
    $paymentRef = trim((string)($data['payment_ref'] ?? '')) ?: null;
    $remarks = trim((string)($data['notes'] ?? $data['remarks'] ?? '')) ?: null;
    $gstPct = max(0, min(100, (float)($data['gst_percent'] ?? 0)));

    $pdo->prepare(
        'INSERT INTO stock_inward (
            pinv_no, inward_date, supplier_id, invoice_no, challan_no, material_id, batch_no, expiry_date,
            quantity, rate, gst_percent, transport_charges, loading_charges, other_charges, discount_amount,
            subtotal, gst_amount, total_amount, paid_amount, payment_status, payment_mode, due_date, payment_ref,
            warehouse_location, received_by, remarks
        ) VALUES (
            :pinv,:d,:sid,:inv,:ch,:mid,:bn,:ex,:q,:rate,:gst,:tr,:ld,:ot,:disc,
            :sub,:gamt,:tot,:paid,:pst,:pm,:due,:pref,:wh,:rb,:rm
        )'
    )->execute([
        'pinv' => $pinv,
        'd' => $date,
        'sid' => $supplierId,
        'inv' => $supplierInvoice,
        'ch' => $challan,
        'mid' => $materialId,
        'bn' => $batchNo,
        'ex' => $expiryDate,
        'q' => $qty,
        'rate' => $rate,
        'gst' => $gstPct,
        'tr' => max(0, (float)($data['transport_charges'] ?? 0)),
        'ld' => max(0, (float)($data['loading_charges'] ?? 0)),
        'ot' => max(0, (float)($data['other_charges'] ?? 0)),
        'disc' => max(0, (float)($data['discount_amount'] ?? $data['discount'] ?? 0)),
        'sub' => $calc['subtotal'],
        'gamt' => $calc['gst_amount'],
        'tot' => $calc['total'],
        'paid' => 0,
        'pst' => 'Unpaid',
        'pm' => $paymentMode,
        'due' => $dueDate,
        'pref' => $paymentRef,
        'wh' => $warehouse,
        'rb' => inv_current_user_name(),
        'rm' => $remarks,
    ]);
    $inwardId = (int)$pdo->lastInsertId();

    inv_increase_stock(
        $pdo,
        $materialId,
        $qty,
        'inward',
        $pinv . ': ' . $material['material_name'] . ' +' . $qty . ' ' . $material['unit']
    );
    inv_purchase_update_avg_rate($pdo, $materialId, $qty, $rate);
    inv_apply_material_limits($pdo, $materialId, $data, $isFirstInward);

    if ($warehouse !== null && $warehouse !== '') {
        $pdo->prepare('UPDATE raw_materials SET storage_location = :loc WHERE id = :id AND (storage_location IS NULL OR storage_location = \'\')')
            ->execute(['loc' => $warehouse, 'id' => $materialId]);
    }

    if ($calc['paid'] > inv_purchase_tolerance()) {
        inv_purchase_add_payment($pdo, $inwardId, [
            'payment_date' => $date,
            'amount' => $calc['paid'],
            'payment_mode' => $paymentMode,
            'payment_ref' => $paymentRef,
            'notes' => 'Initial payment on purchase inward',
        ]);
    }

    return $inwardId;
}

function inv_purchase_get(PDO $pdo, int $id): ?array
{
    inv_purchase_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT i.*, rm.material_name, rm.unit, rm.material_code,
                s.name AS supplier_name, s.contact_person, s.phone AS supplier_phone, s.gst_number AS supplier_gst
         FROM stock_inward i
         JOIN raw_materials rm ON rm.id = i.material_id
         LEFT JOIN suppliers s ON s.id = i.supplier_id
         WHERE i.id = :id LIMIT 1"
    );
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['pending_amount'] = max(0, round((float)($row['total_amount'] ?? 0) - (float)($row['paid_amount'] ?? 0), 2));
    if (empty($row['pinv_no'])) {
        $row['pinv_no'] = 'PINV-' . (int)$row['id'];
    }

    return $row;
}

/** @return array{label: string, badge: string} */
function inv_purchase_payment_meta(string $status): array
{
    return match ($status) {
        'Paid' => ['label' => 'Paid', 'badge' => 'paid'],
        'Partial' => ['label' => 'Partial', 'badge' => 'partial'],
        default => ['label' => 'Unpaid', 'badge' => 'unpaid'],
    };
}

/**
 * @param array{q?: string, from?: string, to?: string, supplier_id?: int, material_id?: int, payment_status?: string, limit?: int} $filters
 */
function inv_purchase_list(PDO $pdo, array $filters = []): array
{
    inv_purchase_ensure_schema($pdo);
    $sql = "SELECT i.id, i.pinv_no, i.inward_date, i.quantity, i.rate,
                   COALESCE(NULLIF(i.subtotal, 0), i.quantity * i.rate) AS subtotal,
                   i.gst_amount,
                   COALESCE(NULLIF(i.total_amount, 0), i.quantity * i.rate + COALESCE(i.gst_amount, 0)) AS total_amount,
                   i.paid_amount, i.payment_status, i.invoice_no, i.challan_no, i.due_date,
                   rm.material_name, rm.unit, s.name AS supplier_name
            FROM stock_inward i
            JOIN raw_materials rm ON rm.id = i.material_id
            LEFT JOIN suppliers s ON s.id = i.supplier_id
            WHERE 1=1";
    $params = [];
    if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['from'])) {
        $sql .= ' AND i.inward_date >= :df';
        $params['df'] = $filters['from'];
    }
    if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['to'])) {
        $sql .= ' AND i.inward_date <= :dt';
        $params['dt'] = $filters['to'];
    }
    if ((int)($filters['supplier_id'] ?? 0) > 0) {
        $sql .= ' AND i.supplier_id = :sid';
        $params['sid'] = (int)$filters['supplier_id'];
    }
    if ((int)($filters['material_id'] ?? 0) > 0) {
        $sql .= ' AND i.material_id = :mid';
        $params['mid'] = (int)$filters['material_id'];
    }
    if (!empty($filters['payment_status']) && in_array($filters['payment_status'], ['Paid', 'Partial', 'Unpaid'], true)) {
        $sql .= ' AND i.payment_status = :pst';
        $params['pst'] = $filters['payment_status'];
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $sql .= ' AND (i.pinv_no LIKE :q OR i.invoice_no LIKE :q OR rm.material_name LIKE :q OR s.name LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY i.inward_date DESC, i.id DESC';
    $limit = (int)($filters['limit'] ?? 0);
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['pending_amount'] = max(0, round((float)($r['total_amount'] ?? 0) - (float)($r['paid_amount'] ?? 0), 2));
        if (empty($r['pinv_no'])) {
            $r['pinv_no'] = 'PINV-' . (int)$r['id'];
        }
    }
    unset($r);

    return $rows;
}

function inv_supplier_ledger_list(PDO $pdo, string $q = ''): array
{
    inv_purchase_ensure_schema($pdo);
    $sql = "SELECT s.id, s.name, s.contact_person, s.phone, s.gst_number,
                   COALESCE(SUM(i.total_amount), 0) AS total_purchased,
                   COALESCE(SUM(i.paid_amount), 0) AS total_paid,
                   COALESCE(SUM(GREATEST(i.total_amount - i.paid_amount, 0)), 0) AS pending_balance,
                   MAX(i.inward_date) AS last_purchase_date
            FROM suppliers s
            LEFT JOIN stock_inward i ON i.supplier_id = s.id
            WHERE (s.status = 'Active' OR s.status IS NULL)";
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (s.name LIKE :q OR s.contact_person LIKE :q OR s.phone LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' GROUP BY s.id ORDER BY pending_balance DESC, s.name ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array{total_purchases: float, total_paid: float, total_pending: float, top_material: ?array, supplier_outstanding: list} */
function inv_purchase_report_summary(PDO $pdo, string $from, string $to, int $supplierId = 0, int $materialId = 0, string $paymentStatus = ''): array
{
    inv_purchase_ensure_schema($pdo);
    $where = 'WHERE i.inward_date >= :f AND i.inward_date <= :t';
    $params = ['f' => $from, 't' => $to];
    if ($supplierId > 0) {
        $where .= ' AND i.supplier_id = :sid';
        $params['sid'] = $supplierId;
    }
    if ($materialId > 0) {
        $where .= ' AND i.material_id = :mid';
        $params['mid'] = $materialId;
    }
    if ($paymentStatus !== '' && in_array($paymentStatus, ['Paid', 'Partial', 'Unpaid'], true)) {
        $where .= ' AND i.payment_status = :pst';
        $params['pst'] = $paymentStatus;
    }

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(i.total_amount),0) AS total_purchases,
                COALESCE(SUM(i.paid_amount),0) AS total_paid,
                COALESCE(SUM(GREATEST(i.total_amount - i.paid_amount, 0)),0) AS total_pending
         FROM stock_inward i {$where}"
    );
    $st->execute($params);
    $sum = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $topSt = $pdo->prepare(
        "SELECT rm.material_name, SUM(i.quantity) AS total_qty
         FROM stock_inward i JOIN raw_materials rm ON rm.id = i.material_id
         {$where}
         GROUP BY i.material_id ORDER BY total_qty DESC LIMIT 1"
    );
    $topSt->execute($params);
    $topMaterial = $topSt->fetch(PDO::FETCH_ASSOC) ?: null;

    $outSt = $pdo->prepare(
        "SELECT s.name AS supplier_name,
                COALESCE(SUM(GREATEST(i.total_amount - i.paid_amount, 0)), 0) AS pending_balance
         FROM stock_inward i
         JOIN suppliers s ON s.id = i.supplier_id
         {$where}
         GROUP BY i.supplier_id
         HAVING pending_balance > 0.02
         ORDER BY pending_balance DESC
         LIMIT 15"
    );
    $outSt->execute($params);
    $supplierOutstanding = $outSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'total_purchases' => (float)($sum['total_purchases'] ?? 0),
        'total_paid' => (float)($sum['total_paid'] ?? 0),
        'total_pending' => (float)($sum['total_pending'] ?? 0),
        'top_material' => $topMaterial,
        'supplier_outstanding' => $supplierOutstanding,
    ];
}

/** Dashboard purchase widgets. */
function inv_purchase_dashboard_data(PDO $pdo): array
{
    inv_purchase_ensure_schema($pdo);
    $recentLimit = defined('INV_RECENT_PURCHASES_DASHBOARD') ? INV_RECENT_PURCHASES_DASHBOARD : 8;
    $recent = inv_purchase_list($pdo, ['limit' => $recentLimit]);
    $ledger = inv_supplier_ledger_list($pdo);
    $outstanding = array_values(array_filter($ledger, static fn($r) => (float)($r['pending_balance'] ?? 0) > 0.02));
    usort($outstanding, static fn($a, $b) => (float)$b['pending_balance'] <=> (float)$a['pending_balance']);
    $outstanding = array_slice($outstanding, 0, 8);

    $pendingPayables = (float)$pdo->query(
        'SELECT COALESCE(SUM(GREATEST(total_amount - paid_amount, 0)), 0) FROM stock_inward'
    )->fetchColumn();

    $stockValue = (float)$pdo->query(
        'SELECT COALESCE(SUM(stock_qty * COALESCE(NULLIF(avg_purchase_rate, 0), 0)), 0) FROM raw_materials WHERE status = \'Active\''
    )->fetchColumn();

    $trend = [];
    $st = $pdo->query(
        "SELECT u.usage_date AS d, COALESCE(SUM(u.quantity), 0) AS qty
         FROM stock_usage u
         WHERE u.usage_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
         GROUP BY u.usage_date ORDER BY u.usage_date ASC"
    );
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $trend[] = $row;
    }

    return [
        'recent_purchases' => $recent,
        'supplier_outstanding' => $outstanding,
        'pending_payables' => $pendingPayables,
        'stock_value' => $stockValue,
        'consumption_trend' => $trend,
    ];
}

function inv_purchase_pdf_filename(array $row): string
{
    $pinv = (string)($row['pinv_no'] ?? 'PINV-' . ($row['id'] ?? '0'));

    return preg_replace('/[^A-Za-z0-9\-_]/', '', $pinv) . '.pdf';
}

/** Print view URL — opens HTML slip; use browser Print → Save as PDF for a real PDF file. */
function inv_purchase_print_url(int $id, bool $autoPrint = false): string
{
    $q = ['id' => $id];
    if ($autoPrint) {
        $q['print'] = '1';
    }

    return route_url('inventory/purchase-print', $q);
}
