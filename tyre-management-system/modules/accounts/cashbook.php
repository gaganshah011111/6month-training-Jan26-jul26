<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';

$allowed = acc_treasury_allowed_tx_types();
if ($allowed === []) {
    echo '<div class="alert alert-warning m-3">You do not have access to Cash &amp; Bank.</div>';
    return;
}

$pdo = Database::connection();
acc_treasury_ensure_schema($pdo);
acc_treasury_handle_export($pdo);

$canManage = acc_treasury_can_manage();
$canAdjust = acc_treasury_can_adjust();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'opening') {
            acc_treasury_save_opening($pdo, $_POST);
            set_flash('success', 'Opening balances saved.');
        } elseif ($action === 'add_loan') {
            acc_treasury_add_loan($pdo, $_POST, $_FILES);
            set_flash('success', 'Loan recorded and funds increased.');
        } elseif ($action === 'repay_loan') {
            acc_treasury_repay_loan($pdo, $_POST);
            set_flash('success', 'Loan repayment recorded.');
        } elseif ($action === 'adjust' && $canAdjust) {
            acc_treasury_adjust_funds($pdo, $_POST);
            set_flash('success', 'Fund adjustment posted.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('accounts/cashbook', array_filter([
        'from' => $_GET['from'] ?? null,
        'to' => $_GET['to'] ?? null,
        'tx_type' => $_GET['tx_type'] ?? null,
        'search' => $_GET['search'] ?? null,
    ], static fn($v) => $v !== null && $v !== ''));
}

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$txType = (string)($_GET['tx_type'] ?? '');
$q = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));
$loanSource = (string)($_GET['loan_source'] ?? '');
$partyFilter = trim((string)($_GET['party'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');
$tab = (string)($_GET['tab'] ?? 'treasury');
$limitedHr = has_role('HR Manager') && !acc_treasury_can_manage();
$limitedSales = has_role('Sales Manager') && !acc_treasury_can_manage();

$kpis = acc_treasury_kpis($pdo, false);
$opening = $kpis['opening'];
$loans = acc_treasury_can_manage() ? acc_treasury_loans_list($pdo) : [];
$rows = acc_treasury_list($pdo, [
    'from' => $from,
    'to' => $to,
    'tx_type' => $txType,
    'q' => $q,
    'loan_source' => $loanSource,
    'party' => $partyFilter,
    'status' => $statusFilter,
    'allowed_types' => $allowed,
]);
$exportQs = array_filter([
    'from' => $from,
    'to' => $to,
    'tx_type' => $txType,
    'search' => $q,
    'loan_source' => $loanSource,
    'party' => $partyFilter,
    'status' => $statusFilter,
], static fn($v) => $v !== '');
$salaryMonth = $limitedHr ? acc_treasury_month_type_total($pdo, 'Salary Payment', 'debit') : 0.0;
$customerMonth = $limitedSales ? acc_treasury_month_type_total($pdo, 'Customer Payment', 'credit') : 0.0;
$nextDue = $kpis['next_due_loan'];
?>

<div class="accounts-page acc-treasury-page">
    <header class="prod-page__head d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="prod-page__title mb-1">Cash &amp; Bank</h1>
            <p class="prod-page__sub mb-0">Company treasury — available funds, cash, bank, loans, and full transaction history.</p>
        </div>
        <?php if ($canManage): ?>
        <div class="d-flex flex-wrap gap-2">
            <?php if (!$opening): ?>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#treasuryOpeningModal"><i class="bi bi-flag"></i> Setup Opening</button>
            <?php endif; ?>
            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#treasuryLoanModal"><i class="bi bi-plus-lg"></i> Add Loan</button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#treasuryRepayModal"><i class="bi bi-arrow-return-left"></i> Repay Loan</button>
            <?php if ($canAdjust): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#treasuryAdjustModal"><i class="bi bi-sliders"></i> Adjust Funds</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>

    <div class="acc-treasury-kpis">
        <?php if ($limitedSales): ?>
        <article class="acc-treasury-kpi acc-treasury-kpi--primary">
            <span class="acc-treasury-kpi__label">Total Receivable</span>
            <strong><?= e(sales_format_money((float)$kpis['total_receivable'])) ?></strong>
        </article>
        <article class="acc-treasury-kpi">
            <span class="acc-treasury-kpi__label">Customer Collections (month)</span>
            <strong class="text-success"><?= e(sales_format_money($customerMonth)) ?></strong>
        </article>
        <article class="acc-treasury-kpi acc-treasury-kpi--muted">
            <span class="acc-treasury-kpi__label">Transactions in view</span>
            <strong><?= count($rows) ?></strong>
        </article>
        <?php elseif ($limitedHr): ?>
        <article class="acc-treasury-kpi acc-treasury-kpi--primary">
            <span class="acc-treasury-kpi__label">Salary Payments (month)</span>
            <strong class="text-danger"><?= e(sales_format_money($salaryMonth)) ?></strong>
        </article>
        <article class="acc-treasury-kpi acc-treasury-kpi--muted">
            <span class="acc-treasury-kpi__label">Records in view</span>
            <strong><?= count($rows) ?></strong>
        </article>
        <?php else: ?>
        <article class="acc-treasury-kpi acc-treasury-kpi--primary">
            <span class="acc-treasury-kpi__label">Current Available Funds</span>
            <?php $k = acc_treasury_money_display((float)$kpis['available_funds']); ?>
            <strong title="<?= e($k['full']) ?>"><?= e($k['short']) ?></strong>
        </article>
        <article class="acc-treasury-kpi">
            <span class="acc-treasury-kpi__label">Cash in Hand</span>
            <?php $k = acc_treasury_money_display((float)$kpis['cash_in_hand']); ?>
            <strong title="<?= e($k['full']) ?>"><?= e($k['short']) ?></strong>
        </article>
        <article class="acc-treasury-kpi">
            <span class="acc-treasury-kpi__label">Bank Balance</span>
            <?php $k = acc_treasury_money_display((float)$kpis['bank_balance']); ?>
            <strong title="<?= e($k['full']) ?>"><?= e($k['short']) ?></strong>
        </article>
        <article class="acc-treasury-kpi">
            <span class="acc-treasury-kpi__label">Total Inflow (month)</span>
            <?php $k = acc_treasury_money_display((float)$kpis['total_inflow']); ?>
            <strong class="text-success" title="<?= e($k['full']) ?>"><?= e($k['short']) ?></strong>
        </article>
        <article class="acc-treasury-kpi">
            <span class="acc-treasury-kpi__label">Total Outflow (month)</span>
            <?php $k = acc_treasury_money_display((float)$kpis['total_outflow']); ?>
            <strong class="text-danger" title="<?= e($k['full']) ?>"><?= e($k['short']) ?></strong>
        </article>
        <article class="acc-treasury-kpi">
            <span class="acc-treasury-kpi__label">Active Loans</span>
            <strong><?= (int)$kpis['active_loans'] ?></strong>
        </article>
        <article class="acc-treasury-kpi">
            <span class="acc-treasury-kpi__label">Outstanding Loan</span>
            <?php $k = acc_treasury_money_display((float)$kpis['outstanding_loan']); ?>
            <strong class="text-warning" title="<?= e($k['full']) ?>"><?= e($k['short']) ?></strong>
        </article>
        <article class="acc-treasury-kpi acc-treasury-kpi--muted">
            <span class="acc-treasury-kpi__label">Receivable</span>
            <strong><?= e(sales_format_money((float)$kpis['total_receivable'])) ?></strong>
        </article>
        <article class="acc-treasury-kpi acc-treasury-kpi--muted">
            <span class="acc-treasury-kpi__label">Payable</span>
            <strong><?= e(sales_format_money((float)$kpis['total_payable'])) ?></strong>
        </article>
        <?php endif; ?>
    </div>

    <?php if (!$opening && $canManage): ?>
        <div class="alert alert-info py-2 small mb-3"><i class="bi bi-info-circle me-1"></i> Set <strong>opening cash and bank balances</strong> once to establish company base funds.</div>
    <?php elseif ($opening): ?>
        <div class="acc-treasury-opening-bar small mb-3">
            Opening (<?= e((string)$opening['effective_date']) ?>):
            Cash <?= e(sales_format_money((float)$opening['opening_cash'])) ?> ·
            Bank <?= e(sales_format_money((float)$opening['opening_bank'])) ?> ·
            By <?= e((string)($opening['entered_by'] ?? '—')) ?>
            <?php if ($canManage): ?><button type="button" class="btn btn-link btn-sm p-0 ms-2" data-bs-toggle="modal" data-bs-target="#treasuryOpeningModal">Edit</button><?php endif; ?>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs acc-treasury-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $tab === 'treasury' ? 'active' : '' ?>" href="<?= e(route_url('accounts/cashbook', ['tab' => 'treasury'] + $exportQs)) ?>">Transaction History</a></li>
        <?php if ($canManage): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === 'loans' ? 'active' : '' ?>" href="<?= e(route_url('accounts/cashbook', ['tab' => 'loans'] + $exportQs)) ?>">Loan Register</a></li>
        <?php endif; ?>
    </ul>

    <?php if ($tab === 'loans' && $canManage): ?>
        <div class="acc-treasury-loan-kpis mb-3">
            <article><span>Total loans taken</span><strong><?= e(sales_format_money((float)$kpis['loans_taken'])) ?></strong></article>
            <article><span>Total repaid</span><strong><?= e(sales_format_money((float)$kpis['loans_repaid'])) ?></strong></article>
            <article><span>Outstanding</span><strong class="text-warning"><?= e(sales_format_money((float)$kpis['outstanding_loan'])) ?></strong></article>
            <article><span>Interest liability (est.)</span><strong><?= e(sales_format_money((float)$kpis['interest_liability'])) ?></strong></article>
            <article><span>Next due</span><strong><?= $nextDue ? e((string)$nextDue['loan_code']) . ' · ' . e((string)$nextDue['due_date']) . ' · ' . e(sales_format_money((float)$nextDue['outstanding'])) : '—' ?></strong></article>
        </div>
        <section class="sales-card">
            <div class="sales-card__head"><span class="sales-card__title">Loan Register</span></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Loan ID</th><th>Source</th><th>Lender</th><th class="text-end">Principal</th><th class="text-end">Repaid</th><th class="text-end">Outstanding</th><th>Rate</th><th>Loan Date</th><th>Due</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($loans === []): ?><tr><td colspan="10" class="text-center text-muted py-4">No loans recorded.</td></tr><?php endif; ?>
                    <?php foreach ($loans as $ln): ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string)$ln['loan_code']) ?></td>
                            <td><?= e((string)$ln['loan_source']) ?></td>
                            <td><?= e((string)($ln['lender_name'] ?? '—')) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$ln['principal_amount'])) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$ln['repaid_amount'])) ?></td>
                            <td class="text-end fw-semibold"><?= e(sales_format_money((float)$ln['outstanding'])) ?></td>
                            <td><?= e((string)$ln['interest_rate']) ?>%</td>
                            <td><?= e((string)$ln['loan_date']) ?></td>
                            <td><?= e((string)($ln['due_date'] ?? '—')) ?></td>
                            <td><span class="badge bg-<?= (string)$ln['status_label'] === 'Closed' ? 'success' : 'primary' ?>"><?= e((string)$ln['status_label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php else: ?>

    <form method="get" class="acc-exp-filters mb-3">
        <input type="hidden" name="page" value="accounts/cashbook">
        <input type="hidden" name="tab" value="treasury">
        <div class="acc-exp-filters__row acc-exp-filters__row--fields">
            <div class="acc-exp-filters__field"><label class="form-label">From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
            <div class="acc-exp-filters__field"><label class="form-label">To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
            <div class="acc-exp-filters__field acc-exp-filters__field--select">
                <label class="form-label">Type</label>
                <select class="form-select form-select-sm" name="tx_type" data-erp-searchable="off">
                    <option value="">All types</option>
                    <?php foreach (ACC_TREASURY_TX_TYPES as $tt): ?>
                        <?php if ($allowed === null || in_array($tt, $allowed, true)): ?>
                            <option value="<?= e($tt) ?>" <?= $txType === $tt ? 'selected' : '' ?>><?= e($tt) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($canManage): ?>
            <div class="acc-exp-filters__field acc-exp-filters__field--select">
                <label class="form-label">Loan source</label>
                <select class="form-select form-select-sm" name="loan_source" data-erp-searchable="off">
                    <option value="">All</option>
                    <?php foreach (ACC_LOAN_SOURCES as $ls): ?><option value="<?= e($ls) ?>" <?= $loanSource === $ls ? 'selected' : '' ?>><?= e($ls) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="acc-exp-filters__field">
                <label class="form-label">Party</label>
                <input type="text" class="form-control form-control-sm" name="party" value="<?= e($partyFilter) ?>" placeholder="Customer, supplier…">
            </div>
            <div class="acc-exp-filters__field acc-exp-filters__field--select">
                <label class="form-label">Status</label>
                <select class="form-select form-select-sm" name="status" data-erp-searchable="off">
                    <option value="">All</option>
                    <?php foreach (['Completed', 'Pending', 'Partial'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="acc-exp-filters__field acc-exp-filters__field--search">
                <label class="form-label">Search</label>
                <input type="search" class="form-control form-control-sm" name="search" value="<?= e($q) ?>" placeholder="Party, reference, ID…">
            </div>
        </div>
        <div class="acc-exp-filters__row acc-exp-filters__row--actions">
            <div class="acc-exp-filters__actions">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('accounts/cashbook')) ?>">Reset</a>
                <?php if ($canManage): ?>
                    <a class="btn btn-outline-primary btn-sm" href="<?= e(route_url('accounts/cashbook', ['sync' => '1'])) ?>" title="Pull entries from receivables, payables, expenses, salary">Sync ledger</a>
                <?php endif; ?>
            </div>
            <div class="acc-exp-filters__export erp-export-toolbar erp-export-toolbar--server">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/cashbook', $exportQs + ['export' => 'pdf'])) ?>" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/cashbook', $exportQs + ['export' => 'csv'])) ?>"><i class="bi bi-file-spreadsheet"></i> Excel</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/cashbook', $exportQs + ['export' => 'print'])) ?>" target="_blank"><i class="bi bi-printer"></i> Print</a>
            </div>
        </div>
    </form>

    <section class="sales-card acc-treasury-table-card">
        <div class="sales-card__head d-flex justify-content-between"><span class="sales-card__title">Cash &amp; Bank Ledger</span><span class="small text-muted"><?= count($rows) ?> transactions</span></div>
        <div class="acc-treasury-scroll-panel">
            <div class="acc-treasury-scroll-hint small text-muted mb-1">
                <i class="bi bi-arrows-expand"></i> Use the slider below or drag sideways to see all columns
            </div>
            <div class="acc-treasury-table-wrap" id="accTreasuryTableScroll" tabindex="0">
            <table class="table table-sm table-hover mb-0 acc-treasury-ledger-table" id="acc-treasury-table">
                <thead>
                <tr>
                    <th>Txn ID</th><th>Date</th><th>Type</th><th>Party</th><th>Reference</th>
                    <th class="text-end">Amount</th><th>Dr/Cr</th>
                    <th class="text-end">Bal. Before</th><th class="text-end">Bal. After</th>
                    <th>Mode</th><th>Status</th><th>By</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">No transactions for selected filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r):
                    $isCredit = (string)$r['direction'] === 'credit';
                    $meta = acc_payment_meta((string)($r['tx_status'] ?? 'Completed'));
                    $partyLabel = acc_treasury_display_party((string)($r['party'] ?? ''), (string)($r['tx_type'] ?? ''));
                    $amtM = acc_treasury_money_display((float)$r['amount']);
                    $bbM = acc_treasury_money_display((float)$r['balance_before']);
                    $baM = acc_treasury_money_display((float)$r['balance_after']);
                ?>
                    <tr>
                        <td class="acc-treasury-td-id"><?= e((string)$r['tx_code']) ?></td>
                        <td class="text-nowrap acc-treasury-td-date"><?= e((string)$r['tx_date']) ?></td>
                        <td class="acc-treasury-td-type"><span class="acc-treasury-type"><?= e((string)$r['tx_type']) ?></span></td>
                        <td class="acc-treasury-td-party" title="<?= e($partyLabel) ?>"><?= e($partyLabel) ?></td>
                        <td class="acc-treasury-td-ref" title="<?= e((string)($r['reference_no'] ?? '')) ?>"><?= e((string)($r['reference_no'] ?? '—')) ?></td>
                        <td class="text-end acc-treasury-td-amt fw-semibold <?= $isCredit ? 'text-success' : 'text-danger' ?>" title="<?= e($amtM['full']) ?>">
                            <?= $isCredit ? '+' : '−' ?><?= e($amtM['short']) ?>
                        </td>
                        <td class="acc-treasury-td-dc"><span class="badge <?= $isCredit ? 'bg-success' : 'bg-danger' ?>"><?= $isCredit ? 'Cr' : 'Dr' ?></span></td>
                        <td class="text-end acc-treasury-td-bal text-muted" title="<?= e($bbM['full']) ?>"><?= e($bbM['short']) ?></td>
                        <td class="text-end acc-treasury-td-bal fw-bold" title="<?= e($baM['full']) ?>"><?= e($baM['short']) ?></td>
                        <td class="small"><?= e((string)($r['payment_mode'] ?? '—')) ?></td>
                        <td><span class="badge <?= e($meta['cls']) ?>"><?= e($meta['label']) ?></span></td>
                        <td class="small text-muted text-truncate acc-treasury-td-by" title="<?= e((string)($r['created_by'] ?? '')) ?>">
                            <?= e((string)($r['created_by'] ?? '—')) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="acc-treasury-h-slider-wrap">
                <button type="button" class="btn btn-sm btn-light acc-treasury-scroll-btn" id="accTreasuryScrollLeft" title="Scroll left" aria-label="Scroll left"><i class="bi bi-chevron-left"></i></button>
                <input type="range" class="acc-treasury-h-slider form-range" id="accTreasuryHSlider" min="0" max="100" value="0" aria-label="Horizontal table scroll">
                <button type="button" class="btn btn-sm btn-light acc-treasury-scroll-btn" id="accTreasuryScrollRight" title="Scroll right" aria-label="Scroll right"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
<!-- Opening balance -->
<div class="modal fade acc-treasury-modal" id="treasuryOpeningModal" tabindex="-1" aria-labelledby="treasuryOpeningModalLabel" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post" class="acc-treasury-post-form"><?= csrf_input() ?><input type="hidden" name="action" value="opening">
            <div class="modal-header"><h5 class="modal-title">Opening Balance Setup</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-6"><label class="form-label">Opening Cash</label><input type="number" step="0.01" min="0" class="form-control" name="opening_cash" value="<?= e((string)($opening['opening_cash'] ?? '0')) ?>" required></div>
                <div class="col-6"><label class="form-label">Opening Bank</label><input type="number" step="0.01" min="0" class="form-control" name="opening_bank" value="<?= e((string)($opening['opening_bank'] ?? '0')) ?>" required></div>
                <div class="col-12"><label class="form-label">Effective Date</label><input type="date" class="form-control" name="effective_date" value="<?= e((string)($opening['effective_date'] ?? date('Y-m-d'))) ?>" required></div>
                <p class="small text-muted mb-0">One-time company base funds. Locked after save (Super Admin can change).</p>
            </div>
            <div class="modal-footer acc-treasury-modal__foot"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary acc-treasury-submit-btn">Save Opening</button></div>
        </form>
    </div></div>
</div>

<!-- Add loan (quick form — same layout as Repay Loan) -->
<div class="modal fade acc-treasury-modal acc-treasury-quick-modal" id="treasuryLoanModal" tabindex="-1" aria-labelledby="treasuryLoanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered acc-treasury-modal-dialog"><div class="modal-content">
        <form method="post" class="acc-treasury-post-form acc-treasury-quick-form" id="treasuryLoanForm"><?= csrf_input() ?>
            <input type="hidden" name="action" value="add_loan">
            <input type="hidden" name="interest_rate" value="0">
            <div class="modal-header"><h5 class="modal-title" id="treasuryLoanModalLabel">Add Loan</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body acc-treasury-quick-form__body">
                <div class="mb-2">
                    <label class="form-label">Loan Source</label>
                    <select class="form-select form-select-sm" name="loan_source" required data-erp-searchable="off">
                        <?php foreach (ACC_LOAN_SOURCES as $ls): ?><option><?= e($ls) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Lender / Party Name</label>
                    <input type="text" class="form-control form-control-sm" name="lender_name" placeholder="Bank, director, investor…" required autocomplete="off">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label">Loan Amount</label>
                        <input type="text" class="form-control form-control-sm acc-treasury-amount-input" name="loan_amount" required inputmode="decimal" placeholder="0.00" autocomplete="off" data-max-amount="<?= e((string)ACC_TREASURY_MAX_AMOUNT) ?>">
                        <div class="form-text">Large amounts allowed (e.g. crores / arab)</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Payment Mode</label>
                        <select class="form-select form-select-sm" name="payment_mode" data-erp-searchable="off">
                            <option>Cash</option>
                            <option>Bank</option>
                            <option>UPI</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Loan Date</label>
                    <input type="date" class="form-control form-control-sm" name="loan_date" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Due Date <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="date" class="form-control form-control-sm" name="due_date">
                </div>
                <div class="mb-2">
                    <label class="form-label">Reference Number</label>
                    <input type="text" class="form-control form-control-sm" name="reference_no" placeholder="Bank / agreement ref." autocomplete="off">
                </div>
                <div class="mb-0">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control form-control-sm" name="remarks" rows="2" placeholder="Optional notes"></textarea>
                </div>
            </div>
            <div class="modal-footer acc-treasury-modal__foot">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm acc-treasury-submit-btn">Record Loan</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Repay loan -->
<div class="modal fade acc-treasury-modal acc-treasury-quick-modal" id="treasuryRepayModal" tabindex="-1" aria-labelledby="treasuryRepayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered acc-treasury-modal-dialog"><div class="modal-content">
        <form method="post" class="acc-treasury-post-form acc-treasury-quick-form" id="treasuryRepayForm"><?= csrf_input() ?><input type="hidden" name="action" value="repay_loan">
            <div class="modal-header"><h5 class="modal-title" id="treasuryRepayModalLabel">Repay Loan</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body acc-treasury-quick-form__body">
                <div class="mb-2">
                    <label class="form-label">Loan</label>
                    <select class="form-select form-select-sm" name="loan_id" required data-erp-searchable="off">
                        <option value="">Select loan…</option>
                        <?php foreach ($loans as $ln): if ((float)$ln['outstanding'] <= 0.01) continue; ?>
                            <option value="<?= (int)$ln['id'] ?>"><?= e((string)$ln['loan_code']) ?> — <?= e(sales_format_money((float)$ln['outstanding'])) ?> due</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label">Repayment Amount</label>
                        <input type="text" class="form-control form-control-sm acc-treasury-amount-input" name="amount" required inputmode="decimal" placeholder="0.00" autocomplete="off" data-max-amount="<?= e((string)ACC_TREASURY_MAX_AMOUNT) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Payment Mode</label>
                        <select class="form-select form-select-sm" name="payment_mode" data-erp-searchable="off"><?php foreach (['Cash', 'Bank', 'UPI', 'Cheque', 'NEFT'] as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control form-control-sm" name="payment_date" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Reference Number</label>
                    <input type="text" class="form-control form-control-sm" name="reference_no" autocomplete="off">
                </div>
                <div class="mb-0">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control form-control-sm" name="remarks" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer acc-treasury-modal__foot">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm acc-treasury-submit-btn">Post Repayment</button>
            </div>
        </form>
    </div></div>
</div>

<?php if ($canAdjust): ?>
<div class="modal fade acc-treasury-modal" id="treasuryAdjustModal" tabindex="-1" aria-labelledby="treasuryAdjustModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable acc-treasury-modal-dialog"><div class="modal-content">
        <form method="post" class="acc-treasury-post-form" id="treasuryAdjustForm"><?= csrf_input() ?><input type="hidden" name="action" value="adjust">
            <div class="modal-header"><h5 class="modal-title" id="treasuryAdjustModalLabel">Adjust Funds</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body row g-2">
                <div class="col-6"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" max="<?= e((string)ACC_TREASURY_MAX_AMOUNT) ?>" class="form-control" name="amount" required></div>
                <div class="col-6"><label class="form-label">Add / Deduct</label><select class="form-select" name="adjust_type" data-erp-searchable="off"><option value="add">Add to funds</option><option value="deduct">Deduct from funds</option></select></div>
                <div class="col-6"><label class="form-label">Date</label><input type="date" class="form-control" name="adjust_date" value="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="col-6"><label class="form-label">Payment Mode</label><select class="form-select" name="payment_mode" data-erp-searchable="off"><option>Cash</option><option>Bank</option></select></div>
                <div class="col-12"><label class="form-label">Reference</label><input class="form-control" name="reference_no"></div>
                <div class="col-12"><label class="form-label">Reason (required)</label><textarea class="form-control" name="reason" rows="2" required></textarea></div>
            </div>
            <div class="modal-footer acc-treasury-modal__foot"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning acc-treasury-submit-btn">Post Adjustment</button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>
<?php endif; ?>
