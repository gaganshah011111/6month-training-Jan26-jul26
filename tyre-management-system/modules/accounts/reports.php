<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';

$pdo = Database::connection();
$report = (string)($_GET['report'] ?? 'ledger');
$reports = [
    'ledger' => ['title' => 'Customer ledger', 'hint' => 'Debit/credit entries per customer'],
    'aging' => ['title' => 'Invoice aging', 'hint' => 'Overdue and due-soon receivables'],
    'collection' => ['title' => 'Payment collection', 'hint' => 'Collections by period and mode'],
    'dispatch_billing' => ['title' => 'Dispatch billing', 'hint' => 'Dispatches vs invoiced amounts'],
    'profit' => ['title' => 'Profit summary', 'hint' => 'Revenue vs expense snapshot'],
];
if (!isset($reports[$report])) {
    $report = 'ledger';
}
$meta = $reports[$report];
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Financial reports</h1>
            <p class="prod-page__sub"><?= e($meta['hint']) ?></p>
        </div>
    </header>

    <div class="sales-filter-bar mb-3 d-flex flex-wrap gap-2">
        <?php foreach ($reports as $key => $r): ?>
            <a class="btn btn-sm <?= $report === $key ? 'btn-primary' : 'btn-outline-secondary' ?>"
               href="<?= e(route_url('accounts/reports', ['report' => $key])) ?>"><?= e($r['title']) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($report === 'ledger'): ?>
        <p class="mb-2"><a href="<?= e(route_url('accounts/ledger')) ?>">Open customer ledger →</a></p>
    <?php elseif ($report === 'aging'): ?>
        <p class="mb-2"><a href="<?= e(route_url('accounts/receivables', ['filter' => 'overdue'])) ?>">Open receivables (overdue) →</a></p>
    <?php elseif ($report === 'collection'): ?>
        <p class="mb-2"><a href="<?= e(route_url('accounts/payments')) ?>">Open payment history →</a></p>
    <?php endif; ?>

    <section class="sales-card">
        <div class="sales-card__head d-flex justify-content-between align-items-center">
            <h2 class="sales-card__title mb-0"><?= e($meta['title']) ?></h2>
            <?= erp_export_toolbar('accounts-report-table', $report) ?>
        </div>
        <div class="erp-table-panel">
            <table class="table table-sm erp-data-table" id="accounts-report-table">
                <thead><tr><th>Report</th><th>Status</th><th>Link</th></tr></thead>
                <tbody>
                    <tr>
                        <td><?= e($meta['title']) ?></td>
                        <td><span class="crm-track crm-track--generated">Available</span></td>
                        <td>
                            <?php if ($report === 'ledger'): ?>
                                <a href="<?= e(route_url('accounts/ledger')) ?>">View ledger</a>
                            <?php elseif ($report === 'aging'): ?>
                                <a href="<?= e(route_url('accounts/receivables')) ?>">View receivables</a>
                            <?php else: ?>
                                <a href="<?= e(route_url('accounts/dashboard')) ?>">Dashboard</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="small text-muted p-3 mb-0">Detailed export for this report uses the linked module tables (ledger, receivables, payments) with PDF / Excel / Print toolbars.</p>
    </section>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
