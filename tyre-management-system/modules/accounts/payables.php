<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
inv_purchase_ensure_schema($pdo);

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$paymentStatus = (string)($_GET['payment_status'] ?? '');
$filters = ['from' => $from, 'to' => $to, 'supplier_id' => $supplierId, 'payment_status' => $paymentStatus];
$rows = inv_purchase_list($pdo, $filters);
$suppliers = inv_list_suppliers($pdo);
$kpis = acc_payables_page_kpis($pdo, $filters);
$totalPending = (float)$kpis['pending'];
$totalOverdue = (float)$kpis['overdue'];
$monthPaid = (float)$kpis['month_paid'];
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Payables</h1>
            <p class="prod-page__sub">Auto-linked with Purchase Inward. Track pending supplier payments and post settlements.</p>
        </div>
    </header>

    <div class="sales-kpis accounts-kpis mb-3">
        <article class="sales-kpi"><span class="sales-kpi__label">Total pending</span><strong class="text-danger"><?= e(sales_format_money($totalPending)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Overdue amount</span><strong class="text-danger"><?= e(sales_format_money($totalOverdue)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">This month paid</span><strong class="text-success"><?= e(sales_format_money($monthPaid)) ?></strong></article>
    </div>

    <form method="get" class="sales-filter-bar mb-3">
        <input type="hidden" name="page" value="accounts/payables">
        <div class="sales-filter-bar__row d-flex flex-wrap gap-2 align-items-end">
            <div class="sales-filter-bar__field" style="min-width:150px"><label>From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
            <div class="sales-filter-bar__field" style="min-width:150px"><label>To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
            <div class="sales-filter-bar__field" style="min-width:210px"><label>Supplier</label>
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="0">All</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= e((string)$s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sales-filter-bar__field" style="min-width:150px"><label>Status</label>
                <select name="payment_status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['Paid', 'Partial', 'Unpaid'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= $paymentStatus === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><button class="btn btn-sm btn-primary">Apply</button></div>
            <div><a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/payables')) ?>">Reset</a></div>
            <div class="ms-auto"><?= erp_export_toolbar('acc-payable-table', 'payables') ?></div>
        </div>
    </form>

    <section class="sales-card">
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0" id="acc-payable-table">
                <thead><tr><th>PINV no</th><th>Supplier</th><th class="text-end">Purchase amount</th><th class="text-end">Paid amount</th><th class="text-end">Pending amount</th><th>Due date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $pm = inv_purchase_payment_meta((string)($r['payment_status'] ?? 'Unpaid')); ?>
                    <tr data-inward-id="<?= (int)$r['id'] ?>">
                        <td><?= e((string)$r['pinv_no']) ?></td>
                        <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                        <td class="text-end js-total"><?= e(sales_format_money((float)$r['total_amount'])) ?></td>
                        <td class="text-end js-paid"><?= e(sales_format_money((float)$r['paid_amount'])) ?></td>
                        <td class="text-end fw-semibold js-pending"><?= e(sales_format_money((float)$r['pending_amount'])) ?></td>
                        <td><?= e((string)($r['due_date'] ?? '—')) ?></td>
                        <td><span class="badge inv-pay--<?= e($pm['badge']) ?> js-status"><?= e($pm['label']) ?></span></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('accounts/payable-invoice', ['id' => (int)$r['id']])) ?>">View Invoice</a>
                            <button type="button" class="btn btn-sm btn-outline-success js-open-payable-payment" data-inward-id="<?= (int)$r['id'] ?>"<?= (float)$r['pending_amount'] <= inv_purchase_tolerance() ? ' disabled' : '' ?>>Record Payment</button>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(inv_purchase_print_url((int)$r['id'], true)) ?>" target="_blank" rel="noopener">PDF</a>
                            <a class="btn btn-sm btn-outline-dark" href="<?= e(route_url('accounts/supplier-ledger', ['supplier_id' => (int)($r['supplier_id'] ?? 0)])) ?>">Ledger</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?><tr><td colspan="8" class="sales-empty">No payables for selected filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<div class="modal fade" id="accPayableModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Record Supplier Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="accPayableMsg" class="small mb-2"></div>
        <p class="small text-muted mb-2">Invoice summary is read-only. Edit payment date, amount, mode, reference, and remarks below — or pick a previous payment to update.</p>
        <form id="accPayableForm" class="row g-2">
          <?= csrf_input() ?>
          <input type="hidden" name="inward_id" id="accPayableInwardId">
          <input type="hidden" name="payment_id" id="accPayablePaymentId" value="">
          <div class="col-md-6"><label class="form-label small text-muted">Supplier</label><div class="form-control form-control-sm bg-light border-0" id="accPayableSupplier"></div></div>
          <div class="col-md-6"><label class="form-label small text-muted">PINV</label><div class="form-control form-control-sm bg-light border-0" id="accPayablePinv"></div></div>
          <div class="col-md-3"><label class="form-label small text-muted">Total</label><div class="form-control form-control-sm bg-light border-0" id="accPayableTotal"></div></div>
          <div class="col-md-3"><label class="form-label small text-muted">Already paid</label><div class="form-control form-control-sm bg-light border-0" id="accPayablePaid"></div></div>
          <div class="col-md-3"><label class="form-label small text-muted">Remaining</label><div class="form-control form-control-sm bg-light border-0 fw-semibold text-danger" id="accPayableRemaining"></div></div>
          <div class="col-md-3"><label class="form-label small text-muted">Due date</label><div class="form-control form-control-sm bg-light border-0" id="accPayableDue"></div></div>
          <div class="col-12"><hr class="my-1"></div>
          <div class="col-md-3"><label class="form-label small">Payment date</label><input type="date" class="form-control form-control-sm" name="payment_date" id="accPayablePaymentDate" value="<?= e(date('Y-m-d')) ?>" required></div>
          <div class="col-md-3"><label class="form-label small">Amount</label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" id="accPayableAmount" required autocomplete="off"></div>
          <div class="col-md-3"><label class="form-label small">Mode</label><select class="form-select form-select-sm" name="payment_mode" id="accPayablePaymentMode"><option>Cash</option><option>UPI</option><option>Bank</option><option>Credit</option></select></div>
          <div class="col-md-3"><label class="form-label small">Bank/Cash</label><input type="text" class="form-control form-control-sm" name="bank_cash" id="accPayableBankCash" placeholder="Bank / cash details" autocomplete="off"></div>
          <div class="col-md-6"><label class="form-label small">Reference no</label><input type="text" class="form-control form-control-sm" name="payment_ref" id="accPayablePaymentRef" autocomplete="off"></div>
          <div class="col-md-6"><label class="form-label small">Remarks</label><input type="text" class="form-control form-control-sm" name="notes" id="accPayableNotes" autocomplete="off"></div>
        </form>
        <hr>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0">Previous payments <span id="accPayableEditHint" class="text-muted small ms-1"></span></h6>
          <button type="button" class="btn btn-link btn-sm p-0 js-new-payable-payment">+ Record new payment</button>
        </div>
        <div class="sales-table-wrap sales-table-scroll" style="max-height:180px">
          <table class="table table-sm mb-0">
            <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th></th></tr></thead>
            <tbody id="accPayablePaymentRows"><tr><td colspan="5" class="text-muted">No payments yet.</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="accPayableSaveBtn">Save payment</button>
      </div>
    </div>
  </div>
</div>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div id="accPayableToast" class="toast text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">Supplier payment recorded successfully.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
<script>
(function () {
  const TOLERANCE = 0.02;
  const DEFAULT_DATE = <?= json_encode(date('Y-m-d'), JSON_THROW_ON_ERROR) ?>;

  function initAccPayablesPaymentUi() {
    const modalEl = document.getElementById('accPayableModal');
    if (!modalEl) return;
    const modal = (window.bootstrap && window.bootstrap.Modal) ? new bootstrap.Modal(modalEl) : null;
    const form = document.getElementById('accPayableForm');
    const msg = document.getElementById('accPayableMsg');
    const btnSave = document.getElementById('accPayableSaveBtn');
    const editHint = document.getElementById('accPayableEditHint');
    const toastEl = document.getElementById('accPayableToast');
    const toast = (toastEl && window.bootstrap && window.bootstrap.Toast)
      ? new bootstrap.Toast(toastEl, { delay: 1800 })
      : null;
    if (!form || !msg || !btnSave) return;

    const f = {
      inward: document.getElementById('accPayableInwardId'),
      paymentId: document.getElementById('accPayablePaymentId'),
      supplier: document.getElementById('accPayableSupplier'),
      pinv: document.getElementById('accPayablePinv'),
      total: document.getElementById('accPayableTotal'),
      paid: document.getElementById('accPayablePaid'),
      rem: document.getElementById('accPayableRemaining'),
      due: document.getElementById('accPayableDue'),
      amt: document.getElementById('accPayableAmount'),
      paymentDate: document.getElementById('accPayablePaymentDate'),
      paymentMode: document.getElementById('accPayablePaymentMode'),
      bankCash: document.getElementById('accPayableBankCash'),
      paymentRef: document.getElementById('accPayablePaymentRef'),
      notes: document.getElementById('accPayableNotes'),
      rows: document.getElementById('accPayablePaymentRows')
    };

    let saving = false;
    let loading = false;
    let currentRemaining = 0;
    let editingAmount = 0;
    let paymentMap = {};
    let invoiceSnapshot = null;

    const fmt = (n) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(n || 0));
    const setMsg = (t, ok) => {
      msg.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
      msg.textContent = t || '';
    };
    const pageFilters = () => {
      const q = new URLSearchParams(window.location.search);
      return {
        filter_from: q.get('from') || '',
        filter_to: q.get('to') || '',
        filter_supplier_id: q.get('supplier_id') || '0',
        filter_payment_status: q.get('payment_status') || ''
      };
    };
    const splitNotes = (raw) => {
      const text = String(raw || '');
      const idx = text.indexOf(' | Bank/Cash: ');
      if (idx === -1) return { notes: text, bank: '' };
      return { notes: text.slice(0, idx), bank: text.slice(idx + 14) };
    };
    const cleanupModal = () => {
      document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('overflow');
      document.body.style.removeProperty('padding-right');
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
      if (f.paymentMode) f.paymentMode.selectedIndex = 0;
      if (f.bankCash) f.bankCash.value = '';
      if (f.paymentRef) f.paymentRef.value = '';
      if (f.notes) f.notes.value = '';
      f.amt.value = '';
      f.amt.readOnly = false;
      f.amt.removeAttribute('max');
      f.amt.setAttribute('min', '0.01');
      f.amt.required = true;
      editingAmount = 0;
      btnSave.textContent = 'Save payment';
      if (editHint) editHint.textContent = '';
    }
    function resetForm() {
      f.inward.value = '';
      f.supplier.textContent = '';
      f.pinv.textContent = '';
      f.total.textContent = '';
      f.paid.textContent = '';
      f.rem.textContent = '';
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
        f.amt.readOnly = false;
        f.amt.setAttribute('min', '0.01');
        f.amt.required = true;
        f.amt.max = String(cap);
        if (!f.paymentId.value) {
          f.amt.value = currentRemaining > TOLERANCE ? currentRemaining.toFixed(2) : '';
        }
        btnSave.disabled = false;
        return;
      }
      f.amt.removeAttribute('min');
      f.amt.removeAttribute('max');
      f.amt.required = false;
      f.amt.value = '';
      f.amt.readOnly = true;
      btnSave.disabled = !f.paymentId.value;
    }
    function renderSummary(i) {
      invoiceSnapshot = i;
      f.inward.value = String(i.id);
      f.supplier.textContent = i.supplier || '—';
      f.pinv.textContent = i.pinv_no || '—';
      f.total.textContent = fmt(i.total);
      f.paid.textContent = fmt(i.paid);
      f.rem.textContent = fmt(i.remaining);
      f.due.textContent = i.due_date || '—';
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
        tr.innerHTML = `<td>${p.date}</td><td class="text-end">${fmt(p.amount)}</td><td>${p.mode || '—'}</td><td>${p.reference || '—'}</td><td><button type="button" class="btn btn-sm btn-outline-primary js-edit-payable-payment" data-payment-id="${p.id}">Edit</button></td>`;
        f.rows.appendChild(tr);
      });
    }
    function beginEditPayment(paymentId) {
      const p = paymentMap[String(paymentId)];
      if (!p) return;
      const parts = splitNotes(p.notes || '');
      f.paymentId.value = String(p.id);
      f.paymentDate.value = p.date || DEFAULT_DATE;
      f.amt.value = Number(p.amount || 0).toFixed(2);
      if (f.paymentMode) f.paymentMode.value = p.mode || 'Cash';
      if (f.paymentRef) f.paymentRef.value = p.reference || '';
      if (f.notes) f.notes.value = parts.notes;
      if (f.bankCash) f.bankCash.value = parts.bank;
      editingAmount = Number(p.amount || 0);
      setAmountField(currentRemaining, editingAmount);
      btnSave.textContent = 'Update payment';
      if (editHint) editHint.textContent = '(Editing payment #' + p.id + ')';
      setMsg('Update the fields below and click Update payment.', true);
    }
    function updateRow(inv) {
      const row = document.querySelector(`tr[data-inward-id="${inv.id}"]`);
      if (!row) return;
      const paidEl = row.querySelector('.js-paid');
      const pendingEl = row.querySelector('.js-pending');
      const statusEl = row.querySelector('.js-status');
      const payBtn = row.querySelector('.js-open-payable-payment');
      if (paidEl) paidEl.textContent = fmt(inv.paid);
      if (pendingEl) pendingEl.textContent = fmt(inv.remaining);
      if (statusEl) {
        statusEl.textContent = inv.status_label || inv.status || 'Unpaid';
        statusEl.className = 'badge inv-pay--' + (inv.status_badge || 'unpaid') + ' js-status';
      }
      if (payBtn) {
        const fullyPaid = Number(inv.remaining || 0) <= TOLERANCE;
        payBtn.disabled = fullyPaid;
        payBtn.classList.toggle('disabled', fullyPaid);
      }
    }
    function updateKpis(dash) {
      if (!dash) return;
      const cards = document.querySelectorAll('.accounts-kpis .sales-kpi strong');
      if (cards.length >= 3) {
        cards[0].textContent = fmt(dash.pending || 0);
        cards[1].textContent = fmt(dash.overdue || 0);
        cards[2].textContent = fmt(dash.month_paid || 0);
      }
    }

    f.amt.addEventListener('focus', () => { f.amt.select(); });

    modalEl.addEventListener('hidden.bs.modal', () => {
      cleanupModal();
      resetForm();
    });
    modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach((el) => {
      el.addEventListener('click', closeModal);
    });

    async function loadInward(id) {
      if (!id || saving || loading) return;
      loading = true;
      openModal();
      setMsg('Loading invoice details...', true);
      try {
        const res = await fetch(`index.php?page=api/accounts-payable-payment&action=inward&inward_id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
        const text = await res.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch {
          throw new Error('Server returned an invalid response. Please refresh and try again.');
        }
        if (!data.ok) throw new Error(data.error || 'Unable to load');
        resetEntryFields();
        const i = data.invoice;
        renderSummary(i);
        renderPaymentRows(data.payments || []);
        setAmountField(i.remaining, 0);
        if (currentRemaining <= TOLERANCE && !(data.payments || []).length) {
          setMsg('This purchase invoice is already fully paid.', false);
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
        setMsg('Nothing left to pay on this invoice.', false);
        return;
      }
      const amount = Number(f.amt.value || 0);
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
        Object.entries(pageFilters()).forEach(([k, v]) => fd.append(k, v));
        const res = await fetch('index.php?page=api/accounts-payable-payment&action=save_payment', { method: 'POST', body: fd, credentials: 'same-origin' });
        const text = await res.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch {
          throw new Error('Server returned an invalid response. Please refresh and try again.');
        }
        if (!data.ok) throw new Error(data.error || 'Save failed');

        updateRow(data.invoice);
        updateKpis(data.dashboard);
        if (toast) {
          toastEl.querySelector('.toast-body').textContent = data.message || 'Saved.';
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
      const btn = e.target.closest('.js-open-payable-payment');
      if (!btn || btn.disabled || saving || loading) return;
      e.preventDefault();
      loadInward(btn.getAttribute('data-inward-id')).catch((err) => {
        openModal();
        setMsg(err && err.message ? err.message : 'Unable to open payment form.', false);
      });
    });
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.js-edit-payable-payment');
      if (!btn) return;
      e.preventDefault();
      beginEditPayment(btn.getAttribute('data-payment-id'));
    });
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.js-new-payable-payment');
      if (!btn || !invoiceSnapshot) return;
      e.preventDefault();
      resetEntryFields();
      setAmountField(invoiceSnapshot.remaining, 0);
      setMsg('', true);
    });
    btnSave.addEventListener('click', savePayment);
  }

  if (document.readyState === 'complete') {
    initAccPayablesPaymentUi();
  } else {
    window.addEventListener('load', initAccPayablesPaymentUi, { once: true });
  }
})();
</script>
