(function () {
  'use strict';

  var form = document.getElementById('salesOrderForm');
  if (!form) return;

  var linesRoot = document.getElementById('salesOrderLines');
  var addBtn = document.getElementById('salesAddLine');
  var stockApi = form.getAttribute('data-stock-api') || '';
  var orderDisc = document.getElementById('so-order-discount');

  function money(n) {
    return '₹' + (Number(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function initTyreSelect(select) {
    if (!select || select.tomselect || typeof TomSelect === 'undefined') return;
    if (select.getAttribute('data-so-ts') === '1') return;
    select.setAttribute('data-so-ts', '1');
    new TomSelect(select, {
      allowEmptyOption: true,
      maxOptions: null,
      maxHeight: 220,
      dropdownParent: 'body',
      placeholder: select.getAttribute('data-placeholder') || 'Search tyre type…',
      plugins: ['dropdown_input'],
      render: {
        option: function (data, escape) {
          return '<div class="so-ts-option">' + escape(data.text) + '</div>';
        },
      },
    });
  }

  function cardTemplate(index) {
    var tyreOpts = (window.SALES_TYRE_TYPES || [])
      .map(function (t) {
        return '<option value="' + String(t).replace(/"/g, '&quot;') + '">' + t + '</option>';
      })
      .join('');
    return (
      '<article class="so-line-card sales-order-line" data-line-index="' + index + '">' +
      '<header class="so-line-card__head">' +
      '<span class="so-line-card__num">Line ' + index + '</span>' +
      '<button type="button" class="btn btn-sm btn-outline-danger sales-remove-line" title="Remove line" aria-label="Remove line">' +
      '<i class="bi bi-trash"></i></button></header>' +
      '<div class="row g-2 so-line-card__grid">' +
      '<div class="col-12 col-md-6 col-xl-4">' +
      '<label class="so-field-label">Tyre type <span class="text-danger">*</span></label>' +
      '<select class="form-select form-select-sm so-tyre-select" name="tyre_type[]" data-line-tyre required data-placeholder="Search tyre type…">' +
      '<option value="">Select tyre type…</option>' + tyreOpts + '</select></div>' +
      '<div class="col-6 col-md-3 col-xl-2"><label class="so-field-label">Available stock</label>' +
      '<div class="so-avail-display" data-line-avail>—</div></div>' +
      '<div class="col-6 col-md-3 col-xl-2"><label class="so-field-label">Quantity <span class="text-danger">*</span></label>' +
      '<input type="number" class="form-control form-control-sm" name="qty[]" data-line-qty min="1" step="1" required></div>' +
      '<div class="col-6 col-md-3 col-xl-2"><label class="so-field-label">Rate (₹)</label>' +
      '<input type="number" class="form-control form-control-sm" name="rate[]" data-line-rate min="0" step="0.01"></div>' +
      '<div class="col-6 col-md-3 col-xl-2"><label class="so-field-label">GST %</label>' +
      '<input type="number" class="form-control form-control-sm" name="gst_percent[]" data-line-gst min="0" max="100" step="0.01" value="18"></div>' +
      '<div class="col-6 col-md-3 col-xl-2"><label class="so-field-label">Discount (₹)</label>' +
      '<input type="number" class="form-control form-control-sm" name="line_discount[]" data-line-disc min="0" step="0.01" value="0"></div>' +
      '<div class="col-6 col-md-3 col-xl-2"><label class="so-field-label">Line total</label>' +
      '<div class="so-line-total-display" data-line-total>₹0.00</div></div>' +
      '<div class="col-12 col-md-6 col-xl-4"><label class="so-field-label">Stock status</label>' +
      '<div class="so-stock-display" data-line-stock><span class="so-stock-muted">Select tyre and quantity</span></div></div>' +
      '</div></article>'
    );
  }

  function renumberLines() {
    linesRoot.querySelectorAll('.sales-order-line').forEach(function (row, i) {
      var n = i + 1;
      row.setAttribute('data-line-index', String(n));
      var label = row.querySelector('.so-line-card__num');
      if (label) label.textContent = 'Line ' + n;
    });
  }

  function lineCalc(row) {
    var qty = parseFloat(row.querySelector('[data-line-qty]')?.value, 10) || 0;
    var rate = parseFloat(row.querySelector('[data-line-rate]')?.value, 10) || 0;
    var gst = parseFloat(row.querySelector('[data-line-gst]')?.value, 10) || 0;
    var disc = parseFloat(row.querySelector('[data-line-disc]')?.value, 10) || 0;
    var sub = Math.max(0, qty * rate - disc);
    var gstAmt = sub * (gst / 100);
    return { sub: sub, gst: gstAmt, total: sub + gstAmt, disc: disc };
  }

  function refreshStock(row) {
    var tyre = row.querySelector('[data-line-tyre]');
    var qtyEl = row.querySelector('[data-line-qty]');
    var hint = row.querySelector('[data-line-stock]');
    var availCell = row.querySelector('[data-line-avail]');
    if (!tyre || !qtyEl || !hint) return;

    var tt = tyre.tomselect ? tyre.tomselect.getValue() : tyre.value;
    var q = parseInt(qtyEl.value, 10) || 0;

    delete row.dataset.stockState;

    if (!tt || q < 1 || !stockApi) {
      if (availCell) availCell.textContent = '—';
      hint.innerHTML = '<span class="so-stock-muted">Select tyre and quantity</span>';
      updateSummary();
      return;
    }

    hint.innerHTML = '<span class="so-stock-loading">Checking…</span>';
    fetch(stockApi + '&tyre_type=' + encodeURIComponent(tt) + '&qty=' + q)
      .then(function (r) {
        return r.json();
      })
      .then(function (d) {
        if (!d.ok || !d.stock) {
          if (availCell) availCell.textContent = '—';
          hint.innerHTML = '<span class="so-stock-muted">—</span>';
          updateSummary();
          return;
        }
        var s = d.stock;
        if (availCell) {
          availCell.innerHTML =
            '<strong>' + s.available + '</strong> <span class="so-stock-muted">tyres</span>';
        }
        var pending = parseInt(row.querySelector('[data-line-qty]')?.value, 10) || 0;
        var avail = parseInt(s.available, 10) || 0;
        if (pending > 0 && avail >= pending) {
          hint.innerHTML = '<span class="so-badge so-badge--ready">READY</span>';
          row.dataset.stockState = 'ready';
        } else if (pending > 0 && avail > 0) {
          hint.innerHTML =
            '<span class="so-badge so-badge--partial">PARTIAL STOCK</span>' +
            '<span class="so-stock-muted ms-1">Ship up to ' + avail + '</span>';
          row.dataset.stockState = 'partial';
        } else {
          hint.innerHTML = '<span class="so-badge so-badge--production">PRODUCTION REQUIRED</span>';
          row.dataset.stockState = 'production';
        }
        updateSummary();
      })
      .catch(function () {
        if (availCell) availCell.textContent = '—';
        hint.innerHTML = '<span class="so-stock-muted">Unavailable</span>';
        updateSummary();
      });
  }

  function updateLineTotal(row) {
    var el = row.querySelector('[data-line-total]');
    if (el) el.textContent = money(lineCalc(row).total);
  }

  function updateSummary() {
    var rows = linesRoot.querySelectorAll('.sales-order-line');
    var items = 0;
    var qtySum = 0;
    var subSum = 0;
    var gstSum = 0;
    var lineDiscSum = 0;
    var ready = 0;
    var prod = 0;
    var partial = 0;
    var checked = 0;

    rows.forEach(function (row) {
      var tyre = row.querySelector('[data-line-tyre]');
      var tt = tyre && (tyre.tomselect ? tyre.tomselect.getValue() : tyre.value);
      if (!tt) return;
      items += 1;
      var c = lineCalc(row);
      var q = parseInt(row.querySelector('[data-line-qty]')?.value, 10) || 0;
      qtySum += q;
      subSum += c.sub;
      gstSum += c.gst;
      lineDiscSum += c.disc;
      if (row.dataset.stockState === 'ready') ready += 1;
      if (row.dataset.stockState === 'production') prod += 1;
      if (row.dataset.stockState === 'partial') partial += 1;
      if (row.dataset.stockState) checked += 1;
    });

    var orderDiscVal = parseFloat(document.getElementById('so-order-discount')?.value, 10) || 0;
    var finalAmt = Math.max(0, subSum + gstSum - orderDiscVal);

    var set = function (id, val) {
      var el = document.getElementById(id);
      if (el) el.textContent = val;
    };
    set('soSumItems', String(items));
    set('soSumQty', String(qtySum));
    set('soSumSub', money(subSum));
    set('soSumGst', money(gstSum));
    set('soSumLineDisc', money(lineDiscSum));
    set('soSumOrderDisc', money(orderDiscVal));
    set('soSumFinal', money(finalAmt));

    var readiness = document.getElementById('soSumReadiness');
    if (!readiness) return;
    if (items === 0) {
      readiness.className = 'so-badge so-badge--pending';
      readiness.textContent = 'Add items';
    } else if (checked < items) {
      readiness.className = 'so-badge so-badge--pending';
      readiness.textContent = 'Check stock';
    } else if ((prod > 0 || partial > 0) && (ready > 0 || partial > 0)) {
      readiness.className = 'so-badge so-badge--partial';
      readiness.textContent = 'Mixed / Partial';
    } else if (prod > 0) {
      readiness.className = 'so-badge so-badge--production';
      readiness.textContent = 'Production Required';
    } else if (ready === items) {
      readiness.className = 'so-badge so-badge--ready';
      readiness.textContent = 'Ready for Dispatch';
    } else {
      readiness.className = 'so-badge so-badge--pending';
      readiness.textContent = 'Check stock';
    }
  }

  function bindRow(row) {
    initTyreSelect(row.querySelector('.so-tyre-select'));
    row.querySelectorAll('[data-line-tyre],[data-line-qty],[data-line-rate],[data-line-gst],[data-line-disc]').forEach(function (el) {
      el.addEventListener('change', function () {
        updateLineTotal(row);
        refreshStock(row);
        updateSummary();
      });
      el.addEventListener('input', function () {
        updateLineTotal(row);
        if (el.hasAttribute('data-line-qty') || el.hasAttribute('data-line-tyre')) {
          refreshStock(row);
        } else {
          updateSummary();
        }
      });
    });
    var tyreSelect = row.querySelector('.so-tyre-select');
    if (tyreSelect && tyreSelect.tomselect) {
      tyreSelect.tomselect.on('change', function () {
        updateLineTotal(row);
        refreshStock(row);
        updateSummary();
      });
    }
    var rm = row.querySelector('.sales-remove-line');
    if (rm) {
      rm.addEventListener('click', function () {
        if (linesRoot.querySelectorAll('.sales-order-line').length > 1) {
          var sel = row.querySelector('.so-tyre-select');
          if (sel && sel.tomselect) sel.tomselect.destroy();
          row.remove();
          renumberLines();
          updateSummary();
        }
      });
    }
    updateLineTotal(row);
    refreshStock(row);
  }

  if (addBtn) {
    addBtn.addEventListener('click', function () {
      var count = linesRoot.querySelectorAll('.sales-order-line').length + 1;
      linesRoot.insertAdjacentHTML('beforeend', cardTemplate(count));
      var rows = linesRoot.querySelectorAll('.sales-order-line');
      bindRow(rows[rows.length - 1]);
      updateSummary();
    });
  }

  if (orderDisc) {
    orderDisc.addEventListener('input', updateSummary);
    orderDisc.addEventListener('change', updateSummary);
  }

  linesRoot.querySelectorAll('.sales-order-line').forEach(bindRow);
  updateSummary();
})();
