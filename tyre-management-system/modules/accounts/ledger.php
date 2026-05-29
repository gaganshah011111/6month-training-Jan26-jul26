<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_ledger.php';
require_once __DIR__ . '/../../includes/sales_service.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin', 'Sales Manager'])) {
    echo '<div class="alert alert-warning m-3">Access denied.</div>';
    return;
}

$pdo = Database::connection();
acc_ledger_handle_export($pdo, (string)($_GET['export_scope'] ?? 'list'));

$filters = [
    'customer_id' => (int)($_GET['customer_id'] ?? 0),
    'status' => trim((string)($_GET['status'] ?? '')),
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'q' => trim((string)($_GET['search'] ?? $_GET['q'] ?? '')),
];
$customers = acc_customer_ledger_list($pdo, $filters);
$customerOptions = sales_list_customers($pdo, ['status' => 'Active']);
?>

<div class="accounts-page acc-ledger-page">
    <header class="prod-page__head d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="prod-page__title">Customer Ledger</h1>
            <p class="prod-page__sub mb-0">Outstanding balances from CRM invoices, customer payments, and receivables.</p>
        </div>
        <?= acc_ledger_export_toolbar('accounts/ledger', 'customer', 'list', 'customer-ledger') ?>
    </header>

    <form method="get" class="sales-card mb-3 p-3 acc-ledger-filters">
        <input type="hidden" name="page" value="accounts/ledger">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Customer</label>
                <select class="form-select form-select-sm" name="customer_id">
                    <option value="">All customers</option>
                    <?php foreach ($customerOptions as $co): ?>
                        <option value="<?= (int)$co['id'] ?>" <?= $filters['customer_id'] === (int)$co['id'] ? 'selected' : '' ?>><?= e((string)$co['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All</option>
                    <option value="Paid" <?= $filters['status'] === 'Paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="Partial" <?= $filters['status'] === 'Partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="Unpaid" <?= $filters['status'] === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from" value="<?= e($filters['from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to" value="<?= e($filters['to']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Search Customer</label>
                <input type="search" class="form-control form-control-sm" name="search" value="<?= e($filters['q']) ?>" placeholder="Name or code">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            </div>
            <div class="col-auto">
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('accounts/ledger')) ?>">Reset</a>
            </div>
        </div>
    </form>

    <section class="sales-card">
        <div class="sales-card__head">
            <h2 class="sales-card__title mb-0">Customer accounts</h2>
        </div>
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm table-hover mb-0" id="acc-customer-ledger-table">
                <thead>
                <tr>
                    <th>Customer</th>
                    <th class="text-end">Total Invoiced</th>
                    <th class="text-end">Total Paid</th>
                    <th class="text-end">Pending Amount</th>
                    <th>Last Payment Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($customers === []): ?>
                    <tr><td colspan="7" class="sales-empty">No customers with invoice or payment activity for these filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($customers as $c):
                    $meta = $c['status_meta'] ?? acc_payment_meta('Unpaid');
                ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e((string)$c['company_name']) ?></div>
                            <div class="small text-muted font-monospace"><?= e((string)($c['customer_code'] ?? '')) ?></div>
                        </td>
                        <td class="text-end"><?= e(sales_format_money((float)$c['total_invoiced'])) ?></td>
                        <td class="text-end text-success"><?= e(sales_format_money((float)$c['total_paid'])) ?></td>
                        <td class="text-end text-danger fw-semibold"><?= e(sales_format_money((float)$c['pending'])) ?></td>
                        <td><?= e((string)($c['last_payment'] ?? '—')) ?></td>
                        <td><span class="badge <?= e($meta['cls']) ?>"><?= e((string)$meta['label']) ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-primary" href="<?= e(route_url('accounts/customer-ledger-detail', ['customer_id' => (int)$c['id']])) ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
