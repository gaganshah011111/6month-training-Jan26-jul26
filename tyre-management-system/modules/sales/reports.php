<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$customerId = (int)($_GET['customer_id'] ?? 0);
$dateSql = '';
$params = [];
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $dateSql .= ' AND i.invoice_date >= :df';
    $params['df'] = $from;
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $dateSql .= ' AND i.invoice_date <= :dt';
    $params['dt'] = $to;
}
if ($customerId > 0) {
    $dateSql .= ' AND i.customer_id = :cid';
    $params['cid'] = $customerId;
}

$loadError = false;
$byCustomer = [];
$byTyre = [];
$pending = [];
$customers = [];

try {
    $st = $pdo->prepare(
        "SELECT c.company_name, SUM(i.total_amount) AS revenue, SUM(i.total_amount - i.amount_paid) AS pending
         FROM sales_invoices i INNER JOIN sales_customers c ON c.id = i.customer_id WHERE 1=1 {$dateSql}
         GROUP BY c.id ORDER BY revenue DESC LIMIT 15"
    );
    $st->execute($params);
    $byCustomer = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $st = $pdo->prepare(
        "SELECT ii.tyre_type, SUM(ii.qty) AS units, SUM(ii.line_total) AS revenue
         FROM sales_invoice_items ii INNER JOIN sales_invoices i ON i.id = ii.invoice_id WHERE 1=1 {$dateSql}
         GROUP BY ii.tyre_type ORDER BY units DESC"
    );
    $st->execute($params);
    $byTyre = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $pending = $pdo->query(
        "SELECT c.company_name, SUM(i.total_amount - i.amount_paid) AS due
         FROM sales_invoices i INNER JOIN sales_customers c ON c.id = i.customer_id
         WHERE i.payment_status IN ('Pending','Partial','Overdue')
         GROUP BY c.id ORDER BY due DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $customers = sales_list_customers($pdo, []);
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_reports');
    $loadError = true;
}
?>

<div class="sales-page">
    <header class="prod-page__head"><div><h1 class="prod-page__title">Sales Reports</h1><p class="prod-page__sub">Customer-wise, tyre-wise, and payment outstanding.</p></div></header>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if ($loadError): ?><?= sales_error_alert('Unable to load reports. Please adjust filters or try again later.') ?><?php endif; ?>
    <form method="get" class="sales-filter-bar">
        <input type="hidden" name="page" value="sales/reports">
        <div class="sales-filter-bar__grid">
            <div class="sales-filter-bar__field"><label>From (optional)</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
            <div class="sales-filter-bar__field"><label>To (optional)</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
            <div class="sales-filter-bar__field"><label>Customer</label><select class="form-select form-select-sm" name="customer_id"><option value="0">All</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="sales-filter-bar__actions"><button class="btn btn-primary btn-sm" type="submit">Apply</button><button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button></div>
    </form>
    <div class="row g-3">
        <div class="col-lg-6">
            <section class="sales-card"><div class="sales-card__head"><h2 class="sales-card__title">Top customers</h2></div>
                <div class="sales-table-wrap"><table class="table table-sm mb-0"><thead><tr><th>Customer</th><th class="text-end">Revenue</th><th class="text-end">Pending</th></tr></thead><tbody>
                <?php foreach ($byCustomer as $r): ?><tr><td><?= e($r['company_name']) ?></td><td class="text-end"><?= e(sales_format_money((float)$r['revenue'])) ?></td><td class="text-end"><?= e(sales_format_money((float)$r['pending'])) ?></td></tr><?php endforeach; ?>
                </tbody></table></div></section>
        </div>
        <div class="col-lg-6">
            <section class="sales-card"><div class="sales-card__head"><h2 class="sales-card__title">Tyre-wise sales</h2></div>
                <div class="sales-table-wrap"><table class="table table-sm mb-0"><thead><tr><th>Tyre</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead><tbody>
                <?php foreach ($byTyre as $r): ?><tr><td><?= e($r['tyre_type']) ?></td><td class="text-end"><?= e((string)$r['units']) ?></td><td class="text-end"><?= e(sales_format_money((float)$r['revenue'])) ?></td></tr><?php endforeach; ?>
                </tbody></table></div></section>
        </div>
        <div class="col-12">
            <section class="sales-card"><div class="sales-card__head"><h2 class="sales-card__title">Pending / overdue payments</h2></div>
                <div class="sales-table-wrap"><table class="table table-sm mb-0"><thead><tr><th>Customer</th><th class="text-end">Outstanding</th></tr></thead><tbody>
                <?php foreach ($pending as $r): ?><tr><td><?= e($r['company_name']) ?></td><td class="text-end text-danger"><?= e(sales_format_money((float)$r['due'])) ?></td></tr><?php endforeach; ?>
                </tbody></table></div></section>
        </div>
    </div>
</div>
