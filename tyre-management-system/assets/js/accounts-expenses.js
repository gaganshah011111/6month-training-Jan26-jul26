(function () {
  'use strict';

  const fmt = (n) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(n || 0));
  const apiBase = window.ACC_EXP_API || '';

  /** Move modals to body so backdrop does not block form inputs (nested layout fix). */
  function moveModalsToBody() {
    ['expAddModal', 'expViewModal', 'expEditModal'].forEach((id) => {
      const el = document.getElementById(id);
      if (el && el.parentElement !== document.body) {
        document.body.appendChild(el);
      }
    });
  }

  const viewModalEl = document.getElementById('expViewModal');
  const editModalEl = document.getElementById('expEditModal');
  const addModalEl = document.getElementById('expAddModal');
  const viewModal = viewModalEl ? bootstrap.Modal.getOrCreateInstance(viewModalEl) : null;
  const editModal = editModalEl ? bootstrap.Modal.getOrCreateInstance(editModalEl) : null;
  const addModal = addModalEl ? bootstrap.Modal.getOrCreateInstance(addModalEl, { focus: true }) : null;

  if (addModalEl) {
    addModalEl.addEventListener('shown.bs.modal', () => {
      const amt = addModalEl.querySelector('input[name="amount"]');
      if (amt) {
        amt.focus();
      }
    });
    addModalEl.addEventListener('hidden.bs.modal', () => {
      const form = document.getElementById('accExpAddForm');
      if (form) {
        form.reset();
        const dateEl = form.querySelector('input[name="expense_date"]');
        if (dateEl) {
          dateEl.value = new Date().toISOString().slice(0, 10);
        }
      }
    });
    if (new URLSearchParams(window.location.search).get('add') === '1' && addModal) {
      addModal.show();
    }
  }

  async function fetchExp(id) {
    const sep = apiBase.includes('?') ? '&' : '?';
    const res = await fetch(apiBase + sep + 'action=get&id=' + encodeURIComponent(id), { credentials: 'same-origin' });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Load failed');
    return data.expense;
  }

  document.addEventListener('click', async (e) => {
    const viewBtn = e.target.closest('.js-exp-view');
    if (viewBtn && viewModal) {
      const body = document.getElementById('expViewBody');
      body.innerHTML = '<p class="text-muted">Loading…</p>';
      viewModal.show();
      try {
        const ex = await fetchExp(viewBtn.getAttribute('data-id'));
        let files = '';
        Object.values(ex.files || {}).forEach((f) => {
          files += `<a class="btn btn-sm btn-outline-secondary me-1 mb-1" href="${f.url}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> ${f.label}: ${f.name || ''}</a>`;
        });
        body.innerHTML = `
          <dl class="row small mb-0">
            <dt class="col-4 text-muted">Date</dt><dd class="col-8">${ex.expense_date}</dd>
            <dt class="col-4 text-muted">Category</dt><dd class="col-8 fw-semibold">${ex.category}${ex.source_type ? ' <span class="acc-exp-badge-auto">Auto</span>' : ''}</dd>
            <dt class="col-4 text-muted">Amount</dt><dd class="col-8 text-danger fw-bold">${fmt(ex.amount)}</dd>
            <dt class="col-4 text-muted">Mode</dt><dd class="col-8">${ex.payment_mode}</dd>
            <dt class="col-4 text-muted">Reference</dt><dd class="col-8">${ex.reference_no || '—'}</dd>
            <dt class="col-4 text-muted">Remarks</dt><dd class="col-8">${ex.remarks || '—'}</dd>
            <dt class="col-4 text-muted">Created by</dt><dd class="col-8">${ex.created_by || '—'}</dd>
            <dt class="col-4 text-muted">Created at</dt><dd class="col-8">${ex.created_at || '—'}</dd>
          </dl>
          <div class="mt-2">${files || '<span class="text-muted">No attachments</span>'}</div>`;
      } catch (err) {
        body.innerHTML = `<p class="text-danger">${err.message}</p>`;
      }
      return;
    }

    const editBtn = e.target.closest('.js-exp-edit');
    if (editBtn && editModal) {
      try {
        const ex = await fetchExp(editBtn.getAttribute('data-id'));
        document.getElementById('expEditId').value = ex.id;
        document.getElementById('expEditCategory').value = ex.category;
        document.getElementById('expEditAmount').value = ex.amount;
        document.getElementById('expEditMode').value = ex.payment_mode;
        document.getElementById('expEditDate').value = ex.expense_date;
        document.getElementById('expEditRef').value = ex.reference_no || '';
        document.getElementById('expEditRemarks').value = ex.remarks || '';
        editModal.show();
      } catch (err) {
        alert(err.message);
      }
    }
  });

  moveModalsToBody();
})();
