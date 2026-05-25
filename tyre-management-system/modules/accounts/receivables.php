<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';

$pdo = Database::connection();
$filter = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'overdue', 'week', 'month'], true)) {
    $filter = 'all';
}
$rows = sales_receivables_list($pdo, $filter);
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Receivables</h1>
            <p class="prod-page__sub">Pending customer payments — overdue highlighted in red, due soon in yellow.</p>
        </div>
    </header>

    <div class="sales-filter-bar mb-3 d-flex flex-wrap gap-2">
        <?php foreach (['all' => 'All pending', 'overdue' => 'Overdue', 'week' => 'Due this week', 'month' => 'Due this month'] as $k => $label): ?>
            <a class="btn btn-sm <?= $filter === $k ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e(route_url('accounts/receivables', ['filter' => $k])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <section class="sales-card">
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0">
                <thead>
                    <tr><th>Status</th><th>Invoice</th><th>Customer</th><th>SO</th><th>Due</th><th class="text-end">Balance</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $inv): ?>
                    <?php
                    $due = (string)($inv['due_date'] ?? '');
                    $paid = (float)$inv['amount_paid'] >= (float)$inv['total_amount'] - 0.01;
                    $rowClass = 'recv-row';
                    if ($paid) {
                        $rowClass .= ' recv-row--paid';
                        $statusLabel = 'Paid';
                    } elseif ($due !== '' && $due < date('Y-m-d')) {
                        $rowClass .= ' recv-row--overdue';
                        $statusLabel = 'Overdue';
                    } elseif ($due !== '' && $due <= date('Y-m-d', strtotime('+7 days'))) {
                        $rowClass .= ' recv-row--soon';
                        $statusLabel = 'Due soon';
                    } else {
                        $statusLabel = 'Pending';
                    }
                    ?>
                    <tr class="<?= e($rowClass) ?>">
                        <td><span class="recv-pill"><?= e($statusLabel) ?></span></td>
                        <td><?= e($inv['invoice_no']) ?></td>
                        <td><?= e($inv['company_name']) ?></td>
                        <td><?= e((string)($inv['so_number'] ?? '—')) ?></td>
                        <td><?= e($due ?: '—') ?></td>
                        <td class="text-end fw-semibold"><?= e(sales_format_money((float)$inv['balance'])) ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('sales/invoice', ['id' => (int)$inv['id']])) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="sales-empty">No receivables for this filter.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
