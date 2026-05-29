<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_expenses.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin', 'Sales Manager'])) {
    echo '<div class="alert alert-warning m-3">Access denied.</div>';
    return;
}

$pdo = Database::connection();
acc_expense_ensure_schema($pdo);
acc_expense_handle_export($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    try {
        if ($action === 'delete') {
            acc_delete_expense($pdo, (int)($_POST['id'] ?? 0));
            set_flash('success', 'Expense deleted.');
        } elseif ($action === 'update') {
            acc_update_expense($pdo, (int)($_POST['id'] ?? 0), $_POST, $_FILES);
            set_flash('success', 'Expense updated.');
        } else {
            acc_save_expense($pdo, $_POST, $_FILES);
            set_flash('success', 'Expense entry saved.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('accounts/expenses', array_filter([
        'from' => $_GET['from'] ?? $_POST['filter_from'] ?? null,
        'to' => $_GET['to'] ?? $_POST['filter_to'] ?? null,
        'category' => $_GET['category'] ?? $_POST['filter_category'] ?? null,
        'payment_mode' => $_GET['payment_mode'] ?? $_POST['filter_mode'] ?? null,
        'search' => $_GET['search'] ?? $_POST['filter_search'] ?? null,
    ], static fn($v) => $v !== null && $v !== ''));
}

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$category = (string)($_GET['category'] ?? '');
$paymentMode = (string)($_GET['payment_mode'] ?? '');
$q = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));
$filterParams = ['from' => $from, 'to' => $to, 'category' => $category, 'payment_mode' => $paymentMode, 'q' => $q];

$rows = acc_list_expenses($pdo, [
    'from' => $from,
    'to' => $to,
    'category' => $category,
    'payment_mode' => $paymentMode,
    'q' => $q,
]);
$filteredTotal = array_sum(array_map(static fn($r) => (float)$r['amount'], $rows));

$kpis = acc_expense_dashboard_kpis($pdo);
$apiBase = route_url('api/accounts-expense');

$manualCategories = acc_expense_manual_categories();
?>

<div class="accounts-page acc-exp-page">
    <header class="acc-exp-head">
        <div class="acc-exp-head__text">
            <h1 class="prod-page__title mb-1">Expenses</h1>
            <p class="prod-page__sub mb-0">Track operating costs and monthly spending. Use <strong>Add Expense</strong> to record electricity, diesel, maintenance, office, internet, and other manual entries.</p>
        </div>
        <div class="acc-exp-head__actions">
            <button type="button" class="btn btn-danger acc-exp-btn-add" data-bs-toggle="modal" data-bs-target="#expAddModal" aria-controls="expAddModal">
                <i class="bi bi-plus-lg"></i> Add Expense
            </button>
        </div>
    </header>

    <div class="acc-exp-kpis">
        <article class="acc-exp-kpi">
            <span class="acc-exp-kpi__icon acc-exp-kpi__icon--red"><i class="bi bi-graph-down-arrow"></i></span>
            <div>
                <span class="acc-exp-kpi__label">Total Expenses</span>
                <strong class="acc-exp-kpi__value"><?= e(sales_format_money($kpis['month_total'])) ?></strong>
                <span class="acc-exp-kpi__hint">This month</span>
            </div>
        </article>
        <article class="acc-exp-kpi">
            <span class="acc-exp-kpi__icon acc-exp-kpi__icon--orange"><i class="bi bi-calendar-day"></i></span>
            <div>
                <span class="acc-exp-kpi__label">Today</span>
                <strong class="acc-exp-kpi__value"><?= e(sales_format_money($kpis['today_total'])) ?></strong>
            </div>
        </article>
        <article class="acc-exp-kpi">
            <span class="acc-exp-kpi__icon acc-exp-kpi__icon--purple"><i class="bi bi-people"></i></span>
            <div>
                <span class="acc-exp-kpi__label">Salary</span>
                <strong class="acc-exp-kpi__value"><?= e(sales_format_money($kpis['salary_month'])) ?></strong>
                <span class="acc-exp-kpi__hint">This month</span>
            </div>
        </article>
        <article class="acc-exp-kpi">
            <span class="acc-exp-kpi__icon acc-exp-kpi__icon--blue"><i class="bi bi-building"></i></span>
            <div>
                <span class="acc-exp-kpi__label">Operational</span>
                <strong class="acc-exp-kpi__value"><?= e(sales_format_money($kpis['operational_month'])) ?></strong>
                <span class="acc-exp-kpi__hint">This month</span>
            </div>
        </article>
    </div>

    <form method="get" class="acc-exp-filters">
        <input type="hidden" name="page" value="accounts/expenses">
        <div class="acc-exp-filters__row acc-exp-filters__row--fields">
            <div class="acc-exp-filters__field">
                <label class="form-label" for="expFilterFrom">From date</label>
                <input type="date" class="form-control form-control-sm" id="expFilterFrom" name="from" value="<?= e($from) ?>">
            </div>
            <div class="acc-exp-filters__field">
                <label class="form-label" for="expFilterTo">To date</label>
                <input type="date" class="form-control form-control-sm" id="expFilterTo" name="to" value="<?= e($to) ?>">
            </div>
            <div class="acc-exp-filters__field acc-exp-filters__field--select">
                <label class="form-label" for="expFilterCategory">Category</label>
                <select class="form-select form-select-sm" id="expFilterCategory" name="category" data-erp-searchable="off">
                    <option value="">All categories</option>
                    <?php foreach (ACC_EXPENSE_CATEGORIES as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="acc-exp-filters__field acc-exp-filters__field--select">
                <label class="form-label" for="expFilterMode">Payment mode</label>
                <select class="form-select form-select-sm" id="expFilterMode" name="payment_mode" data-erp-searchable="off">
                    <option value="">All modes</option>
                    <?php foreach (ACC_PAYMENT_MODES as $m): ?>
                        <option value="<?= e($m) ?>" <?= $paymentMode === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="acc-exp-filters__field acc-exp-filters__field--search">
                <label class="form-label" for="expFilterSearch">Search</label>
                <input type="search" class="form-control form-control-sm" id="expFilterSearch" name="search" value="<?= e($q) ?>" placeholder="Remarks, ref no…">
            </div>
        </div>
        <div class="acc-exp-filters__row acc-exp-filters__row--actions">
            <div class="acc-exp-filters__actions">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('accounts/expenses')) ?>">Reset</a>
            </div>
            <div class="acc-exp-filters__export">
                <?= acc_expense_export_toolbar($filterParams) ?>
            </div>
        </div>
    </form>

    <section class="acc-exp-panel acc-exp-panel--table">
        <div class="acc-exp-panel__head">
            <div>
                <h2 class="acc-exp-panel__title">Expense History</h2>
                <p class="acc-exp-panel__sub mb-0"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?> · <?= e($from) ?> to <?= e($to) ?></p>
            </div>
            <?php if ($rows !== []): ?>
                <div class="acc-exp-panel__total">
                    <span>Filtered total</span>
                    <strong><?= e(sales_format_money($filteredTotal)) ?></strong>
                </div>
            <?php endif; ?>
        </div>
        <div class="acc-exp-table-wrap table-responsive">
            <table class="table table-hover mb-0 acc-exp-table" id="acc-expense-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th class="text-end">Amount</th>
                    <th>Mode</th>
                    <th>Reference</th>
                    <th>Remarks</th>
                    <th>Attachment</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="8" class="acc-exp-empty">
                            <i class="bi bi-wallet2"></i>
                            <p>No expenses match your filters.</p>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#expAddModal">
                                <i class="bi bi-plus-lg"></i> Add Expense
                            </button>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $r):
                    $isAuto = !empty($r['source_type']);
                    $attachments = acc_expense_attachment_items($r);
                ?>
                    <tr>
                        <td class="text-nowrap"><?= e((string)$r['expense_date']) ?></td>
                        <td>
                            <span class="acc-exp-cat"><?= e((string)$r['category']) ?></span>
                            <?php if ($isAuto): ?><span class="acc-exp-badge-auto">Auto</span><?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold text-nowrap"><?= e(sales_format_money((float)$r['amount'])) ?></td>
                        <td><?= e((string)$r['payment_mode']) ?></td>
                        <td class="acc-exp-ref"><?= e((string)($r['reference_no'] ?? '—')) ?></td>
                        <td class="acc-exp-remarks" title="<?= e((string)($r['remarks'] ?? '')) ?>"><?= e((string)($r['remarks'] ?? '—')) ?></td>
                        <td>
                            <?php if ($attachments === []): ?>
                                <span class="text-muted">—</span>
                            <?php else: ?>
                                <div class="acc-exp-att-links">
                                    <?php foreach ($attachments as $att): ?>
                                        <a href="<?= e(acc_expense_file_url((int)$r['id'], $att['which'])) ?>" target="_blank" rel="noopener" title="<?= e($att['name']) ?>">
                                            <i class="bi bi-paperclip"></i> <?= e($att['label']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="acc-exp-actions">
                                <button type="button" class="btn btn-light btn-sm js-exp-view" data-id="<?= (int)$r['id'] ?>" title="View"><i class="bi bi-eye"></i></button>
                                <?php if (!$isAuto): ?>
                                    <button type="button" class="btn btn-light btn-sm js-exp-edit" data-id="<?= (int)$r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <a class="btn btn-light btn-sm" href="<?= e(route_url('accounts/expenses', ['export' => 'pdf', 'id' => (int)$r['id']])) ?>" target="_blank" rel="noopener" title="PDF"><i class="bi bi-file-pdf"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this expense?');">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-light btn-sm text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($rows !== []): ?>
                <tfoot>
                    <tr>
                        <td colspan="2" class="fw-semibold">Total (filtered)</td>
                        <td class="text-end fw-bold"><?= e(sales_format_money($filteredTotal)) ?></td>
                        <td colspan="5"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </section>
</div>

<div class="modal fade acc-exp-add-modal-wrap" id="expAddModal" tabindex="-1" aria-labelledby="expAddModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content acc-exp-add-modal">
            <form method="post" enctype="multipart/form-data" class="acc-exp-add-form" id="accExpAddForm" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="filter_from" value="<?= e($from) ?>">
                <input type="hidden" name="filter_to" value="<?= e($to) ?>">
                <div class="modal-header acc-exp-add-modal__head">
                    <div>
                        <h5 class="modal-title" id="expAddModalLabel"><i class="bi bi-wallet2 me-2"></i>Add Expense</h5>
                        <p class="acc-exp-add-modal__sub mb-0">Manual entry — electricity, diesel, transport, maintenance, office, internet, salary adjustment, emergency, etc.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="expCategorySelect">Category <span class="text-danger">*</span></label>
                        <select class="form-select" name="category" id="expCategorySelect" required data-erp-searchable="off">
                            <option value="">Select category…</option>
                            <?php foreach ($manualCategories as $cat): ?>
                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Mode</label>
                            <select class="form-select" name="payment_mode" data-erp-searchable="off">
                                <?php foreach (ACC_PAYMENT_MODES as $m): ?><option><?= e($m) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="expense_date" value="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference Number</label>
                            <input type="text" class="form-control" name="reference_no" placeholder="Cheque / UTR / voucher no.">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Bill details, vendor name, notes…"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Upload Attachment</label>
                            <div class="acc-exp-upload-row mb-0">
                                <input type="file" class="form-control" name="attachment_bill" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <p class="acc-exp-hint mb-0 mt-2">Bill, receipt, or invoice — PDF, JPG, or PNG</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer acc-exp-add-modal__foot">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-check-lg me-1"></i>Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="expViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Expense Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="expViewBody"><p class="text-muted">Loading…</p></div>
        </div>
    </div>
</div>

<div class="modal fade" id="expEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data" id="expEditForm">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="expEditId">
                <div class="modal-body row g-2">
                    <div class="col-12"><label class="form-label small">Category</label><input class="form-control form-control-sm" name="category" id="expEditCategory" required></div>
                    <div class="col-6"><label class="form-label small">Amount</label><input type="number" step="0.01" class="form-control form-control-sm" name="amount" id="expEditAmount" required></div>
                    <div class="col-6"><label class="form-label small">Mode</label><select class="form-select form-select-sm" name="payment_mode" id="expEditMode"><?php foreach (ACC_PAYMENT_MODES as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label small">Date</label><input type="date" class="form-control form-control-sm" name="expense_date" id="expEditDate" required></div>
                    <div class="col-12"><label class="form-label small">Reference</label><input class="form-control form-control-sm" name="reference_no" id="expEditRef"></div>
                    <div class="col-12"><label class="form-label small">Remarks</label><textarea class="form-control form-control-sm" name="remarks" id="expEditRemarks" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.ACC_EXP_API = <?= json_encode($apiBase, JSON_THROW_ON_ERROR) ?>;</script>
<script src="assets/js/accounts-expenses.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/accounts-expenses.js')) ?>"></script>
