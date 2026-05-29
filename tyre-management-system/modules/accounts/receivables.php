<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
$filter = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'overdue', 'week', 'month'], true)) {
    $filter = 'all';
}
$customerId = (int)($_GET['customer_id'] ?? 0);
$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($statusFilter, ['all', 'pending', 'partial', 'paid', 'overdue'], true)) {
    $statusFilter = 'all';
}
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = '';
}
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = '';
}

$rows = sales_list_invoices($pdo, [
    'customer_id' => $customerId > 0 ? $customerId : null,
    'payment_status' => $statusFilter !== 'all' ? ucfirst($statusFilter) : '',
    'from' => $from,
    'to' => $to,
]);
$today = date('Y-m-d');
$rows = array_values(array_filter($rows, static function (array $inv) use ($filter, $statusFilter, $today): bool {
    $due = (string)($inv['due_date'] ?? '');
    $bal = (float)$inv['total_amount'] - (float)$inv['amount_paid'];
    $needsOutstanding = $statusFilter !== 'paid';
    if ($filter === 'overdue') {
        if ($due === '' || $due >= $today) {
            return false;
        }
        return $needsOutstanding ? $bal > 0.01 : true;
    }
    if ($filter === 'week') {
        if ($due === '' || $due < $today || $due > date('Y-m-d', strtotime('+7 days'))) {
            return false;
        }
        return $needsOutstanding ? $bal > 0.01 : true;
    }
    if ($filter === 'month') {
        if ($due === '' || $due < $today || $due > date('Y-m-d', strtotime('+30 days'))) {
            return false;
        }
        return $needsOutstanding ? $bal > 0.01 : true;
    }

    return true;
}));

$totalPending = 0.0;
$totalOverdue = 0.0;
foreach ($rows as $r) {
    $bal = (float)$r['total_amount'] - (float)$r['amount_paid'];
    $totalPending += $bal;
    $due = (string)($r['due_date'] ?? '');
    if ($due !== '' && $due < date('Y-m-d')) {
        $totalOverdue += $bal;
    }
}
$monthCollection = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM sales_payments WHERE payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetchColumn();
$customers = sales_list_customers($pdo, ['status' => 'Active']);

$latestPayByInvoice = [];
$invoiceIds = array_values(array_unique(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $rows)));
$invoiceIds = array_values(array_filter($invoiceIds, static fn(int $v): bool => $v > 0));
if ($invoiceIds !== []) {
    $ph = implode(',', array_fill(0, count($invoiceIds), '?'));
    $stPay = $pdo->prepare("SELECT invoice_id, MAX(id) AS payment_id FROM sales_payments WHERE invoice_id IN ($ph) GROUP BY invoice_id");
    $stPay->execute($invoiceIds);
    foreach ($stPay->fetchAll(PDO::FETCH_ASSOC) ?: [] as $rp) {
        $latestPayByInvoice[(int)$rp['invoice_id']] = (int)$rp['payment_id'];
    }
}
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Receivables</h1>
            <p class="prod-page__sub">Track unpaid customer invoices with due dates, collection status, and quick payment actions.</p>
        </div>
    </header>

    <div class="sales-kpis accounts-kpis mb-3">
        <article class="sales-kpi"><span class="sales-kpi__label">Total pending</span><strong class="text-warning"><?= e(sales_format_money($totalPending)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Overdue amount</span><strong class="text-danger"><?= e(sales_format_money($totalOverdue)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">This month collection</span><strong class="text-success"><?= e(sales_format_money($monthCollection)) ?></strong></article>
    </div>

    <form method="get" class="sales-filter-bar mb-3 d-flex flex-wrap gap-2 align-items-end">
        <input type="hidden" name="page" value="accounts/receivables">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <?php foreach (['all' => 'All invoices', 'overdue' => 'Overdue', 'week' => 'Due this week', 'month' => 'Due this month'] as $k => $label): ?>
            <a class="btn btn-sm <?= $filter === $k ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e(route_url('accounts/receivables', ['filter' => $k, 'customer_id' => $customerId > 0 ? $customerId : null, 'status' => $statusFilter, 'from' => $from !== '' ? $from : null, 'to' => $to !== '' ? $to : null])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <select class="form-select form-select-sm" name="customer_id" style="max-width:220px">
            <option value="0">All customers</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>><?= e((string)$c['company_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select form-select-sm" name="status" style="max-width:170px">
            <?php foreach (['all' => 'All status', 'pending' => 'Pending', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>" style="max-width:160px">
        <input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>" style="max-width:160px">
        <button class="btn btn-sm btn-primary">Apply</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/receivables')) ?>">Reset</a>
        <span class="ms-auto"><?= erp_export_toolbar('acc-recv-table', 'receivables') ?></span>
    </form>

    <div class="row g-3">
        <div class="col-12">
            <section class="sales-card mb-3">
                <div class="sales-table-wrap sales-table-scroll">
                    <table class="table table-sm mb-0" id="acc-recv-table">
                        <thead>
                            <tr><th>Invoice no</th><th>Customer</th><th class="text-end">Invoice amount</th><th class="text-end">Paid</th><th class="text-end">Remaining</th><th>Due date</th><th>Status</th><th>Actions</th></tr>
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
                            $latestPayId = (int)($latestPayByInvoice[(int)$inv['id']] ?? 0);
                            ?>
                            <tr class="<?= e($rowClass) ?>" data-invoice-id="<?= (int)$inv['id'] ?>">
                                <td><?= e($inv['invoice_no']) ?></td>
                                <td><?= e($inv['company_name']) ?></td>
                                <td class="text-end js-total"><?= e(sales_format_money((float)$inv['total_amount'])) ?></td>
                                <td class="text-end js-paid"><?= e(sales_format_money((float)$inv['amount_paid'])) ?></td>
                                <td class="text-end fw-semibold js-remaining"><?= e(sales_format_money((float)$inv['total_amount'] - (float)$inv['amount_paid'])) ?></td>
                                <td class="js-due"><?= e($due ?: '—') ?></td>
                                <td><span class="recv-pill js-status"><?= e($statusLabel) ?></span></td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('accounts/invoice-view', ['id' => (int)$inv['id']])) ?>">View invoice</a>
                                    <button type="button" class="btn btn-sm btn-outline-success js-open-payment" data-invoice-id="<?= (int)$inv['id'] ?>"><?= $paid ? 'View / edit payments' : 'Record payment' ?></button>
                                    <a class="btn btn-sm btn-outline-secondary js-receipt-link <?= $latestPayId > 0 ? '' : 'disabled' ?>" href="<?= $latestPayId > 0 ? e(route_url('accounts/payment-receipt', ['id' => $latestPayId])) : '#' ?>" target="_blank" rel="noopener">View receipt PDF</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="8" class="sales-empty">No receivables for this filter.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>

    </div>
</div>

<div class="modal fade" id="accPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Record Customer Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="accPayMsg" class="small mb-2"></div>
        <p class="small text-muted mb-2">Invoice summary is read-only. Edit payment date, amount, mode, reference, and remarks below — or pick a previous payment to update.</p>
        <form id="accPayForm" class="row g-2">
            <?= csrf_input() ?>
            <input type="hidden" name="invoice_id" id="accInvoiceId">
            <input type="hidden" name="customer_id" id="accCustomerId">
            <input type="hidden" name="payment_id" id="accPaymentId" value="">
            <div class="col-md-6"><label class="form-label small text-muted">Invoice number</label><div class="form-control form-control-sm bg-light border-0" id="accInvoiceNo"></div></div>
            <div class="col-md-6"><label class="form-label small text-muted">Customer name</label><div class="form-control form-control-sm bg-light border-0" id="accCustomerName"></div></div>
            <div class="col-md-4"><label class="form-label small text-muted">Invoice total</label><div class="form-control form-control-sm bg-light border-0" id="accTotal"></div></div>
            <div class="col-md-4"><label class="form-label small text-muted">Already paid</label><div class="form-control form-control-sm bg-light border-0" id="accPaid"></div></div>
            <div class="col-md-4"><label class="form-label small text-muted">Remaining balance</label><div class="form-control form-control-sm bg-light border-0 fw-semibold text-danger" id="accRemaining"></div></div>
            <div class="col-md-4"><label class="form-label small text-muted">Due date</label><div class="form-control form-control-sm bg-light border-0" id="accDueDate"></div></div>
            <div class="col-12"><hr class="my-1"></div>
            <div class="col-md-4"><label class="form-label small">Payment date</label><input type="date" class="form-control form-control-sm" name="payment_date" id="accPaymentDate" value="<?= e(date('Y-m-d')) ?>" required></div>
            <div class="col-md-4"><label class="form-label small">Payment amount</label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" id="accAmount" required autocomplete="off"></div>
            <div class="col-md-4"><label class="form-label small">Payment mode</label>
                <select class="form-select form-select-sm" name="payment_mode" id="accPaymentMode">
                    <?php foreach (SALES_PAYMENT_MODES as $m): ?><option><?= e($m) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label small">Transaction reference</label><input type="text" class="form-control form-control-sm" name="reference_no" id="accPaymentRef" autocomplete="off"></div>
            <div class="col-md-8"><label class="form-label small">Remarks</label><input type="text" class="form-control form-control-sm" name="remarks" id="accPaymentRemarks" autocomplete="off"></div>
        </form>
        <hr>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0">Previous payments <span id="accEditHint" class="text-muted small ms-1"></span></h6>
          <button type="button" class="btn btn-link btn-sm p-0 js-new-recv-payment">+ Record new payment</button>
        </div>
        <div class="sales-table-wrap sales-table-scroll" style="max-height:180px">
            <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th></th></tr></thead>
                <tbody id="accInvoicePaymentRows"><tr><td colspan="5" class="text-muted">No payments yet.</td></tr></tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer">
        <a id="accReceiptBtn" class="btn btn-outline-secondary btn-sm disabled" href="#" target="_blank" rel="noopener">Download Receipt</a>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="accSavePayBtn">Save payment</button>
      </div>
    </div>
  </div>
</div>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div id="accSaveToast" class="toast text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">Payment recorded successfully.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
<script>
(function () {
    const TOLERANCE = 0.01;
    const DEFAULT_DATE = <?= json_encode(date('Y-m-d'), JSON_THROW_ON_ERROR) ?>;

    function initAccReceivablesPaymentUi() {
        const modalEl = document.getElementById('accPaymentModal');
        if (!modalEl) return;
        const modal = (window.bootstrap && window.bootstrap.Modal) ? new bootstrap.Modal(modalEl) : null;
        const msg = document.getElementById('accPayMsg');
        const form = document.getElementById('accPayForm');
        const btnSave = document.getElementById('accSavePayBtn');
        const receiptBtn = document.getElementById('accReceiptBtn');
        const editHint = document.getElementById('accEditHint');
        const toastEl = document.getElementById('accSaveToast');
        const toast = (toastEl && window.bootstrap && window.bootstrap.Toast)
            ? new bootstrap.Toast(toastEl, { delay: 1800 })
            : null;
        if (!form || !msg || !btnSave) return;

        const f = {
            invoiceId: document.getElementById('accInvoiceId'),
            customerId: document.getElementById('accCustomerId'),
            paymentId: document.getElementById('accPaymentId'),
            invoiceNo: document.getElementById('accInvoiceNo'),
            customer: document.getElementById('accCustomerName'),
            total: document.getElementById('accTotal'),
            paid: document.getElementById('accPaid'),
            remain: document.getElementById('accRemaining'),
            due: document.getElementById('accDueDate'),
            amount: document.getElementById('accAmount'),
            mode: document.getElementById('accPaymentMode'),
            paymentDate: document.getElementById('accPaymentDate'),
            reference: document.getElementById('accPaymentRef'),
            remarks: document.getElementById('accPaymentRemarks'),
            rows: document.getElementById('accInvoicePaymentRows')
        };

        let saving = false;
        let loading = false;
        let currentRemaining = 0;
        let editingAmount = 0;
        let paymentMap = {};
        let invoiceSnapshot = null;

        const fmt = (n) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(n || 0));
        const setMsg = (text, ok) => {
            msg.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
            msg.textContent = text || '';
        };
        const cleanupModal = () => {
            document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        };
        const rowStatusLabel = (status) => {
            const s = String(status || '').toUpperCase();
            if (s === 'PAID') return 'Paid';
            if (s.includes('PARTIAL')) return 'Partial';
            if (s === 'OVERDUE') return 'Overdue';
            if (s.includes('DUE')) return 'Due soon';
            return 'Pending';
        };
        function openModal() {
            if (modal) {
                modal.show();
                return;
            }
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
            modalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }
        function closeModal() {
            if (modal) {
                modal.hide();
                return;
            }
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
            cleanupModal();
        }
        function resetEntryFields() {
            f.paymentId.value = '';
            if (f.paymentDate) f.paymentDate.value = DEFAULT_DATE;
            if (f.mode) f.mode.selectedIndex = 0;
            if (f.reference) f.reference.value = '';
            if (f.remarks) f.remarks.value = '';
            f.amount.value = '';
            f.amount.readOnly = false;
            f.amount.removeAttribute('max');
            f.amount.setAttribute('min', '0.01');
            f.amount.required = true;
            editingAmount = 0;
            btnSave.textContent = 'Save payment';
            if (editHint) editHint.textContent = '';
            if (receiptBtn) {
                receiptBtn.classList.add('disabled');
                receiptBtn.href = '#';
            }
        }
        function resetForm() {
            f.invoiceId.value = '';
            f.customerId.value = '';
            f.invoiceNo.textContent = '';
            f.customer.textContent = '';
            f.total.textContent = '';
            f.paid.textContent = '';
            f.remain.textContent = '';
            f.due.textContent = '';
            resetEntryFields();
            paymentMap = {};
            invoiceSnapshot = null;
            currentRemaining = 0;
            btnSave.disabled = false;
            if (f.rows) f.rows.innerHTML = '<tr><td colspan="5" class="text-muted">No payments yet.</td></tr>';
            setMsg('', true);
        }
        function maxPayableAmount() {
            return Number(currentRemaining || 0) + Number(editingAmount || 0);
        }
        function setAmountField(remaining, editAmt) {
            currentRemaining = Number(remaining || 0);
            editingAmount = Number(editAmt || 0);
            const cap = maxPayableAmount();
            if (cap > TOLERANCE) {
                f.amount.readOnly = false;
                f.amount.setAttribute('min', '0.01');
                f.amount.required = true;
                f.amount.max = String(cap);
                if (!f.paymentId.value) {
                    f.amount.value = currentRemaining > TOLERANCE ? currentRemaining.toFixed(2) : '';
                }
                btnSave.disabled = false;
                return;
            }
            f.amount.removeAttribute('min');
            f.amount.removeAttribute('max');
            f.amount.required = false;
            f.amount.value = '';
            f.amount.readOnly = true;
            btnSave.disabled = !f.paymentId.value;
        }
        function renderSummary(inv) {
            invoiceSnapshot = inv;
            f.invoiceId.value = String(inv.id);
            f.customerId.value = String(inv.customer_id);
            f.invoiceNo.textContent = inv.invoice_no || '—';
            f.customer.textContent = inv.customer || '—';
            f.total.textContent = fmt(inv.total);
            f.paid.textContent = fmt(inv.paid);
            f.remain.textContent = fmt(inv.remaining);
            f.due.textContent = inv.due_date || '—';
        }
        function renderPaymentRows(payments) {
            paymentMap = {};
            if (!f.rows) return;
            f.rows.innerHTML = '';
            if (!payments || !payments.length) {
                f.rows.innerHTML = '<tr><td colspan="5" class="text-muted">No payments yet.</td></tr>';
                return;
            }
            payments.forEach((p) => {
                paymentMap[String(p.id)] = p;
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${p.date}</td><td class="text-end">${fmt(p.amount)}</td><td>${p.mode || '—'}</td><td>${p.reference || '—'}</td><td><button type="button" class="btn btn-sm btn-outline-primary js-edit-pay" data-payment-id="${p.id}">Edit</button></td>`;
                f.rows.appendChild(tr);
            });
        }
        function beginEditPayment(paymentId) {
            const p = paymentMap[String(paymentId)];
            if (!p) return;
            f.paymentId.value = String(p.id);
            f.paymentDate.value = p.date || DEFAULT_DATE;
            f.amount.value = Number(p.amount || 0).toFixed(2);
            if (f.mode) f.mode.value = p.mode || 'Cash';
            if (f.reference) f.reference.value = p.reference || '';
            if (f.remarks) f.remarks.value = p.remarks || '';
            editingAmount = Number(p.amount || 0);
            setAmountField(currentRemaining, editingAmount);
            btnSave.textContent = 'Update payment';
            if (editHint) editHint.textContent = '(Editing payment #' + p.id + ')';
            if (receiptBtn) {
                receiptBtn.classList.remove('disabled');
                receiptBtn.href = p.receipt_url || '#';
            }
            setMsg('Update the fields below and click Update payment.', true);
        }
        function updateRow(inv) {
            const row = document.querySelector(`tr[data-invoice-id="${inv.id}"]`);
            if (!row) return;
            const paidEl = row.querySelector('.js-paid');
            const pendingEl = row.querySelector('.js-remaining');
            const statusEl = row.querySelector('.js-status');
            const payBtn = row.querySelector('.js-open-payment');
            const rowReceipt = row.querySelector('.js-receipt-link');
            if (paidEl) paidEl.textContent = fmt(inv.paid);
            if (pendingEl) pendingEl.textContent = fmt(inv.remaining);
            if (statusEl) statusEl.textContent = rowStatusLabel(inv.status);
            if (payBtn) {
                const fullyPaid = Number(inv.remaining || 0) <= TOLERANCE;
                payBtn.textContent = fullyPaid ? 'View / edit payments' : 'Record payment';
            }
            if (rowReceipt && inv.receipt_payment_id) {
                rowReceipt.classList.remove('disabled');
                rowReceipt.href = dataReceiptUrl(inv.receipt_payment_id);
            }
        }
        function dataReceiptUrl(paymentId) {
            return 'index.php?page=accounts/payment-receipt&id=' + encodeURIComponent(String(paymentId));
        }
        function updateKpis(dash) {
            if (!dash) return;
            const cards = document.querySelectorAll('.accounts-kpis .sales-kpi strong');
            if (cards.length >= 3) {
                cards[0].textContent = fmt(dash.pending || 0);
                cards[1].textContent = fmt(dash.overdue || 0);
                cards[2].textContent = fmt(dash.collected || 0);
            }
        }

        f.amount.addEventListener('focus', () => { f.amount.select(); });

        modalEl.addEventListener('hidden.bs.modal', () => {
            cleanupModal();
            resetForm();
        });
        modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });

        async function loadInvoice(invoiceId) {
            if (!invoiceId || saving || loading) return;
            loading = true;
            openModal();
            setMsg('Loading invoice details...', true);
            try {
                const res = await fetch(`index.php?page=api/accounts-receivable-payment&action=invoice&invoice_id=${encodeURIComponent(invoiceId)}`, { credentials: 'same-origin' });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch {
                    throw new Error('Server returned an invalid response. Please refresh and try again.');
                }
                if (!data.ok) throw new Error(data.error || 'Unable to load invoice');
                resetEntryFields();
                const inv = data.invoice;
                renderSummary(inv);
                renderPaymentRows(data.payments || []);
                setAmountField(inv.remaining, 0);
                const payments = data.payments || [];
                if (currentRemaining <= TOLERANCE && !payments.length) {
                    setMsg('This invoice is already fully paid.', false);
                } else if (currentRemaining <= TOLERANCE) {
                    setMsg('Fully paid. Select a previous payment below to edit.', true);
                } else {
                    setMsg('', true);
                }
            } finally {
                loading = false;
            }
        }

        async function savePayment() {
            if (saving) return;
            const isEdit = Boolean(f.paymentId.value);
            const cap = maxPayableAmount();
            if (!isEdit && cap <= TOLERANCE) {
                setMsg('Nothing left to collect on this invoice.', false);
                return;
            }
            const amount = Number(f.amount.value || 0);
            if (!(amount > 0)) {
                setMsg('Payment amount must be greater than zero.', false);
                return;
            }
            if (amount > cap + TOLERANCE) {
                setMsg('Amount cannot exceed remaining balance (' + fmt(cap) + ').', false);
                return;
            }
            saving = true;
            btnSave.disabled = true;
            setMsg(isEdit ? 'Updating payment...' : 'Saving payment...', true);
            try {
                const fd = new FormData(form);
                const res = await fetch('index.php?page=api/accounts-receivable-payment&action=save_payment', { method: 'POST', body: fd, credentials: 'same-origin' });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch {
                    throw new Error('Server returned an invalid response. Please refresh and try again.');
                }
                if (!data.ok) throw new Error(data.error || 'Unable to save payment');

                const invUpdate = Object.assign({}, data.invoice, { receipt_payment_id: data.payment_id });
                updateRow(invUpdate);
                updateKpis(data.dashboard);
                if (toast) {
                    const body = toastEl.querySelector('.toast-body');
                    if (body) body.textContent = data.message || 'Payment saved successfully.';
                    toast.show();
                }
                closeModal();
                resetForm();
            } catch (e) {
                setMsg(e.message || 'Save failed', false);
                btnSave.disabled = false;
            } finally {
                saving = false;
            }
        }

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-open-payment');
            if (!btn || btn.disabled || saving || loading) return;
            e.preventDefault();
            loadInvoice(btn.getAttribute('data-invoice-id')).catch((err) => {
                openModal();
                setMsg(err && err.message ? err.message : 'Unable to open payment form.', false);
            });
        });
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-edit-pay');
            if (!btn) return;
            e.preventDefault();
            beginEditPayment(btn.getAttribute('data-payment-id'));
        });
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-new-recv-payment');
            if (!btn || !invoiceSnapshot) return;
            e.preventDefault();
            resetEntryFields();
            setAmountField(invoiceSnapshot.remaining, 0);
            setMsg('', true);
        });
        btnSave.addEventListener('click', savePayment);
    }

    if (document.readyState === 'complete') {
        initAccReceivablesPaymentUi();
    } else {
        window.addEventListener('load', initAccReceivablesPaymentUi, { once: true });
    }
})();
</script>
