<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/qc_service.php';
require_once __DIR__ . '/_flow.php';

if (!has_role(['Quality Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$stock = qc_rework_stock($pdo);
?>

<div class="qc-page">
    <?php qc_render_flow_bar('qc'); ?>

    <header class="qc-page__head">
        <div>
            <h1 class="qc-page__title">Rejected / Rework Stock</h1>
            <p class="qc-page__sub">Tyres not cleared for dispatch — held for rework or scrap disposition</p>
        </div>
        <nav class="qc-nav-quick">
            <a href="<?= e(route_url('quality/pending')) ?>">Pending inspections</a>
            <a href="<?= e(route_url('inventory/dashboard')) ?>">Inventory</a>
        </nav>
    </header>

    <div class="alert alert-light border small mb-3">
        <strong>ERP rule:</strong> Only <em>QC passed</em> stock appears in dispatch. Reject and rework quantities are stored separately below.
    </div>

    <section class="qc-card">
        <header class="qc-card__head"><h2 class="qc-card__title">Rework & scrap inventory</h2></header>
        <div class="table-responsive">
            <table class="qc-table">
                <thead>
                    <tr>
                        <th>Tyre type</th>
                        <th>Batch ref</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th class="text-end">Qty</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($stock as $s): ?>
                    <tr>
                        <td><?= e((string)$s['product_name']) ?></td>
                        <td><?= e((string)$s['batch_ref']) ?></td>
                        <td>
                            <span class="qc-badge qc-badge--<?= ($s['stock_category'] ?? '') === QC_STOCK_SCRAP ? 'rejected' : 'rework' ?>">
                                <?= ($s['stock_category'] ?? '') === QC_STOCK_SCRAP ? 'Scrap / Reject' : 'Rework' ?>
                            </span>
                        </td>
                        <td><?= e((string)$s['warehouse_location']) ?></td>
                        <td class="text-end"><?= e(qc_format_qty((int)$s['qty'])) ?></td>
                        <td><?= e((string)($s['updated_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($stock === []): ?>
                    <tr><td colspan="6" class="qc-empty">No rework or scrap stock on hand.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
