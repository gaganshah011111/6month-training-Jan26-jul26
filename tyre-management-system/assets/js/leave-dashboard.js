(function () {
    'use strict';

    function daysInclusive(fromStr, toStr) {
        if (!fromStr || !toStr) return 0;
        var from = new Date(fromStr + 'T12:00:00');
        var to = new Date(toStr + 'T12:00:00');
        if (isNaN(from.getTime()) || isNaN(to.getTime()) || to < from) return 0;
        return Math.round((to - from) / 86400000) + 1;
    }

    function initLeaveApplyForm(form) {
        if (!form || form.dataset.leaveUxInit === '1') return;
        form.dataset.leaveUxInit = '1';

        var modeSelect = form.querySelector('[name="leave_duration_mode"]');
        var fromInput = form.querySelector('[name="from_date"]');
        var toInput = form.querySelector('[name="to_date"]');
        var toWrap = form.querySelector('.leave-apply-form__to');
        var fromLabel = form.querySelector('.leave-apply-form__from-label');

        if (!fromInput) return;

        if (!toInput) {
            toInput = document.createElement('input');
            toInput.type = 'hidden';
            toInput.name = 'to_date';
            form.appendChild(toInput);
        }

        function isSingle() {
            return !modeSelect || modeSelect.value === 'single';
        }

        function syncSingle() {
            if (isSingle() && fromInput.value) {
                toInput.value = fromInput.value;
            }
        }

        function setMode() {
            var single = isSingle();
            if (toWrap) toWrap.classList.toggle('is-hidden', single);
            if (single) {
                toInput.removeAttribute('required');
                if (fromLabel) fromLabel.textContent = 'Date';
                syncSingle();
            } else {
                toInput.setAttribute('required', 'required');
                if (fromLabel) fromLabel.textContent = 'From';
                if (fromInput.value && (!toInput.value || toInput.value < fromInput.value)) {
                    toInput.value = fromInput.value;
                }
                if (toInput.min !== undefined) toInput.min = fromInput.value || '';
            }
        }

        if (modeSelect) modeSelect.addEventListener('change', setMode);
        fromInput.addEventListener('change', function () {
            syncSingle();
            if (!isSingle() && toInput.min !== undefined) toInput.min = fromInput.value;
        });
        form.addEventListener('submit', syncSingle);
        setMode();
    }

    document.querySelectorAll('.leave-apply-form').forEach(initLeaveApplyForm);

    var toggleManual = document.getElementById('toggleManualEntry');
    var manualPanel = document.getElementById('manualEntryPanel');
    if (toggleManual && manualPanel) {
        toggleManual.addEventListener('click', function () {
            var open = manualPanel.classList.toggle('is-hidden') === false;
            toggleManual.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggleManual.textContent = open ? '− Manual Leave Entry' : '+ Manual Leave Entry';
        });
    }

    var rejectModal = document.getElementById('leaveRejectModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            var idInput = rejectModal.querySelector('[name="id"]');
            var title = rejectModal.querySelector('.js-reject-employee');
            if (idInput) idInput.value = btn.getAttribute('data-leave-id') || '';
            if (title) title.textContent = btn.getAttribute('data-employee-name') || '';
        });
    }

    var viewModal = document.getElementById('leaveViewModal');
    if (viewModal) {
        viewModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;

            var set = function (id, val) {
                var el = document.getElementById(id);
                if (el) el.textContent = val || '—';
            };

            set('leaveViewTitle', btn.getAttribute('data-name'));
            set('lvName', btn.getAttribute('data-name'));
            set('lvDept', btn.getAttribute('data-dept'));
            set('lvDates', btn.getAttribute('data-dates'));
            set('lvDays', btn.getAttribute('data-days'));
            set('lvType', btn.getAttribute('data-type'));
            set('lvStatus', btn.getAttribute('data-status'));
            set('lvBalance', btn.getAttribute('data-paid-left'));
            set('lvReason', btn.getAttribute('data-reason'));

            var foot = document.getElementById('leaveViewFooter');
            if (!foot) return;
            foot.innerHTML = '';

            var csrf = document.querySelector('input[name="csrf_token"]');
            var id = btn.getAttribute('data-id') || '';
            var name = btn.getAttribute('data-name') || '';

            function addHidden(form, n, v) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = n;
                inp.value = v;
                form.appendChild(inp);
            }

            if (btn.getAttribute('data-pending') === '1') {
                var approveForm = document.createElement('form');
                approveForm.method = 'post';
                approveForm.className = 'd-inline';
                if (csrf) approveForm.appendChild(csrf.cloneNode(true));
                addHidden(approveForm, 'action', 'approve');
                addHidden(approveForm, 'id', id);
                var approveBtn = document.createElement('button');
                approveBtn.type = 'submit';
                approveBtn.className = 'btn btn-sm btn-success';
                approveBtn.textContent = 'Approve';
                approveForm.appendChild(approveBtn);
                foot.appendChild(approveForm);

                var rejectBtn = document.createElement('button');
                rejectBtn.type = 'button';
                rejectBtn.className = 'btn btn-sm btn-outline-danger';
                rejectBtn.textContent = 'Reject';
                rejectBtn.setAttribute('data-bs-toggle', 'modal');
                rejectBtn.setAttribute('data-bs-target', '#leaveRejectModal');
                rejectBtn.setAttribute('data-leave-id', id);
                rejectBtn.setAttribute('data-employee-name', name);
                foot.appendChild(rejectBtn);
            }

            if (btn.getAttribute('data-convert') === '1') {
                var convertForm = document.createElement('form');
                convertForm.method = 'post';
                convertForm.className = 'd-inline';
                convertForm.onsubmit = function () { return confirm('Convert to unpaid?'); };
                if (csrf) convertForm.appendChild(csrf.cloneNode(true));
                addHidden(convertForm, 'action', 'convert_unpaid');
                addHidden(convertForm, 'id', id);
                var convertBtn = document.createElement('button');
                convertBtn.type = 'submit';
                convertBtn.className = 'btn btn-sm btn-outline-secondary';
                convertBtn.textContent = 'Unpaid';
                convertForm.appendChild(convertBtn);
                foot.appendChild(convertForm);
            }

            if (!foot.children.length) {
                foot.style.display = 'none';
            } else {
                foot.style.display = '';
            }
        });
    }
})();
