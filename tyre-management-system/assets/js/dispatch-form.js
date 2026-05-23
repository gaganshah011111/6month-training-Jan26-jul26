/**
 * Dispatch entry — searchable combos, stock, logistics, preview IDs.
 */
(function () {
    'use strict';

    function parseJson(attr, fallback) {
        try {
            return JSON.parse(attr || '');
        } catch (e) {
            return fallback;
        }
    }

    /** Single searchable dropdown (no duplicate search + select). */
    function DspCombo(root, options, cfg) {
        this.root = root;
        this.options = options || [];
        this.cfg = cfg || {};
        this.selected = null;
        this.disabled = root.getAttribute('data-disabled') === '1';
        this.filtered = [];
        this.activeIdx = -1;
        this.picking = false;

        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'form-control dsp-combo__input';
        this.input.placeholder = root.getAttribute('data-placeholder') || 'Search or select…';
        this.input.autocomplete = 'off';
        if (this.disabled) {
            this.input.disabled = true;
        }

        this.btn = document.createElement('button');
        this.btn.type = 'button';
        this.btn.className = 'dsp-combo__toggle';
        this.btn.setAttribute('aria-label', 'Show options');
        this.btn.textContent = '▼';

        this.menu = document.createElement('ul');
        this.menu.className = 'dsp-combo__menu d-none';
        this.menu.setAttribute('role', 'listbox');

        root.classList.add('dsp-combo');
        root.appendChild(this.input);
        root.appendChild(this.btn);
        root.appendChild(this.menu);

        var self = this;
        this.input.addEventListener('input', function () {
            if (self.selected && self.input.value !== self.selected.label) {
                self.selected = null;
                if (self.cfg.onChange) {
                    self.cfg.onChange(null);
                }
            }
            self.filter(self.input.value);
            self.open();
        });
        this.input.addEventListener('focus', function () {
            self.filter(self.input.value);
            self.open();
        });
        this.input.addEventListener('keydown', function (e) {
            self.onKeydown(e);
        });
        this.input.addEventListener('blur', function () {
            setTimeout(function () {
                if (self.picking) {
                    return;
                }
                self.tryAutoPick();
                self.close();
                if (self.cfg.allowFreeText && !self.selected && self.input.value.trim()) {
                    self.applyFreeText(self.input.value.trim());
                }
            }, 220);
        });
        this.btn.addEventListener('mousedown', function (e) {
            e.preventDefault();
        });
        this.btn.addEventListener('click', function () {
            if (self.menu.classList.contains('d-none')) {
                self.filter(self.input.value);
                self.open();
                self.input.focus();
            } else {
                self.close();
            }
        });
        document.addEventListener('click', function (e) {
            if (!self.root.contains(e.target)) {
                self.close();
            }
        });
    }

    DspCombo.prototype.onKeydown = function (e) {
        if (e.key === 'Escape') {
            this.close();
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (this.menu.classList.contains('d-none')) {
                this.filter(this.input.value);
                this.open();
            }
            this.activeIdx = Math.min(this.activeIdx + 1, this.filtered.length - 1);
            this.renderActive();
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.activeIdx = Math.max(this.activeIdx - 1, 0);
            this.renderActive();
            return;
        }
        if (e.key === 'Enter' && !this.menu.classList.contains('d-none')) {
            e.preventDefault();
            if (this.activeIdx >= 0 && this.filtered[this.activeIdx]) {
                this.pick(this.filtered[this.activeIdx]);
            } else if (this.filtered.length === 1) {
                this.pick(this.filtered[0]);
            }
        }
    };

    DspCombo.prototype.renderActive = function () {
        var items = this.menu.querySelectorAll('.dsp-combo__item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.toggle('dsp-combo__item--active', i === this.activeIdx);
        }
    };

    DspCombo.prototype.tryAutoPick = function () {
        if (!this.cfg.autoPickOnBlur || this.selected) {
            return;
        }
        var text = this.input.value.trim().toLowerCase();
        if (!text) {
            return;
        }
        var exact = null;
        for (var i = 0; i < this.options.length; i++) {
            if (this.options[i].label.toLowerCase() === text) {
                exact = this.options[i];
                break;
            }
        }
        if (exact) {
            this.pick(exact);
        }
    };

    DspCombo.prototype.filter = function (q) {
        var term = (q || '').toLowerCase().trim();
        this.menu.innerHTML = '';
        this.filtered = [];
        var self = this;
        for (var i = 0; i < this.options.length; i++) {
            (function (opt) {
                var hay = (opt.label + ' ' + (opt.sub || '')).toLowerCase();
                if (term && hay.indexOf(term) === -1) {
                    return;
                }
                self.filtered.push(opt);
                var li = document.createElement('li');
                var out = (opt.stock !== undefined && opt.stock < 1);
                li.className = 'dsp-combo__item' + (out ? ' dsp-combo__item--out' : '');
                li.setAttribute('role', 'option');
                li.innerHTML = '<span class="dsp-combo__label">' + escapeHtml(opt.label) + '</span>'
                    + (opt.sub ? '<span class="dsp-combo__sub">' + escapeHtml(opt.sub) + '</span>' : '');
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    self.picking = true;
                });
                li.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.pick(opt);
                    self.picking = false;
                    self.input.focus();
                });
                self.menu.appendChild(li);
            })(this.options[i]);
        }
        this.activeIdx = this.filtered.length > 0 ? 0 : -1;
        if (this.filtered.length === 0) {
            var empty = document.createElement('li');
            empty.className = 'dsp-combo__empty';
            empty.textContent = 'No matches — try another name';
            this.menu.appendChild(empty);
        }
    };

    DspCombo.prototype.pick = function (opt, silent) {
        this.selected = opt;
        this.input.value = opt.label;
        this.picking = false;
        this.close();
        if (!silent && this.cfg.onChange) {
            this.cfg.onChange(opt);
        }
    };

    DspCombo.prototype.applyFreeText = function (text) {
        this.selected = { value: '', label: text, free: true };
        if (this.cfg.onChange) {
            this.cfg.onChange(this.selected);
        }
    };

    DspCombo.prototype.setValue = function (value, silent) {
        for (var i = 0; i < this.options.length; i++) {
            if (String(this.options[i].value) === String(value)) {
                this.pick(this.options[i], silent);
                return;
            }
        }
    };

    DspCombo.prototype.clear = function () {
        this.selected = null;
        this.input.value = '';
        if (this.cfg.onChange) {
            this.cfg.onChange(null);
        }
    };

    DspCombo.prototype.open = function () {
        if (!this.disabled) {
            this.menu.classList.remove('d-none');
            this.root.classList.add('dsp-combo--open');
        }
    };

    DspCombo.prototype.close = function () {
        this.menu.classList.add('d-none');
        this.root.classList.remove('dsp-combo--open');
    };

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    var form = document.getElementById('dsp-dispatch-form');
    if (!form) {
        return;
    }

    var stockMap = parseJson(form.getAttribute('data-stock'), {});
    var logistics = parseJson(form.getAttribute('data-logistics'), { drivers: [], vehicles: [] });
    var stockApiUrl = form.getAttribute('data-stock-api') || '';
    var saveApiUrl = form.getAttribute('data-save-api') || '';
    var previewApiUrl = form.getAttribute('data-preview-api') || '';

    var qtyInput = document.getElementById('dsp-qty');
    var stockHint = document.getElementById('dsp-stock-hint');
    var qtyError = document.getElementById('dsp-qty-error');
    var tyreHidden = document.getElementById('dsp-tyre-type');
    var customerIdInput = document.getElementById('dsp-customer-id');
    var customerNameInput = document.getElementById('dsp-customer-name');
    var driverIdInput = document.getElementById('dsp-driver-id');
    var vehicleIdInput = document.getElementById('dsp-vehicle-id');
    var transportDisplay = document.getElementById('dsp-transport-display');
    var transportIdInput = document.getElementById('dsp-transport-id');
    var grossInput = document.getElementById('dsp-gross');
    var tareInput = document.getElementById('dsp-tare');
    var netDisplay = document.getElementById('dsp-net');
    var weightError = document.getElementById('dsp-weight-error');
    var invoicePreview = document.getElementById('dsp-invoice-preview');
    var dispatchCodeEl = document.getElementById('dsp-dispatch-code');
    var btnSave = document.getElementById('dsp-btn-save');
    var successBox = document.getElementById('dsp-form-success');
    var pdfActions = document.getElementById('dsp-form-actions-success');
    var formError = document.getElementById('dsp-form-error');

    var available = 0;
    var weightInvalid = false;
    var syncing = false;

    function driverById(id) {
        id = parseInt(id, 10);
        for (var i = 0; i < logistics.drivers.length; i++) {
            if (logistics.drivers[i].id === id) {
                return logistics.drivers[i];
            }
        }
        return null;
    }

    function vehicleById(id) {
        id = parseInt(id, 10);
        for (var i = 0; i < logistics.vehicles.length; i++) {
            if (logistics.vehicles[i].id === id) {
                return logistics.vehicles[i];
            }
        }
        return null;
    }

    function setTransport(tid, name) {
        if (transportIdInput) {
            transportIdInput.value = tid ? String(tid) : '';
        }
        if (transportDisplay) {
            transportDisplay.value = name || '';
        }
        updateButtons();
    }

    function syncFromDriver() {
        if (syncing) {
            return;
        }
        var id = parseInt(driverIdInput && driverIdInput.value, 10);
        var d = driverById(id);
        if (!d) {
            updateButtons();
            return;
        }
        syncing = true;
        if (comboVehicle && d.vehicle_id) {
            comboVehicle.setValue(String(d.vehicle_id), true);
            if (vehicleIdInput) {
                vehicleIdInput.value = String(d.vehicle_id);
            }
        }
        setTransport(d.transport_id, d.transport);
        syncing = false;
    }

    function syncFromVehicle() {
        if (syncing) {
            return;
        }
        var id = parseInt(vehicleIdInput && vehicleIdInput.value, 10);
        var v = vehicleById(id);
        if (!v) {
            updateButtons();
            return;
        }
        syncing = true;
        if (comboDriver && v.driver_id) {
            comboDriver.setValue(String(v.driver_id), true);
            if (driverIdInput) {
                driverIdInput.value = String(v.driver_id);
            }
        }
        setTransport(v.transport_id, v.transport);
        syncing = false;
    }

    var comboCustomer = new DspCombo(
        document.getElementById('dsp-combo-customer'),
        parseJson(form.getAttribute('data-customers'), []),
        {
            allowFreeText: true,
            onChange: function (opt) {
                if (!opt) {
                    if (customerIdInput) {
                        customerIdInput.value = '';
                    }
                    if (customerNameInput) {
                        customerNameInput.value = '';
                    }
                } else if (opt.free) {
                    if (customerIdInput) {
                        customerIdInput.value = '';
                    }
                    if (customerNameInput) {
                        customerNameInput.value = opt.label;
                    }
                } else {
                    if (customerIdInput) {
                        customerIdInput.value = opt.value;
                    }
                    if (customerNameInput) {
                        customerNameInput.value = opt.label;
                    }
                }
                updateButtons();
            },
        }
    );

    var comboTyre = new DspCombo(
        document.getElementById('dsp-combo-tyre'),
        parseJson(form.getAttribute('data-tyres'), []),
        {
            autoPickOnBlur: true,
            onChange: function (opt) {
                if (tyreHidden) {
                    tyreHidden.value = opt ? opt.value : '';
                }
                if (opt) {
                    fetchStock(opt.value);
                } else {
                    fetchStock('');
                }
                updateButtons();
            },
        }
    );

    var comboDriver = new DspCombo(
        document.getElementById('dsp-combo-driver'),
        parseJson(form.getAttribute('data-drivers'), []),
        {
            onChange: function (opt) {
                if (driverIdInput) {
                    driverIdInput.value = opt ? opt.value : '';
                }
                if (opt) {
                    syncFromDriver();
                } else {
                    updateButtons();
                }
            },
        }
    );

    var comboVehicle = new DspCombo(
        document.getElementById('dsp-combo-vehicle'),
        parseJson(form.getAttribute('data-vehicles'), []),
        {
            onChange: function (opt) {
                if (vehicleIdInput) {
                    vehicleIdInput.value = opt ? opt.value : '';
                }
                if (opt) {
                    syncFromVehicle();
                } else {
                    updateButtons();
                }
            },
        }
    );

    function updateButtons() {
        var qty = parseInt(qtyInput && qtyInput.value, 10) || 0;
        var tyre = tyreHidden ? tyreHidden.value : '';
        var driver = driverIdInput ? driverIdInput.value : '';
        var vehicle = vehicleIdInput ? vehicleIdInput.value : '';
        var transport = transportIdInput ? transportIdInput.value : '';
        var customer = (customerNameInput && customerNameInput.value.trim()) || '';
        var bad = !tyre || qty < 1 || qty > available || available < 1
            || !driver || !vehicle || !transport || !customer || weightInvalid;
        if (btnSave) {
            btnSave.disabled = bad;
        }
    }

    function setQtyState(invalid, message, okHint) {
        if (qtyInput) {
            qtyInput.classList.toggle('is-invalid', invalid);
        }
        if (qtyError) {
            qtyError.textContent = message || '';
            qtyError.classList.toggle('d-none', !message);
            qtyError.classList.toggle('dsp-field-hint--danger', !!invalid);
            qtyError.classList.toggle('dsp-field-hint--ok', !!okHint && !invalid);
        }
        updateButtons();
    }

    function validateQty() {
        if (!qtyInput || !tyreHidden) {
            return;
        }
        var qty = parseInt(qtyInput.value, 10);
        if (!tyreHidden.value || qtyInput.value === '' || isNaN(qty)) {
            setQtyState(false, '');
            return;
        }
        if (qty < 1) {
            setQtyState(true, 'Enter a valid quantity.');
            return;
        }
        if (qty > available) {
            setQtyState(true, 'Only ' + available + ' available');
            return;
        }
        setQtyState(false, available.toLocaleString() + ' tyres available', true);
    }

    function bestStockType() {
        var best = null;
        var bestN = 0;
        for (var k in stockMap) {
            if (stockMap.hasOwnProperty(k)) {
                var n = parseInt(stockMap[k], 10) || 0;
                if (n > bestN) {
                    bestN = n;
                    best = k;
                }
            }
        }
        return best ? { type: best, qty: bestN } : null;
    }

    function updateStockDisplay(avail) {
        available = avail;
        if (stockHint) {
            if (!tyreHidden || !tyreHidden.value) {
                stockHint.textContent = 'Click field or ▼ to choose tyre type';
                stockHint.className = 'dsp-field-hint';
            } else if (avail < 1) {
                var tip = bestStockType();
                stockHint.textContent = tip
                    ? 'No stock for this type — try ' + tip.type + ' (' + tip.qty.toLocaleString() + ' available)'
                    : 'No stock for this tyre type';
                stockHint.className = 'dsp-field-hint dsp-field-hint--danger';
            } else {
                stockHint.textContent = avail.toLocaleString() + ' tyres available';
                stockHint.className = 'dsp-field-hint dsp-field-hint--ok';
            }
        }
        validateQty();
    }

    function fetchStock(tyreType) {
        if (!tyreType) {
            updateStockDisplay(0);
            return;
        }
        var local = parseInt(stockMap[tyreType], 10) || 0;
        if (!stockApiUrl) {
            updateStockDisplay(local);
            return;
        }
        fetch(stockApiUrl + '&tyre_type=' + encodeURIComponent(tyreType), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.stock) {
                    stockMap = data.stock;
                }
                updateStockDisplay(parseInt(data.available, 10) || 0);
            })
            .catch(function () {
                updateStockDisplay(local);
            });
    }

    function setWeightState(invalid, message) {
        weightInvalid = invalid;
        if (grossInput) {
            grossInput.classList.toggle('is-invalid', invalid);
        }
        if (tareInput) {
            tareInput.classList.toggle('is-invalid', invalid);
        }
        if (weightError) {
            weightError.textContent = message || '';
            weightError.classList.toggle('d-none', !message);
        }
        updateButtons();
    }

    function calcNet() {
        if (!grossInput || !tareInput || !netDisplay) {
            return;
        }
        var g = grossInput.value === '' ? null : parseFloat(grossInput.value);
        var t = tareInput.value === '' ? null : parseFloat(tareInput.value);
        if (g === null && t === null) {
            netDisplay.value = '—';
            setWeightState(false, '');
            return;
        }
        if (g === null || t === null || isNaN(g) || isNaN(t)) {
            netDisplay.value = '—';
            setWeightState(false, '');
            return;
        }
        if (g <= t) {
            netDisplay.value = '—';
            setWeightState(true, 'Gross must be greater than tare');
            return;
        }
        var net = Math.round((g - t) * 100) / 100;
        netDisplay.value = net.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        setWeightState(false, '');
    }

    function refreshPreview() {
        if (!previewApiUrl) {
            return;
        }
        fetch(previewApiUrl + '&refresh=1', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    if (invoicePreview) {
                        invoicePreview.value = data.invoice_no || '';
                    }
                    if (dispatchCodeEl) {
                        dispatchCodeEl.value = data.dispatch_code || '';
                    }
                }
            })
            .catch(function () {});
    }

    function resetFormForNext() {
        comboCustomer.clear();
        comboTyre.clear();
        comboDriver.clear();
        comboVehicle.clear();
        if (tyreHidden) {
            tyreHidden.value = '';
        }
        if (qtyInput) {
            qtyInput.value = '';
        }
        setTransport('', '');
        var dateInput = form.querySelector('input[name="dispatch_date"]');
        var savedDate = dateInput ? dateInput.value : '';
        form.reset();
        if (dateInput && savedDate) {
            dateInput.value = savedDate;
        }
        refreshPreview();
        fetchStock('');
        updateButtons();
    }

    function showSuccess(data) {
        var html = '<strong>' + (data.message || 'Saved') + '</strong>';
        if (successBox) {
            successBox.innerHTML = html;
            successBox.classList.remove('d-none');
        }
        if (invoicePreview && data.invoice_no) {
            invoicePreview.value = data.invoice_no;
        }
        if (dispatchCodeEl && data.dispatch_code) {
            dispatchCodeEl.value = data.dispatch_code;
        }
        if (pdfActions && data.id) {
            var slip = 'index.php?page=dispatch/slip&id=' + data.id;
            pdfActions.innerHTML =
                '<a class="btn btn-outline-primary btn-sm" href="' + slip + '" target="_blank">Download PDF</a>' +
                '<a class="btn btn-primary btn-sm" href="' + slip + '&print=1" target="_blank">Print invoice</a>';
            pdfActions.classList.remove('d-none');
        }
        if (formError) {
            formError.classList.add('d-none');
        }
    }

    function showFieldError(field, msg) {
        if (field === 'qty') {
            setQtyState(true, msg);
            return;
        }
        if (formError) {
            formError.textContent = msg;
            formError.classList.remove('d-none');
        }
    }

    if (qtyInput) {
        qtyInput.addEventListener('input', validateQty);
    }
    if (grossInput) {
        grossInput.addEventListener('input', calcNet);
    }
    if (tareInput) {
        tareInput.addEventListener('input', calcNet);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        validateQty();
        calcNet();
        if (btnSave && btnSave.disabled) {
            return;
        }
        if (formError) {
            formError.classList.add('d-none');
        }
        if (pdfActions) {
            pdfActions.classList.add('d-none');
            pdfActions.innerHTML = '';
        }

        var fd = new FormData(form);

        if (btnSave) {
            btnSave.disabled = true;
            btnSave.textContent = 'Creating…';
        }

        fetch(saveApiUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    showSuccess(data);
                    resetFormForNext();
                    return;
                }
                showFieldError(data.field || 'form', data.error || 'Save failed.');
            })
            .catch(function () {
                showFieldError('form', 'Network error.');
            })
            .finally(function () {
                if (btnSave) {
                    btnSave.textContent = 'Create dispatch';
                }
                updateButtons();
            });
    });

    calcNet();
    updateStockDisplay(0);
    updateButtons();
})();
