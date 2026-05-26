(function () {
  const payModalEl = document.getElementById('invPayModal');
  const payForm = document.getElementById('inv-pay-form');
  const payAmount = document.getElementById('inv-pay-amount');
  let payModal;

  function money(n) {
    return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function openPayModal(btn) {
    if (!payModalEl) return;
    if (!payModal) payModal = new bootstrap.Modal(payModalEl);

    const id = btn.getAttribute('data-id');
    const pending = parseFloat(btn.getAttribute('data-pending') || '0');

    document.getElementById('inv-pay-inward-id').value = id;
    document.getElementById('inv-pay-pinv').textContent = btn.getAttribute('data-pinv') || '—';
    document.getElementById('inv-pay-supplier').textContent = btn.getAttribute('data-supplier') || '—';
    document.getElementById('inv-pay-total').textContent = money(btn.getAttribute('data-total'));
    document.getElementById('inv-pay-paid').textContent = money(btn.getAttribute('data-paid'));
    document.getElementById('inv-pay-pending').textContent = money(pending);

    if (payAmount) {
      payAmount.value = pending > 0 ? pending.toFixed(2) : '';
      payAmount.max = pending > 0 ? pending.toFixed(2) : '';
    }

    payModal.show();
  }

  document.querySelectorAll('.inv-open-pay-modal, [data-pay-open]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      openPayModal(this);
    });
  });

  if (payForm && payAmount) {
    payForm.addEventListener('submit', function (e) {
      const pending = parseFloat(document.getElementById('inv-pay-pending')?.textContent?.replace(/[^\d.-]/g, '') || '0');
      const amt = parseFloat(payAmount.value || '0');
      if (amt > pending + 0.02) {
        e.preventDefault();
        alert('Payment cannot exceed remaining balance of ' + money(pending));
      }
    });
  }

  const histModalEl = document.getElementById('invPayHistoryModal');
  let histModal;

  document.querySelectorAll('.inv-open-pay-history, [data-pay-history]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      if (!histModalEl) return;
      if (!histModal) histModal = new bootstrap.Modal(histModalEl);

      const id = this.getAttribute('data-id');
      const pinv = this.getAttribute('data-pinv') || '';
      document.getElementById('inv-pay-history-title').textContent = 'Payment history — ' + pinv;
      const body = document.getElementById('inv-pay-history-body');
      body.innerHTML = '<tr><td colspan="7" class="text-center inv-muted p-3">Loading…</td></tr>';
      histModal.show();

      fetch('index.php?page=inventory/purchase-history&ajax=payments&inward_id=' + encodeURIComponent(id))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          const rows = data.payments || [];
          if (!rows.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-center inv-muted p-3">No payments recorded.</td></tr>';
            return;
          }
          body.innerHTML = rows.map(function (p) {
            return '<tr><td>' + p.payment_date + '</td><td>' + (p.pinv_no || pinv) + '</td><td>' + (p.supplier_name || '—') + '</td>'
              + '<td class="text-end">₹' + Number(p.amount).toFixed(2) + '</td><td>' + (p.payment_mode || '—') + '</td>'
              + '<td>' + (p.payment_ref || '—') + '</td><td>' + (p.recorded_by || '—') + '</td></tr>';
          }).join('');
        })
        .catch(function () {
          body.innerHTML = '<tr><td colspan="7" class="text-danger p-3">Failed to load payments.</td></tr>';
        });
    });
  });
})();
