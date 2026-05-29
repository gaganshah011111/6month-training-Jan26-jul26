<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_oversight_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
admin_oversight_handle_post($pdo);
$q = trim((string)($_GET['q'] ?? ''));
$customers = admin_oversight_customers($pdo, $q);
$orders = admin_oversight_recent_orders($pdo, 10);
?>
<div class="admin-cc module-shell">
    <?php admin_page_head('Sales Oversight', 'Monitor customers, orders, collections, and dispatch — admin control actions'); ?>
    <form method="get" class="admin-cc__filters">
        <input type="hidden" name="page" value="admin/sales-oversight">
        <div class="admin-field"><label>Search customers</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($q) ?>"></div>
        <button class="btn btn-sm btn-primary">Search</button>
        <a href="<?= e(route_url('sales/customers')) ?>" class="btn btn-sm btn-outline-secondary">Sales CRM</a>
    </form>
    <section class="admin-card admin-table-wrap">
        <h2 class="admin-card__title">Customers</h2>
        <table class="table table-sm admin-table mb-0">
            <thead><tr><th>Customer</th><th>Status</th><th>Pending</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <?php $frozen = !empty($c['is_frozen']); ?>
                <tr>
                    <td><strong><?= e((string)$c['company_name']) ?></strong><small><?= e((string)$c['customer_code']) ?></small></td>
                    <td><span class="admin-badge <?= $frozen ? 'admin-badge--warn' : 'admin-badge--ok' ?>"><?= $frozen ? 'Frozen' : e((string)$c['status']) ?></span></td>
                    <td><?= e(sales_format_money((float)($c['pending'] ?? 0))) ?></td>
                    <td>
                        <div class="admin-labeled-actions">
                            <a href="<?= e(route_url('sales/customers', ['id' => $c['id']])) ?>" class="admin-action-btn admin-action-btn--neutral">View</a>
                            <?php if ($frozen): ?>
                                <form method="post" class="d-inline admin-confirm-form"><?= csrf_input() ?><input type="hidden" name="action" value="unfreeze_customer"><input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="return" value="admin/sales-oversight"><button class="admin-action-btn admin-action-btn--success" data-confirm="Reactivate this customer?">Unfreeze</button></form>
                            <?php else: ?>
                                <form method="post" class="d-inline admin-confirm-form"><?= csrf_input() ?><input type="hidden" name="action" value="freeze_customer"><input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="return" value="admin/sales-oversight"><button class="admin-action-btn admin-action-btn--warn" data-confirm="Freeze this customer account?">Freeze</button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="admin-card admin-table-wrap">
        <h2 class="admin-card__title">Recent Orders</h2>
        <table class="table table-sm admin-table mb-0">
            <thead><tr><th>SO #</th><th>Customer</th><th>Date</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="<?= e(route_url('sales/orders')) ?>"><?= e((string)$o['so_number']) ?></a></td>
                    <td><?= e((string)$o['company_name']) ?></td>
                    <td><?= e((string)$o['order_date']) ?></td>
                    <td><?= e((string)$o['status']) ?></td>
                    <td class="text-end"><?= e(sales_format_money((float)($o['total_amount'] ?? 0))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="p-2"><a href="<?= e(route_url('accounts/receivables')) ?>" class="admin-link-btn">Pending Collections →</a> · <a href="<?= e(route_url('dispatch/history')) ?>" class="admin-link-btn">Dispatch Status →</a></div>
    </section>
</div>
