(function () {
    'use strict';

    function daysInclusive(fromStr, toStr) {
        if (!fromStr || !toStr) return 0;
        var from = new Date(fromStr + 'T12:00:00');
        var to = new Date(toStr + 'T12:00:00');
        if (isNaN(from.getTime()) || isNaN(to.getTime()) || to < from) return 0;
        return Math.round((to - from) / 86400000) + 1;
    }

    function dayLabel(count) {
        if (count === 1) return '1 day leave';
        if (count > 1) return count + ' days leave';
        return '';
    }

    function initLeaveApplyForm(form) {
        if (!form || form.dataset.leaveUxInit === '1') return;
        form.dataset.leaveUxInit = '1';

        var modeSelect = form.querySelector('[name="leave_duration_mode"]');
        var fromInput = form.querySelector('[name="from_date"]');
        var toInput = form.querySelector('[name="to_date"]');
        var toWrap = form.querySelector('.leave-apply-form__to');
        var fromLabel = form.querySelector('.leave-apply-form__from-label');
        var pill = form.querySelector('.leave-days-pill') || form.querySelector('.leave-duration-summary__text');

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
            if (toWrap) {
                toWrap.classList.toggle('is-hidden', single);
            }
            if (single) {
                toInput.removeAttribute('required');
                if (fromLabel) fromLabel.textContent = 'Leave date';
                syncSingle();
            } else {
                toInput.setAttribute('required', 'required');
                if (fromLabel) fromLabel.textContent = 'From date';
                if (fromInput.value && (!toInput.value || toInput.value < fromInput.value)) {
                    toInput.value = fromInput.value;
                }
                if (toInput.min !== undefined) {
                    toInput.min = fromInput.value || '';
                }
            }
            updatePill();
        }

        function updatePill() {
            if (!pill) return;
            var from = fromInput.value;
            var to = isSingle() ? from : toInput.value;
            if (!from) {
                pill.classList.add('is-hidden');
                pill.textContent = '';
                return;
            }
            if (!isSingle() && !to) {
                pill.classList.add('is-hidden');
                return;
            }
            var days = daysInclusive(from, to || from);
            var label = dayLabel(days);
            if (!label) {
                pill.classList.add('is-hidden');
                return;
            }
            pill.textContent = label;
            pill.classList.remove('is-hidden');
            pill.classList.toggle('leave-days-pill--warn', !isSingle() && to < from);
            if (!isSingle() && to < from) {
                pill.textContent = 'To date must be after from date';
            }
        }

        if (modeSelect) {
            modeSelect.addEventListener('change', setMode);
        }
        fromInput.addEventListener('change', function () {
            syncSingle();
            if (!isSingle() && toInput.min !== undefined) {
                toInput.min = fromInput.value;
            }
            updatePill();
        });
        toInput.addEventListener('change', updatePill);
        form.addEventListener('submit', syncSingle);

        setMode();
    }

    document.querySelectorAll('.leave-apply-form').forEach(initLeaveApplyForm);

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
})();
