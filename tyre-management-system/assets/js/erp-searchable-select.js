/**
 * ERP searchable selects — Tom Select (global) on high-cardinality form fields.
 */
(function (global) {
    'use strict';

    if (typeof TomSelect === 'undefined') {
        return;
    }

    /** Always searchable when matched (even with few options). */
    var FORCE_NAMES = {
        machine_id: true,
        operator_id: true,
        tyre_type: true,
        material_id: true,
        supplier_id: true,
        employee_id: true,
        driver_id: true,
        vehicle_id: true,
        transport_company_id: true,
        customer_id: true,
    };

    /** Searchable when option count >= threshold. */
    var THRESHOLD_NAMES = {
        department_id: 4,
        department: 4,
        dept: 4,
        reg_dept: 4,
    };

    var MIN_OPTIONS_DEFAULT = 6;

    var EXCLUDE_SELECTOR = [
        'select[data-erp-searchable="off"]',
        'select[data-no-search]',
        'select[name="shift"]',
        'select[name="status"]',
        'select[name="salary_status"]',
        'select[name="employee_type"]',
        'select[name="leave_duration_mode"]',
        'select[name="unit"]',
        'select[name="reason"]',
        'select[name="usage_reason"]',
        'select[name="gender"]',
        'select[name="filter"]',
        'select[name="sort"]',
        'select[name="submit_action"]',
        'select.att-status-select',
        'select[name^="rows["]',
        'select[id*="org_category"]',
        'select[id*="org_department"]',
        'select[id*="org_designation"]',
        'select[id^="edit_org_"]',
        '.dsp-combo select',
        '.dsp-entry-page select',
    ].join(', ');

    function dedupeOptions(selectEl) {
        var seen = {};
        var opts = Array.prototype.slice.call(selectEl.options);
        opts.forEach(function (opt) {
            var key = String(opt.value) + '\0' + String(opt.text).trim().toLowerCase();
            if (seen[key]) {
                opt.remove();
            } else {
                seen[key] = true;
            }
        });
    }

    function placeholderFromSelect(el) {
        var empty = el.querySelector('option[value=""]');
        if (empty && empty.textContent.trim() !== '') {
            return empty.textContent.trim();
        }
        var ph = el.getAttribute('data-placeholder');
        if (ph) {
            return ph;
        }
        return 'Search or select…';
    }

    function isOrgCascadeSelect(el) {
        var id = el.id || '';
        if (id.indexOf('org_') !== -1) {
            return true;
        }
        if (id.indexOf('create_org_') === 0 || id.indexOf('edit_org_') === 0) {
            return true;
        }
        return el.getAttribute('data-dept-cascade') === '1';
    }

    function shouldEnhance(el) {
        if (!el || el.tagName !== 'SELECT' || el.multiple) {
            return false;
        }
        if (el.tomselect || el.classList.contains('tomselected')) {
            return false;
        }
        if (el.closest('.dsp-combo') || el.closest('.dsp-entry-page')) {
            return false;
        }
        if (isOrgCascadeSelect(el)) {
            return false;
        }
        if (el.matches(EXCLUDE_SELECTOR)) {
            return false;
        }
        if (el.closest('.acc-exp-filters')) {
            return false;
        }
        if (el.getAttribute('data-erp-searchable') === 'on' || el.classList.contains('erp-select-search')) {
            return true;
        }
        if (el.getAttribute('data-erp-searchable') === 'off') {
            return false;
        }

        var name = el.getAttribute('name') || '';
        if (FORCE_NAMES[name]) {
            return el.options.length >= 1;
        }
        if (Object.prototype.hasOwnProperty.call(THRESHOLD_NAMES, name)) {
            return el.options.length >= THRESHOLD_NAMES[name];
        }
        if (el.options.length < MIN_OPTIONS_DEFAULT) {
            return false;
        }
        return el.classList.contains('form-select');
    }

    function buildConfig(el) {
        var isSm = el.classList.contains('form-select-sm');
        return {
            plugins: ['dropdown_input'],
            create: false,
            persist: false,
            maxOptions: 1000,
            maxItems: el.multiple ? null : 1,
            allowEmptyOption: !el.required,
            placeholder: placeholderFromSelect(el),
            searchField: ['text'],
            sortField: { field: 'text', direction: 'asc' },
            hideSelected: false,
            closeAfterSelect: true,
            dropdownParent: 'body',
            controlInput: null,
            render: {
                option: function (data, escape) {
                    var sub = data.$option && data.$option.dataset ? data.$option.dataset.sub : '';
                    if (sub) {
                        return '<div class="erp-ts-option">'
                            + '<span class="erp-ts-option__label">' + escape(data.text) + '</span>'
                            + '<span class="erp-ts-option__sub">' + escape(sub) + '</span>'
                            + '</div>';
                    }
                    return '<div class="erp-ts-option"><span class="erp-ts-option__label">' + escape(data.text) + '</span></div>';
                },
            },
            onInitialize: function () {
                if (isSm) {
                    this.wrapper.classList.add('erp-ts--sm');
                }
            },
        };
    }

    function initSelect(el) {
        if (!shouldEnhance(el)) {
            return null;
        }
        dedupeOptions(el);
        if (el.options.length === 0) {
            return null;
        }
        try {
            return new TomSelect(el, buildConfig(el));
        } catch (err) {
            if (typeof console !== 'undefined' && console.warn) {
                console.warn('ErpSearchableSelect:', err);
            }
            return null;
        }
    }

    function scan(root) {
        root = root || document;
        var nodes = root.querySelectorAll('select.form-select, select.erp-select-search');
        nodes.forEach(function (el) {
            initSelect(el);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        scan(document);
    });

    document.addEventListener('shown.bs.modal', function (ev) {
        var modal = ev.target;
        if (modal && modal.classList && modal.classList.contains('modal')) {
            scan(modal);
        }
    });

    global.ErpSearchableSelect = {
        init: initSelect,
        scan: scan,
    };
})(window);
