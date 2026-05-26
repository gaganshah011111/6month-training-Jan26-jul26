<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
acc_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        acc_save_expense($pdo, $_POST);
        set_flash('success', 'Expense entry saved.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('accounts/expenses');
}

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$category = (string)($_GET['category'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$rows = acc_list_expenses($pdo, ['from' => $from, 'to' => $to, 'category' => $category, 'q' => $q]);
$totals = acc_expense_totals($pdo, $from, $to);
?>
<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Expenses</h1>
            <p class="prod-page__sub">Track operating expenses and auto-include them in finance reports.</p>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title mb-0">Add expense</h2></div>
                <div class="sales-card__body">
                    <form method="post" class="row g-2">
                        <?= csrf_input() ?>
                        <div class="col-12"><label class="form-label small">Category</label>
                            <select class="form-select form-select-sm" name="category" required>
                                <?php foreach (ACC_EXPENSE_CATEGORIES as $cat): ?><option value="<?= e($cat) ?>"><?= e($cat) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label small">Amount</label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" required></div>
                        <div class="col-12"><label class="form-label small">Payment mode</label>
                            <select class="form-select form-select-sm" name="payment_mode"><?php foreach (ACC_PAYMENT_MODES as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="col-12"><label class="form-label small">Date</label><input type="date" class="form-control form-control-sm" name="expense_date" value="<?= e(date('Y-m-d')) ?>" required></div>
                        <div class="col-12"><label class="form-label small">Remarks</label><textarea class="form-control form-control-sm" name="remarks" rows="2"></textarea></div>
                        <div class="col-12"><label class="form-label small">Attachment (optional name)</label><input class="form-control form-control-sm" name="attachment" placeholder="Bill-2026-05.pdf"></div>
                        <div class="col-12"><button class="btn btn-primary btn-sm w-100">Save expense</button></div>
                    </form>
                </div>
            </section>
            <section class="sales-card mt-3">
                <div class="sales-card__body small">
                    <p class="mb-1">Period total: <strong><?= e(sales_format_money((float)$totals['total'])) ?></strong></p>
                    <p class="mb-0">This month: <strong><?= e(sales_format_money((float)$totals['month_total'])) ?></strong></p>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <form method="get" class="sales-filter-bar mb-3">
                <input type="hidden" name="page" value="accounts/expenses">
                <div class="sales-filter-bar__row">
                    <div class="sales-filter-bar__field"><label>From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
                    <div class="sales-filter-bar__field"><label>To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
                    <div class="sales-filter-bar__field"><label>Category</label>
                        <select class="form-select form-select-sm" name="category">
                            <option value="">All</option>
                            <?php foreach (ACC_EXPENSE_CATEGORIES as $cat): ?><option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sales-filter-bar__field"><label>Search</label><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="remarks / attachment"></div>
                    <div class="align-self-end"><button class="btn btn-sm btn-primary">Apply</button></div>
                    <div class="ms-auto"><?= erp_export_toolbar('acc-expense-table', 'expenses') ?></div>
                </div>
            </form>

            <section class="sales-card">
                <div class="sales-table-wrap sales-table-scroll">
                    <table class="table table-sm mb-0" id="acc-expense-table">
                        <thead><tr><th>Date</th><th>Category</th><th class="text-end">Amount</th><th>Mode</th><th>Remarks</th><th>Attachment</th><th>By</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e((string)$r['expense_date']) ?></td>
                                <td><?= e((string)$r['category']) ?></td>
                                <td class="text-end"><?= e(sales_format_money((float)$r['amount'])) ?></td>
                                <td><?= e((string)$r['payment_mode']) ?></td>
                                <td><?= e((string)($r['remarks'] ?? '—')) ?></td>
                                <td><?= e((string)($r['attachment'] ?? '—')) ?></td>
                                <td><?= e((string)($r['created_by'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?><tr><td colspan="7" class="sales-empty">No expense entries for selected filters.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
