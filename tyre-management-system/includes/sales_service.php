<?php
declare(strict_types=1);

require_once __DIR__ . '/production_service.php';
require_once __DIR__ . '/dispatch_service.php';
require_once __DIR__ . '/sales_finance.php';
require_once __DIR__ . '/erp_export.php';
require_once __DIR__ . '/sales_reports_data.php';
require_once __DIR__ . '/crm_ui.php';

const SALES_CUSTOMER_TYPES = ['Dealer', 'Distributor', 'Retailer', 'Industrial Buyer'];
const SALES_CUSTOMER_STATUSES = ['Active', 'Inactive'];
const SALES_ORDER_PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];
const SALES_ORDER_STATUSES = ['Pending', 'In Production', 'Ready', 'Partially Dispatched', 'Completed', 'Cancelled'];
const SALES_PAYMENT_MODES = ['Cash', 'Bank Transfer', 'UPI', 'Cheque'];
const SALES_PAYMENT_STATUSES = ['Paid', 'Partial', 'Pending', 'Overdue'];

function sales_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!dh_table_exists($pdo, 'sales_customers')) {
        return;
    }
    if (!dh_table_exists($pdo, 'sales_dispatch_queue')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sales_dispatch_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sales_order_id INT NOT NULL,
                sales_order_item_id INT NOT NULL,
                so_number VARCHAR(40) NOT NULL,
                customer_id INT NOT NULL,
                company_name VARCHAR(180) NOT NULL,
                tyre_type VARCHAR(120) NOT NULL,
                ordered_qty INT NOT NULL DEFAULT 0,
                available_qty INT NOT NULL DEFAULT 0,
                pending_qty INT NOT NULL DEFAULT 0,
                dispatchable_qty INT NOT NULL DEFAULT 0,
                stock_status ENUM('READY','PARTIAL STOCK','PRODUCTION REQUIRED') NOT NULL DEFAULT 'PRODUCTION REQUIRED',
                queue_status ENUM('Waiting for Production','Ready for Dispatch','Partially Ready','Completed') NOT NULL DEFAULT 'Waiting for Production',
                dispatch_readiness VARCHAR(60) NOT NULL DEFAULT 'Waiting for Production',
                expected_dispatch_date DATE NULL,
                order_date DATE NOT NULL,
                order_priority VARCHAR(20) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sdq_item (sales_order_item_id),
                INDEX idx_sdq_order (sales_order_id),
                INDEX idx_sdq_queue_status (queue_status),
                INDEX idx_sdq_stock_status (stock_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
    try {
        $pdo->exec('ALTER TABLE dispatch ADD COLUMN sales_customer_id INT NULL');
    } catch (Throwable) {
    }
}

function sales_current_user_label(): string
{
    $u = function_exists('current_user') ? current_user() : null;

    return trim((string)($u['full_name'] ?? $u['username'] ?? 'Sales'));
}

function sales_generate_customer_code(PDO $pdo): string
{
    $prefix = 'CUS-' . date('Y') . '-';
    $st = $pdo->prepare('SELECT COUNT(*) FROM sales_customers WHERE customer_code LIKE :p');
    $st->execute(['p' => $prefix . '%']);
    $seq = (int)$st->fetchColumn() + 1;

    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function sales_generate_so_number(PDO $pdo): string
{
    $prefix = 'SO-' . date('Ymd') . '-';
    $st = $pdo->prepare('SELECT COUNT(*) FROM sales_orders WHERE so_number LIKE :p');
    $st->execute(['p' => $prefix . '%']);
    $seq = (int)$st->fetchColumn() + 1;

    return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

function sales_generate_invoice_no(PDO $pdo): string
{
    for ($i = 0; $i < 50; $i++) {
        $inv = 'INV-' . date('Ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $st = $pdo->prepare('SELECT 1 FROM sales_invoices WHERE invoice_no = :i LIMIT 1');
        $st->execute(['i' => $inv]);
        if (!$st->fetchColumn()) {
            return $inv;
        }
    }

    return 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function sales_fg_stock(string $tyreType): int
{
    $pdo = Database::connection();

    return dispatch_fg_available($pdo, $tyreType);
}

function sales_stock_snapshot(PDO $pdo, string $tyreType, int $qtyRequired): array
{
    $available = sales_fg_stock($tyreType);

    return [
        'available' => $available,
        'required' => $qtyRequired,
        'shortfall' => max(0, $qtyRequired - $available),
        'fulfillment_status' => $qtyRequired <= $available ? 'Ready for Dispatch' : 'Production Required',
    ];
}

function sales_format_money(float $n): string
{
    return '₹' . number_format($n, 2);
}

function sales_status_badge(string $status): array
{
    $class = match ($status) {
        'Ready', 'Ready for Dispatch', 'Paid' => 'sales-badge sales-badge--ok',
        'In Production', 'Production Required', 'Partial', 'Partially Dispatched' => 'sales-badge sales-badge--warn',
        'Completed' => 'sales-badge sales-badge--done',
        'Cancelled', 'Inactive', 'Overdue' => 'sales-badge sales-badge--danger',
        'Pending' => 'sales-badge sales-badge--muted',
        'Urgent', 'High' => 'sales-badge sales-badge--urgent',
        default => 'sales-badge',
    };

    return ['class' => $class, 'label' => $status];
}

/** Order list / workflow status badge (ERP sales orders). */
function sales_order_status_badge(string $status): array
{
    $map = match ($status) {
        'Ready' => ['so-badge so-badge--ready', 'READY'],
        'In Production' => ['so-badge so-badge--production', 'IN PRODUCTION'],
        'Partially Dispatched' => ['so-badge so-badge--partial', 'PARTIAL DISPATCH'],
        'Completed' => ['so-badge so-badge--dispatched', 'DISPATCHED'],
        'Pending' => ['so-badge so-badge--pending', 'PENDING'],
        'Cancelled' => ['so-badge so-badge--cancelled', 'CANCELLED'],
        default => ['so-badge so-badge--pending', strtoupper($status)],
    };

    return ['class' => $map[0], 'label' => $map[1]];
}

/** Stock readiness label for order list. */
function sales_order_stock_badge(string $stockStatus): array
{
    $map = match ($stockStatus) {
        'READY', 'Ready for Dispatch' => ['so-badge so-badge--ready', 'READY'],
        'PARTIAL STOCK', 'Partial Stock' => ['so-badge so-badge--partial', 'PARTIAL STOCK'],
        'PRODUCTION REQUIRED', 'Production Required' => ['so-badge so-badge--production', 'PRODUCTION REQUIRED'],
        'Mixed' => ['so-badge so-badge--partial', 'MIXED'],
        default => ['so-badge so-badge--pending', 'PENDING'],
    };

    return ['class' => $map[0], 'label' => $map[1]];
}

/**
 * Live stock state for an open order line (supports partial dispatch).
 *
 * @return array{stock_status: string, dispatchable_qty: int, fulfillment_status: string, queue_status: string, dispatch_readiness: string}
 */
function sales_line_stock_state(int $pendingQty, int $liveStock): array
{
    if ($pendingQty < 1) {
        return [
            'stock_status' => 'READY',
            'dispatchable_qty' => 0,
            'fulfillment_status' => 'Ready for Dispatch',
            'queue_status' => 'Completed',
            'dispatch_readiness' => 'Fully Dispatched',
        ];
    }
    $dispatchable = min($pendingQty, max(0, $liveStock));
    if ($dispatchable >= $pendingQty) {
        return [
            'stock_status' => 'READY',
            'dispatchable_qty' => $pendingQty,
            'fulfillment_status' => 'Ready for Dispatch',
            'queue_status' => 'Ready for Dispatch',
            'dispatch_readiness' => 'Ready for Dispatch',
        ];
    }
    if ($dispatchable > 0) {
        return [
            'stock_status' => 'PARTIAL STOCK',
            'dispatchable_qty' => $dispatchable,
            'fulfillment_status' => 'Production Required',
            'queue_status' => 'Partially Ready',
            'dispatch_readiness' => 'Partial — ' . $dispatchable . ' of ' . $pendingQty . ' ready',
        ];
    }

    return [
        'stock_status' => 'PRODUCTION REQUIRED',
        'dispatchable_qty' => 0,
        'fulfillment_status' => 'Production Required',
        'queue_status' => 'Waiting for Production',
        'dispatch_readiness' => 'Waiting for Production',
    ];
}

/** ERP workflow stage label for display (CRM order lifecycle). */
function sales_order_workflow_stage(PDO $pdo, array $order): array
{
    $status = (string)($order['status'] ?? 'Pending');
    if ($status === 'Cancelled') {
        return ['stage' => 'CANCELLED', 'label' => 'Cancelled', 'class' => 'so-flow-stage so-flow-stage--cancelled'];
    }

    $orderId = (int)($order['id'] ?? 0);
    $invId = $orderId > 0 ? sales_invoice_id_for_order($pdo, $orderId) : null;
    if ($invId) {
        $st = $pdo->prepare('SELECT payment_status FROM sales_invoices WHERE id = :id LIMIT 1');
        $st->execute(['id' => $invId]);
        $ps = (string)$st->fetchColumn();
        if ($ps === 'Paid') {
            return ['stage' => 'PAID', 'label' => 'Paid', 'class' => 'so-flow-stage so-flow-stage--paid'];
        }

        return ['stage' => 'PAYMENT PENDING', 'label' => 'Payment Pending', 'class' => 'so-flow-stage so-flow-stage--payment'];
    }

    $totalDispatched = 0;
    $totalOrdered = 0;
    foreach ($order['items'] ?? [] as $it) {
        $totalOrdered += (int)($it['qty_ordered'] ?? 0);
        $totalDispatched += (int)($it['qty_dispatched'] ?? 0);
    }

    if ($status === 'Completed' || ($totalOrdered > 0 && $totalDispatched >= $totalOrdered)) {
        return ['stage' => 'DISPATCHED', 'label' => 'Dispatched', 'class' => 'so-flow-stage so-flow-stage--dispatched'];
    }
    if ($status === 'Partially Dispatched' || $totalDispatched > 0) {
        return ['stage' => 'PARTIALLY DISPATCHED', 'label' => 'Partially Dispatched', 'class' => 'so-flow-stage so-flow-stage--partial'];
    }
    if ($status === 'Ready') {
        return ['stage' => 'READY FOR DISPATCH', 'label' => 'Ready for Dispatch', 'class' => 'so-flow-stage so-flow-stage--ready'];
    }
    if ($status === 'In Production') {
        return ['stage' => 'WAITING FOR PRODUCTION', 'label' => 'Waiting for Production', 'class' => 'so-flow-stage so-flow-stage--production'];
    }

    return ['stage' => 'STOCK CHECK', 'label' => 'Stock Check', 'class' => 'so-flow-stage so-flow-stage--check'];
}

function sales_priority_badge(string $priority): array
{
    $map = match ($priority) {
        'Urgent' => ['so-badge so-badge--urgent', 'URGENT'],
        'High' => ['so-badge so-badge--high', 'HIGH'],
        'Low' => ['so-badge so-badge--muted', 'LOW'],
        default => ['so-badge so-badge--muted', 'MEDIUM'],
    };

    return ['class' => $map[0], 'label' => $map[1]];
}

function sales_invoice_id_for_order(PDO $pdo, int $orderId): ?int
{
    if ($orderId < 1 || !dh_table_exists($pdo, 'sales_invoices')) {
        return null;
    }
    $st = $pdo->prepare('SELECT id FROM sales_invoices WHERE order_id = :oid ORDER BY id DESC LIMIT 1');
    $st->execute(['oid' => $orderId]);
    $id = $st->fetchColumn();

    return $id !== false ? (int)$id : null;
}

/** @return list<array<string, mixed>> */
function sales_list_customers(PDO $pdo, array $filters = []): array
{
    sales_ensure_schema($pdo);
    $sql = 'SELECT c.*,
        COALESCE((
            SELECT SUM(i.total_amount - i.amount_paid)
            FROM sales_invoices i
            WHERE i.customer_id = c.id AND i.payment_status IN (\'Pending\',\'Partial\',\'Overdue\')
        ), 0) AS pending_amount
        FROM sales_customers c WHERE 1=1';
    $params = [];
    if (!empty($filters['status']) && in_array($filters['status'], SALES_CUSTOMER_STATUSES, true)) {
        $sql .= ' AND c.status = :st';
        $params['st'] = $filters['status'];
    }
    if (!empty($filters['type']) && in_array($filters['type'], SALES_CUSTOMER_TYPES, true)) {
        $sql .= ' AND c.customer_type = :tp';
        $params['tp'] = $filters['type'];
    }
    $searchQ = trim((string)($filters['q'] ?? ''));
    if ($searchQ !== '') {
        $sql .= ' AND (c.company_name LIKE :q OR c.customer_code LIKE :q OR c.contact_person LIKE :q
            OR c.phone LIKE :q OR c.gst_number LIKE :q OR c.email LIKE :q OR c.city LIKE :q OR c.pan_number LIKE :q)';
        $params['q'] = '%' . $searchQ . '%';
    }
    $sql .= ' ORDER BY c.company_name ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function sales_get_customer(PDO $pdo, int $id): ?array
{
    sales_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM sales_customers WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function sales_save_customer(PDO $pdo, array $data, ?int $id = null): int
{
    sales_ensure_schema($pdo);
    $company = trim((string)($data['company_name'] ?? ''));
    if ($company === '') {
        throw new InvalidArgumentException('Company name is required.');
    }
    $type = trim((string)($data['customer_type'] ?? 'Dealer'));
    if (!in_array($type, SALES_CUSTOMER_TYPES, true)) {
        throw new InvalidArgumentException('Invalid customer type.');
    }
    $status = trim((string)($data['status'] ?? 'Active'));
    if (!in_array($status, SALES_CUSTOMER_STATUSES, true)) {
        $status = 'Active';
    }

    $fields = [
        'company_name' => $company,
        'customer_type' => $type,
        'contact_person' => trim((string)($data['contact_person'] ?? '')) ?: null,
        'gst_number' => trim((string)($data['gst_number'] ?? '')) ?: null,
        'pan_number' => trim((string)($data['pan_number'] ?? '')) ?: null,
        'phone' => trim((string)($data['phone'] ?? '')) ?: null,
        'email' => trim((string)($data['email'] ?? '')) ?: null,
        'billing_address' => trim((string)($data['billing_address'] ?? '')) ?: null,
        'shipping_address' => trim((string)($data['shipping_address'] ?? '')) ?: null,
        'city' => trim((string)($data['city'] ?? '')) ?: null,
        'state' => trim((string)($data['state'] ?? '')) ?: null,
        'pincode' => trim((string)($data['pincode'] ?? '')) ?: null,
        'credit_limit' => max(0, (float)($data['credit_limit'] ?? 0)),
        'payment_terms' => trim((string)($data['payment_terms'] ?? '')) ?: null,
        'status' => $status,
        'remarks' => trim((string)($data['remarks'] ?? '')) ?: null,
    ];

    if ($id !== null && $id > 0) {
        $pdo->prepare(
            'UPDATE sales_customers SET company_name=:company_name, customer_type=:customer_type, contact_person=:contact_person,
             gst_number=:gst_number, pan_number=:pan_number, phone=:phone, email=:email, billing_address=:billing_address,
             shipping_address=:shipping_address, city=:city, state=:state, pincode=:pincode, credit_limit=:credit_limit,
             payment_terms=:payment_terms, status=:status, remarks=:remarks WHERE id=:id'
        )->execute($fields + ['id' => $id]);

        return $id;
    }

    $code = sales_generate_customer_code($pdo);
    $pdo->prepare(
        'INSERT INTO sales_customers (customer_code, company_name, customer_type, contact_person, gst_number, pan_number,
         phone, email, billing_address, shipping_address, city, state, pincode, credit_limit, payment_terms, status, remarks)
         VALUES (:code, :company_name, :customer_type, :contact_person, :gst_number, :pan_number, :phone, :email,
         :billing_address, :shipping_address, :city, :state, :pincode, :credit_limit, :payment_terms, :status, :remarks)'
    )->execute($fields + ['code' => $code]);

    return (int)$pdo->lastInsertId();
}

function sales_customer_pending(PDO $pdo, int $customerId): float
{
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(total_amount - amount_paid), 0) FROM sales_invoices
         WHERE customer_id = :cid AND payment_status IN ('Pending','Partial','Overdue')"
    );
    $st->execute(['cid' => $customerId]);

    return (float)$st->fetchColumn();
}

/** @return list<array<string, mixed>> */
function sales_order_items(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare('SELECT * FROM sales_order_items WHERE order_id = :oid ORDER BY line_no ASC, id ASC');
    $st->execute(['oid' => $orderId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function sales_get_order(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        'SELECT o.*, c.company_name, c.customer_code, c.contact_person, c.phone, c.gst_number
         FROM sales_orders o
         INNER JOIN sales_customers c ON c.id = o.customer_id
         WHERE o.id = :id LIMIT 1'
    );
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['items'] = sales_order_items($pdo, $id);

    return $row;
}

/** @return list<array<string, mixed>> */
function sales_list_orders(PDO $pdo, array $filters = []): array
{
    sales_ensure_schema($pdo);
    $sql = 'SELECT o.*, c.company_name, c.customer_code,
        (SELECT GROUP_CONCAT(DISTINCT oi.tyre_type ORDER BY oi.line_no SEPARATOR ", ")
         FROM sales_order_items oi WHERE oi.order_id = o.id) AS tyre_types,
        (SELECT COALESCE(SUM(oi.qty_ordered), 0) FROM sales_order_items oi WHERE oi.order_id = o.id) AS total_qty,
        (SELECT CASE
            WHEN COUNT(*) = 0 THEN \'Pending\'
            WHEN SUM(CASE WHEN oi.fulfillment_status = \'Production Required\' THEN 1 ELSE 0 END) > 0
                 AND SUM(CASE WHEN oi.fulfillment_status = \'Ready for Dispatch\' THEN 1 ELSE 0 END) > 0 THEN \'Mixed\'
            WHEN SUM(CASE WHEN oi.fulfillment_status = \'Production Required\' THEN 1 ELSE 0 END) > 0 THEN \'Production Required\'
            ELSE \'Ready for Dispatch\'
         END FROM sales_order_items oi WHERE oi.order_id = o.id) AS stock_status
        FROM sales_orders o
        INNER JOIN sales_customers c ON c.id = o.customer_id
        WHERE 1=1';
    $params = [];
    if (!empty($filters['customer_id'])) {
        $sql .= ' AND o.customer_id = :cid';
        $params['cid'] = (int)$filters['customer_id'];
    }
    if (!empty($filters['status']) && in_array($filters['status'], SALES_ORDER_STATUSES, true)) {
        $sql .= ' AND o.status = :st';
        $params['st'] = $filters['status'];
    }
    if (!empty($filters['priority']) && in_array($filters['priority'], SALES_ORDER_PRIORITIES, true)) {
        $sql .= ' AND o.priority = :pr';
        $params['pr'] = $filters['priority'];
    }
    if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['from'])) {
        $sql .= ' AND o.order_date >= :df';
        $params['df'] = $filters['from'];
    }
    if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['to'])) {
        $sql .= ' AND o.order_date <= :dt';
        $params['dt'] = $filters['to'];
    }
    if (!empty($filters['tyre_type'])) {
        $sql .= ' AND EXISTS (SELECT 1 FROM sales_order_items oi WHERE oi.order_id = o.id AND oi.tyre_type = :tt)';
        $params['tt'] = $filters['tyre_type'];
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $sql .= ' AND (o.so_number LIKE :q OR c.company_name LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $dispatchFilter = (string)($filters['dispatch_status'] ?? '');
    if ($dispatchFilter !== '') {
        $map = [
            'ready' => ['Ready'],
            'production' => ['In Production'],
            'partial' => ['Partially Dispatched'],
            'dispatched' => ['Completed'],
            'pending' => ['Pending'],
        ];
        if (isset($map[$dispatchFilter])) {
            $placeholders = [];
            foreach ($map[$dispatchFilter] as $i => $stVal) {
                $key = 'ds' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $stVal;
            }
            $sql .= ' AND o.status IN (' . implode(',', $placeholders) . ')';
        }
    }
    $sql .= ' ORDER BY o.order_date DESC, o.id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['dispatch_status'] = (string)($row['status'] ?? 'Pending');
        $types = trim((string)($row['tyre_types'] ?? ''));
        $row['tyre_types_short'] = $types === '' ? '—' : (strlen($types) > 42 ? substr($types, 0, 40) . '…' : $types);
    }
    unset($row);

    return $rows;
}

/** Refresh line fulfillment from live FG stock and sync dispatch queue. */
function sales_refresh_order_fulfillment(PDO $pdo, int $orderId): void
{
    sales_ensure_schema($pdo);
    $order = sales_get_order($pdo, $orderId);
    if (!$order || in_array((string)$order['status'], ['Cancelled', 'Completed'], true)) {
        return;
    }

    $upd = $pdo->prepare(
        'UPDATE sales_order_items SET stock_available = :stk, fulfillment_status = :ful WHERE id = :id'
    );
    foreach ($order['items'] as $it) {
        $pending = (int)$it['qty_ordered'] - (int)$it['qty_dispatched'];
        if ($pending < 1) {
            continue;
        }
        $live = sales_fg_stock((string)$it['tyre_type']);
        $state = sales_line_stock_state($pending, $live);
        $upd->execute([
            'stk' => $live,
            'ful' => $state['fulfillment_status'],
            'id' => (int)$it['id'],
        ]);
    }
    sales_sync_dispatch_queue($pdo, $orderId);
    sales_recalculate_order_status($pdo, $orderId);
}

/** When FG inventory changes (production, QC, dispatch), refresh open CRM orders. */
function sales_on_inventory_changed(PDO $pdo, ?string $tyreType = null): void
{
    if (!dh_table_exists($pdo, 'sales_orders')) {
        return;
    }
    $sql = "SELECT DISTINCT o.id FROM sales_orders o
            INNER JOIN sales_order_items oi ON oi.order_id = o.id
            WHERE o.status NOT IN ('Completed','Cancelled')
              AND oi.qty_ordered > oi.qty_dispatched";
    $params = [];
    if ($tyreType !== null && $tyreType !== '') {
        $sql .= ' AND oi.tyre_type = :tt';
        $params['tt'] = $tyreType;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($ids as $oid) {
        sales_refresh_order_fulfillment($pdo, (int)$oid);
    }
}

/** Upsert dispatch queue rows for all open lines on an order. */
function sales_sync_dispatch_queue(PDO $pdo, int $orderId): void
{
    sales_ensure_schema($pdo);
    if (!dh_table_exists($pdo, 'sales_dispatch_queue')) {
        return;
    }
    $order = sales_get_order($pdo, $orderId);
    if (!$order) {
        return;
    }

    $del = $pdo->prepare('DELETE FROM sales_dispatch_queue WHERE sales_order_id = :oid');
    $del->execute(['oid' => $orderId]);

    if (in_array((string)$order['status'], ['Cancelled', 'Completed'], true)) {
        return;
    }

    $ins = $pdo->prepare(
        'INSERT INTO sales_dispatch_queue (
            sales_order_id, sales_order_item_id, so_number, customer_id, company_name, tyre_type,
            ordered_qty, available_qty, pending_qty, dispatchable_qty, stock_status, queue_status,
            dispatch_readiness, expected_dispatch_date, order_date, order_priority
        ) VALUES (
            :oid, :iid, :so, :cid, :cn, :tt, :oq, :av, :pq, :dq, :ss, :qs, :dr, :edd, :od, :pr
        )'
    );

    foreach ($order['items'] as $it) {
        $pending = (int)$it['qty_ordered'] - (int)$it['qty_dispatched'];
        if ($pending < 1) {
            continue;
        }
        $live = sales_fg_stock((string)$it['tyre_type']);
        $state = sales_line_stock_state($pending, $live);
        $ins->execute([
            'oid' => $orderId,
            'iid' => (int)$it['id'],
            'so' => (string)$order['so_number'],
            'cid' => (int)$order['customer_id'],
            'cn' => (string)$order['company_name'],
            'tt' => (string)$it['tyre_type'],
            'oq' => (int)$it['qty_ordered'],
            'av' => $live,
            'pq' => $pending,
            'dq' => $state['dispatchable_qty'],
            'ss' => $state['stock_status'],
            'qs' => $state['queue_status'],
            'dr' => $state['dispatch_readiness'],
            'edd' => $order['delivery_date'] ?? null,
            'od' => (string)$order['order_date'],
            'pr' => (string)$order['priority'],
        ]);
    }
}

function sales_recalculate_order_status(PDO $pdo, int $orderId): void
{
    $items = sales_order_items($pdo, $orderId);
    if ($items === []) {
        return;
    }
    $st = $pdo->prepare('SELECT status FROM sales_orders WHERE id = :id LIMIT 1');
    $st->execute(['id' => $orderId]);
    $cur = (string)$st->fetchColumn();
    if ($cur === 'Cancelled') {
        return;
    }

    $totalOrdered = 0;
    $totalDispatched = 0;
    $anyCanDispatch = false;
    $allFullyReady = true;
    $anyWaiting = false;
    foreach ($items as $it) {
        $oq = (int)$it['qty_ordered'];
        $dq = (int)$it['qty_dispatched'];
        $pending = $oq - $dq;
        $totalOrdered += $oq;
        $totalDispatched += $dq;
        if ($pending < 1) {
            continue;
        }
        $live = sales_fg_stock((string)$it['tyre_type']);
        $state = sales_line_stock_state($pending, $live);
        if ($state['dispatchable_qty'] > 0) {
            $anyCanDispatch = true;
        }
        if ($state['stock_status'] !== 'READY') {
            $allFullyReady = false;
        }
        if ($state['stock_status'] === 'PRODUCTION REQUIRED') {
            $anyWaiting = true;
        }
    }

    $newStatus = 'Pending';
    if ($totalOrdered > 0 && $totalDispatched >= $totalOrdered) {
        $newStatus = 'Completed';
    } elseif ($totalDispatched > 0) {
        $newStatus = 'Partially Dispatched';
    } elseif ($anyWaiting && !$anyCanDispatch) {
        $newStatus = 'In Production';
    } elseif ($allFullyReady && $anyCanDispatch) {
        $newStatus = 'Ready';
    } elseif ($anyCanDispatch) {
        $newStatus = 'Ready';
    } elseif ($anyWaiting) {
        $newStatus = 'In Production';
    }

    $pdo->prepare('UPDATE sales_orders SET status = :st WHERE id = :id')->execute(['st' => $newStatus, 'id' => $orderId]);
}

function sales_save_order(PDO $pdo, array $data, ?int $id = null): int
{
    sales_ensure_schema($pdo);
    $customerId = (int)($data['customer_id'] ?? 0);
    if ($customerId < 1 || !sales_get_customer($pdo, $customerId)) {
        throw new InvalidArgumentException('Valid customer is required.');
    }
    $orderDate = trim((string)($data['order_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $orderDate)) {
        throw new InvalidArgumentException('Valid order date is required.');
    }
    $deliveryDate = trim((string)($data['delivery_date'] ?? ''));
    $deliveryDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $deliveryDate) ? $deliveryDate : null;
    $priority = trim((string)($data['priority'] ?? 'Medium'));
    if (!in_array($priority, SALES_ORDER_PRIORITIES, true)) {
        $priority = 'Medium';
    }
    $discountOrder = max(0, (float)($data['discount_amount'] ?? 0));
    $remarks = trim((string)($data['remarks'] ?? '')) ?: null;
    $paymentTerms = trim((string)($data['payment_terms'] ?? '')) ?: null;

    $tyreTypes = $data['tyre_type'] ?? [];
    $qtys = $data['qty'] ?? [];
    $rates = $data['rate'] ?? [];
    $gsts = $data['gst_percent'] ?? [];
    $discounts = $data['line_discount'] ?? [];
    if (!is_array($tyreTypes)) {
        $tyreTypes = [$tyreTypes];
        $qtys = [$qtys];
        $rates = [$rates];
        $gsts = [$gsts];
        $discounts = [$discounts];
    }

    $lines = [];
    $subtotal = 0.0;
    $gstTotal = 0.0;
    $lineNo = 0;
    foreach ($tyreTypes as $i => $tt) {
        $tt = trim((string)$tt);
        $qty = (int)($qtys[$i] ?? 0);
        if ($tt === '' || $qty < 1) {
            continue;
        }
        if (!in_array($tt, TYRE_TYPES, true)) {
            throw new InvalidArgumentException('Invalid tyre type: ' . $tt);
        }
        $rate = max(0, (float)($rates[$i] ?? 0));
        $gstPct = max(0, min(100, (float)($gsts[$i] ?? 18)));
        $lineDisc = max(0, (float)($discounts[$i] ?? 0));
        $lineSub = max(0, ($qty * $rate) - $lineDisc);
        $lineGst = round($lineSub * ($gstPct / 100), 2);
        $lineTotal = $lineSub + $lineGst;
        $stock = sales_fg_stock($tt);
        $fulfillment = $qty <= $stock ? 'Ready for Dispatch' : 'Production Required';
        $lineNo++;
        $lines[] = [
            'line_no' => $lineNo,
            'tyre_type' => $tt,
            'qty_ordered' => $qty,
            'rate' => $rate,
            'gst_percent' => $gstPct,
            'discount_amount' => $lineDisc,
            'line_subtotal' => $lineSub,
            'line_gst' => $lineGst,
            'line_total' => $lineTotal,
            'stock_available' => $stock,
            'fulfillment_status' => $fulfillment,
        ];
        $subtotal += $lineSub;
        $gstTotal += $lineGst;
    }
    if ($lines === []) {
        throw new InvalidArgumentException('Add at least one order line with quantity.');
    }
    $totalAmount = max(0, $subtotal + $gstTotal - $discountOrder);

    $pdo->beginTransaction();
    try {
        if ($id !== null && $id > 0) {
            $existing = sales_get_order($pdo, $id);
            if (!$existing || in_array((string)$existing['status'], ['Completed', 'Cancelled'], true)) {
                throw new InvalidArgumentException('Order cannot be edited in current status.');
            }
            $dispatched = array_sum(array_map(static fn($it) => (int)$it['qty_dispatched'], $existing['items']));
            if ($dispatched > 0) {
                throw new InvalidArgumentException('Order has dispatches — create a new order for changes.');
            }
            $pdo->prepare(
                'UPDATE sales_orders SET customer_id=:cid, order_date=:od, delivery_date=:dd, priority=:pr,
                 payment_terms=:pt, discount_amount=:da, subtotal=:sub, gst_total=:gst, total_amount=:tot, remarks=:rm WHERE id=:id'
            )->execute([
                'cid' => $customerId,
                'od' => $orderDate,
                'dd' => $deliveryDate,
                'pr' => $priority,
                'pt' => $paymentTerms,
                'da' => $discountOrder,
                'sub' => $subtotal,
                'gst' => $gstTotal,
                'tot' => $totalAmount,
                'rm' => $remarks,
                'id' => $id,
            ]);
            $pdo->prepare('DELETE FROM sales_order_items WHERE order_id = :oid')->execute(['oid' => $id]);
            $orderId = $id;
        } else {
            $so = sales_generate_so_number($pdo);
            $pdo->prepare(
                'INSERT INTO sales_orders (so_number, customer_id, order_date, delivery_date, priority, status,
                 payment_terms, discount_amount, subtotal, gst_total, total_amount, remarks)
                 VALUES (:so, :cid, :od, :dd, :pr, \'Pending\', :pt, :da, :sub, :gst, :tot, :rm)'
            )->execute([
                'so' => $so,
                'cid' => $customerId,
                'od' => $orderDate,
                'dd' => $deliveryDate,
                'pr' => $priority,
                'pt' => $paymentTerms,
                'da' => $discountOrder,
                'sub' => $subtotal,
                'gst' => $gstTotal,
                'tot' => $totalAmount,
                'rm' => $remarks,
            ]);
            $orderId = (int)$pdo->lastInsertId();
        }

        $ins = $pdo->prepare(
            'INSERT INTO sales_order_items (order_id, line_no, tyre_type, qty_ordered, rate, gst_percent, discount_amount,
             line_subtotal, line_gst, line_total, stock_available, fulfillment_status)
             VALUES (:oid, :ln, :tt, :qty, :rate, :gst, :disc, :sub, :lgst, :tot, :stk, :ful)'
        );
        foreach ($lines as $ln) {
            $ins->execute([
                'oid' => $orderId,
                'ln' => $ln['line_no'],
                'tt' => $ln['tyre_type'],
                'qty' => $ln['qty_ordered'],
                'rate' => $ln['rate'],
                'gst' => $ln['gst_percent'],
                'disc' => $ln['discount_amount'],
                'sub' => $ln['line_subtotal'],
                'lgst' => $ln['line_gst'],
                'tot' => $ln['line_total'],
                'stk' => $ln['stock_available'],
                'ful' => $ln['fulfillment_status'],
            ]);
        }
        sales_refresh_order_fulfillment($pdo, $orderId);
        $pdo->commit();

        return $orderId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function sales_cancel_order(PDO $pdo, int $orderId): void
{
    $order = sales_get_order($pdo, $orderId);
    if (!$order) {
        throw new InvalidArgumentException('Order not found.');
    }
    $dispatched = 0;
    foreach ($order['items'] as $it) {
        $dispatched += (int)$it['qty_dispatched'];
    }
    if ($dispatched > 0) {
        throw new InvalidArgumentException('Cannot cancel — tyres already dispatched.');
    }
    $pdo->prepare("UPDATE sales_orders SET status = 'Cancelled' WHERE id = :id")->execute(['id' => $orderId]);
    if (dh_table_exists($pdo, 'sales_dispatch_queue')) {
        $pdo->prepare('DELETE FROM sales_dispatch_queue WHERE sales_order_id = :id')->execute(['id' => $orderId]);
    }
}

/** Link dispatch to sales order line; update qty_dispatched and status. */
function sales_apply_dispatch(PDO $pdo, int $dispatchId, int $salesOrderId, int $salesOrderItemId, int $qty): void
{
    if ($salesOrderId < 1 || $salesOrderItemId < 1 || $qty < 1) {
        return;
    }
    if (!dh_table_exists($pdo, 'sales_dispatch_allocations')) {
        return;
    }

    $st = $pdo->prepare(
        'SELECT oi.*, o.status AS order_status FROM sales_order_items oi
         INNER JOIN sales_orders o ON o.id = oi.order_id
         WHERE oi.id = :id AND oi.order_id = :oid LIMIT 1'
    );
    $st->execute(['id' => $salesOrderItemId, 'oid' => $salesOrderId]);
    $item = $st->fetch(PDO::FETCH_ASSOC);
    if (!$item || (string)$item['order_status'] === 'Cancelled') {
        throw new InvalidArgumentException('Invalid sales order line for dispatch.');
    }
    $remaining = (int)$item['qty_ordered'] - (int)$item['qty_dispatched'];
    if ($qty > $remaining) {
        throw new InvalidArgumentException('Dispatch qty exceeds remaining order qty (' . $remaining . ').');
    }

    $pdo->prepare(
        'INSERT INTO sales_dispatch_allocations (sales_order_id, sales_order_item_id, dispatch_id, qty)
         VALUES (:oid, :iid, :did, :qty)'
    )->execute([
        'oid' => $salesOrderId,
        'iid' => $salesOrderItemId,
        'did' => $dispatchId,
        'qty' => $qty,
    ]);

    $pdo->prepare('UPDATE sales_order_items SET qty_dispatched = qty_dispatched + :q WHERE id = :id')
        ->execute(['q' => $qty, 'id' => $salesOrderItemId]);

    $pdo->prepare('UPDATE dispatch SET sales_order_id = :oid, sales_order_item_id = :iid WHERE id = :did')
        ->execute(['oid' => $salesOrderId, 'iid' => $salesOrderItemId, 'did' => $dispatchId]);

    sales_refresh_order_fulfillment($pdo, $salesOrderId);
    sales_create_invoice_from_dispatch($pdo, $dispatchId, $salesOrderId);
    sales_on_inventory_changed($pdo, (string)$item['tyre_type']);
}

function sales_create_invoice_from_dispatch(PDO $pdo, int $dispatchId, int $salesOrderId): void
{
    $dst = $pdo->prepare('SELECT * FROM dispatch WHERE id = :id LIMIT 1');
    $dst->execute(['id' => $dispatchId]);
    $d = $dst->fetch(PDO::FETCH_ASSOC);
    if (!$d) {
        return;
    }

    $order = sales_get_order($pdo, $salesOrderId);
    if (!$order) {
        return;
    }

    $itemId = (int)($d['sales_order_item_id'] ?? 0);
    $rate = 0.0;
    $gstPct = 18.0;
    foreach ($order['items'] as $it) {
        if ((int)$it['id'] === $itemId) {
            $rate = (float)$it['rate'];
            $gstPct = (float)$it['gst_percent'];
            break;
        }
    }

    $qty = (int)$d['qty'];
    $lineSub = round($qty * $rate, 2);
    $lineGst = round($lineSub * ($gstPct / 100), 2);
    $lineTotal = $lineSub + $lineGst;

    $chk = $pdo->prepare('SELECT id FROM sales_invoices WHERE order_id = :oid AND remarks LIKE :rm LIMIT 1');
    $chk->execute(['oid' => $salesOrderId, 'rm' => '%dispatch:' . $dispatchId . '%']);
    if ($chk->fetchColumn()) {
        return;
    }

    $invNo = sales_generate_invoice_no($pdo);
    $due = date('Y-m-d', strtotime('+30 days'));
    $pdo->prepare(
        'INSERT INTO sales_invoices (invoice_no, customer_id, order_id, invoice_date, due_date, subtotal, gst_total,
         total_amount, amount_paid, payment_status, remarks)
         VALUES (:no, :cid, :oid, :idt, :due, :sub, :gst, :tot, 0, \'Pending\', :rm)'
    )->execute([
        'no' => $invNo,
        'cid' => (int)$order['customer_id'],
        'oid' => $salesOrderId,
        'idt' => (string)($d['dispatch_date'] ?? date('Y-m-d')),
        'due' => $due,
        'sub' => $lineSub,
        'gst' => $lineGst,
        'tot' => $lineTotal,
        'rm' => 'Auto from dispatch:' . $dispatchId . ' (' . (string)$d['dispatch_code'] . ')',
    ]);
    $invId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO sales_invoice_items (invoice_id, order_item_id, tyre_type, qty, rate, gst_percent, line_subtotal, line_gst, line_total)
         VALUES (:iid, :oiid, :tt, :qty, :rate, :gst, :sub, :lgst, :tot)'
    )->execute([
        'iid' => $invId,
        'oiid' => $itemId > 0 ? $itemId : null,
        'tt' => (string)$d['tyre_type'],
        'qty' => $qty,
        'rate' => $rate,
        'gst' => $gstPct,
        'sub' => $lineSub,
        'lgst' => $lineGst,
        'tot' => $lineTotal,
    ]);
}

/** @return list<array<string, mixed>> */
function sales_list_invoices(PDO $pdo, array $filters = []): array
{
    $sql = 'SELECT i.*, c.company_name, c.customer_code, o.so_number
        FROM sales_invoices i
        INNER JOIN sales_customers c ON c.id = i.customer_id
        LEFT JOIN sales_orders o ON o.id = i.order_id
        WHERE 1=1';
    $params = [];
    if (!empty($filters['customer_id'])) {
        $sql .= ' AND i.customer_id = :cid';
        $params['cid'] = (int)$filters['customer_id'];
    }
    if (!empty($filters['payment_status']) && in_array($filters['payment_status'], SALES_PAYMENT_STATUSES, true)) {
        $sql .= ' AND i.payment_status = :ps';
        $params['ps'] = $filters['payment_status'];
    }
    if (!empty($filters['from'])) {
        $sql .= ' AND i.invoice_date >= :df';
        $params['df'] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $sql .= ' AND i.invoice_date <= :dt';
        $params['dt'] = $filters['to'];
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $sql .= ' AND (i.invoice_no LIKE :q OR c.company_name LIKE :q OR o.so_number LIKE :q OR i.remarks LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY i.invoice_date DESC, i.id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function sales_get_invoice(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        'SELECT i.*, c.company_name, c.customer_code, c.contact_person, c.gst_number, c.billing_address, c.city, c.state,
         c.phone, c.email, o.so_number
         FROM sales_invoices i
         INNER JOIN sales_customers c ON c.id = i.customer_id
         LEFT JOIN sales_orders o ON o.id = i.order_id
         WHERE i.id = :id LIMIT 1'
    );
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $ist = $pdo->prepare('SELECT * FROM sales_invoice_items WHERE invoice_id = :id ORDER BY id ASC');
    $ist->execute(['id' => $id]);
    $row['items'] = $ist->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return $row;
}

function sales_refresh_invoice_payment_status(PDO $pdo, int $invoiceId): void
{
    $st = $pdo->prepare('SELECT total_amount, amount_paid, due_date FROM sales_invoices WHERE id = :id');
    $st->execute(['id' => $invoiceId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }
    $total = (float)$row['total_amount'];
    $paid = (float)$row['amount_paid'];
    $due = (string)($row['due_date'] ?? '');
    $status = 'Pending';
    if (sales_is_invoice_fully_paid($total, $paid)) {
        $status = 'Paid';
        $pdo->prepare('UPDATE sales_invoices SET amount_paid = total_amount, payment_status = :st WHERE id = :id')
            ->execute(['st' => $status, 'id' => $invoiceId]);

        return;
    }
    if ($paid > sales_payment_tolerance()) {
        $status = 'Partial';
    } elseif ($due !== '' && $due < date('Y-m-d')) {
        $status = 'Overdue';
    }
    $pdo->prepare('UPDATE sales_invoices SET payment_status = :st WHERE id = :id')
        ->execute(['st' => $status, 'id' => $invoiceId]);
}

/** Snap penny balances (e.g. ₹0.01 left) to Paid after rounding. */
function sales_reconcile_invoice_balances(PDO $pdo): void
{
    if (!dh_table_exists($pdo, 'sales_invoices')) {
        return;
    }
    $rows = $pdo->query('SELECT id, total_amount, amount_paid FROM sales_invoices')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $total = (float)$row['total_amount'];
        $paid = (float)$row['amount_paid'];
        if (sales_is_invoice_fully_paid($total, $paid)) {
            $pdo->prepare(
                "UPDATE sales_invoices SET amount_paid = total_amount, payment_status = 'Paid' WHERE id = :id"
            )->execute(['id' => $id]);
        } else {
            sales_refresh_invoice_payment_status($pdo, $id);
        }
    }
}

function sales_save_payment(PDO $pdo, array $data): int
{
    $customerId = (int)($data['customer_id'] ?? 0);
    $invoiceId = (int)($data['invoice_id'] ?? 0);
    $amount = max(0, (float)($data['amount'] ?? 0));
    $date = trim((string)($data['payment_date'] ?? date('Y-m-d')));
    $mode = trim((string)($data['payment_mode'] ?? 'Bank Transfer'));
    if ($customerId < 1 || $invoiceId < 1 || $amount <= 0) {
        throw new InvalidArgumentException('Customer, invoice, and amount are required.');
    }
    if (!in_array($mode, SALES_PAYMENT_MODES, true)) {
        throw new InvalidArgumentException('Invalid payment mode.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid payment date is required.');
    }

    $inv = sales_get_invoice($pdo, $invoiceId);
    if (!$inv || (int)$inv['customer_id'] !== $customerId) {
        throw new InvalidArgumentException('Invoice not found for customer.');
    }
    $total = sales_round_money((float)$inv['total_amount']);
    $paidSoFar = sales_round_money((float)$inv['amount_paid']);
    $pending = sales_invoice_pending_amount($total, $paidSoFar);
    if ($pending <= 0) {
        throw new InvalidArgumentException('This invoice is already fully paid.');
    }
    $amount = sales_round_money($amount);
    if ($amount <= 0) {
        throw new InvalidArgumentException('Payment amount must be greater than zero.');
    }
    if ($amount > $pending + 0.001) {
        throw new InvalidArgumentException('Amount exceeds invoice pending balance (' . sales_format_money($pending) . ').');
    }
    if ($amount >= $pending - 0.001) {
        $amount = $pending;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO sales_payments (customer_id, invoice_id, payment_date, amount, payment_mode, reference_no, remarks)
             VALUES (:cid, :iid, :dt, :amt, :mode, :ref, :rm)'
        )->execute([
            'cid' => $customerId,
            'iid' => $invoiceId,
            'dt' => $date,
            'amt' => $amount,
            'mode' => $mode,
            'ref' => trim((string)($data['reference_no'] ?? '')) ?: null,
            'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
        ]);
        $pdo->prepare('UPDATE sales_invoices SET amount_paid = amount_paid + :a WHERE id = :id')
            ->execute(['a' => $amount, 'id' => $invoiceId]);
        sales_refresh_invoice_payment_status($pdo, $invoiceId);
        $pdo->commit();

        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function sales_add_note(PDO $pdo, int $customerId, string $note): void
{
    $note = trim($note);
    if ($customerId < 1 || $note === '') {
        throw new InvalidArgumentException('Note is required.');
    }
    $pdo->prepare(
        'INSERT INTO sales_customer_notes (customer_id, note_text, created_by) VALUES (:cid, :nt, :by)'
    )->execute(['cid' => $customerId, 'nt' => $note, 'by' => sales_current_user_label()]);
}

/** @return list<array<string, mixed>> */
function sales_customer_notes(PDO $pdo, int $customerId): array
{
    $st = $pdo->prepare('SELECT * FROM sales_customer_notes WHERE customer_id = :id ORDER BY created_at DESC');
    $st->execute(['id' => $customerId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed> */
function sales_empty_dashboard(bool $loadError = false): array
{
    return [
        'total_customers' => 0,
        'active_orders' => 0,
        'pending_dispatch' => 0,
        'monthly_revenue' => 0.0,
        'pending_payments' => 0.0,
        'overdue_payments' => 0.0,
        'top_customer' => '—',
        'top_tyre' => '—',
        'recent_orders' => [],
        'recent_invoices' => [],
        'payment_alerts' => [],
        'monthly_trend' => [],
        'recent_dispatch' => [],
        'load_error' => $loadError,
    ];
}

/** @return array<string, mixed> */
function sales_dashboard(PDO $pdo): array
{
    sales_ensure_schema($pdo);
    if (!dh_table_exists($pdo, 'sales_customers')) {
        return sales_empty_dashboard();
    }

    try {
        $monthStart = date('Y-m-01');
        $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM sales_customers WHERE status = 'Active'")->fetchColumn();
        $activeOrders = (int)$pdo->query(
            "SELECT COUNT(*) FROM sales_orders WHERE status NOT IN ('Completed','Cancelled')"
        )->fetchColumn();
        $pendingDispatch = (int)$pdo->query(
            "SELECT COUNT(*) FROM sales_orders WHERE status IN ('Ready','Partially Dispatched','In Production','Pending')"
        )->fetchColumn();
        $monthlyRevenue = (float)$pdo->query(
            'SELECT COALESCE(SUM(total_amount),0) FROM sales_invoices WHERE invoice_date >= ' . $pdo->quote($monthStart)
        )->fetchColumn();
        $pendingPayments = (float)$pdo->query(
            "SELECT COALESCE(SUM(total_amount - amount_paid),0) FROM sales_invoices WHERE payment_status IN ('Pending','Partial','Overdue')"
        )->fetchColumn();
        $overduePayments = (float)$pdo->query(
            "SELECT COALESCE(SUM(total_amount - amount_paid),0) FROM sales_invoices WHERE payment_status = 'Overdue'"
        )->fetchColumn();

        $topCust = $pdo->query(
            "SELECT c.company_name, SUM(i.total_amount) AS rev FROM sales_invoices i
             INNER JOIN sales_customers c ON c.id = i.customer_id
             WHERE i.invoice_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
             GROUP BY c.id ORDER BY rev DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        $topTyre = $pdo->query(
            'SELECT tyre_type, SUM(qty_ordered) AS q FROM sales_order_items GROUP BY tyre_type ORDER BY q DESC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);

        $recentOrders = array_slice(sales_list_orders($pdo, []), 0, 8);
        $recentInvoices = array_slice(sales_list_invoices($pdo, []), 0, 8);
        $paymentAlerts = $pdo->query(
            "SELECT i.invoice_no, c.company_name, (i.total_amount - i.amount_paid) AS due_amt, i.due_date, i.payment_status
             FROM sales_invoices i INNER JOIN sales_customers c ON c.id = i.customer_id
             WHERE i.payment_status IN ('Pending','Partial','Overdue')
             ORDER BY i.due_date ASC LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $trend = $pdo->query(
            "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS ym, SUM(total_amount) AS revenue
             FROM sales_invoices
             WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY ym ORDER BY ym ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $recentDispatch = [];
        if (dh_table_exists($pdo, 'sales_dispatch_allocations')) {
            $recentDispatch = $pdo->query(
                'SELECT d.dispatch_code, d.dispatch_date, d.tyre_type, d.status, o.so_number, c.company_name, a.qty AS alloc_qty
                 FROM sales_dispatch_allocations a
                 INNER JOIN dispatch d ON d.id = a.dispatch_id
                 INNER JOIN sales_orders o ON o.id = a.sales_order_id
                 INNER JOIN sales_customers c ON c.id = o.customer_id
                 ORDER BY d.dispatch_date DESC, a.id DESC LIMIT 8'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [
            'total_customers' => $totalCustomers,
            'active_orders' => $activeOrders,
            'pending_dispatch' => $pendingDispatch,
            'monthly_revenue' => $monthlyRevenue,
            'pending_payments' => $pendingPayments,
            'overdue_payments' => $overduePayments,
            'top_customer' => (string)($topCust['company_name'] ?? '—'),
            'top_tyre' => (string)($topTyre['tyre_type'] ?? '—'),
            'recent_orders' => $recentOrders,
            'recent_invoices' => $recentInvoices,
            'payment_alerts' => $paymentAlerts,
            'monthly_trend' => $trend,
            'recent_dispatch' => $recentDispatch,
            'load_error' => false,
        ];
    } catch (Throwable $e) {
        if (function_exists('sales_log_exception')) {
            sales_log_exception($e, 'sales_dashboard');
        } else {
            error_log('[Sales CRM][sales_dashboard] ' . $e->getMessage());
        }

        return sales_empty_dashboard(true);
    }
}

/**
 * Dispatch queue rows (synced from CRM sales orders).
 *
 * @return list<array<string, mixed>>
 */
function sales_backfill_dispatch_queue(PDO $pdo): void
{
    if (!dh_table_exists($pdo, 'sales_dispatch_queue')) {
        return;
    }
    $cnt = (int)$pdo->query('SELECT COUNT(*) FROM sales_dispatch_queue')->fetchColumn();
    if ($cnt > 0) {
        return;
    }
    $ids = $pdo->query(
        "SELECT id FROM sales_orders WHERE status NOT IN ('Completed','Cancelled')"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($ids as $oid) {
        sales_refresh_order_fulfillment($pdo, (int)$oid);
    }
}

function sales_dispatch_queue_list(PDO $pdo, ?string $filter = null): array
{
    sales_ensure_schema($pdo);
    if (!dh_table_exists($pdo, 'sales_dispatch_queue')) {
        return sales_dispatch_pending_lines_legacy($pdo);
    }
    sales_backfill_dispatch_queue($pdo);

    $sql = 'SELECT q.*, o.status AS order_status, o.priority
             FROM sales_dispatch_queue q
             INNER JOIN sales_orders o ON o.id = q.sales_order_id
             WHERE o.status NOT IN (\'Completed\', \'Cancelled\')';
    if ($filter === 'ready') {
        $sql .= " AND q.dispatchable_qty > 0";
    } elseif ($filter === 'waiting') {
        $sql .= " AND q.dispatchable_qty < 1";
    }
    $sql .= ' ORDER BY q.order_date DESC, q.id ASC LIMIT 300';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $r['ready_qty'] = (int)$r['dispatchable_qty'];
        $r['item_id'] = (int)$r['sales_order_item_id'];
        $r['order_id'] = (int)$r['sales_order_id'];
    }
    unset($r);

    return $rows;
}

/** @return list<array<string, mixed>> */
function sales_dispatch_pending_lines(PDO $pdo): array
{
    return sales_dispatch_queue_list($pdo);
}

/** @return list<array<string, mixed>> */
function sales_dispatch_ready_lines(PDO $pdo): array
{
    return sales_dispatch_queue_list($pdo, 'ready');
}

/** Legacy fallback when queue table missing. */
function sales_dispatch_pending_lines_legacy(PDO $pdo): array
{
    if (!dh_table_exists($pdo, 'sales_orders') || !dh_table_exists($pdo, 'sales_order_items')) {
        return [];
    }

    $st = $pdo->query(
        'SELECT o.id AS order_id, o.so_number, o.status AS order_status, o.order_date, o.customer_id,
                c.company_name, oi.id AS item_id, oi.tyre_type, oi.qty_ordered, oi.qty_dispatched,
                oi.fulfillment_status, o.delivery_date AS expected_dispatch_date,
                (oi.qty_ordered - oi.qty_dispatched) AS pending_qty
         FROM sales_orders o
         INNER JOIN sales_customers c ON c.id = o.customer_id
         INNER JOIN sales_order_items oi ON oi.order_id = o.id
         WHERE o.status NOT IN (\'Completed\', \'Cancelled\')
           AND oi.qty_ordered > oi.qty_dispatched
         ORDER BY o.order_date DESC, oi.line_no ASC, oi.id ASC
         LIMIT 200'
    );
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $pending = (int)$r['pending_qty'];
        $live = sales_fg_stock((string)$r['tyre_type']);
        $state = sales_line_stock_state($pending, $live);
        $r['available_qty'] = $live;
        $r['ordered_qty'] = (int)$r['qty_ordered'];
        $r['dispatchable_qty'] = $state['dispatchable_qty'];
        $r['ready_qty'] = $state['dispatchable_qty'];
        $r['stock_status'] = $state['stock_status'];
        $r['queue_status'] = $state['queue_status'];
        $r['dispatch_readiness'] = $state['dispatch_readiness'];
    }
    unset($r);

    return $rows;
}

/**
 * Prefill payload for dispatch form from CRM queue line.
 *
 * @return array<string, mixed>
 */
function sales_dispatch_prefill(PDO $pdo, int $orderId, int $itemId): array
{
    sales_refresh_order_fulfillment($pdo, $orderId);
    $order = sales_get_order($pdo, $orderId);
    if (!$order) {
        throw new InvalidArgumentException('Sales order not found.');
    }
    $line = null;
    foreach ($order['items'] as $it) {
        if ((int)$it['id'] === $itemId) {
            $line = $it;
            break;
        }
    }
    if (!$line) {
        throw new InvalidArgumentException('Order line not found.');
    }
    $pending = (int)$line['qty_ordered'] - (int)$line['qty_dispatched'];
    if ($pending < 1) {
        throw new InvalidArgumentException('No quantity remaining on this line.');
    }
    $live = sales_fg_stock((string)$line['tyre_type']);
    $state = sales_line_stock_state($pending, $live);
    $shipQty = $state['dispatchable_qty'];
    if ($shipQty < 1) {
        throw new InvalidArgumentException('No stock available for dispatch. Awaiting production.');
    }

    return [
        'ok' => true,
        'sales_order_id' => $orderId,
        'sales_order_item_id' => $itemId,
        'sales_customer_id' => (int)$order['customer_id'],
        'customer_name' => (string)$order['company_name'],
        'so_number' => (string)$order['so_number'],
        'tyre_type' => (string)$line['tyre_type'],
        'ordered_qty' => (int)$line['qty_ordered'],
        'qty' => $shipQty,
        'max_qty' => $shipQty,
        'pending_qty' => $pending,
        'available_qty' => $live,
        'stock_status' => $state['stock_status'],
        'dispatch_readiness' => $state['dispatch_readiness'],
        'rate' => (float)$line['rate'],
        'order_status' => (string)$order['status'],
    ];
}

/** Customers for dispatch UI — sourced from CRM only. */
function sales_customers_for_dispatch(PDO $pdo): array
{
    return sales_list_customers($pdo, ['status' => 'Active']);
}

/** Open orders for dispatch dropdown */
function sales_open_orders_for_dispatch(PDO $pdo): array
{
    if (!dh_table_exists($pdo, 'sales_orders')) {
        return [];
    }
    $st = $pdo->query(
        "SELECT o.id, o.so_number, o.customer_id, c.company_name, o.status
         FROM sales_orders o
         INNER JOIN sales_customers c ON c.id = o.customer_id
         WHERE o.status NOT IN ('Completed','Cancelled')
         ORDER BY o.order_date DESC LIMIT 100"
    );

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Lines with remaining qty for dispatch */
function sales_order_lines_remaining(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare(
        'SELECT id, tyre_type, qty_ordered, qty_dispatched, rate, fulfillment_status,
         (qty_ordered - qty_dispatched) AS qty_remaining
         FROM sales_order_items
         WHERE order_id = :oid AND qty_ordered > qty_dispatched
         ORDER BY line_no ASC'
    );
    $st->execute(['oid' => $orderId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function sales_seed_demo_data(PDO $pdo): void
{
    if (!dh_table_exists($pdo, 'sales_customers')) {
        return;
    }
    $cnt = (int)$pdo->query('SELECT COUNT(*) FROM sales_customers')->fetchColumn();
    if ($cnt > 0) {
        return;
    }

    $customers = [
        ['CUS-2026-0001', 'Metro Tyre Traders', 'Distributor', 'Rajesh Mehta', '29AABCM1234F1Z5', '9876500101', 'Mumbai', 'Maharashtra'],
        ['CUS-2026-0002', 'Southern Auto Mart', 'Dealer', 'Priya Nair', '33AABCS5678G1Z2', '9876500202', 'Chennai', 'Tamil Nadu'],
        ['CUS-2026-0003', 'Bharat Wheels', 'Retailer', 'Amit Sharma', '07AABCB9012H1Z3', '9876500303', 'Delhi', 'Delhi'],
        ['CUS-2026-0004', 'Punjab Tyres Distributor', 'Distributor', 'Harpreet Singh', '03AABCP3456J1Z4', '9876500404', 'Ludhiana', 'Punjab'],
    ];
    $ins = $pdo->prepare(
        'INSERT INTO sales_customers (customer_code, company_name, customer_type, contact_person, gst_number, phone, city, state,
         credit_limit, payment_terms, status, billing_address)
         VALUES (:code, :co, :tp, :cp, :gst, :ph, :city, :st, 500000, \'Net 30 days\', \'Active\', :addr)'
    );
    $ids = [];
    foreach ($customers as $c) {
        $ins->execute([
            'code' => $c[0],
            'co' => $c[1],
            'tp' => $c[2],
            'cp' => $c[3],
            'gst' => $c[4],
            'ph' => $c[5],
            'city' => $c[6],
            'st' => $c[7],
            'addr' => $c[1] . ', ' . $c[6],
        ]);
        $ids[] = (int)$pdo->lastInsertId();
    }

    $orders = [
        [$ids[0], 'PCR Car', 500, 3200],
        [$ids[0], 'TBR Truck', 200, 8500],
        [$ids[1], 'Two Wheeler', 300, 1100],
        [$ids[2], 'Farm / OTR', 80, 12000],
        [$ids[3], 'PCR SUV', 150, 3800],
    ];
    foreach ($orders as $idx => $o) {
        $_POST = [];
        $data = [
            'customer_id' => $o[0],
            'order_date' => date('Y-m-d', strtotime('-' . (5 - $idx) . ' days')),
            'delivery_date' => date('Y-m-d', strtotime('+' . (10 + $idx) . ' days')),
            'priority' => $idx === 0 ? 'Urgent' : 'Medium',
            'payment_terms' => 'Net 30 days',
            'tyre_type' => [$o[1]],
            'qty' => [$o[2]],
            'rate' => [$o[3]],
            'gst_percent' => [18],
            'line_discount' => [0],
            'discount_amount' => 0,
        ];
        sales_save_order($pdo, $data);
    }
}
