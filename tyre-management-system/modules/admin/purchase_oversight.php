<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_oversight_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
admin_oversight_handle_post($pdo);
$q = trim((string)($_GET['q'] ?? ''));
$suppliers = admin_oversight_suppliers($pdo, $q);
$purchases = admin_oversight_recent_purchases($pdo, 10);
?>
<div class="admin-cc module-shell">
    <?php admin_page_head('Purchase Oversight', 'Monitor suppliers, purchase inward, payments, and ledger'); ?>
    <form method="get" class="admin-cc__filters">
        <input type="hidden" name="page" value="admin/purchase-oversight">
        <div class="admin-field"><label>Search suppliers</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($q) ?>"></div>
        <button class="btn btn-sm btn-primary">Search</button>
        <a href="<?= e(route_url('inventory/suppliers')) ?>" class="btn btn-sm btn-outline-secondary">Inventory Suppliers</a>
    </form>
    <section class="admin-card admin-table-wrap">
        <h2 class="admin-card__title">Suppliers</h2>
        <table class="table table-sm admin-table mb-0">
            <thead><tr><th>Supplier</th><th>Contact</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($suppliers as $s): ?>
                <?php $bl = strtolower((string)($s['status'] ?? 'active')) === 'blacklisted'; ?>
                <tr>
                    <td><strong><?= e((string)$s['name']) ?></strong></td>
                    <td><?= e((string)($s['phone'] ?? '—')) ?></td>
                    <td><span class="admin-badge <?= $bl ? 'admin-badge--danger' : 'admin-badge--ok' ?>"><?= $bl ? 'Blacklisted' : 'Active' ?></span></td>
                    <td>
                        <div class="admin-labeled-actions">
                            <a href="<?= e(route_url('accounts/supplier-ledger')) ?>" class="admin-action-btn admin-action-btn--neutral">Ledger</a>
                            <?php if ($bl): ?>
                                <form method="post" class="d-inline admin-confirm-form"><?= csrf_input() ?><input type="hidden" name="action" value="reactivate_supplier"><input type="hidden" name="supplier_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="return" value="admin/purchase-oversight"><button class="admin-action-btn admin-action-btn--success" data-confirm="Reactivate this supplier?">Reactivate</button></form>
                            <?php else: ?>
                                <form method="post" class="d-inline admin-confirm-form"><?= csrf_input() ?><input type="hidden" name="action" value="blacklist_supplier"><input type="hidden" name="supplier_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="return" value="admin/purchase-oversight"><button class="admin-action-btn admin-action-btn--danger" data-confirm="Blacklist this supplier?">Blacklist</button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="admin-card admin-table-wrap">
        <h2 class="admin-card__title">Recent Purchase Inward</h2>
        <table class="table table-sm admin-table mb-0">
            <thead><tr><th>Date</th><th>Supplier</th><th>Payment</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($purchases as $p): ?>
                <tr>
                    <td><?= e((string)($p['inward_date'] ?? '')) ?></td>
                    <td><?= e((string)($p['supplier_name'] ?? '—')) ?></td>
                    <td><?= e((string)($p['payment_status'] ?? '—')) ?></td>
                    <td class="text-end"><?= e(sales_format_money((float)($p['total_amount'] ?? 0))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="p-2"><a href="<?= e(route_url('inventory/purchase-history')) ?>" class="admin-link-btn">Purchase History →</a> · <a href="<?= e(route_url('accounts/payables')) ?>" class="admin-link-btn">Supplier Payments →</a></div>
    </section>
</div>
