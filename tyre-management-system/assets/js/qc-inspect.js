(function () {
    'use strict';

    var form = document.getElementById('qc-inspect-form');
    if (!form) {
        return;
    }

    var produced = parseInt(form.getAttribute('data-produced'), 10) || 0;
    var inspected = document.getElementById('qc-inspected');
    var passed = document.getElementById('qc-passed');
    var rejected = document.getElementById('qc-rejected');
    var rework = document.getElementById('qc-rework');
    var errEl = document.getElementById('qc-qty-error');
    var defectContainer = document.getElementById('qc-defect-rows');
    var addBtn = document.getElementById('qc-add-defect');

    function validateQty() {
        var i = parseInt(inspected && inspected.value, 10) || 0;
        var p = parseInt(passed && passed.value, 10) || 0;
        var r = parseInt(rejected && rejected.value, 10) || 0;
        var w = parseInt(rework && rework.value, 10) || 0;
        var msg = '';
        if (i > produced) {
            msg = 'Inspected qty cannot exceed produced qty (' + produced + ').';
        } else if (p + r + w > i) {
            msg = 'Pass + Reject + Rework cannot exceed inspected qty.';
        }
        if (errEl) {
            errEl.textContent = msg;
            errEl.classList.toggle('d-none', !msg);
        }
        return msg === '';
    }

    [inspected, passed, rejected, rework].forEach(function (el) {
        if (el) {
            el.addEventListener('input', validateQty);
        }
    });

    form.addEventListener('submit', function (e) {
        if (!validateQty()) {
            e.preventDefault();
        }
    });

    if (addBtn && defectContainer) {
        addBtn.addEventListener('click', function () {
            var first = defectContainer.querySelector('.qc-defect-row');
            if (!first) {
                return;
            }
            var clone = first.cloneNode(true);
            clone.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
            clone.querySelectorAll('input[type="number"]').forEach(function (n) { n.value = ''; });
            var rm = clone.querySelector('.qc-remove-defect');
            if (rm) {
                rm.disabled = false;
            }
            defectContainer.appendChild(clone);
        });
    }

    if (defectContainer) {
        defectContainer.addEventListener('click', function (e) {
            var btn = e.target.closest('.qc-remove-defect');
            if (!btn || btn.disabled) {
                return;
            }
            var row = btn.closest('.qc-defect-row');
            if (row && defectContainer.querySelectorAll('.qc-defect-row').length > 1) {
                row.remove();
            }
        });
    }

    validateQty();
})();
