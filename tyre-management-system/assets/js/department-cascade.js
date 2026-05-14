(function (global) {
    'use strict';

    function apiUrl(action, extra) {
        var base = 'index.php?page=api/departments&action=' + encodeURIComponent(action);
        if (extra) {
            for (var k in extra) {
                if (Object.prototype.hasOwnProperty.call(extra, k) && extra[k] !== null && extra[k] !== undefined && extra[k] !== '') {
                    base += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(String(extra[k]));
                }
            }
        }
        return base;
    }

    function fetchJson(url) {
        return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (res) {
            if (!res.ok) {
                throw new Error('Request failed');
            }
            return res.json();
        });
    }

    function toTomOptions(items) {
        return (items || []).map(function (row) {
            return { value: String(row.id), text: row.label };
        });
    }

    /**
     * @param {object} opts
     * @param {string} opts.categorySelectId
     * @param {string} opts.departmentSelectId
     * @param {string} opts.designationSelectId
     * @param {number|string} [opts.initialCategoryId]
     * @param {number|string} [opts.initialDepartmentId]
     * @param {number|string} [opts.initialDesignationId]
     */
    function init(opts) {
        var catEl = document.getElementById(opts.categorySelectId);
        var depEl = document.getElementById(opts.departmentSelectId);
        var desEl = document.getElementById(opts.designationSelectId);
        if (!catEl || !depEl || !desEl) {
            return null;
        }

        if (catEl.tomselect) {
            catEl.tomselect.destroy();
        }
        if (depEl.tomselect) {
            depEl.tomselect.destroy();
        }
        if (desEl.tomselect) {
            desEl.tomselect.destroy();
        }

        var tsDep = new TomSelect(depEl, {
            valueField: 'value',
            labelField: 'text',
            searchField: ['text'],
            maxOptions: 500,
            placeholder: 'Select department…',
            allowEmptyOption: true,
        });
        var tsDes = new TomSelect(desEl, {
            valueField: 'value',
            labelField: 'text',
            searchField: ['text'],
            maxOptions: 500,
            placeholder: 'Select designation (optional)…',
            allowEmptyOption: true,
        });
        var tsCat = new TomSelect(catEl, {
            valueField: 'value',
            labelField: 'text',
            searchField: ['text'],
            maxOptions: 200,
            placeholder: 'Select category…',
            allowEmptyOption: true,
        });

        function loadDepartments(categoryId, preselectDeptId) {
            tsDep.clearOptions();
            tsDep.clear(true);
            tsDes.clearOptions();
            tsDes.clear(true);
            if (!categoryId) {
                tsDep.disable();
                tsDes.disable();
                return Promise.resolve();
            }
            tsDep.enable();
            return fetchJson(apiUrl('departments', { category_id: categoryId }))
                .then(function (data) {
                    if (!data.ok) {
                        throw new Error(data.error || 'Load failed');
                    }
                    tsDep.addOption(toTomOptions(data.items));
                    tsDep.refreshOptions(false);
                    if (preselectDeptId) {
                        tsDep.setValue(String(preselectDeptId), true);
                    }
                })
                .catch(function () {
                    tsDep.disable();
                });
        }

        function loadDesignations(departmentId, preselectDesigId) {
            tsDes.clearOptions();
            tsDes.clear(true);
            if (!departmentId) {
                tsDes.disable();
                return Promise.resolve();
            }
            tsDes.enable();
            return fetchJson(apiUrl('designations', { department_id: departmentId }))
                .then(function (data) {
                    if (!data.ok) {
                        throw new Error(data.error || 'Load failed');
                    }
                    tsDes.addOption({ value: '', text: '— None —' });
                    tsDes.addOption(toTomOptions(data.items));
                    tsDes.refreshOptions(false);
                    if (preselectDesigId) {
                        tsDes.setValue(String(preselectDesigId), true);
                    } else {
                        tsDes.setValue('', true);
                    }
                })
                .catch(function () {
                    tsDes.disable();
                });
        }

        tsCat.on('change', function () {
            var val = tsCat.getValue();
            loadDepartments(val, null).then(function () {
                return loadDesignations('', null);
            });
        });

        tsDep.on('change', function () {
            var val = tsDep.getValue();
            loadDesignations(val, null);
        });

        tsDep.disable();
        tsDes.disable();

        var ic = opts.initialCategoryId ? String(opts.initialCategoryId) : '';
        var idd = opts.initialDepartmentId ? String(opts.initialDepartmentId) : '';
        var ids = opts.initialDesignationId ? String(opts.initialDesignationId) : '';

        fetchJson(apiUrl('categories'))
            .then(function (data) {
                if (!data.ok) {
                    throw new Error(data.error || 'Load failed');
                }
                tsCat.addOption(toTomOptions(data.items));
                tsCat.refreshOptions(false);
                tsCat.enable();
                if (ic) {
                    tsCat.setValue(ic, true);
                    return loadDepartments(ic, idd || null).then(function () {
                        if (idd) {
                            return loadDesignations(idd, ids || null);
                        }
                        return null;
                    });
                }
                return null;
            })
            .catch(function () {});

        return { tsCat: tsCat, tsDep: tsDep, tsDes: tsDes };
    }

    function bindModal(modalEl, fieldPrefix) {
        if (!modalEl || modalEl.getAttribute('data-dept-cascade-bound') === '1') {
            return;
        }
        modalEl.setAttribute('data-dept-cascade-bound', '1');
        modalEl.addEventListener('shown.bs.modal', function () {
            var ic = modalEl.getAttribute('data-initial-category-id') || '';
            var idd = modalEl.getAttribute('data-initial-department-id') || '';
            var ids = modalEl.getAttribute('data-initial-designation-id') || '';
            init({
                categorySelectId: fieldPrefix + 'category_id',
                departmentSelectId: fieldPrefix + 'department_id',
                designationSelectId: fieldPrefix + 'designation_id',
                initialCategoryId: ic,
                initialDepartmentId: idd,
                initialDesignationId: ids,
            });
        });
    }

    global.DepartmentCascade = { init: init, bindModal: bindModal };
})(window);
