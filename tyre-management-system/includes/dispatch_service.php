<?php
declare(strict_types=1);

require_once __DIR__ . '/production_service.php';

/** All new dispatches are saved as Delivered (stock deducted on create). */
const DISPATCH_STATUS_DELIVERED = 'Delivered';

/** Legacy statuses (existing rows only). */
const DISPATCH_STATUS_PENDING = 'Pending';
const DISPATCH_STATUS_DISPATCHED = 'Dispatched';

const DISPATCH_STATUSES = [
    DISPATCH_STATUS_DELIVERED,
    DISPATCH_STATUS_PENDING,
    DISPATCH_STATUS_DISPATCHED,
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

/** Unique invoice number — INV-YYYYMMDD-#### with collision retry. */
function dispatch_generate_invoice_no(PDO $pdo): string
{
    for ($i = 0; $i < 50; $i++) {
        $suffix = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $inv = 'INV-' . date('Ymd') . '-' . $suffix;
        $st = $pdo->prepare('SELECT 1 FROM dispatch WHERE invoice_no = :i LIMIT 1');
        $st->execute(['i' => $inv]);
        if (!$st->fetchColumn()) {
            return $inv;
        }
    }
    $inv = 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $st = $pdo->prepare('SELECT 1 FROM dispatch WHERE invoice_no = :i LIMIT 1');
    $st->execute(['i' => $inv]);
    if ($st->fetchColumn()) {
        throw new RuntimeException('Could not generate unique invoice number.');
    }

    return $inv;
}

/** Session-backed preview codes shown on New Dispatch (consumed on save). */
function dispatch_form_preview(PDO $pdo, bool $forceNew = false): array
{
    ensure_session_started();
    $key = 'dispatch_form_preview';
    $existing = $_SESSION[$key] ?? null;
    if (
        !$forceNew
        && is_array($existing)
        && !empty($existing['invoice_no'])
        && !empty($existing['dispatch_code'])
        && (time() - (int)($existing['ts'] ?? 0)) < 3600
    ) {
        return $existing;
    }
    $preview = [
        'invoice_no' => dispatch_generate_invoice_no($pdo),
        'dispatch_code' => dispatch_generate_code($pdo),
        'ts' => time(),
    ];
    $_SESSION[$key] = $preview;

    return $preview;
}

function dispatch_consume_form_preview(PDO $pdo): array
{
    ensure_session_started();
    $preview = $_SESSION['dispatch_form_preview'] ?? null;
    if (
        is_array($preview)
        && !empty($preview['invoice_no'])
        && !empty($preview['dispatch_code'])
    ) {
        unset($_SESSION['dispatch_form_preview']);

        return $preview;
    }

    return [
        'invoice_no' => dispatch_generate_invoice_no($pdo),
        'dispatch_code' => dispatch_generate_code($pdo),
    ];
}

function dispatch_company_name(PDO $pdo): string
{
    try {
        $st = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'company_name' LIMIT 1");
        $name = (string)($st->fetchColumn() ?: '');
        if ($name !== '') {
            return $name;
        }
    } catch (Throwable) {
    }

    return defined('APP_NAME') ? APP_NAME : 'Tyre Manufacturing ERP';
}

/** @return array{gross: ?float, tare: ?float, net: ?float} */
function dispatch_parse_weights(array $data): array
{
    $gross = ($data['gross_weight_kg'] ?? '') !== '' ? (float)$data['gross_weight_kg'] : null;
    $tare = ($data['tare_weight_kg'] ?? '') !== '' ? (float)$data['tare_weight_kg'] : null;

    if ($gross !== null && $gross < 0) {
        throw new InvalidArgumentException('Gross weight cannot be negative.');
    }
    if ($tare !== null && $tare < 0) {
        throw new InvalidArgumentException('Tare weight cannot be negative.');
    }
    if ($gross !== null && $tare !== null) {
        if ($gross <= $tare) {
            throw new InvalidArgumentException('Gross weight must be greater than tare weight.');
        }
        $net = round($gross - $tare, 2);
    } elseif (($data['net_weight_kg'] ?? '') !== '') {
        $net = round((float)$data['net_weight_kg'], 2);
    } else {
        $net = null;
    }

    return ['gross' => $gross, 'tare' => $tare, 'net' => $net];
}

function dispatch_format_weight(?float $kg): string
{
    if ($kg === null) {
        return '—';
    }

    return number_format($kg, 2, '.', ',') . ' kg';
}

/** Finished goods stock by tyre type (from inventory table). */
function dispatch_fg_stock_by_type(PDO $pdo): array
{
    require_once __DIR__ . '/qc_service.php';
    $rows = $pdo->query(
        "SELECT product_name AS tyre_type, SUM(qty) AS total_qty
         FROM inventory WHERE " . qc_dispatch_stock_sql() . "
         GROUP BY product_name HAVING total_qty > 0 ORDER BY product_name"
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
    require_once __DIR__ . '/qc_service.php';
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(qty),0) FROM inventory WHERE LOWER(product_name) = LOWER(:t) AND ' . qc_dispatch_stock_sql()
    );
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
    require_once __DIR__ . '/qc_service.php';
    $st = $pdo->prepare(
        'SELECT id, qty FROM inventory WHERE LOWER(product_name) = LOWER(:t) AND qty > 0 AND '
        . qc_dispatch_stock_sql() . ' ORDER BY id ASC FOR UPDATE'
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

/** @return array{id: int, name: string, vehicle_no: ?string, transport_company_id: ?int, transport_company: ?string} */
function dispatch_resolve_driver(PDO $pdo, array $data): array
{
    $driverId = (int)($data['driver_id'] ?? 0);
    if ($driverId < 1) {
        throw new InvalidArgumentException('Please select a registered driver.');
    }
    $st = $pdo->prepare(
        "SELECT d.*, v.vehicle_number AS linked_vehicle_no, v.transport_company_id AS vehicle_transport_id,
                t.company_name AS transport_name, tv.company_name AS vehicle_transport_name
         FROM dispatch_drivers d
         LEFT JOIN dispatch_vehicles v ON v.id = d.vehicle_id
         LEFT JOIN dispatch_transport_companies t ON t.id = d.transport_company_id
         LEFT JOIN dispatch_transport_companies tv ON tv.id = v.transport_company_id
         WHERE d.id = :id AND d.status = 'Active' LIMIT 1"
    );
    $st->execute(['id' => $driverId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Selected driver is not active or does not exist.');
    }

    $transportId = (int)($row['vehicle_transport_id'] ?? $row['transport_company_id'] ?? 0) ?: null;
    $transportName = trim((string)($row['vehicle_transport_name'] ?? $row['transport_name'] ?? $row['transport_company'] ?? '')) ?: null;
    $vehicleNo = trim((string)($row['linked_vehicle_no'] ?? $row['vehicle_no'] ?? '')) ?: null;

    return [
        'id' => (int)$row['id'],
        'name' => (string)$row['driver_name'],
        'vehicle_no' => $vehicleNo,
        'transport_company_id' => $transportId,
        'transport_company' => $transportName,
    ];
}

/** @return array{id: int, name: string} */
function dispatch_resolve_transport(PDO $pdo, array $data, ?array $driver = null): array
{
    $transportId = (int)($data['transport_company_id'] ?? 0);
    if ($transportId < 1 && $driver && !empty($driver['transport_company_id'])) {
        $transportId = (int)$driver['transport_company_id'];
    }
    if ($transportId < 1) {
        throw new InvalidArgumentException('Please select a registered transport company.');
    }
    $st = $pdo->prepare(
        "SELECT * FROM dispatch_transport_companies WHERE id = :id AND status = 'Active' LIMIT 1"
    );
    $st->execute(['id' => $transportId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Selected transport company is not active.');
    }

    return ['id' => (int)$row['id'], 'name' => (string)$row['company_name']];
}

function dispatch_validate_stock_qty(PDO $pdo, string $tyreType, int $qty): void
{
    $available = dispatch_fg_available($pdo, $tyreType);
    if ($qty > $available) {
        throw new InvalidArgumentException(
            'Only ' . $available . ' tyres available in stock'
        );
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

function dispatch_save(PDO $pdo, array $data): int
{
    $tyreType = trim((string)($data['tyre_type'] ?? ''));
    $qty = (int)($data['qty'] ?? 0);
    $date = (string)($data['dispatch_date'] ?? date('Y-m-d'));

    if ($tyreType === '' || $qty < 1) {
        throw new InvalidArgumentException('Tyre type and quantity are required.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid dispatch date is required.');
    }

    require_once __DIR__ . '/logistics_service.php';
    $customer = dispatch_resolve_customer($pdo, $data);
    $logistics = logistics_resolve_dispatch($pdo, $data);
    $driver = $logistics['driver'];
    $transport = $logistics['transport'];
    $weights = dispatch_parse_weights($data);
    $preview = dispatch_consume_form_preview($pdo);
    $invoice = (string)$preview['invoice_no'];
    $dispatchCode = (string)$preview['dispatch_code'];

    dispatch_validate_stock_qty($pdo, $tyreType, $qty);

    $pdo->beginTransaction();
    try {
        dispatch_reduce_fg_stock($pdo, $tyreType, $qty);

        $code = $dispatchCode;
        $orderNo = dispatch_generate_order_no($pdo);
        $dupInv = $pdo->prepare('SELECT 1 FROM dispatch WHERE invoice_no = :i LIMIT 1');
        $dupInv->execute(['i' => $invoice]);
        if ($dupInv->fetchColumn()) {
            $invoice = dispatch_generate_invoice_no($pdo);
        }
        $dupCode = $pdo->prepare('SELECT 1 FROM dispatch WHERE dispatch_code = :c LIMIT 1');
        $dupCode->execute(['c' => $code]);
        if ($dupCode->fetchColumn()) {
            $code = dispatch_generate_code($pdo);
        }

        $vehicleIdVal = $logistics['vehicle_id'] ?? null;
        $pdo->prepare(
            'INSERT INTO dispatch (
                dispatch_code, order_no, customer_id, customer_name, tyre_type, invoice_no,
                vehicle_no, vehicle_id, driver_id, driver_name, transport_company_id, transport_company,
                dispatch_date, qty, gross_weight_kg, tare_weight_kg, net_weight_kg, remarks,
                status, stock_deducted, inventory_id
            ) VALUES (
                :code, :ord, :cid, :cname, :tt, :inv, :veh, :vid, :did, :drv, :tid, :trans, :dt, :qty,
                :gw, :tw, :nw, :rm, :st, :ded, NULL
            )'
        )->execute([
            'code' => $code,
            'ord' => $orderNo,
            'cid' => $customer['id'],
            'cname' => $customer['name'],
            'tt' => $tyreType,
            'inv' => $invoice,
            'veh' => $logistics['vehicle_no'] ?: $driver['vehicle_no'],
            'vid' => $vehicleIdVal,
            'did' => $driver['id'],
            'drv' => $driver['name'],
            'tid' => $transport['id'],
            'trans' => $transport['name'],
            'dt' => $date,
            'qty' => $qty,
            'gw' => $weights['gross'],
            'tw' => $weights['tare'],
            'nw' => $weights['net'],
            'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
            'st' => DISPATCH_STATUS_DELIVERED,
            'ded' => 1,
        ]);

        $id = (int)$pdo->lastInsertId();
        try {
            $pdo->prepare('UPDATE dispatch SET dispatch_status = :ds WHERE id = :id')->execute([
                'ds' => 'Delivered',
                'id' => $id,
            ]);
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

function dispatch_dashboard(PDO $pdo): array
{
    $today = date('Y-m-d');

    $todayQty = (int)$pdo->query(
        "SELECT COALESCE(SUM(qty),0) FROM dispatch WHERE dispatch_date = CURDATE() AND status = 'Delivered'"
    )->fetchColumn();

    $dispatchesToday = (int)$pdo->query(
        "SELECT COUNT(*) FROM dispatch WHERE dispatch_date = CURDATE() AND status = 'Delivered'"
    )->fetchColumn();

    $vehiclesToday = (int)$pdo->query(
        "SELECT COUNT(DISTINCT COALESCE(NULLIF(vehicle_no,''), CONCAT('id-', vehicle_id)))
         FROM dispatch WHERE dispatch_date = CURDATE() AND status = 'Delivered'"
    )->fetchColumn();

    $recentRows = $pdo->query(
        "SELECT dispatch_code, invoice_no, customer_name, vehicle_no, qty, driver_name, status, dispatch_date
         FROM dispatch WHERE status = 'Delivered'
         ORDER BY id DESC LIMIT 15"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'today_qty' => $todayQty,
        'dispatches_today' => $dispatchesToday,
        'vehicles_today' => $vehiclesToday,
        'recent_rows' => $recentRows,
        'fg_stock' => dispatch_fg_stock_by_type($pdo),
    ];
}

/** @return list<array<string, mixed>> */
function dispatch_list(PDO $pdo, string $search, string $from, string $to, string $status): array
{
    $sql = 'SELECT d.*, dr.driver_name AS registered_driver_name, dr.license_number AS driver_license
            FROM dispatch d
            LEFT JOIN dispatch_drivers dr ON dr.id = d.driver_id
            WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (d.dispatch_code LIKE :q OR d.order_no LIKE :q OR d.customer_name LIKE :q
            OR d.invoice_no LIKE :q OR d.tyre_type LIKE :q OR d.driver_name LIKE :q OR d.vehicle_no LIKE :q
            OR d.transport_company LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $sql .= ' AND d.dispatch_date >= :f';
        $params['f'] = $from;
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $sql .= ' AND d.dispatch_date <= :t';
        $params['t'] = $to;
    }
    if ($status !== '' && in_array($status, DISPATCH_STATUSES, true)) {
        $sql .= ' AND d.status = :st';
        $params['st'] = $status;
    }

    $sql .= ' ORDER BY d.id DESC LIMIT 500';
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
    foreach ($rows as $r) {
        $q = (int)$r['qty'];
        $totalQty += $q;
        if (($r['status'] ?? '') === DISPATCH_STATUS_DELIVERED) {
            $deliveredQty += $q;
        }
    }

    return [
        'summary' => [
            'total_dispatch' => count($rows),
            'total_qty' => $totalQty,
            'delivered_qty' => $deliveredQty,
        ],
        'rows' => $rows,
    ];
}

function dispatch_status_badge(string $status): string
{
    return match ($status) {
        DISPATCH_STATUS_DELIVERED => 'delivered',
        DISPATCH_STATUS_DISPATCHED => 'dispatched',
        DISPATCH_STATUS_PENDING => 'pending',
        default => 'delivered',
    };
}

function dispatch_save_driver(PDO $pdo, array $data, ?int $id = null): int
{
    $name = trim((string)($data['driver_name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Driver name is required.');
    }
    $transportId = (int)($data['transport_company_id'] ?? 0);
    $transport = $transportId > 0 ? dispatch_resolve_transport($pdo, ['transport_company_id' => $transportId]) : null;
    if (!$transport) {
        throw new InvalidArgumentException('Transport company is required for driver.');
    }
    $params = [
        'n' => $name,
        'p' => trim((string)($data['phone'] ?? '')) ?: null,
        'l' => trim((string)($data['license_number'] ?? '')) ?: null,
        'v' => trim((string)($data['vehicle_no'] ?? '')) ?: null,
        'tid' => $transport['id'],
        't' => $transport['name'],
        'a' => trim((string)($data['address'] ?? '')) ?: null,
        's' => in_array(($data['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
    ];

    if ($id) {
        $pdo->prepare(
            'UPDATE dispatch_drivers SET driver_name=:n, phone=:p, license_number=:l, vehicle_no=:v,
             transport_company_id=:tid, transport_company=:t, address=:a, status=:s WHERE id=:id'
        )->execute($params + ['id' => $id]);

        return $id;
    }

    $pdo->prepare(
        'INSERT INTO dispatch_drivers (driver_name, phone, license_number, vehicle_no, transport_company_id, transport_company, address, status)
         VALUES (:n,:p,:l,:v,:tid,:t,:a,:s)'
    )->execute($params);

    return (int)$pdo->lastInsertId();
}

function dispatch_save_transport(PDO $pdo, array $data, ?int $id = null): int
{
    $name = trim((string)($data['company_name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Company name is required.');
    }
    $params = [
        'n' => $name,
        'cp' => trim((string)($data['contact_person'] ?? '')) ?: null,
        'p' => trim((string)($data['phone'] ?? '')) ?: null,
        'g' => trim((string)($data['gst_number'] ?? '')) ?: null,
        'a' => trim((string)($data['address'] ?? '')) ?: null,
        's' => in_array(($data['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
    ];

    if ($id) {
        $pdo->prepare(
            'UPDATE dispatch_transport_companies SET company_name=:n, contact_person=:cp, phone=:p,
             gst_number=:g, address=:a, status=:s WHERE id=:id'
        )->execute($params + ['id' => $id]);

        return $id;
    }

    $pdo->prepare(
        'INSERT INTO dispatch_transport_companies (company_name, contact_person, phone, gst_number, address, status)
         VALUES (:n,:cp,:p,:g,:a,:s)'
    )->execute($params);

    return (int)$pdo->lastInsertId();
}

function dispatch_list_transport(PDO $pdo): array
{
    return $pdo->query(
        "SELECT * FROM dispatch_transport_companies WHERE status = 'Active' ORDER BY company_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function dispatch_all_transport(PDO $pdo, string $search = '', string $statusFilter = ''): array
{
    $sql = 'SELECT * FROM dispatch_transport_companies WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (company_name LIKE :q OR contact_person LIKE :q OR phone LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }
    if ($statusFilter === 'Active' || $statusFilter === 'Inactive') {
        $sql .= ' AND status = :st';
        $params['st'] = $statusFilter;
    }
    $sql .= ' ORDER BY company_name ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dispatch_get_transport(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM dispatch_transport_companies WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function dispatch_get_by_id(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        'SELECT d.*, t.company_name AS transport_master_name, t.contact_person AS transport_contact, t.phone AS transport_phone
         FROM dispatch d
         LEFT JOIN dispatch_transport_companies t ON t.id = d.transport_company_id
         WHERE d.id = :id LIMIT 1'
    );
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function dispatch_slip_url(int $id, string $mode = 'view'): string
{
    $page = 'dispatch/slip';
    $qs = 'page=' . rawurlencode($page) . '&id=' . $id;
    if ($mode === 'print') {
        $qs .= '&print=1';
    }

    return 'index.php?' . $qs;
}

/** Active drivers for dispatch dropdown. */
function dispatch_list_drivers(PDO $pdo): array
{
    require_once __DIR__ . '/logistics_service.php';

    return logistics_list_drivers($pdo);
}

/** @return list<array<string, mixed>> */
function dispatch_all_drivers(PDO $pdo, string $search = '', string $statusFilter = ''): array
{
    $sql = 'SELECT * FROM dispatch_drivers WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (driver_name LIKE :q OR vehicle_no LIKE :q OR phone LIKE :q OR license_number LIKE :q OR transport_company LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }
    if ($statusFilter === 'Active' || $statusFilter === 'Inactive') {
        $sql .= ' AND status = :st';
        $params['st'] = $statusFilter;
    }
    $sql .= ' ORDER BY driver_name ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dispatch_get_driver(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM dispatch_drivers WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}
