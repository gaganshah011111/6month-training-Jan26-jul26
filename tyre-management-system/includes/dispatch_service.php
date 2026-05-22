<?php
declare(strict_types=1);

require_once __DIR__ . '/production_service.php';

const DISPATCH_STATUS_PENDING = 'Pending';
const DISPATCH_STATUS_DISPATCHED = 'Dispatched';
const DISPATCH_STATUS_DELIVERED = 'Delivered';

const DISPATCH_STATUSES = [
    DISPATCH_STATUS_PENDING,
    DISPATCH_STATUS_DISPATCHED,
    DISPATCH_STATUS_DELIVERED,
];

function dispatch_format_qty(int $qty): string
{
    return number_format($qty, 0, '.', ',');
}

function dispatch_current_user(): string
{
    $u = function_exists('current_user') ? current_user() : null;

    return trim((string)($u['full_name'] ?? $u['username'] ?? 'Dispatch'));
}

function dispatch_generate_code(PDO $pdo): string
{
    $prefix = 'DSP-' . date('Ymd') . '-';
    $st = $pdo->prepare('SELECT COUNT(*) FROM dispatch WHERE dispatch_code LIKE :p');
    $st->execute(['p' => $prefix . '%']);
    $seq = (int)$st->fetchColumn() + 1;

    return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

function dispatch_generate_order_no(PDO $pdo): string
{
    $prefix = 'ORD-' . date('ymd') . '-';
    $st = $pdo->prepare('SELECT COUNT(*) FROM dispatch WHERE order_no LIKE :p');
    $st->execute(['p' => $prefix . '%']);
    $seq = (int)$st->fetchColumn() + 1;

    return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

/** Finished goods stock by tyre type (from inventory table). */
function dispatch_fg_stock_by_type(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT product_name AS tyre_type, SUM(qty) AS total_qty
         FROM inventory GROUP BY product_name HAVING total_qty > 0 ORDER BY product_name"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $all = [];
    foreach (TYRE_TYPES as $t) {
        $all[$t] = 0;
    }
    foreach ($rows as $r) {
        $all[(string)$r['tyre_type']] = (int)$r['total_qty'];
    }

    return $all;
}

function dispatch_fg_available(PDO $pdo, string $tyreType): int
{
    $st = $pdo->prepare('SELECT COALESCE(SUM(qty),0) FROM inventory WHERE LOWER(product_name) = LOWER(:t)');
    $st->execute(['t' => trim($tyreType)]);

    return (int)$st->fetchColumn();
}

/** Reduce finished goods inventory FIFO by product name match. */
function dispatch_reduce_fg_stock(PDO $pdo, string $tyreType, int $qty): void
{
    if ($qty < 1) {
        throw new InvalidArgumentException('Dispatch quantity must be at least 1.');
    }
    $remaining = $qty;
    $st = $pdo->prepare(
        'SELECT id, qty FROM inventory WHERE LOWER(product_name) = LOWER(:t) AND qty > 0 ORDER BY id ASC FOR UPDATE'
    );
    $st->execute(['t' => trim($tyreType)]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $available = 0;
    foreach ($rows as $r) {
        $available += (int)$r['qty'];
    }
    if ($available < $qty) {
        throw new InvalidArgumentException(
            'Insufficient finished stock for ' . $tyreType . '. Available: ' . $available . ', requested: ' . $qty
        );
    }
    foreach ($rows as $r) {
        if ($remaining < 1) {
            break;
        }
        $take = min($remaining, (int)$r['qty']);
        $pdo->prepare('UPDATE inventory SET qty = qty - :q WHERE id = :id')->execute(['q' => $take, 'id' => (int)$r['id']]);
        $remaining -= $take;
    }
}

function dispatch_resolve_customer(PDO $pdo, array $data): array
{
    $customerId = (int)($data['customer_id'] ?? 0);
    $customerName = trim((string)($data['customer_name'] ?? ''));

    if ($customerId > 0) {
        $st = $pdo->prepare('SELECT * FROM dispatch_customers WHERE id = :id AND status = \'Active\' LIMIT 1');
        $st->execute(['id' => $customerId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return ['id' => (int)$row['id'], 'name' => (string)$row['customer_name']];
        }
    }
    if ($customerName === '') {
        throw new InvalidArgumentException('Customer is required.');
    }

    return ['id' => null, 'name' => $customerName];
}

function dispatch_save(PDO $pdo, array $data, string $targetStatus): int
{
    $tyreType = trim((string)($data['tyre_type'] ?? ''));
    $qty = (int)($data['qty'] ?? 0);
    $invoice = trim((string)($data['invoice_no'] ?? ''));
    $date = (string)($data['dispatch_date'] ?? date('Y-m-d'));

    if ($tyreType === '' || $qty < 1) {
        throw new InvalidArgumentException('Tyre type and quantity are required.');
    }
    if ($invoice === '') {
        throw new InvalidArgumentException('Invoice number is required.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid dispatch date is required.');
    }
    if (!in_array($targetStatus, DISPATCH_STATUSES, true)) {
        throw new InvalidArgumentException('Invalid dispatch status.');
    }

    $customer = dispatch_resolve_customer($pdo, $data);
    $deductStock = in_array($targetStatus, [DISPATCH_STATUS_DISPATCHED, DISPATCH_STATUS_DELIVERED], true);

    $pdo->beginTransaction();
    try {
        if ($deductStock) {
            dispatch_reduce_fg_stock($pdo, $tyreType, $qty);
        }

        $code = dispatch_generate_code($pdo);
        $orderNo = dispatch_generate_order_no($pdo);

        $pdo->prepare(
            'INSERT INTO dispatch (
                dispatch_code, order_no, customer_id, customer_name, tyre_type, invoice_no,
                vehicle_no, driver_name, transport_company, dispatch_date, qty, remarks,
                status, stock_deducted, inventory_id
            ) VALUES (
                :code, :ord, :cid, :cname, :tt, :inv, :veh, :drv, :trans, :dt, :qty, :rm,
                :st, :ded, NULL
            )'
        )->execute([
            'code' => $code,
            'ord' => $orderNo,
            'cid' => $customer['id'],
            'cname' => $customer['name'],
            'tt' => $tyreType,
            'inv' => $invoice,
            'veh' => trim((string)($data['vehicle_no'] ?? '')) ?: null,
            'drv' => trim((string)($data['driver_name'] ?? '')) ?: null,
            'trans' => trim((string)($data['transport_company'] ?? '')) ?: null,
            'dt' => $date,
            'qty' => $qty,
            'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
            'st' => $targetStatus,
            'ded' => $deductStock ? 1 : 0,
        ]);

        $id = (int)$pdo->lastInsertId();
        try {
            $legacy = match ($targetStatus) {
                DISPATCH_STATUS_DELIVERED => 'Delivered',
                DISPATCH_STATUS_PENDING => 'Created',
                default => 'In Transit',
            };
            $pdo->prepare('UPDATE dispatch SET dispatch_status = :ds WHERE id = :id')->execute(['ds' => $legacy, 'id' => $id]);
        } catch (Throwable) {
        }
        $pdo->commit();

        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function dispatch_mark_delivered(PDO $pdo, int $id): void
{
    $st = $pdo->prepare('SELECT * FROM dispatch WHERE id = :id LIMIT 1 FOR UPDATE');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Dispatch not found.');
    }
    if (($row['status'] ?? '') === DISPATCH_STATUS_DELIVERED) {
        return;
    }

    $pdo->beginTransaction();
    try {
        if ((int)($row['stock_deducted'] ?? 0) === 0) {
            dispatch_reduce_fg_stock($pdo, (string)$row['tyre_type'], (int)$row['qty']);
            $pdo->prepare('UPDATE dispatch SET stock_deducted = 1 WHERE id = :id')->execute(['id' => $id]);
        }
        $pdo->prepare('UPDATE dispatch SET status = :st WHERE id = :id')->execute([
            'st' => DISPATCH_STATUS_DELIVERED,
            'id' => $id,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function dispatch_mark_dispatched(PDO $pdo, int $id): void
{
    $st = $pdo->prepare('SELECT * FROM dispatch WHERE id = :id LIMIT 1 FOR UPDATE');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Dispatch not found.');
    }
    if (($row['status'] ?? '') !== DISPATCH_STATUS_PENDING) {
        throw new InvalidArgumentException('Only pending orders can be dispatched.');
    }

    $pdo->beginTransaction();
    try {
        dispatch_reduce_fg_stock($pdo, (string)$row['tyre_type'], (int)$row['qty']);
        $pdo->prepare('UPDATE dispatch SET status = :st, stock_deducted = 1 WHERE id = :id')->execute([
            'st' => DISPATCH_STATUS_DISPATCHED,
            'id' => $id,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function dispatch_dashboard(PDO $pdo): array
{
    $today = date('Y-m-d');

    $todayQty = (int)$pdo->query(
        "SELECT COALESCE(SUM(qty),0) FROM dispatch WHERE dispatch_date = CURDATE() AND status != 'Pending'"
    )->fetchColumn();

    $pendingCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM dispatch WHERE status = 'Pending'"
    )->fetchColumn();

    $deliveredToday = (int)$pdo->query(
        "SELECT COUNT(*) FROM dispatch WHERE status = 'Delivered' AND dispatch_date = CURDATE()"
    )->fetchColumn();

    $vehiclesOut = (int)$pdo->query(
        "SELECT COUNT(DISTINCT vehicle_no) FROM dispatch WHERE status = 'Dispatched' AND vehicle_no IS NOT NULL AND vehicle_no != ''"
    )->fetchColumn();

    $pendingRows = $pdo->query(
        "SELECT id, dispatch_code, order_no, customer_name, tyre_type, qty, dispatch_date, status
         FROM dispatch WHERE status = 'Pending' ORDER BY dispatch_date ASC, id DESC LIMIT 15"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $recentRows = $pdo->query(
        "SELECT dispatch_code, invoice_no, customer_name, vehicle_no, qty, driver_name, status, dispatch_date
         FROM dispatch WHERE status IN ('Dispatched','Delivered')
         ORDER BY id DESC LIMIT 15"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'today_qty' => $todayQty,
        'pending_count' => $pendingCount,
        'delivered_today' => $deliveredToday,
        'vehicles_out' => $vehiclesOut,
        'pending_rows' => $pendingRows,
        'recent_rows' => $recentRows,
        'fg_stock' => dispatch_fg_stock_by_type($pdo),
    ];
}

/** @return list<array<string, mixed>> */
function dispatch_list(PDO $pdo, string $search, string $from, string $to, string $status): array
{
    $sql = 'SELECT * FROM dispatch WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (dispatch_code LIKE :q OR order_no LIKE :q OR customer_name LIKE :q OR invoice_no LIKE :q OR tyre_type LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $sql .= ' AND dispatch_date >= :f';
        $params['f'] = $from;
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $sql .= ' AND dispatch_date <= :t';
        $params['t'] = $to;
    }
    if ($status !== '' && in_array($status, DISPATCH_STATUSES, true)) {
        $sql .= ' AND status = :st';
        $params['st'] = $status;
    }

    $sql .= ' ORDER BY id DESC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dispatch_save_customer(PDO $pdo, array $data, ?int $id = null): int
{
    $name = trim((string)($data['customer_name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Customer name is required.');
    }
    $params = [
        'n' => $name,
        'co' => trim((string)($data['company'] ?? '')) ?: null,
        'p' => trim((string)($data['phone'] ?? '')) ?: null,
        'g' => trim((string)($data['gst_number'] ?? '')) ?: null,
        'a' => trim((string)($data['address'] ?? '')) ?: null,
        'ci' => trim((string)($data['city'] ?? '')) ?: null,
        'st' => trim((string)($data['state'] ?? '')) ?: null,
        's' => in_array(($data['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
    ];

    if ($id) {
        $pdo->prepare(
            'UPDATE dispatch_customers SET customer_name=:n, company=:co, phone=:p, gst_number=:g, address=:a, city=:ci, state=:st, status=:s WHERE id=:id'
        )->execute($params + ['id' => $id]);

        return $id;
    }

    $pdo->prepare(
        'INSERT INTO dispatch_customers (customer_name, company, phone, gst_number, address, city, state, status)
         VALUES (:n,:co,:p,:g,:a,:ci,:st,:s)'
    )->execute($params);

    return (int)$pdo->lastInsertId();
}

function dispatch_list_customers(PDO $pdo): array
{
    return $pdo->query(
        "SELECT * FROM dispatch_customers WHERE status = 'Active' ORDER BY customer_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dispatch_all_customers(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM dispatch_customers ORDER BY customer_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array{summary: array, rows: list}
 */
function dispatch_report(PDO $pdo, string $from, string $to, string $customer, string $tyreType, string $status): array
{
    $sql = 'SELECT * FROM dispatch WHERE dispatch_date >= :f AND dispatch_date <= :t';
    $params = ['f' => $from, 't' => $to];

    if ($customer !== '') {
        $sql .= ' AND customer_name LIKE :c';
        $params['c'] = '%' . $customer . '%';
    }
    if ($tyreType !== '') {
        $sql .= ' AND tyre_type = :tt';
        $params['tt'] = $tyreType;
    }
    if ($status !== '' && in_array($status, DISPATCH_STATUSES, true)) {
        $sql .= ' AND status = :st';
        $params['st'] = $status;
    }

    $sql .= ' ORDER BY dispatch_date DESC, id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $totalQty = 0;
    $deliveredQty = 0;
    $pendingQty = 0;
    foreach ($rows as $r) {
        $q = (int)$r['qty'];
        $totalQty += $q;
        if (($r['status'] ?? '') === DISPATCH_STATUS_DELIVERED) {
            $deliveredQty += $q;
        }
        if (($r['status'] ?? '') === DISPATCH_STATUS_PENDING) {
            $pendingQty += $q;
        }
    }

    return [
        'summary' => [
            'total_dispatch' => count($rows),
            'total_qty' => $totalQty,
            'delivered_qty' => $deliveredQty,
            'pending_qty' => $pendingQty,
        ],
        'rows' => $rows,
    ];
}

function dispatch_status_badge(string $status): string
{
    return match ($status) {
        DISPATCH_STATUS_DELIVERED => 'delivered',
        DISPATCH_STATUS_DISPATCHED => 'dispatched',
        default => 'pending',
    };
}
