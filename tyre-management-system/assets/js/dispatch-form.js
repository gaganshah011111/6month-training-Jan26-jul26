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

    DspCombo.prototype.setOptions = function (options, silent) {
        this.options = options || [];
        this.selected = null;
        this.input.value = '';
        this.close();
        if (!silent && this.cfg.onChange) {
            this.cfg.onChange(null);
        }
    };

    DspCombo.prototype.setDisabled = function (disabled) {
        this.disabled = !!disabled;
        this.input.disabled = this.disabled;
        this.btn.disabled = this.disabled;
        this.root.classList.toggle('dsp-combo--disabled', this.disabled);
        if (this.disabled) {
            this.close();
        }
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

    var queueOnlyMode = !!document.getElementById('dsp-order-locked');
    var legacyComboMode = !!document.getElementById('dsp-combo-customer');

    var logistics = parseJson(form.getAttribute('data-logistics'), { drivers: [], vehicles: [] });
    var saveApiUrl = form.getAttribute('data-save-api') || '';
    var previewApiUrl = form.getAttribute('data-preview-api') || '';
    var prefillApiUrl = form.getAttribute('data-prefill-api') || '';

    var qtyInput = document.getElementById('dsp-qty');
    var stockHint = document.getElementById('dsp-stock-hint');
    var qtyError = document.getElementById('dsp-qty-error');
    var tyreHidden = document.getElementById('dsp-tyre-type');
    var customerIdInput = document.getElementById('dsp-customer-id');
    var customerNameInput = document.getElementById('dsp-customer-name');
    var salesCustomerInput = document.getElementById('dsp-sales-customer-id');
    var soIdInput = document.getElementById('dsp-sales-order-id');
    var soItemInput = document.getElementById('dsp-sales-order-item-id');
    var orderDetailsSection = document.getElementById('dsp-order-details-section');
    var orderLocked = document.getElementById('dsp-order-locked');
    var submitHint = document.getElementById('dsp-submit-hint');
    var shipmentFields = document.getElementById('dsp-shipment-fields');
    var dispatchWorkspace = document.getElementById('dsp-dispatch-workspace');
    var soNumberDisplay = document.getElementById('dsp-so-number');
    var customerDisplay = document.getElementById('dsp-customer-display');
    var tyreDisplay = document.getElementById('dsp-tyre-display');
    var orderedQtyDisplay = document.getElementById('dsp-ordered-qty');
    var pendingQtyDisplay = document.getElementById('dsp-pending-qty');
    var availableQtyDisplay = document.getElementById('dsp-available-qty');
    var soLinkBanner = document.getElementById('dsp-so-link-banner');
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
    var orderMaxQty = 0;
    var orderPendingQty = 0;
    var orderLinked = false;
    var weightInvalid = false;
    var syncing = false;

    function formatQty(n) {
        var x = parseInt(n, 10) || 0;
        return x.toLocaleString();
    }

    function getSoIds() {
        return {
            orderId: parseInt(soIdInput && soIdInput.value, 10) || 0,
            itemId: parseInt(soItemInput && soItemInput.value, 10) || 0,
        };
    }

    function isOrderLinked() {
        var ids = getSoIds();
        if (queueOnlyMode) {
            return orderLinked && ids.orderId > 0 && ids.itemId > 0;
        }
        if (document.getElementById('dsp-sales-order-line-select')) {
            return ids.orderId > 0 && ids.itemId > 0;
        }
        return ids.orderId > 0 && ids.itemId > 0;
    }

    function setOrderLinkedUI(linked) {
        orderLinked = linked;
        if (!queueOnlyMode) {
            updateButtons();
            return;
        }
        if (orderDetailsSection) {
            orderDetailsSection.classList.toggle('d-none', !linked);
        }
        if (submitHint) {
            submitHint.classList.toggle('d-none', linked);
        }
        if (shipmentFields) {
            shipmentFields.classList.toggle('is-locked', !linked);
            shipmentFields.classList.toggle('is-active', linked);
        }
        if (dispatchWorkspace) {
            dispatchWorkspace.classList.toggle('is-active', linked);
        }
        if (qtyInput) {
            qtyInput.disabled = !linked;
        }
        if (submitHint) {
            if (linked) {
                submitHint.textContent = '';
                submitHint.classList.add('d-none');
            } else {
                submitHint.classList.remove('d-none');
                submitHint.innerHTML = '<i class="bi bi-arrow-up-circle me-1" aria-hidden="true"></i> Select CRM sales order first';
            }
        }
        updateButtons();
    }

    function clearOrderLink() {
        orderLinked = false;
        orderMaxQty = 0;
        orderPendingQty = 0;
        available = 0;
        if (soIdInput) {
            soIdInput.value = '';
        }
        if (soItemInput) {
            soItemInput.value = '';
        }
        if (salesCustomerInput) {
            salesCustomerInput.value = '';
        }
        if (customerIdInput) {
            customerIdInput.value = '';
        }
        if (customerNameInput) {
            customerNameInput.value = '';
        }
        if (tyreHidden) {
            tyreHidden.value = '';
        }
        if (soNumberDisplay) {
            soNumberDisplay.value = '';
        }
        if (customerDisplay) {
            customerDisplay.value = '';
        }
        if (tyreDisplay) {
            tyreDisplay.value = '';
        }
        if (orderedQtyDisplay) {
            orderedQtyDisplay.value = '';
        }
        if (pendingQtyDisplay) {
            pendingQtyDisplay.value = '';
        }
        if (availableQtyDisplay) {
            availableQtyDisplay.value = '';
        }
        if (qtyInput) {
            qtyInput.value = '';
            qtyInput.removeAttribute('max');
        }
        if (soLinkBanner) {
            soLinkBanner.classList.add('d-none');
            soLinkBanner.innerHTML = '';
        }
        if (stockHint) {
            stockHint.textContent = '';
        }
        setOrderLinkedUI(false);
        document.dispatchEvent(new CustomEvent('dsp:order-cleared'));
    }

    document.addEventListener('dsp:order-clear', function () {
        clearOrderLink();
    });

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

    var allVehicleRecords = logistics.vehicles || [];

    function vehicleOptionsToCombo(records) {
        return (records || []).map(function (v) {
            return {
                value: String(v.id),
                label: v.number,
                sub: v.driver_name || v.type || '',
            };
        });
    }

    function vehiclesForDriver(driverId) {
        driverId = parseInt(driverId, 10) || 0;
        if (driverId < 1) {
            return [];
        }
        var d = driverById(driverId);
        var allowed = {};
        if (d && d.vehicle_ids) {
            for (var i = 0; i < d.vehicle_ids.length; i++) {
                allowed[d.vehicle_ids[i]] = true;
            }
        }
        if (d && d.vehicle_id) {
            allowed[d.vehicle_id] = true;
        }
        return allVehicleRecords.filter(function (v) {
            var vid = parseInt(v.id, 10);
            if (allowed[vid]) {
                return true;
            }
            return parseInt(v.driver_id, 10) === driverId;
        });
    }

    function applyVehicleOptionsForDriver(driverId, preferredVehicleId) {
        if (!comboVehicle) {
            return;
        }
        driverId = parseInt(driverId, 10) || 0;
        var records = vehiclesForDriver(driverId);
        var comboOpts = vehicleOptionsToCombo(records);
        syncing = true;
        comboVehicle.setOptions(comboOpts, true);
        var enable = driverId > 0 && comboOpts.length > 0;
        comboVehicle.setDisabled(!enable);
        if (vehicleIdInput) {
            vehicleIdInput.value = '';
        }
        var pickId = preferredVehicleId || 0;
        if (!pickId && records.length === 1) {
            pickId = records[0].id;
        }
        if (pickId && enable) {
            comboVehicle.setValue(String(pickId), true);
            if (vehicleIdInput) {
                vehicleIdInput.value = String(pickId);
            }
            var v = vehicleById(pickId);
            if (v) {
                setTransport(v.transport_id, v.transport);
            }
        } else if (driverId > 0) {
            var dr = driverById(driverId);
            if (dr) {
                setTransport(dr.transport_id, dr.transport);
            }
        }
        syncing = false;
        updateButtons();
    }

    function syncFromDriver() {
        if (syncing) {
            return;
        }
        var id = parseInt(driverIdInput && driverIdInput.value, 10);
        var d = driverById(id);
        if (!d) {
            applyVehicleOptionsForDriver(0, 0);
            setTransport('', '');
            updateButtons();
            return;
        }
        var preferred = d.vehicle_id || (d.vehicle_ids && d.vehicle_ids.length ? d.vehicle_ids[0] : 0);
        applyVehicleOptionsForDriver(id, preferred);
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
        var currentDriver = parseInt(driverIdInput && driverIdInput.value, 10) || 0;
        if (currentDriver > 0 && v.driver_id && v.driver_id !== currentDriver) {
            if (formError) {
                formError.textContent = 'This vehicle is not assigned to the selected driver.';
                formError.classList.remove('d-none');
            }
            if (vehicleIdInput) {
                vehicleIdInput.value = '';
            }
            comboVehicle.clear();
            updateButtons();
            return;
        }
        syncing = true;
        setTransport(v.transport_id, v.transport);
        syncing = false;
        updateButtons();
    }

    var comboCustomer = null;
    var comboTyre = null;

    if (legacyComboMode) {
        comboCustomer = new DspCombo(
            document.getElementById('dsp-combo-customer'),
            parseJson(form.getAttribute('data-customers'), []),
            {
                onChange: function (opt) {
                    if (!opt) {
                        if (customerIdInput) {
                            customerIdInput.value = '';
                        }
                        if (customerNameInput) {
                            customerNameInput.value = '';
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

        comboTyre = new DspCombo(
            document.getElementById('dsp-combo-tyre'),
            parseJson(form.getAttribute('data-tyres'), []),
            {
                autoPickOnBlur: true,
                onChange: function (opt) {
                    if (tyreHidden) {
                        tyreHidden.value = opt ? opt.value : '';
                    }
                    if (opt && !queueOnlyMode) {
                        orderMaxQty = parseInt(opt.stock, 10) || 0;
                        available = orderMaxQty;
                    }
                    updateButtons();
                },
            }
        );
    }

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
                    applyVehicleOptionsForDriver(0, 0);
                    setTransport('', '');
                    updateButtons();
                }
            },
        }
    );

    var comboVehicleEl = document.getElementById('dsp-combo-vehicle');
    var comboVehicle = comboVehicleEl ? new DspCombo(
        comboVehicleEl,
        [],
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
    ) : null;

    function updateButtons() {
        var qty = parseInt(qtyInput && qtyInput.value, 10) || 0;
        var driver = driverIdInput ? driverIdInput.value : '';
        var vehicle = vehicleIdInput ? vehicleIdInput.value : '';
        var transport = transportIdInput ? transportIdInput.value : '';
        var linked = isOrderLinked();
        var maxQty = linked ? (orderMaxQty || available) : 0;
        var tyre = tyreHidden ? tyreHidden.value : '';
        var customer = (customerNameInput && customerNameInput.value.trim()) || '';
        var bad;

        if (queueOnlyMode) {
            bad = !linked || qty < 1 || qty > maxQty || maxQty < 1
                || !driver || !vehicle || !transport || weightInvalid;
        } else {
            bad = !linked || !tyre || qty < 1
                || (maxQty > 0 && qty > maxQty)
                || !driver || !vehicle || !transport || !customer || weightInvalid;
        }
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
        if (!qtyInput) {
            return;
        }
        if (!isOrderLinked()) {
            setQtyState(false, '');
            return;
        }
        if (!queueOnlyMode && orderMaxQty < 1) {
            setQtyState(false, '');
            return;
        }
        var qty = parseInt(qtyInput.value, 10);
        if (qtyInput.value === '' || isNaN(qty)) {
            setQtyState(false, '');
            return;
        }
        if (qty < 1) {
            setQtyState(true, 'Enter a valid quantity.');
            return;
        }
        if (qty > orderMaxQty) {
            var msg = orderMaxQty < orderPendingQty
                ? 'Max ' + formatQty(orderMaxQty) + ' (stock limited; ' + formatQty(orderPendingQty) + ' pending)'
                : 'Max ' + formatQty(orderMaxQty) + ' for this line';
            setQtyState(true, msg);
            return;
        }
        var okMsg = orderMaxQty < orderPendingQty
            ? 'Partial dispatch: ' + formatQty(qty) + ' of ' + formatQty(orderPendingQty) + ' pending'
            : 'Dispatching ' + formatQty(qty) + ' tyre(s)';
        setQtyState(false, okMsg, true);
    }

    function updateOrderStockHint(data) {
        if (!stockHint || !data) {
            return;
        }
        var pending = parseInt(data.pending_qty, 10) || 0;
        var avail = parseInt(data.available_qty, 10) || 0;
        var maxQ = parseInt(data.max_qty, 10) || 0;
        if (maxQ < pending) {
            stockHint.textContent = 'FG stock: ' + formatQty(avail) + ' — partial dispatch allowed up to ' + formatQty(maxQ);
            stockHint.className = 'dsp-entry-hint dsp-field-hint--ok';
        } else {
            stockHint.textContent = formatQty(avail) + ' in stock · ' + formatQty(pending) + ' pending on order';
            stockHint.className = 'dsp-entry-hint';
        }
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
        if (comboCustomer) {
            comboCustomer.clear();
        }
        if (comboTyre) {
            comboTyre.clear();
        }
        comboDriver.clear();
        if (comboVehicle) {
            applyVehicleOptionsForDriver(0, 0);
        }
        document.querySelectorAll('.dsp-crm-pending-row.is-selected, .dsp-queue-row.is-selected, .dsp-order-picker-row.is-selected').forEach(function (r) {
            r.classList.remove('is-selected');
        });
        if (queueOnlyMode) {
            clearOrderLink();
        } else if (soIdInput) {
            soIdInput.value = '';
        }
        if (!queueOnlyMode && soItemInput) {
            soItemInput.value = '';
        }
        setTransport('', '');
        var dateInput = form.querySelector('input[name="dispatch_date"]');
        var savedDate = dateInput ? dateInput.value : '';
        form.reset();
        if (dateInput && savedDate) {
            dateInput.value = savedDate;
        }
        refreshPreview();
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
        if (!isOrderLinked()) {
            showFieldError('form', queueOnlyMode
                ? 'Select CRM sales order before dispatch.'
                : 'Select a sales order and order line before dispatching.');
            return;
        }
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
                    btnSave.textContent = 'Create Dispatch';
                }
                updateButtons();
            });
    });

    function showSoLinkBanner(data) {
        if (!soLinkBanner) {
            return;
        }
        soLinkBanner.classList.remove('d-none');
        var statusLabel = data.stock_status || '';
        if (statusLabel === 'READY') {
            statusLabel = 'READY TO SHIP';
        } else if (statusLabel === 'PRODUCTION REQUIRED') {
            statusLabel = 'WAITING STOCK';
        }
        soLinkBanner.innerHTML =
            '<i class="bi bi-link-45deg me-1"></i> <strong>Sales Order Linked:</strong> ' +
            escapeHtml(data.so_number || '') +
            ' · ' + escapeHtml(data.tyre_type || '') +
            ' · ' + escapeHtml(statusLabel) +
            (data.qty ? ' · Qty ' + escapeHtml(String(data.qty)) : '');
    }

    function applyPrefillData(data, row) {
        if (!data || !soIdInput || !soItemInput) {
            return;
        }
        soIdInput.value = String(data.sales_order_id || '');
        soItemInput.value = String(data.sales_order_item_id || '');
        orderMaxQty = parseInt(data.max_qty, 10) || 0;
        orderPendingQty = parseInt(data.pending_qty, 10) || 0;
        available = orderMaxQty;

        if (salesCustomerInput) {
            salesCustomerInput.value = String(data.sales_customer_id || '');
        }
        if (customerNameInput) {
            customerNameInput.value = data.customer_name || '';
        }
        if (customerIdInput) {
            customerIdInput.value = String(data.sales_customer_id || '');
        }
        if (tyreHidden) {
            tyreHidden.value = data.tyre_type || '';
        }
        if (soNumberDisplay) {
            soNumberDisplay.value = data.so_number || '';
        }
        if (customerDisplay) {
            customerDisplay.value = data.customer_name || '';
        }
        if (tyreDisplay) {
            tyreDisplay.value = data.tyre_type || '';
        }
        if (orderedQtyDisplay) {
            orderedQtyDisplay.value = formatQty(data.ordered_qty);
        }
        if (pendingQtyDisplay) {
            pendingQtyDisplay.value = formatQty(data.pending_qty);
        }
        if (availableQtyDisplay) {
            availableQtyDisplay.value = formatQty(data.available_qty);
        }

        document.querySelectorAll('.dsp-queue-row.is-selected, .dsp-crm-pending-row.is-selected').forEach(function (r) {
            r.classList.remove('is-selected');
        });
        if (row) {
            row.classList.add('is-selected');
        }

        if (qtyInput) {
            qtyInput.value = String(data.qty || '');
            if (data.max_qty) {
                qtyInput.setAttribute('max', String(data.max_qty));
            }
            qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        if (queueOnlyMode) {
            setOrderLinkedUI(true);
            updateOrderStockHint(data);
        } else if (comboCustomer && comboTyre) {
            var cid = String(data.sales_customer_id || '');
            if (cid) {
                comboCustomer.setValue(cid, true);
            } else if (data.customer_name) {
                comboCustomer.input.value = data.customer_name;
                comboCustomer.selected = { value: '', label: data.customer_name };
            }
            if (customerNameInput) {
                customerNameInput.value = data.customer_name || '';
            }
            if (data.tyre_type) {
                comboTyre.setValue(data.tyre_type, true);
                if (!comboTyre.selected) {
                    comboTyre.input.value = data.tyre_type;
                }
            }
            if (salesCustomerInput) {
                salesCustomerInput.value = String(data.sales_customer_id || '');
            }
        }

        showSoLinkBanner(data);
        validateQty();
        updateButtons();

        document.dispatchEvent(new CustomEvent('dsp:order-selected', {
            detail: {
                so_number: data.so_number,
                customer_name: data.customer_name,
                tyre_type: data.tyre_type,
            },
        }));

        if (orderDetailsSection) {
            orderDetailsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function highlightPickerRow(orderId, itemId) {
        document.querySelectorAll('.dsp-order-picker-row.is-selected').forEach(function (r) {
            r.classList.remove('is-selected');
        });
        if (orderId < 1 || itemId < 1) {
            return;
        }
        var matched = null;
        document.querySelectorAll('.dsp-order-picker-row').forEach(function (r) {
            var oid = parseInt(r.getAttribute('data-order-id'), 10) || 0;
            var iid = parseInt(r.getAttribute('data-item-id'), 10) || 0;
            if (oid === orderId && iid === itemId) {
                r.classList.add('is-selected');
                matched = r;
            }
        });
        if (matched) {
            var wrap = document.getElementById('dsp-order-picker-body');
            if (wrap && typeof matched.scrollIntoView === 'function') {
                matched.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }

    function loadCrmPrefill(orderId, itemId, row) {
        if (!prefillApiUrl || orderId < 1 || itemId < 1) {
            return;
        }
        if (formError) {
            formError.classList.add('d-none');
        }
        fetch(prefillApiUrl + '&order_id=' + encodeURIComponent(orderId) + '&item_id=' + encodeURIComponent(itemId), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (!data.ok) {
                    showFieldError('form', data.error || 'Unable to load sales order.');
                    return;
                }
                applyPrefillData(data, row || null);
                highlightPickerRow(
                    parseInt(data.sales_order_id, 10) || 0,
                    parseInt(data.sales_order_item_id, 10) || 0
                );
            })
            .catch(function () {
                showFieldError('form', 'Unable to load sales order data.');
            });
    }

    function applyCrmPendingRow(row) {
        if (!row) {
            return;
        }
        var orderId = parseInt(row.getAttribute('data-order-id'), 10) || 0;
        var itemId = parseInt(row.getAttribute('data-item-id'), 10) || 0;
        var ready = parseInt(row.getAttribute('data-ready-qty'), 10) || 0;
        if (ready < 1) {
            showFieldError('form', 'No stock available for this line yet. Awaiting production.');
            return;
        }
        loadCrmPrefill(orderId, itemId, row);
    }

    if (queueOnlyMode) {
        document.querySelectorAll('.dsp-order-picker-row, .dsp-queue-row, .dsp-crm-pending-row').forEach(function (row) {
            row.addEventListener('click', function () {
                applyCrmPendingRow(row);
            });
            row.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    applyCrmPendingRow(row);
                }
            });
        });
    }

    var salesLineSelect = document.getElementById('dsp-sales-order-line-select');
    if (!queueOnlyMode && salesLineSelect && prefillApiUrl) {
        salesLineSelect.addEventListener('change', function () {
            var orderId = parseInt(soIdInput && soIdInput.value, 10) || 0;
            var itemId = parseInt(salesLineSelect.value, 10) || 0;
            if (soItemInput) {
                soItemInput.value = itemId > 0 ? String(itemId) : '';
            }
            if (orderId > 0 && itemId > 0) {
                loadCrmPrefill(orderId, itemId, null);
            } else {
                orderMaxQty = 0;
                updateButtons();
            }
        });
    }

    var initOrderId = parseInt(form.getAttribute('data-prefill-order'), 10) || 0;
    var initItemId = parseInt(form.getAttribute('data-prefill-item'), 10) || 0;
    if (initOrderId > 0 && initItemId > 0) {
        loadCrmPrefill(initOrderId, initItemId, null);
    }

    calcNet();
    if (comboVehicle) {
        applyVehicleOptionsForDriver(0, 0);
    }

    if (queueOnlyMode) {
        setOrderLinkedUI(false);
    } else {
        updateButtons();
    }
})();
