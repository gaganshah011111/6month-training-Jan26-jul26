/**
 * Live salary structure preview — always loads latest Payroll Settings from the API.
 */
(function (global) {
    'use strict';

    var settingsPromise = null;
    var settingsUrl = '';

    function formatInrInt(n) {
        return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(Math.round(n));
    }

    function formatInr2(n) {
        return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 }).format(n);
    }

    function fetchSettings(force) {
        if (!settingsUrl) {
            return Promise.reject(new Error('Payroll settings URL not configured'));
        }
        if (!force && settingsPromise) {
            return settingsPromise;
        }
        settingsPromise = fetch(settingsUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('Failed to load payroll settings');
                }
                return r.json();
            })
            .then(function (data) {
                if (!data || !data.ok || !data.settings) {
                    throw new Error((data && data.error) || 'Invalid payroll settings response');
                }
                return data.settings;
            })
            .catch(function (err) {
                settingsPromise = null;
                throw err;
            });
        return settingsPromise;
    }

    function setPv(root, key, value, asInt) {
        var el = root.querySelector('[data-pv="' + key + '"]');
        if (el) {
            el.textContent = '₹' + (asInt ? formatInrInt(value) : formatInr2(value));
        }
    }

    function setInput(root, name, value) {
        var el = root.querySelector('[name="' + name + '"]');
        if (el && el.type !== 'hidden') {
            el.value = String(roundDisplay(value));
        }
    }

    function roundDisplay(n) {
        return Math.round(n * 100) / 100;
    }

    function recalcRoot(root) {
        var autoEl = root.querySelector('[data-payroll-auto]');
        var grossEl = root.querySelector('[data-payroll-gross]');
        var metroEl = root.querySelector('[data-payroll-metro]');
        var typeEl = root.querySelector('[data-payroll-emp-type]');
        if (!typeEl && root.closest) {
            var formRoot = root.closest('form');
            if (formRoot) {
                typeEl = formRoot.querySelector('[data-payroll-emp-type]');
            }
        }
        var ctcEl = root.querySelector('[data-payroll-ctc]');

        if (!autoEl || !grossEl) {
            return Promise.resolve();
        }

        var isAuto = autoEl.checked || autoEl.type === 'checkbox' && autoEl.checked;

        if (!isAuto) {
            var b = parseFloat(root.querySelector('[name="basic_salary"]')?.value) || 0;
            var da = parseFloat(root.querySelector('[name="dearness_allowance"]')?.value) || 0;
            var hp = parseFloat(root.querySelector('[name="hra_percentage"]')?.value) || 0;
            var hraField = root.querySelector('[name="hra_amount"]');
            var hraRaw = hraField ? hraField.value.trim() : '';
            var hraAmt = hraRaw === '' ? (b * hp / 100) : (parseFloat(hraRaw) || 0);
            var med = parseFloat(root.querySelector('[name="medical_allowance"]')?.value) || 0;
            var trv = parseFloat(root.querySelector('[name="travel_allowance"]')?.value) || 0;
            var spec = parseFloat(root.querySelector('[name="special_allowance"]')?.value) || 0;
            var oth = parseFloat(root.querySelector('[name="other_allowances"]')?.value) || 0;
            var gMan = b + da + hraAmt + med + trv + spec + oth;
            if (ctcEl) {
                ctcEl.textContent = '₹' + formatInrInt(gMan);
            }
            return Promise.resolve();
        }

        return fetchSettings(true).then(function (s) {
            var G = parseFloat(grossEl.value) || 0;
            var metro = metroEl ? metroEl.checked : false;
            var worker = typeEl ? typeEl.value === 'Worker' : false;
            var split = global.IndianPayrollSplit.split(G, metro, worker, s);

            setPv(root, 'basic', split.basic, false);
            setPv(root, 'da', split.da, false);
            setPv(root, 'hra', split.hra, false);
            setPv(root, 'medical', split.medical, false);
            setPv(root, 'travel', split.travel, false);
            setPv(root, 'special', split.special, false);
            setPv(root, 'dw', split.daily_wage, false);
            setPv(root, 'hw', split.ot_rate, false);

            if (ctcEl) {
                ctcEl.textContent = '₹' + formatInrInt(G);
            }

            setInput(root, 'basic_salary', split.basic);
            setInput(root, 'dearness_allowance', split.da);
            setInput(root, 'hra_percentage', split.hra_pct);
            setInput(root, 'hra_amount', split.hra);
            setInput(root, 'medical_allowance', split.medical);
            setInput(root, 'travel_allowance', split.travel);
            setInput(root, 'special_allowance', split.special);
            setInput(root, 'gratuity_monthly', split.gratuity_monthly);
            setInput(root, 'daily_wage', split.daily_wage);
            setInput(root, 'hourly_rate', split.hourly_rate);
            setInput(root, 'overtime_rate', split.ot_rate);

            var pfEl = root.querySelector('[name="pf_percentage"]');
            if (pfEl) {
                pfEl.value = String(s.pf_employee_pct);
            }
            var esiEl = root.querySelector('[name="esi_percentage"]');
            if (esiEl) {
                esiEl.value = String(s.esi_employee_pct);
            }
        });
    }

    var debounceTimers = new WeakMap();

    function scheduleRecalc(root, forceSettings) {
        if (forceSettings) {
            settingsPromise = null;
        }
        var prev = debounceTimers.get(root);
        if (prev) {
            clearTimeout(prev);
        }
        debounceTimers.set(root, setTimeout(function () {
            recalcRoot(root).catch(function () { /* keep last preview on transient errors */ });
        }, forceSettings ? 0 : 120));
    }

    function bindRoot(root) {
        if (!root || root.getAttribute('data-payroll-bound') === '1') {
            return;
        }
        root.setAttribute('data-payroll-bound', '1');

        var inputs = root.querySelectorAll(
            '[data-payroll-gross], [data-payroll-metro], [data-payroll-auto], [data-payroll-emp-type], ' +
            '[name="basic_salary"], [name="dearness_allowance"], [name="hra_percentage"], [name="hra_amount"], ' +
            '[name="medical_allowance"], [name="travel_allowance"], [name="special_allowance"], [name="other_allowances"]'
        );
        inputs.forEach(function (node) {
            node.addEventListener('input', function () { scheduleRecalc(root, false); });
            node.addEventListener('change', function () { scheduleRecalc(root, false); });
        });

        var modalEl = root.closest ? root.closest('.modal') : null;
        if (modalEl) {
            modalEl.addEventListener('shown.bs.modal', function () {
                settingsPromise = null;
                scheduleRecalc(root, true);
            });
        }

        scheduleRecalc(root, true);
    }

    function init(opts) {
        opts = opts || {};
        settingsUrl = opts.settingsUrl || settingsUrl;
        if (!settingsUrl) {
            return;
        }
        document.querySelectorAll('[data-payroll-preview]').forEach(bindRoot);
    }

    function refreshAll() {
        settingsPromise = null;
        document.querySelectorAll('[data-payroll-preview]').forEach(function (root) {
            scheduleRecalc(root, true);
        });
    }

    global.PayrollPreview = {
        init: init,
        refreshAll: refreshAll,
        bindRoot: bindRoot,
        recalcRoot: recalcRoot,
    };
})(typeof window !== 'undefined' ? window : this);
