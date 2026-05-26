(function () {
  const form = document.getElementById('inv-purchase-form');
  if (!form) return;

  const suppliers = window.INV_SUPPLIERS || [];
  const supplierSel = document.getElementById('inv-supplier-select');
  const materialSel = document.getElementById('inv-material-select');
  const unitInp = document.getElementById('inv-unit');
  const warehouseInp = document.getElementById('inv-warehouse');
  const payStatus = document.getElementById('inv-pay-status');
  const paidInp = document.getElementById('inv-paid');
  const remainingInp = document.getElementById('inv-remaining');

  const panel = document.getElementById('inv-limits-panel');
  const title = document.getElementById('inv-limits-title');
  const optional = document.getElementById('inv-limits-optional');
  const updateChk = document.getElementById('inv-update-limits');
  const limitFields = document.getElementById('inv-limits-fields');
  const minInp = document.getElementById('inv-min-stock');
  const maxInp = document.getElementById('inv-max-stock');

  function money(n) {
    return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function num(name) {
    const el = form.querySelector('[name="' + name + '"]');
    return el ? parseFloat(el.value) || 0 : 0;
  }

  function recalc() {
    const qty = num('quantity');
    const rate = num('purchase_rate');
    const gstPct = num('gst_percent');
    const transport = num('transport_charges');
    const loading = num('loading_charges');
    const other = num('other_charges');
    const discount = num('discount_amount');
    let paid = num('paid_amount');

    const subtotal = Math.round(qty * rate * 100) / 100;
    const gst = Math.round(subtotal * (gstPct / 100) * 100) / 100;
    const extra = Math.round((transport + loading + other) * 100) / 100;
    let total = Math.round((subtotal + gst + extra - discount) * 100) / 100;
    if (total < 0) total = 0;

    const status = payStatus ? payStatus.value : 'Unpaid';
    if (status === 'Paid') {
      paid = total;
      if (paidInp) paidInp.value = paid.toFixed(2);
    } else if (status === 'Unpaid' && paidInp && document.activeElement !== paidInp) {
      paid = 0;
      paidInp.value = '0';
    }

    const pending = Math.max(0, Math.round((total - paid) * 100) / 100);
    if (remainingInp) remainingInp.value = pending.toFixed(2);

    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = money(v); };
    set('sum-subtotal', subtotal);
    set('sum-gst', gst);
    set('sum-extra', extra);
    set('sum-discount', discount);
    set('sum-total', total);
  }

  function syncSupplier() {
    const id = supplierSel ? parseInt(supplierSel.value, 10) : 0;
    const row = suppliers.find((s) => s.id === id);
    const gst = document.getElementById('inv-supplier-gst');
    const contact = document.getElementById('inv-supplier-contact');
    const phone = document.getElementById('inv-supplier-phone');
    if (!row) {
      if (gst) gst.value = '';
      if (contact) contact.value = '';
      if (phone) phone.value = '';
      return;
    }
    if (gst) gst.value = row.gst_number || '';
    if (contact) contact.value = row.contact_person || '';
    if (phone) phone.value = row.phone || '';
  }

  function syncMaterial() {
    const opt = materialSel && materialSel.options[materialSel.selectedIndex];
    if (!opt || !opt.value) {
      if (unitInp) unitInp.value = '';
      panel?.classList.add('d-none');
      return;
    }
    if (unitInp) unitInp.value = opt.dataset.unit || '';
    if (warehouseInp && !warehouseInp.value && opt.dataset.loc) warehouseInp.value = opt.dataset.loc;

    const isFirst = opt.dataset.first === '1';
    const needsLimits = opt.dataset.needsLimits === '1';
    panel?.classList.toggle('d-none', !isFirst && !needsLimits);
    optional?.classList.toggle('d-none', isFirst);
    if (title) title.textContent = isFirst ? 'Stock alert settings (first receipt)' : 'Stock alert settings';
    if (isFirst) {
      if (updateChk) updateChk.checked = false;
      limitFields?.classList.remove('d-none');
      minInp?.removeAttribute('disabled');
      maxInp?.removeAttribute('disabled');
    } else {
      limitFields?.classList.toggle('d-none', !updateChk?.checked);
      if (minInp) minInp.value = opt.dataset.min || '0';
      if (maxInp) maxInp.value = opt.dataset.max || '0';
    }
  }

  form.querySelectorAll('.inv-calc').forEach((el) => el.addEventListener('input', recalc));
  payStatus?.addEventListener('change', recalc);
  supplierSel?.addEventListener('change', syncSupplier);
  materialSel?.addEventListener('change', syncMaterial);
  updateChk?.addEventListener('change', function () {
    limitFields?.classList.toggle('d-none', !this.checked);
    minInp?.toggleAttribute('disabled', !this.checked);
    maxInp?.toggleAttribute('disabled', !this.checked);
  });

  syncSupplier();
  syncMaterial();
  recalc();
})();
