<?php
declare(strict_types=1);
/** Pay Salary / History / Profile drawer / Slip modals for Accounts salary pages. */
?>
<div class="offcanvas offcanvas-end sal-drawer" tabindex="-1" id="salProfileDrawer" aria-labelledby="salProfileDrawerLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="salProfileDrawerLabel">Employee Profile</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body" id="salProfileDrawerBody">
    <p class="text-muted mb-0">Loading…</p>
  </div>
</div>

<div class="modal fade sal-pay-modal" id="salPayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pay Salary</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="salPayMsg" class="small mb-2"></div>
        <form id="salPayForm"><?= csrf_input() ?><input type="hidden" name="salary_id" id="salPaySalaryId"></form>
        <dl class="sal-pay-summary-grid" id="salPaySummary"></dl>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Payment Amount</label>
            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" form="salPayForm" id="salPayAmount" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Payment Date</label>
            <input type="date" class="form-control" name="payment_date" form="salPayForm" id="salPayDate" value="<?= e(date('Y-m-d')) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Payment Mode</label>
            <select class="form-select" name="payment_mode" form="salPayForm" id="salPayMode">
              <?php foreach (['Cash', 'UPI', 'NEFT', 'RTGS', 'Bank Transfer'] as $m): ?>
                <option><?= e($m) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Reference Number</label>
            <input type="text" class="form-control" name="reference_no" form="salPayForm" id="salPayRef" placeholder="Transaction / cheque no.">
          </div>
          <div class="col-12">
            <label class="form-label">Remarks</label>
            <input type="text" class="form-control" name="remarks" form="salPayForm" id="salPayRemarks" placeholder="Optional note">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a id="salPayReceiptLink" class="btn btn-outline-secondary d-none" href="#" target="_blank" rel="noopener"><i class="bi bi-receipt"></i> View Receipt</a>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="salPaySaveBtn"><i class="bi bi-check-lg"></i> Save Payment</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="salHistModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment History — <span id="salHistEmpName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 sal-pay-data-table">
            <thead>
            <tr>
              <th>Date</th>
              <th class="text-end">Amount</th>
              <th>Mode</th>
              <th>Reference</th>
              <th>Remarks</th>
              <th></th>
            </tr>
            </thead>
            <tbody id="salHistRows"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="salSlipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Salary Slip — <span id="salSlipEmpName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <iframe id="salSlipFrame" class="sal-slip-frame" title="Salary slip preview"></iframe>
      </div>
      <div class="modal-footer">
        <a id="salSlipDownload" class="btn btn-primary" href="#" target="_blank" rel="noopener"><i class="bi bi-download"></i> Download PDF</a>
        <button type="button" class="btn btn-outline-secondary" id="salSlipPrintBtn"><i class="bi bi-printer"></i> Print</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
window.initAccountsSalaryPayUi = function (apiBase, opts) {
  opts = opts || {};
  const fmt = (n) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(n || 0));
  const payModalEl = document.getElementById('salPayModal');
  if (!payModalEl || !window.bootstrap) return;

  const payModal = new bootstrap.Modal(payModalEl);
  const histModal = new bootstrap.Modal(document.getElementById('salHistModal'));
  const slipModal = new bootstrap.Modal(document.getElementById('salSlipModal'));
  const profileDrawer = new bootstrap.Offcanvas(document.getElementById('salProfileDrawer'));
  const msg = document.getElementById('salPayMsg');
  const form = document.getElementById('salPayForm');
  const summary = document.getElementById('salPaySummary');
  const btnSave = document.getElementById('salPaySaveBtn');
  const receiptLink = document.getElementById('salPayReceiptLink');
  const slipFrame = document.getElementById('salSlipFrame');
  const slipDownload = document.getElementById('salSlipDownload');
  const slipBase = <?= json_encode(route_url('accounts/salary-payslip'), JSON_THROW_ON_ERROR) ?>;
  let saving = false;

  const setMsg = (t, ok) => {
    msg.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
    msg.textContent = t || '';
  };

  const normStatus = (st) => (st === 'unpaid' ? 'pending' : st);

  const statusBadge = (st) => {
    const n = normStatus(st);
    const map = { paid: ['paid', 'Paid'], partial: ['partial', 'Partial'], pending: ['pending', 'Pending'] };
    const [cls, label] = map[n] || map.pending;
    return '<span class="sal-badge sal-badge--' + cls + '">' + label + '</span>';
  };

  const progressClass = (st) => {
    const n = normStatus(st);
    if (n === 'paid') return 'sal-progress__bar--paid';
    if (n === 'partial') return 'sal-progress__bar--partial';
    return 'sal-progress__bar--pending';
  };

  function slipUrl(id) {
    return slipBase + (slipBase.includes('?') ? '&' : '?') + 'id=' + encodeURIComponent(id);
  }

  async function fetchEmp(id) {
    const res = await fetch(apiBase + '&action=employee&salary_id=' + encodeURIComponent(id), { credentials: 'same-origin' });
    const data = JSON.parse(await res.text());
    if (!data.ok) throw new Error(data.error || 'Load failed');
    return data;
  }

  function openSlipModal(id, name) {
    const url = slipUrl(id);
    slipFrame.src = url;
    slipDownload.href = url;
    document.getElementById('salSlipEmpName').textContent = name || '';
    if (!name) fetchEmp(id).then((d) => { document.getElementById('salSlipEmpName').textContent = d.salary.full_name; });
    slipModal.show();
  }

  function openPayModal(id) {
    document.getElementById('salPaySalaryId').value = id;
    receiptLink.classList.add('d-none');
    setMsg('Loading…', true);
    fetchEmp(id).then((data) => {
      const s = data.salary;
      summary.innerHTML = `
        <dt>Employee Name</dt><dd>${s.full_name}</dd>
        <dt>Employee ID</dt><dd class="font-monospace">${s.employee_code}</dd>
        <dt>Payroll Month</dt><dd>${s.month_label}</dd>
        <dt>Total Salary</dt><dd>${fmt(s.net_salary)}</dd>
        <dt>Already Paid</dt><dd class="text-success">${fmt(s.amount_paid)}</dd>
        <dt>Remaining Amount</dt><dd class="text-danger fw-bold">${fmt(s.pending)}</dd>`;
      document.getElementById('salPayAmount').value = Number(s.pending) > 0 ? Number(s.pending).toFixed(2) : '';
      setMsg('', true);
      payModal.show();
    }).catch((err) => { setMsg(err.message, false); payModal.show(); });
  }

  function openProfile(id, initials) {
    document.getElementById('salProfileDrawerBody').innerHTML = '<p class="text-muted mb-0">Loading…</p>';
    profileDrawer.show();
    fetchEmp(id).then((data) => renderProfile(data, initials));
  }

  function renderProfile(data, initials) {
    const s = data.salary;
    const p = data.profile || {};
    const st = s.pay_status || 'pending';
    const nst = normStatus(st);
    const body = document.getElementById('salProfileDrawerBody');
    body.innerHTML = `
      <div class="sal-drawer-hero">
        <div class="sal-drawer-avatar">${initials || 'EM'}</div>
        <h6 class="mb-0 fw-bold">${s.full_name}</h6>
        <p class="text-muted small font-monospace mb-2">${s.employee_code}</p>
        ${statusBadge(nst)}
      </div>
      <p class="small mb-1"><span class="text-muted">Department</span><br><strong>${s.dept_label || '—'}</strong></p>
      <p class="small mb-2"><span class="text-muted">Designation</span><br><strong>${p.designation || s.desig_label || '—'}</strong></p>
      <p class="small mb-2"><span class="text-muted">Payroll Month</span><br><strong>${s.month_label}</strong></p>
      <div class="sal-drawer-stat">
        <div><span>Salary</span><strong>${fmt(s.net_salary)}</strong></div>
        <div><span>Paid</span><strong class="text-success">${fmt(s.amount_paid)}</strong></div>
        <div><span>Pending</span><strong class="text-danger">${fmt(s.pending)}</strong></div>
        <div><span>Status</span><strong>${nst.charAt(0).toUpperCase() + nst.slice(1)}</strong></div>
      </div>
      <hr>
      <p class="small text-muted text-uppercase fw-semibold mb-2">Quick Actions</p>
      <div class="d-grid gap-2">
        <button type="button" class="btn btn-success js-drawer-pay" data-salary-id="${s.id}" ${s.pay_status === 'paid' ? 'disabled' : ''}><i class="bi bi-cash-coin"></i> Pay Salary</button>
        <button type="button" class="btn btn-outline-secondary js-drawer-slip" data-salary-id="${s.id}" data-name="${s.full_name}"><i class="bi bi-file-earmark-pdf"></i> Salary Slip</button>
        <button type="button" class="btn btn-outline-secondary js-drawer-history" data-salary-id="${s.id}" data-name="${s.full_name}"><i class="bi bi-clock-history"></i> Payment History</button>
      </div>`;
  }

  function markRowPaid(tr) {
    const inner = tr.querySelector('.sal-row-actions__inner');
    if (!inner) return;
    const payBtn = inner.querySelector('.js-sal-pay');
    if (payBtn) {
      payBtn.outerHTML = '<span class="sal-btn-paid-done"><i class="bi bi-check-lg"></i> Paid</span>';
    }
  }

  function updateRow(s) {
    const tr = document.querySelector('tr[data-salary-id="' + s.id + '"]');
    if (!tr) return;
    tr.setAttribute('data-pay-status', s.pay_status);

    const paidEl = tr.querySelector('.js-emp-paid');
    const pendEl = tr.querySelector('.js-emp-pending');
    const stEl = tr.querySelector('.js-emp-status');
    if (paidEl) paidEl.textContent = fmt(s.amount_paid);
    if (pendEl) pendEl.textContent = fmt(s.pending);
    if (stEl) {
      const n = normStatus(s.pay_status);
      const label = n === 'paid' ? 'Paid' : (n === 'partial' ? 'Partial' : 'Pending');
      stEl.className = 'sal-badge sal-badge--' + n + ' js-emp-status';
      stEl.textContent = label;
    }

    const pct = s.net_salary > 0 ? Math.min(100, Math.round((s.amount_paid / s.net_salary) * 100)) : 0;
    const prog = tr.querySelector('.js-emp-progress-bar');
    const pctEl = tr.querySelector('.js-emp-progress-pct');
    if (prog) {
      prog.style.width = pct + '%';
      prog.className = 'sal-progress__bar ' + progressClass(s.pay_status) + ' js-emp-progress-bar';
    }
    if (pctEl) pctEl.textContent = pct + '%';

    if (s.pay_status === 'paid') markRowPaid(tr);
    if (typeof opts.onRowUpdated === 'function') opts.onRowUpdated(s);
  }

  function openHistory(id, name) {
    const tbody = document.getElementById('salHistRows');
    tbody.innerHTML = '<tr><td colspan="6" class="p-3 text-muted">Loading…</td></tr>';
    document.getElementById('salHistEmpName').textContent = name || '';
    histModal.show();
    fetchEmp(id).then((data) => {
      document.getElementById('salHistEmpName').textContent = data.salary.full_name;
      const rows = (data.payments || []).map((p) =>
        `<tr><td>${p.date}</td><td class="text-end fw-semibold sal-col-paid">${fmt(p.amount)}</td><td>${p.mode}</td><td>${p.reference || '—'}</td><td class="small">${p.remarks || ''}</td>
        <td><a class="btn btn-sm btn-outline-secondary" href="${p.receipt_url}" target="_blank" rel="noopener">Receipt</a></td></tr>`
      ).join('');
      tbody.innerHTML = rows || '<tr><td colspan="6" class="p-3 text-muted">No payments recorded yet.</td></tr>';
    });
  }

  function rowContext(el) {
    const tr = el.closest('tr[data-salary-id]');
    if (!tr) return { id: el.getAttribute('data-salary-id'), initials: 'EM', name: '' };
    return {
      id: tr.getAttribute('data-salary-id'),
      initials: tr.getAttribute('data-initials') || 'EM',
      name: tr.getAttribute('data-employee-name') || '',
    };
  }

  document.addEventListener('click', (e) => {
    const payBtn = e.target.closest('.js-sal-pay');
    if (payBtn) {
      e.preventDefault();
      openPayModal(payBtn.getAttribute('data-salary-id'));
      return;
    }

    const profBtn = e.target.closest('.js-sal-profile');
    if (profBtn) {
      e.preventDefault();
      const ctx = rowContext(profBtn);
      openProfile(profBtn.getAttribute('data-salary-id') || ctx.id, profBtn.getAttribute('data-initials') || ctx.initials);
      return;
    }

    const slipBtn = e.target.closest('.js-sal-slip, .js-sal-slip-dl');
    if (slipBtn) {
      e.preventDefault();
      const ctx = rowContext(slipBtn);
      const id = slipBtn.getAttribute('data-salary-id') || ctx.id;
      if (slipBtn.classList.contains('js-sal-slip-dl')) {
        window.open(slipUrl(id), '_blank');
      } else {
        openSlipModal(id, ctx.name);
      }
      return;
    }

    const printBtn = e.target.closest('.js-sal-slip-print');
    if (printBtn) {
      e.preventDefault();
      const id = printBtn.getAttribute('data-salary-id') || rowContext(printBtn).id;
      openSlipModal(id, rowContext(printBtn).name);
      setTimeout(() => {
        try { slipFrame.contentWindow.print(); } catch (_) { window.open(slipFrame.src, '_blank')?.print(); }
      }, 600);
      return;
    }

    const histBtn = e.target.closest('.js-sal-history');
    if (histBtn) {
      e.preventDefault();
      const ctx = rowContext(histBtn);
      openHistory(histBtn.getAttribute('data-salary-id') || ctx.id, histBtn.getAttribute('data-employee-name') || ctx.name);
    }
  });

  document.getElementById('salProfileDrawerBody').addEventListener('click', (e) => {
    const pay = e.target.closest('.js-drawer-pay');
    if (pay && !pay.disabled) {
      profileDrawer.hide();
      openPayModal(pay.getAttribute('data-salary-id'));
      return;
    }
    const slip = e.target.closest('.js-drawer-slip');
    if (slip) {
      profileDrawer.hide();
      openSlipModal(slip.getAttribute('data-salary-id'), slip.getAttribute('data-name') || '');
      return;
    }
    const hist = e.target.closest('.js-drawer-history');
    if (hist) {
      profileDrawer.hide();
      openHistory(hist.getAttribute('data-salary-id'), hist.getAttribute('data-name') || '');
    }
  });

  document.getElementById('salSlipPrintBtn').addEventListener('click', () => {
    try { slipFrame.contentWindow.print(); }
    catch (_) { window.open(slipFrame.src, '_blank')?.print(); }
  });

  btnSave.addEventListener('click', async () => {
    if (saving) return;
    saving = true;
    btnSave.disabled = true;
    setMsg('Saving…', true);
    try {
      const fd = new FormData(form);
      const res = await fetch(apiBase + '&action=save_payment', { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = JSON.parse(await res.text());
      if (!data.ok) throw new Error(data.error || 'Save failed');
      updateRow(data.salary);
      if (data.receipt_url) {
        receiptLink.href = data.receipt_url;
        receiptLink.classList.remove('d-none');
      }
      if (data.dashboard && typeof opts.onDashboardUpdate === 'function') {
        opts.onDashboardUpdate(data.dashboard);
      }
      setMsg(data.message || 'Payment saved.', true);
      if (data.salary.pay_status === 'paid') setTimeout(() => payModal.hide(), 900);
    } catch (err) {
      setMsg(err.message, false);
    } finally {
      saving = false;
      btnSave.disabled = false;
    }
  });
};
</script>
