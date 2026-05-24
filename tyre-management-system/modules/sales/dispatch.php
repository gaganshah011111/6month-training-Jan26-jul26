<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
$loadError = false;
$openOrders = [];
$recent = [];

try {
    $openOrders = sales_list_orders($pdo, []);
    $openOrders = array_values(array_filter($openOrders, static fn($o) => !in_array((string)$o['status'], ['Completed', 'Cancelled'], true)));
    if (dh_table_exists($pdo, 'sales_dispatch_allocations')) {
        $recent = $pdo->query(
            'SELECT d.dispatch_code, d.dispatch_date, d.tyre_type, d.qty, d.customer_name, o.so_number, a.qty AS alloc_qty
             FROM sales_dispatch_allocations a
             INNER JOIN dispatch d ON d.id = a.dispatch_id
             INNER JOIN sales_orders o ON o.id = a.sales_order_id
             ORDER BY d.dispatch_date DESC, a.id DESC LIMIT 30'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_dispatch');
    $loadError = true;
}
?>

<div class="sales-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Dispatch Tracking</h1>
            <p class="prod-page__sub">Ship tyres against sales orders — updates order balance and generates CRM invoices.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('sales/dispatch-entry')) ?>">Record dispatch</a>
        </nav>
    </header>

    <?php if ($loadError): ?><?= sales_error_alert('Unable to load dispatch tracking.') ?><?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Orders pending fulfilment</h2></div>
                <div class="sales-table-wrap">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>SO</th><th>Customer</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($openOrders as $o): ?>
                            <tr>
                                <td><?= e($o['so_number']) ?></td>
                                <td><?= e($o['company_name']) ?></td>
                                <td><?= e($o['status']) ?></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('sales/dispatch-entry', ['sales_order_id' => (int)$o['id']])) ?>">Dispatch</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$openOrders): ?><tr><td colspan="4" class="text-center text-muted py-3">No open orders.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Recent CRM-linked dispatches</h2></div>
                <div class="sales-table-wrap">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Dispatch</th><th>SO</th><th>Tyre</th><th class="text-end">Qty</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= e($r['dispatch_code']) ?></td>
                                <td><?= e($r['so_number']) ?></td>
                                <td><?= e($r['tyre_type']) ?></td>
                                <td class="text-end"><?= e((string)$r['alloc_qty']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recent): ?><tr><td colspan="4" class="text-center text-muted py-3">No linked dispatches yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
