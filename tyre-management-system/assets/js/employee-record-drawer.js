/**
 * Employee directory — right offcanvas with full record.
 */
(function () {
    'use strict';

    function init() {
        var drawerEl = document.getElementById('empRecordDrawer');
        var bodyEl = document.getElementById('empRecordDrawerBody');
        var titleEl = document.getElementById('empRecordDrawerTitle');
        if (!drawerEl || !bodyEl || !window.bootstrap || !window.bootstrap.Offcanvas) {
            return;
        }

        var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);

        function openDrawer(empId) {
            var tpl = document.getElementById('empRecordTpl' + empId);
            if (!tpl || !tpl.content) {
                return;
            }
            bodyEl.innerHTML = '';
            bodyEl.appendChild(tpl.content.cloneNode(true));
            if (titleEl) {
                var nameEl = bodyEl.querySelector('.emp-drawer__name');
                titleEl.textContent = nameEl ? nameEl.textContent.trim() : 'Employee record';
            }
            offcanvas.show();
        }

        window.EmpRecordDrawer = { open: openDrawer };

        bodyEl.addEventListener('click', function (e) {
            if (e.target.closest('[data-bs-toggle="modal"]')) {
                offcanvas.hide();
            }
        });

        document.querySelectorAll('[data-emp-drawer-id]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = btn.getAttribute('data-emp-drawer-id');
                if (!id) {
                    return;
                }
                var dd = btn.closest('.dropdown');
                if (dd) {
                    var toggle = dd.querySelector('[data-bs-toggle="dropdown"]');
                    var inst = toggle ? bootstrap.Dropdown.getInstance(toggle) : null;
                    if (inst) {
                        inst.hide();
                    }
                }
                openDrawer(id);
            });
        });

        document.querySelectorAll('.emp-row-clickable').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, form, .dropdown, .emp-actions-toggle, input, select, label')) {
                    return;
                }
                var id = row.getAttribute('data-emp-id');
                if (id) {
                    openDrawer(id);
                }
            });
            row.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    var id = row.getAttribute('data-emp-id');
                    if (id) {
                        openDrawer(id);
                    }
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
