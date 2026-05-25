<?php
declare(strict_types=1);

/** Max balance (₹) treated as fully paid — fixes 0.01 paisa rounding after split payments. */
function sales_payment_tolerance(): float
{
    return 0.02;
}

function sales_round_money(float $amount): float
{
    return round($amount, 2);
}

/** Remaining invoice balance after rounding; zero when within tolerance. */
function sales_invoice_pending_amount(float $total, float $paid): float
{
    $pending = sales_round_money(sales_round_money($total) - sales_round_money($paid));
    if ($pending <= sales_payment_tolerance()) {
        return 0.0;
    }

    return $pending;
}

function sales_is_invoice_fully_paid(float $total, float $paid): bool
{
    return sales_invoice_pending_amount($total, $paid) <= 0.0;
}

/** Display label + CSS class for invoice payment row. */
function sales_invoice_display_status(array $inv): array
{
    $total = (float)($inv['total_amount'] ?? 0);
    $paid = (float)($inv['amount_paid'] ?? 0);
    $pending = sales_invoice_pending_amount($total, $paid);
    $due = (string)($inv['due_date'] ?? '');
    $remarks = (string)($inv['remarks'] ?? '');

    if (sales_is_invoice_fully_paid($total, $paid) && $total > 0) {
        return ['label' => 'Paid', 'key' => 'paid', 'class' => 'sales-badge sales-badge--paid', 'pending' => 0.0];
    }
    if ($paid > sales_payment_tolerance()) {
        return ['label' => 'Partially Paid', 'key' => 'partial', 'class' => 'sales-badge sales-badge--partial', 'pending' => $pending];
    }
    if ($due !== '' && $due < date('Y-m-d')) {
        return ['label' => 'Overdue', 'key' => 'overdue', 'class' => 'sales-badge sales-badge--overdue', 'pending' => $pending];
    }
    if (str_contains($remarks, 'Auto from dispatch')) {
        return ['label' => 'Generated', 'key' => 'generated', 'class' => 'sales-badge sales-badge--generated', 'pending' => $pending];
    }

    return ['label' => 'Pending', 'key' => 'pending', 'class' => 'sales-badge sales-badge--pending', 'pending' => $pending];
}

/** Seven-step CRM workflow timeline for order detail. */
function sales_order_workflow_timeline(PDO $pdo, array $order): array
{
    $orderId = (int)($order['id'] ?? 0);
    $status = (string)($order['status'] ?? 'Pending');
    $totalOrdered = 0;
    $totalDispatched = 0;
    foreach ($order['items'] ?? [] as $it) {
        $totalOrdered += (int)($it['qty_ordered'] ?? 0);
        $totalDispatched += (int)($it['qty_dispatched'] ?? 0);
    }

    $hasStockReady = in_array($status, ['Ready', 'Partially Dispatched', 'Completed'], true);
    if (!$hasStockReady && $totalOrdered > 0) {
        foreach ($order['items'] ?? [] as $it) {
            $pending = (int)$it['qty_ordered'] - (int)$it['qty_dispatched'];
            if ($pending < 1) {
                continue;
            }
            $live = sales_fg_stock((string)$it['tyre_type']);
            if (sales_line_stock_state($pending, $live)['dispatchable_qty'] > 0) {
                $hasStockReady = true;
                break;
            }
        }
    }

    $invRows = [];
    if ($orderId > 0 && dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare('SELECT id, payment_status, amount_paid, total_amount FROM sales_invoices WHERE order_id = :oid ORDER BY id ASC');
        $st->execute(['oid' => $orderId]);
        $invRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    $hasInvoice = $invRows !== [];
    $allPaid = $hasInvoice;
    foreach ($invRows as $inv) {
        if ((float)$inv['amount_paid'] < (float)$inv['total_amount'] - 0.01) {
            $allPaid = false;
            break;
        }
    }

    $current = 0;
    if ($status === 'Cancelled') {
        $current = 0;
    } elseif ($allPaid && $hasInvoice) {
        $current = 6;
    } elseif ($hasInvoice) {
        $current = 5;
    } elseif ($totalDispatched > 0 && $totalDispatched < $totalOrdered) {
        $current = 3;
    } elseif ($totalDispatched >= $totalOrdered && $totalOrdered > 0) {
        $current = 4;
    } elseif ($hasStockReady) {
        $current = 2;
    } elseif ($totalOrdered > 0) {
        $current = 1;
    }

    $steps = [
        ['key' => 'created', 'label' => 'Sales Order Created'],
        ['key' => 'stock', 'label' => 'Stock Verified'],
        ['key' => 'dispatch_pending', 'label' => 'Dispatch Pending'],
        ['key' => 'partial', 'label' => 'Partially Dispatched'],
        ['key' => 'dispatched', 'label' => 'Dispatched'],
        ['key' => 'invoice', 'label' => 'Invoice Generated'],
        ['key' => 'payment', 'label' => 'Payment Pending'],
        ['key' => 'paid', 'label' => 'Paid Complete'],
    ];

    $out = [];
    foreach ($steps as $i => $step) {
        $state = 'upcoming';
        if ($i < $current) {
            $state = 'done';
        } elseif ($i === $current) {
            $state = 'current';
        }
        $out[] = $step + ['state' => $state];
    }

    return $out;
}

/** Composite operational + financial status for order header. */
function sales_order_composite_status(PDO $pdo, array $order): array
{
    $timeline = sales_order_workflow_timeline($pdo, $order);
    foreach (array_reverse($timeline) as $step) {
        if ($step['state'] === 'current') {
            return sales_order_status_badge_from_key($step['key']);
        }
    }

    return sales_order_status_badge((string)($order['status'] ?? 'Pending'));
}

function sales_order_status_badge_from_key(string $key): array
{
    return match ($key) {
        'stock' => ['class' => 'so-badge so-badge--check', 'label' => 'Stock Verified'],
        'dispatch_pending' => ['class' => 'so-badge so-badge--pending', 'label' => 'Ready For Dispatch'],
        'partial' => ['class' => 'so-badge so-badge--partial', 'label' => 'Partially Dispatched'],
        'dispatched' => ['class' => 'so-badge so-badge--dispatched', 'label' => 'Dispatched'],
        'invoice' => ['class' => 'so-badge so-badge--invoice', 'label' => 'Invoice Generated'],
        'payment' => ['class' => 'so-badge so-badge--payment', 'label' => 'Payment Pending'],
        'paid' => ['class' => 'so-badge so-badge--paid', 'label' => 'Paid'],
        'created' => ['class' => 'so-badge so-badge--pending', 'label' => 'Pending Stock'],
        default => ['class' => 'so-badge so-badge--muted', 'label' => strtoupper($key)],
    };
}

/** @return array<string, mixed> */
function sales_order_financial_summary(PDO $pdo, array $order): array
{
    $customerId = (int)($order['customer_id'] ?? 0);
    $orderId = (int)($order['id'] ?? 0);
    $totalOrdered = 0;
    $totalDispatched = 0;
    foreach ($order['items'] ?? [] as $it) {
        $totalOrdered += (int)($it['qty_ordered'] ?? 0);
        $totalDispatched += (int)($it['qty_dispatched'] ?? 0);
    }
    $dispatchPct = $totalOrdered > 0 ? round(100 * $totalDispatched / $totalOrdered, 1) : 0.0;

    $custOrders = 0;
    $custPending = 0.0;
    $custPaid = 0.0;
    if ($customerId > 0) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM sales_orders WHERE customer_id = :cid AND status != \'Cancelled\'');
        $st->execute(['cid' => $customerId]);
        $custOrders = (int)$st->fetchColumn();
        $st = $pdo->prepare(
            "SELECT COALESCE(SUM(total_amount - amount_paid), 0), COALESCE(SUM(amount_paid), 0)
             FROM sales_invoices WHERE customer_id = :cid"
        );
        $st->execute(['cid' => $customerId]);
        $row = $st->fetch(PDO::FETCH_NUM) ?: [0, 0];
        $custPending = (float)$row[0];
        $custPaid = (float)$row[1];
    }

    $invoiceTotal = 0.0;
    $invoicePaid = 0.0;
    $paymentLabel = '—';
    if ($orderId > 0 && dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare('SELECT * FROM sales_invoices WHERE order_id = :oid');
        $st->execute(['oid' => $orderId]);
        $invs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($invs as $inv) {
            $invoiceTotal += (float)$inv['total_amount'];
            $invoicePaid += (float)$inv['amount_paid'];
        }
        if ($invs !== []) {
            $last = $invs[count($invs) - 1];
            $paymentLabel = sales_invoice_display_status($last)['label'];
        }
    }

    return [
        'customer_total_orders' => $custOrders,
        'customer_pending' => $custPending,
        'customer_paid' => $custPaid,
        'order_invoice_total' => $invoiceTotal,
        'order_invoice_paid' => $invoicePaid,
        'order_invoice_pending' => max(0, $invoiceTotal - $invoicePaid),
        'dispatch_pct' => $dispatchPct,
        'payment_status' => $paymentLabel,
    ];
}

/** @return array<string, mixed> */
function sales_customer_insights(PDO $pdo, int $customerId): array
{
    if ($customerId < 1) {
        return [];
    }
    $c = sales_get_customer($pdo, $customerId);
    if (!$c) {
        return [];
    }

    $st = $pdo->prepare(
        "SELECT so_number, order_date, total_amount FROM sales_orders
         WHERE customer_id = :cid AND status != 'Cancelled' ORDER BY order_date DESC, id DESC LIMIT 1"
    );
    $st->execute(['cid' => $customerId]);
    $lastOrder = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(total_amount - amount_paid), 0), COALESCE(SUM(total_amount), 0), COUNT(*)
         FROM sales_invoices WHERE customer_id = :cid AND payment_status IN ('Pending','Partial','Overdue')"
    );
    $st->execute(['cid' => $customerId]);
    $fin = $st->fetch(PDO::FETCH_NUM) ?: [0, 0, 0];

    $st = $pdo->prepare('SELECT COUNT(*) FROM sales_orders WHERE customer_id = :cid AND status != \'Cancelled\'');
    $st->execute(['cid' => $customerId]);
    $orderCount = (int)$st->fetchColumn();

    $avgDays = 30;
    $st = $pdo->query(
        "SELECT AVG(DATEDIFF(p.payment_date, i.invoice_date)) FROM sales_payments p
         INNER JOIN sales_invoices i ON i.id = p.invoice_id
         WHERE i.customer_id = " . (int)$customerId
    );
    if ($st) {
        $avg = $st->fetchColumn();
        if ($avg !== false && $avg !== null) {
            $avgDays = (int)round((float)$avg);
        }
    }

    return [
        'company_name' => (string)$c['company_name'],
        'last_order' => $lastOrder ? (string)$lastOrder['so_number'] . ' · ' . $lastOrder['order_date'] : '—',
        'outstanding' => (float)$fin[0],
        'credit_limit' => (float)($c['credit_limit'] ?? 0),
        'total_business' => (float)$fin[1],
        'avg_payment_days' => $avgDays,
        'pending_invoices' => (int)$fin[2],
        'total_orders' => $orderCount,
    ];
}

/** @return list<array<string, mixed>> */
function sales_invoice_payments(PDO $pdo, int $invoiceId): array
{
    if (!dh_table_exists($pdo, 'sales_payments')) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT p.*, c.company_name FROM sales_payments p
         INNER JOIN sales_customers c ON c.id = p.customer_id
         WHERE p.invoice_id = :id ORDER BY p.payment_date DESC, p.id DESC'
    );
    $st->execute(['id' => $invoiceId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function sales_dispatch_for_order(PDO $pdo, int $orderId): array
{
    if ($orderId < 1) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT d.id, d.dispatch_code, d.dispatch_date, d.tyre_type, d.qty, d.invoice_no, d.customer_name
         FROM dispatch d WHERE d.sales_order_id = :oid ORDER BY d.dispatch_date DESC, d.id DESC'
    );
    $st->execute(['oid' => $orderId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed> */
function sales_payment_dashboard(PDO $pdo): array
{
    sales_ensure_schema($pdo);
    sales_reconcile_invoice_balances($pdo);
    if (!dh_table_exists($pdo, 'sales_invoices')) {
        return [
            'total_receivable' => 0,
            'collected' => 0,
            'pending' => 0,
            'overdue' => 0,
            'today' => 0,
            'month' => 0,
        ];
    }
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $totalReceivable = (float)$pdo->query('SELECT COALESCE(SUM(total_amount), 0) FROM sales_invoices')->fetchColumn();
    $collected = (float)$pdo->query('SELECT COALESCE(SUM(amount_paid), 0) FROM sales_invoices')->fetchColumn();
    $pending = 0.0;
    $overdue = 0.0;
    $invRows = $pdo->query('SELECT total_amount, amount_paid, payment_status, due_date FROM sales_invoices')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($invRows as $inv) {
        $bal = sales_invoice_pending_amount((float)$inv['total_amount'], (float)$inv['amount_paid']);
        if ($bal <= 0) {
            continue;
        }
        $pending += $bal;
        $st = (string)($inv['payment_status'] ?? '');
        $due = (string)($inv['due_date'] ?? '');
        if ($st === 'Overdue' || ($due !== '' && $due < $today && $st !== 'Paid')) {
            $overdue += $bal;
        }
    }
    $todayCol = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount), 0) FROM sales_payments WHERE payment_date = " . $pdo->quote($today)
    )->fetchColumn();
    $monthCol = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount), 0) FROM sales_payments WHERE payment_date >= " . $pdo->quote($monthStart)
    )->fetchColumn();

    return [
        'total_receivable' => $totalReceivable,
        'collected' => $collected,
        'pending' => $pending,
        'overdue' => $overdue,
        'today' => $todayCol,
        'month' => $monthCol,
    ];
}

/** @return list<array<string, mixed>> */
function sales_customer_outstanding(PDO $pdo): array
{
    if (!dh_table_exists($pdo, 'sales_invoices')) {
        return [];
    }
    return $pdo->query(
        "SELECT c.id, c.company_name,
            COALESCE(SUM(i.total_amount), 0) AS total_invoiced,
            COALESCE(SUM(i.amount_paid), 0) AS total_paid,
            COALESCE(SUM(i.total_amount - i.amount_paid), 0) AS pending,
            COALESCE(SUM(CASE WHEN i.due_date < CURDATE() AND i.payment_status != 'Paid'
                THEN i.total_amount - i.amount_paid ELSE 0 END), 0) AS overdue
         FROM sales_customers c
         INNER JOIN sales_invoices i ON i.customer_id = c.id
         GROUP BY c.id, c.company_name
         HAVING pending > 0.01
         ORDER BY pending DESC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed> */
function sales_accounts_dashboard(PDO $pdo): array
{
    $pay = sales_payment_dashboard($pdo);
    $monthStart = date('Y-m-01');
    $salesMonth = 0.0;
    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(total_amount), 0) FROM sales_invoices WHERE invoice_date >= :m');
        $st->execute(['m' => $monthStart]);
        $salesMonth = (float)$st->fetchColumn();
    }
    $trend = [];
    if (dh_table_exists($pdo, 'sales_invoices')) {
        $rows = $pdo->query(
            "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS ym, SUM(total_amount) AS amt
             FROM sales_invoices WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY ym ORDER BY ym ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            $trend[] = ['label' => $r['ym'], 'amount' => (float)$r['amt']];
        }
    }
    $topCustomers = sales_customer_outstanding($pdo);
    $topCustomers = array_slice($topCustomers, 0, 5);

    $gstPayable = 0.0;
    if (dh_table_exists($pdo, 'sales_invoices')) {
        $gstPayable = (float)$pdo->query('SELECT COALESCE(SUM(gst_total), 0) FROM sales_invoices')->fetchColumn();
    }

    return [
        'total_sales' => $pay['total_receivable'],
        'total_received' => $pay['collected'],
        'total_pending' => $pay['pending'],
        'overdue' => $pay['overdue'],
        'monthly_revenue' => $salesMonth,
        'monthly_expense' => 0.0,
        'net_profit' => $salesMonth,
        'gst_payable' => $gstPayable,
        'trend' => $trend,
        'top_receivables' => $topCustomers,
        'payment' => $pay,
    ];
}

/** @return array{rows: list<array>, customer: ?array} */
function sales_customer_ledger(PDO $pdo, int $customerId): array
{
    $customer = sales_get_customer($pdo, $customerId);
    if (!$customer) {
        return ['rows' => [], 'customer' => null];
    }
    $rows = [];
    $balance = 0.0;

    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare(
            'SELECT invoice_date AS dt, invoice_no AS ref, total_amount, amount_paid, id
             FROM sales_invoices WHERE customer_id = :cid ORDER BY invoice_date ASC, id ASC'
        );
        $st->execute(['cid' => $customerId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $inv) {
            $debit = (float)$inv['total_amount'];
            $balance += $debit;
            $rows[] = [
                'date' => $inv['dt'],
                'ref' => 'INV ' . $inv['ref'],
                'debit' => $debit,
                'credit' => 0.0,
                'balance' => $balance,
                'type' => 'invoice',
            ];
            if ((float)$inv['amount_paid'] > 0) {
                $balance -= (float)$inv['amount_paid'];
                $rows[] = [
                    'date' => $inv['dt'],
                    'ref' => 'Payment on ' . $inv['ref'],
                    'debit' => 0.0,
                    'credit' => (float)$inv['amount_paid'],
                    'balance' => $balance,
                    'type' => 'payment',
                ];
            }
        }
    }

    $totalSales = array_sum(array_column(array_filter($rows, static fn($r) => $r['type'] === 'invoice'), 'debit'));
    $totalPaid = array_sum(array_column(array_filter($rows, static fn($r) => $r['type'] === 'payment'), 'credit'));

    return [
        'customer' => $customer,
        'rows' => $rows,
        'summary' => [
            'opening' => 0.0,
            'total_sales' => $totalSales,
            'total_paid' => $totalPaid,
            'outstanding' => max(0, $totalSales - $totalPaid),
            'last_payment' => $pdo->query(
                'SELECT MAX(payment_date) FROM sales_payments WHERE customer_id = ' . (int)$customerId
            )->fetchColumn() ?: '—',
            'credit_days' => (string)($customer['payment_terms'] ?? '—'),
        ],
    ];
}

/** @return list<array<string, mixed>> */
function sales_receivables_list(PDO $pdo, string $filter = 'all'): array
{
    if (!dh_table_exists($pdo, 'sales_invoices')) {
        return [];
    }
    $sql = 'SELECT i.*, c.company_name, o.so_number,
            (i.total_amount - i.amount_paid) AS balance
        FROM sales_invoices i
        INNER JOIN sales_customers c ON c.id = i.customer_id
        LEFT JOIN sales_orders o ON o.id = i.order_id
        WHERE (i.total_amount - i.amount_paid) > 0.01';
    if ($filter === 'overdue') {
        $sql .= " AND (i.payment_status = 'Overdue' OR (i.due_date < CURDATE() AND i.payment_status != 'Paid'))";
    } elseif ($filter === 'week') {
        $sql .= ' AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
    } elseif ($filter === 'month') {
        $sql .= ' AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
    }
    $sql .= ' ORDER BY i.due_date ASC, balance DESC';

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, int> */
function sales_dispatch_tracking_summary(PDO $pdo): array
{
    sales_ensure_schema($pdo);
    $summary = [
        'pending_orders' => 0,
        'ready' => 0,
        'partial' => 0,
        'fully_dispatched' => 0,
        'invoiced' => 0,
    ];
    if (!dh_table_exists($pdo, 'sales_orders')) {
        return $summary;
    }
    $orders = $pdo->query(
        "SELECT o.id, o.status,
            (SELECT COALESCE(SUM(oi.qty_ordered), 0) FROM sales_order_items oi WHERE oi.order_id = o.id) AS qty_ord,
            (SELECT COALESCE(SUM(oi.qty_dispatched), 0) FROM sales_order_items oi WHERE oi.order_id = o.id) AS qty_dsp,
            (SELECT COUNT(*) FROM sales_invoices i WHERE i.order_id = o.id) AS inv_cnt
         FROM sales_orders o WHERE o.status NOT IN ('Cancelled')"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($orders as $o) {
        $ord = (int)$o['qty_ord'];
        $dsp = (int)$o['qty_dsp'];
        $st = (string)$o['status'];
        if (!in_array($st, ['Completed', 'Cancelled'], true) && $ord > $dsp) {
            $summary['pending_orders']++;
        }
        if ($st === 'Ready') {
            $summary['ready']++;
        }
        if ($st === 'Partially Dispatched') {
            $summary['partial']++;
        }
        if ($st === 'Completed' || ($ord > 0 && $dsp >= $ord)) {
            $summary['fully_dispatched']++;
        }
        if ((int)$o['inv_cnt'] > 0) {
            $summary['invoiced']++;
        }
    }

    return $summary;
}

/** @return array{label: string, class: string} */
function sales_track_dispatch_status(int $qtyOrdered, int $qtyDispatched, string $fulfillment): array
{
    $remaining = max(0, $qtyOrdered - $qtyDispatched);
    if ($qtyOrdered > 0 && $qtyDispatched >= $qtyOrdered) {
        return ['label' => 'Fully Dispatched', 'class' => 'crm-track crm-track--full'];
    }
    if ($qtyDispatched > 0) {
        return ['label' => 'Partial Dispatch', 'class' => 'crm-track crm-track--partial'];
    }
    if ($fulfillment === 'Ready for Dispatch' || $fulfillment === 'Ready') {
        return ['label' => 'Ready', 'class' => 'crm-track crm-track--ready'];
    }

    return ['label' => 'Waiting Stock', 'class' => 'crm-track crm-track--waiting'];
}

/** @return array{label: string, class: string, invoice_id: ?int} */
function sales_track_invoice_status(PDO $pdo, int $orderId): array
{
    if ($orderId < 1 || !dh_table_exists($pdo, 'sales_invoices')) {
        return ['label' => 'Not Generated', 'class' => 'crm-track crm-track--muted', 'invoice_id' => null];
    }
    $st = $pdo->prepare('SELECT id, total_amount, amount_paid, payment_status FROM sales_invoices WHERE order_id = :oid ORDER BY id DESC');
    $st->execute(['oid' => $orderId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows === []) {
        return ['label' => 'Not Generated', 'class' => 'crm-track crm-track--muted', 'invoice_id' => null];
    }
    $allPaid = true;
    $anyPartial = false;
    $firstId = (int)$rows[0]['id'];
    foreach ($rows as $inv) {
        $paid = (float)$inv['amount_paid'];
        $total = (float)$inv['total_amount'];
        if ($paid < $total - 0.01) {
            $allPaid = false;
        }
        if ($paid > 0.01 && $paid < $total - 0.01) {
            $anyPartial = true;
        }
    }
    if ($allPaid) {
        return ['label' => 'Paid', 'class' => 'crm-track crm-track--paid', 'invoice_id' => $firstId];
    }
    if ($anyPartial || in_array((string)($rows[0]['payment_status'] ?? ''), ['Partial', 'Pending'], true)) {
        return ['label' => 'Pending', 'class' => 'crm-track crm-track--pending', 'invoice_id' => $firstId];
    }

    return ['label' => 'Generated', 'class' => 'crm-track crm-track--generated', 'invoice_id' => $firstId];
}

/** @return array{label: string, class: string} */
function sales_track_payment_status(PDO $pdo, int $orderId): array
{
    if ($orderId < 1 || !dh_table_exists($pdo, 'sales_invoices')) {
        return ['label' => 'Unpaid', 'class' => 'crm-track crm-track--unpaid'];
    }
    $st = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0), COALESCE(SUM(amount_paid),0) FROM sales_invoices WHERE order_id = :oid');
    $st->execute(['oid' => $orderId]);
    $row = $st->fetch(PDO::FETCH_NUM) ?: [0, 0];
    $total = (float)$row[0];
    $paid = (float)$row[1];
    if ($total < 0.01) {
        return ['label' => 'Unpaid', 'class' => 'crm-track crm-track--unpaid'];
    }
    if ($paid >= $total - 0.01) {
        return ['label' => 'Paid', 'class' => 'crm-track crm-track--paid'];
    }
    if ($paid > 0.01) {
        return ['label' => 'Partial', 'class' => 'crm-track crm-track--partial-pay'];
    }

    return ['label' => 'Unpaid', 'class' => 'crm-track crm-track--unpaid'];
}

/** @return list<array<string, mixed>> */
function sales_dispatch_tracking_rows(PDO $pdo, array $filters = []): array
{
    sales_ensure_schema($pdo);
    if (!dh_table_exists($pdo, 'sales_order_items')) {
        return [];
    }

    $sql = 'SELECT oi.id AS item_id, oi.tyre_type, oi.qty_ordered, oi.qty_dispatched, oi.fulfillment_status,
            o.id AS order_id, o.so_number, o.delivery_date, o.status AS order_status,
            c.company_name, c.id AS customer_id
        FROM sales_order_items oi
        INNER JOIN sales_orders o ON o.id = oi.order_id
        INNER JOIN sales_customers c ON c.id = o.customer_id
        WHERE o.status != \'Cancelled\'';
    $params = [];
    if (!empty($filters['q'])) {
        $sql .= ' AND (o.so_number LIKE :q OR c.company_name LIKE :q OR oi.tyre_type LIKE :q)';
        $params['q'] = '%' . trim((string)$filters['q']) . '%';
    }
    if (!empty($filters['dispatch_status'])) {
        $ds = (string)$filters['dispatch_status'];
        if ($ds === 'waiting') {
            $sql .= ' AND oi.qty_dispatched = 0 AND oi.fulfillment_status NOT IN (\'Ready for Dispatch\')';
        } elseif ($ds === 'ready') {
            $sql .= ' AND oi.qty_dispatched = 0 AND oi.fulfillment_status = \'Ready for Dispatch\'';
        } elseif ($ds === 'partial') {
            $sql .= ' AND oi.qty_dispatched > 0 AND oi.qty_dispatched < oi.qty_ordered';
        } elseif ($ds === 'full') {
            $sql .= ' AND oi.qty_ordered > 0 AND oi.qty_dispatched >= oi.qty_ordered';
        }
    }
    $sql .= ' ORDER BY o.order_date DESC, o.id DESC, oi.line_no ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $lastDispatch = [];
    if (dh_table_exists($pdo, 'sales_dispatch_allocations')) {
        $ld = $pdo->query(
            'SELECT a.sales_order_item_id, MAX(d.dispatch_date) AS last_dt
             FROM sales_dispatch_allocations a
             INNER JOIN dispatch d ON d.id = a.dispatch_id
             GROUP BY a.sales_order_item_id'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($ld as $r) {
            $lastDispatch[(int)$r['sales_order_item_id']] = (string)$r['last_dt'];
        }
    }

    $rows = [];
    foreach ($items as $it) {
        $orderId = (int)$it['order_id'];
        $ordered = (int)$it['qty_ordered'];
        $dispatched = (int)$it['qty_dispatched'];
        $inv = sales_track_invoice_status($pdo, $orderId);
        $pay = sales_track_payment_status($pdo, $orderId);
        $dsp = sales_track_dispatch_status($ordered, $dispatched, (string)$it['fulfillment_status']);
        $rows[] = [
            'item_id' => (int)$it['item_id'],
            'order_id' => $orderId,
            'so_number' => (string)$it['so_number'],
            'customer_id' => (int)$it['customer_id'],
            'company_name' => (string)$it['company_name'],
            'tyre_type' => (string)$it['tyre_type'],
            'qty_ordered' => $ordered,
            'qty_dispatched' => $dispatched,
            'qty_remaining' => max(0, $ordered - $dispatched),
            'expected_delivery' => (string)($it['delivery_date'] ?? ''),
            'last_dispatch_date' => $lastDispatch[(int)$it['item_id']] ?? '—',
            'dispatch_status' => $dsp,
            'invoice_status' => $inv,
            'payment_status' => $pay,
        ];
    }

    return $rows;
}

/** CRM dispatch tracking — summary from actual shipment records. */
function sales_crm_dispatch_shipment_summary(PDO $pdo): array
{
    if (!dh_table_exists($pdo, 'dispatch')) {
        return ['total' => 0, 'in_transit' => 0, 'delivered' => 0, 'pending_billing' => 0];
    }
    $rows = $pdo->query(
        "SELECT d.id, d.status,
            (SELECT COUNT(*) FROM sales_invoices i WHERE i.remarks LIKE CONCAT('%dispatch:', d.id, '%')) AS has_inv
         FROM dispatch d
         WHERE d.sales_order_id IS NOT NULL AND d.sales_order_id > 0"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $total = count($rows);
    $inTransit = 0;
    $delivered = 0;
    $pendingBilling = 0;
    foreach ($rows as $r) {
        $st = (string)($r['status'] ?? '');
        if ($st === 'Dispatched') {
            $inTransit++;
        }
        if ($st === 'Delivered') {
            $delivered++;
        }
        if ((int)($r['has_inv'] ?? 0) < 1) {
            $pendingBilling++;
        }
    }

    return [
        'total' => $total,
        'in_transit' => $inTransit,
        'delivered' => $delivered,
        'pending_billing' => $pendingBilling,
    ];
}

function sales_track_delivery_status(string $status): array
{
    return match ($status) {
        'Delivered' => ['label' => 'Delivered', 'class' => 'crm-track crm-track--full'],
        'Dispatched' => ['label' => 'Dispatched', 'class' => 'crm-track crm-track--partial'],
        'Pending' => ['label' => 'Pending', 'class' => 'crm-track crm-track--waiting'],
        default => ['label' => $status !== '' ? $status : 'Pending', 'class' => 'crm-track crm-track--muted'],
    };
}

function sales_invoice_id_for_dispatch(PDO $pdo, int $dispatchId): ?int
{
    if ($dispatchId < 1 || !dh_table_exists($pdo, 'sales_invoices')) {
        return null;
    }
    $st = $pdo->prepare('SELECT id FROM sales_invoices WHERE remarks LIKE :rm LIMIT 1');
    $st->execute(['rm' => '%dispatch:' . $dispatchId . '%']);
    $id = $st->fetchColumn();

    return $id !== false ? (int)$id : null;
}

/** @return list<array<string, mixed>> */
function sales_crm_dispatch_shipment_rows(PDO $pdo, array $filters = []): array
{
    if (!dh_table_exists($pdo, 'dispatch')) {
        return [];
    }
    $sql = 'SELECT d.id, d.dispatch_code, d.dispatch_date, d.tyre_type, d.qty, d.status,
            d.driver_name, d.vehicle_no, d.sales_order_id, d.customer_name,
            o.so_number, o.id AS order_id, c.id AS customer_id
        FROM dispatch d
        LEFT JOIN sales_orders o ON o.id = d.sales_order_id
        LEFT JOIN sales_customers c ON c.id = o.customer_id
        WHERE d.sales_order_id IS NOT NULL AND d.sales_order_id > 0';
    $params = [];
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $sql .= ' AND (d.dispatch_code LIKE :q OR o.so_number LIKE :q OR d.customer_name LIKE :q OR d.tyre_type LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $ds = (string)($filters['delivery_status'] ?? '');
    if ($ds === 'in_transit') {
        $sql .= " AND d.status = 'Dispatched'";
    } elseif ($ds === 'delivered') {
        $sql .= " AND d.status = 'Delivered'";
    } elseif ($ds === 'pending') {
        $sql .= " AND d.status = 'Pending'";
    }
    $sql .= ' ORDER BY d.dispatch_date DESC, d.id DESC LIMIT 300';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $dispatches = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $rows = [];
    foreach ($dispatches as $d) {
        $orderId = (int)($d['order_id'] ?? $d['sales_order_id'] ?? 0);
        $dispatchId = (int)$d['id'];
        $invId = sales_invoice_id_for_dispatch($pdo, $dispatchId);
        $invSt = $invId ? sales_track_invoice_status_simple($pdo, $invId) : ['label' => 'Not Generated', 'class' => 'crm-track crm-track--muted', 'invoice_id' => null];
        $paySt = $orderId > 0 ? sales_track_payment_status($pdo, $orderId) : ['label' => 'Unpaid', 'class' => 'crm-track crm-track--unpaid'];
        $rows[] = [
            'dispatch_id' => $dispatchId,
            'dispatch_code' => (string)($d['dispatch_code'] ?? ('DSP-' . $dispatchId)),
            'so_number' => (string)($d['so_number'] ?? '—'),
            'order_id' => $orderId,
            'customer_id' => (int)($d['customer_id'] ?? 0),
            'company_name' => (string)($d['customer_name'] ?? '—'),
            'tyre_type' => (string)$d['tyre_type'],
            'qty' => (int)$d['qty'],
            'driver' => (string)($d['driver_name'] ?? '—'),
            'vehicle' => (string)($d['vehicle_no'] ?? '—'),
            'dispatch_date' => (string)$d['dispatch_date'],
            'delivery_status' => sales_track_delivery_status((string)($d['status'] ?? 'Pending')),
            'invoice_status' => $invSt,
            'payment_status' => $paySt,
            'invoice_id' => $invId,
        ];
    }

    return $rows;
}

/** Simplified invoice status: Not Generated | Generated | Paid */
function sales_track_invoice_status_simple(PDO $pdo, int $invoiceId): array
{
    $st = $pdo->prepare('SELECT id, total_amount, amount_paid FROM sales_invoices WHERE id = :id');
    $st->execute(['id' => $invoiceId]);
    $inv = $st->fetch(PDO::FETCH_ASSOC);
    if (!$inv) {
        return ['label' => 'Not Generated', 'class' => 'crm-track crm-track--muted', 'invoice_id' => null];
    }
    if ((float)$inv['amount_paid'] >= (float)$inv['total_amount'] - 0.01) {
        return ['label' => 'Paid', 'class' => 'crm-track crm-track--paid', 'invoice_id' => $invoiceId];
    }

    return ['label' => 'Generated', 'class' => 'crm-track crm-track--generated', 'invoice_id' => $invoiceId];
}

/** @return list<array<string, mixed>> */
function sales_customer_outstanding_full(PDO $pdo): array
{
    if (!dh_table_exists($pdo, 'sales_invoices')) {
        return [];
    }
    $rows = $pdo->query(
        "SELECT c.id, c.company_name,
            COALESCE(SUM(i.total_amount), 0) AS total_invoiced,
            COALESCE(SUM(i.amount_paid), 0) AS total_paid,
            COALESCE(SUM(i.total_amount - i.amount_paid), 0) AS pending,
            COALESCE(SUM(CASE WHEN i.due_date < CURDATE() AND i.payment_status != 'Paid'
                THEN i.total_amount - i.amount_paid ELSE 0 END), 0) AS overdue,
            (SELECT MAX(p.payment_date) FROM sales_payments p WHERE p.customer_id = c.id) AS last_payment
         FROM sales_customers c
         INNER JOIN sales_invoices i ON i.customer_id = c.id
         GROUP BY c.id, c.company_name
         HAVING pending > 0.01
         ORDER BY pending DESC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $pending = (float)$r['pending'];
        $paid = (float)$r['total_paid'];
        $total = (float)$r['total_invoiced'];
        if ($paid >= $total - 0.01 && $total > 0) {
            $r['status_label'] = 'Paid';
            $r['status_class'] = 'crm-track crm-track--paid';
        } elseif ($paid > 0.01) {
            $r['status_label'] = 'Partial';
            $r['status_class'] = 'crm-track crm-track--partial-pay';
        } else {
            $r['status_label'] = 'Unpaid';
            $r['status_class'] = 'crm-track crm-track--unpaid';
        }
        $r['last_payment'] = $r['last_payment'] ?: '—';
        if ((float)$r['overdue'] > 0.01) {
            $r['status_label'] = 'Overdue';
            $r['status_class'] = 'crm-track crm-track--overdue';
        }
    }
    unset($r);

    return $rows;
}

function sales_get_payment(PDO $pdo, int $id): ?array
{
    if ($id < 1 || !dh_table_exists($pdo, 'sales_payments')) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT p.*, i.invoice_no, i.total_amount AS invoice_total, i.amount_paid AS invoice_paid,
         c.company_name, c.customer_code
         FROM sales_payments p
         INNER JOIN sales_invoices i ON i.id = p.invoice_id
         INNER JOIN sales_customers c ON c.id = p.customer_id
         WHERE p.id = :id LIMIT 1'
    );
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}
