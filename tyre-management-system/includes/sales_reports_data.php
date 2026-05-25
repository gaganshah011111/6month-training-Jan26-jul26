<?php
declare(strict_types=1);

require_once __DIR__ . '/sales_finance.php';

/**
 * Build filter SQL fragments for CRM reports (orders, invoices, payments, dispatch).
 *
 * @return array{sql: string, params: array<string, mixed>, order_sql: string, dispatch_sql: string}
 */
function sales_reports_filter_clause(array $filters): array
{
    $params = [];
    $invSql = '';
    $orderSql = '';
    $dispatchSql = '';
    $paySql = '';

    $from = trim((string)($filters['from'] ?? ''));
    $to = trim((string)($filters['to'] ?? ''));
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $invSql .= ' AND i.invoice_date >= :inv_df';
        $orderSql .= ' AND o.order_date >= :ord_df';
        $dispatchSql .= ' AND d.dispatch_date >= :dis_df';
        $paySql .= ' AND p.payment_date >= :pay_df';
        $params['inv_df'] = $from;
        $params['ord_df'] = $from;
        $params['dis_df'] = $from;
        $params['pay_df'] = $from;
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $invSql .= ' AND i.invoice_date <= :inv_dt';
        $orderSql .= ' AND o.order_date <= :ord_dt';
        $dispatchSql .= ' AND d.dispatch_date <= :dis_dt';
        $paySql .= ' AND p.payment_date <= :pay_dt';
        $params['inv_dt'] = $to;
        $params['ord_dt'] = $to;
        $params['dis_dt'] = $to;
        $params['pay_dt'] = $to;
    }
    $cid = (int)($filters['customer_id'] ?? 0);
    if ($cid > 0) {
        $invSql .= ' AND i.customer_id = :inv_cid';
        $orderSql .= ' AND o.customer_id = :ord_cid';
        $dispatchSql .= ' AND o.customer_id = :dis_cid';
        $paySql .= ' AND p.customer_id = :pay_cid';
        $params['inv_cid'] = $cid;
        $params['ord_cid'] = $cid;
        $params['dis_cid'] = $cid;
        $params['pay_cid'] = $cid;
    }
    $invStatus = (string)($filters['invoice_status'] ?? '');
    if ($invStatus !== '' && in_array($invStatus, SALES_PAYMENT_STATUSES, true)) {
        $invSql .= ' AND i.payment_status = :ist';
        $params['ist'] = $invStatus;
    }
    $payFilter = (string)($filters['payment_status'] ?? '');
    if ($payFilter === 'paid') {
        $invSql .= " AND i.amount_paid >= i.total_amount - 0.01";
    } elseif ($payFilter === 'partial') {
        $invSql .= " AND i.amount_paid > 0.01 AND i.amount_paid < i.total_amount - 0.01";
    } elseif ($payFilter === 'unpaid') {
        $invSql .= ' AND i.amount_paid < 0.01';
    } elseif ($payFilter === 'overdue') {
        $invSql .= " AND (i.payment_status = 'Overdue' OR (i.due_date < CURDATE() AND i.payment_status != 'Paid'))";
    }

    return [
        'invoice_sql' => $invSql,
        'order_sql' => $orderSql,
        'dispatch_sql' => $dispatchSql,
        'payment_sql' => $paySql,
        'params' => $params,
        'tyre_type' => trim((string)($filters['tyre_type'] ?? '')),
    ];
}

/** @return array<string, mixed> */
function sales_reports_params_for(array $fc, string ...$scopes): array
{
    $out = [];
    $all = $fc['params'] ?? [];
    $map = [
        'invoice' => ['inv_df', 'inv_dt', 'inv_cid', 'ist'],
        'order' => ['ord_df', 'ord_dt', 'ord_cid'],
        'dispatch' => ['dis_df', 'dis_dt', 'dis_cid'],
        'payment' => ['pay_df', 'pay_dt', 'pay_cid'],
        'tyre' => ['tt'],
    ];
    foreach ($scopes as $scope) {
        foreach ($map[$scope] ?? [] as $key) {
            if (array_key_exists($key, $all)) {
                $out[$key] = $all[$key];
            }
        }
    }

    return $out;
}

/** @return array<string, mixed> */
function sales_crm_reports_bundle(PDO $pdo, array $filters = []): array
{
    sales_ensure_schema($pdo);
    $fc = sales_reports_filter_clause($filters);
    $tyreType = $fc['tyre_type'];
    if ($tyreType !== '') {
        $fc['params']['tt'] = $tyreType;
    }
    $invParams = sales_reports_params_for($fc, 'invoice');
    $ordParams = sales_reports_params_for($fc, 'order');
    $disParams = sales_reports_params_for($fc, 'dispatch');
    $payParams = sales_reports_params_for($fc, 'payment');
    if ($tyreType !== '') {
        $ordParams['tt'] = $tyreType;
        $invParams['tt'] = $tyreType;
    }

    $summary = [
        'total_sales' => 0.0,
        'total_orders' => 0,
        'total_customers' => 0,
        'total_invoices' => 0,
        'total_paid' => 0.0,
        'total_pending' => 0.0,
        'total_dispatches' => 0,
        'total_gst' => 0.0,
    ];

    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare(
            "SELECT COUNT(*) AS inv_cnt, COALESCE(SUM(i.total_amount),0) AS sales,
                COALESCE(SUM(i.amount_paid),0) AS paid,
                COALESCE(SUM(i.total_amount - i.amount_paid),0) AS pending,
                COALESCE(SUM(i.gst_total),0) AS gst
             FROM sales_invoices i WHERE 1=1 {$fc['invoice_sql']}"
        );
        $st->execute($invParams);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $summary['total_sales'] = (float)($row['sales'] ?? 0);
        $summary['total_invoices'] = (int)($row['inv_cnt'] ?? 0);
        $summary['total_paid'] = (float)($row['paid'] ?? 0);
        $summary['total_pending'] = (float)($row['pending'] ?? 0);
        $summary['total_gst'] = (float)($row['gst'] ?? 0);
    }

    if (dh_table_exists($pdo, 'sales_orders')) {
        $st = $pdo->prepare(
            "SELECT COUNT(DISTINCT o.id) FROM sales_orders o
             WHERE o.status != 'Cancelled' {$fc['order_sql']}"
            . ($tyreType !== '' ? " AND EXISTS (SELECT 1 FROM sales_order_items oi WHERE oi.order_id = o.id AND oi.tyre_type = :tt)" : '')
        );
        $st->execute($ordParams);
        $summary['total_orders'] = (int)$st->fetchColumn();
        $st = $pdo->prepare(
            "SELECT COUNT(DISTINCT o.customer_id) FROM sales_orders o
             WHERE o.status != 'Cancelled' {$fc['order_sql']}"
            . ($tyreType !== '' ? " AND EXISTS (SELECT 1 FROM sales_order_items oi WHERE oi.order_id = o.id AND oi.tyre_type = :tt)" : '')
        );
        $st->execute($ordParams);
        $summary['total_customers'] = (int)$st->fetchColumn();
    }

    if (dh_table_exists($pdo, 'dispatch')) {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM dispatch d
             LEFT JOIN sales_orders o ON o.id = d.sales_order_id
             WHERE d.sales_order_id IS NOT NULL AND d.sales_order_id > 0 {$fc['dispatch_sql']}"
        );
        $st->execute($disParams);
        $summary['total_dispatches'] = (int)$st->fetchColumn();
    }

    $topCustomers = [];
    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare(
            "SELECT c.id, c.company_name,
                COUNT(DISTINCT o.id) AS order_count,
                COALESCE(SUM(i.total_amount),0) AS total_amount,
                COALESCE(SUM(i.amount_paid),0) AS paid,
                COALESCE(SUM(i.total_amount - i.amount_paid),0) AS pending
             FROM sales_customers c
             INNER JOIN sales_invoices i ON i.customer_id = c.id
             LEFT JOIN sales_orders o ON o.customer_id = c.id AND o.status != 'Cancelled' {$fc['order_sql']}
             WHERE 1=1 {$fc['invoice_sql']}
             GROUP BY c.id, c.company_name
             ORDER BY total_amount DESC
             LIMIT 15"
        );
        $st->execute(array_merge($invParams, $ordParams));
        $topCustomers = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $maxRev = max(1.0, ...array_map(static fn($r) => (float)$r['total_amount'], $topCustomers ?: [['total_amount' => 1]]));
        foreach ($topCustomers as &$tc) {
            $tc['pct'] = min(100, (int)round(((float)$tc['total_amount'] / $maxRev) * 100));
        }
        unset($tc);
    }

    $tyreSales = [];
    if (dh_table_exists($pdo, 'sales_invoice_items')) {
        $st = $pdo->prepare(
            "SELECT ii.tyre_type,
                COALESCE(SUM(ii.qty),0) AS qty_sold,
                COALESCE(SUM(ii.line_total),0) AS revenue
             FROM sales_invoice_items ii
             INNER JOIN sales_invoices i ON i.id = ii.invoice_id
             WHERE 1=1 {$fc['invoice_sql']}" . ($tyreType !== '' ? ' AND ii.tyre_type = :tt' : '') . '
             GROUP BY ii.tyre_type ORDER BY revenue DESC'
        );
        $st->execute($invParams);
        $tyreSales = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    foreach ($tyreSales as &$ts) {
        $tt = (string)$ts['tyre_type'];
        $pendingDisp = 0;
        if (dh_table_exists($pdo, 'sales_order_items')) {
            $q = $pdo->prepare(
                "SELECT COALESCE(SUM(oi.qty_ordered - oi.qty_dispatched),0)
                 FROM sales_order_items oi INNER JOIN sales_orders o ON o.id = oi.order_id
                 WHERE oi.tyre_type = :tt AND o.status NOT IN ('Completed','Cancelled')"
            );
            $q->execute(['tt' => $tt]);
            $pendingDisp = (int)$q->fetchColumn();
        }
        $ts['pending_dispatch'] = $pendingDisp;
    }
    unset($ts);

    $paymentOutstanding = [];
    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare(
            "SELECT i.id, i.invoice_no, i.due_date, i.total_amount, i.amount_paid,
                c.company_name,
                (i.total_amount - i.amount_paid) AS pending
             FROM sales_invoices i
             INNER JOIN sales_customers c ON c.id = i.customer_id
             WHERE (i.total_amount - i.amount_paid) > 0.01 {$fc['invoice_sql']}
             ORDER BY i.due_date ASC, pending DESC LIMIT 50"
        );
        $st->execute($invParams);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $inv) {
            $paid = (float)$inv['amount_paid'];
            $total = (float)$inv['total_amount'];
            $due = (string)($inv['due_date'] ?? '');
            if ($paid >= $total - 0.01) {
                $stLabel = 'Paid';
                $stClass = 'crm-track crm-track--paid';
            } elseif ($due !== '' && $due < date('Y-m-d')) {
                $stLabel = 'Overdue';
                $stClass = 'crm-track crm-track--overdue';
            } elseif ($paid > 0.01) {
                $stLabel = 'Partial';
                $stClass = 'crm-track crm-track--partial-pay';
            } else {
                $stLabel = 'Unpaid';
                $stClass = 'crm-track crm-track--unpaid';
            }
            $paymentOutstanding[] = array_merge($inv, ['status_label' => $stLabel, 'status_class' => $stClass]);
        }
    }

    $dispatchPerf = [
        'total' => 0,
        'delivered' => 0,
        'pending' => 0,
        'partial' => 0,
        'avg_days' => 0,
    ];
    if (dh_table_exists($pdo, 'dispatch')) {
        $st = $pdo->prepare(
            "SELECT d.status, COUNT(*) AS cnt,
                AVG(DATEDIFF(CURDATE(), d.dispatch_date)) AS avg_days
             FROM dispatch d
             LEFT JOIN sales_orders o ON o.id = d.sales_order_id
             WHERE d.sales_order_id IS NOT NULL AND d.sales_order_id > 0 {$fc['dispatch_sql']}
             GROUP BY d.status"
        );
        $st->execute($disParams);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $avgSum = 0;
        $avgCnt = 0;
        foreach ($rows as $r) {
            $cnt = (int)$r['cnt'];
            $dispatchPerf['total'] += $cnt;
            if ((string)$r['status'] === 'Delivered') {
                $dispatchPerf['delivered'] += $cnt;
            } elseif ((string)$r['status'] === 'Pending') {
                $dispatchPerf['pending'] += $cnt;
            } else {
                $dispatchPerf['partial'] += $cnt;
            }
            if ($r['avg_days'] !== null) {
                $avgSum += (float)$r['avg_days'] * $cnt;
                $avgCnt += $cnt;
            }
        }
        $dispatchPerf['avg_days'] = $avgCnt > 0 ? (int)round($avgSum / $avgCnt) : 0;
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM sales_orders o
             WHERE o.status = 'Partially Dispatched' {$fc['order_sql']}"
        );
        $st->execute($ordParams);
        $dispatchPerf['partial_orders'] = (int)$st->fetchColumn();
    }

    $activity = [];
    if (dh_table_exists($pdo, 'sales_orders')) {
        $st = $pdo->prepare(
            "SELECT o.order_date AS dt, CONCAT('SO created: ', o.so_number) AS label, c.company_name, 'order' AS kind
             FROM sales_orders o INNER JOIN sales_customers c ON c.id = o.customer_id
             WHERE o.status != 'Cancelled' {$fc['order_sql']} ORDER BY o.order_date DESC, o.id DESC LIMIT 15"
        );
        $st->execute($ordParams);
        $activity = array_merge($activity, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
    if (dh_table_exists($pdo, 'dispatch')) {
        $st = $pdo->prepare(
            "SELECT d.dispatch_date AS dt, CONCAT('Dispatch: ', COALESCE(d.dispatch_code, d.id)) AS label,
                d.customer_name AS company_name, 'dispatch' AS kind
             FROM dispatch d
             LEFT JOIN sales_orders o ON o.id = d.sales_order_id
             WHERE d.sales_order_id > 0 {$fc['dispatch_sql']}
             ORDER BY d.dispatch_date DESC LIMIT 15"
        );
        $st->execute($disParams);
        $activity = array_merge($activity, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare(
            "SELECT i.invoice_date AS dt, CONCAT('Invoice: ', i.invoice_no) AS label, c.company_name, 'invoice' AS kind
             FROM sales_invoices i INNER JOIN sales_customers c ON c.id = i.customer_id
             WHERE 1=1 {$fc['invoice_sql']} ORDER BY i.invoice_date DESC LIMIT 15"
        );
        $st->execute($invParams);
        $activity = array_merge($activity, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
    if (dh_table_exists($pdo, 'sales_payments')) {
        $st = $pdo->prepare(
            "SELECT p.payment_date AS dt, CONCAT('Payment: ', FORMAT(p.amount, 0)) AS label, c.company_name, 'payment' AS kind
             FROM sales_payments p INNER JOIN sales_customers c ON c.id = p.customer_id
             WHERE 1=1 {$fc['payment_sql']} ORDER BY p.payment_date DESC LIMIT 15"
        );
        $st->execute($payParams);
        $activity = array_merge($activity, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
    usort($activity, static fn($a, $b) => strcmp((string)$b['dt'], (string)$a['dt']));
    $activity = array_slice($activity, 0, 20);

    $monthlySales = [];
    $collectionTrend = [];
    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->query(
            "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS ym, SUM(total_amount) AS amt
             FROM sales_invoices WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY ym ORDER BY ym ASC"
        );
        $monthlySales = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $st = $pdo->query(
            "SELECT DATE_FORMAT(p.payment_date, '%Y-%m') AS ym, SUM(p.amount) AS amt
             FROM sales_payments p
             WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY ym ORDER BY ym ASC"
        );
        $collectionTrend = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    $customerChart = array_slice($topCustomers, 0, 8);

    return [
        'summary' => $summary,
        'top_customers' => $topCustomers,
        'tyre_sales' => $tyreSales,
        'payment_outstanding' => $paymentOutstanding,
        'dispatch_perf' => $dispatchPerf,
        'activity' => $activity,
        'charts' => [
            'monthly_sales' => $monthlySales,
            'collection' => $collectionTrend,
            'tyre_labels' => array_column($tyreSales, 'tyre_type'),
            'tyre_revenue' => array_map(static fn($r) => (float)$r['revenue'], $tyreSales),
            'customer_labels' => array_column($customerChart, 'company_name'),
            'customer_revenue' => array_map(static fn($r) => (float)$r['total_amount'], $customerChart),
        ],
    ];
}
