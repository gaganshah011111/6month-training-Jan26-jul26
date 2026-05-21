(function () {
    'use strict';

    var modalEl = document.getElementById('empCalDayModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var titleEl = document.getElementById('empCalModalTitle');
    var statusEl = document.getElementById('empCalModalStatus');
    var bodyEl = document.getElementById('empCalModalBody');

    function setRow(label, value) {
        if (!value || value === '—' || value === '') {
            return '';
        }
        return '<div class="emp-cal-modal__row"><span>' + label + '</span><strong>' + value + '</strong></div>';
    }

    document.querySelectorAll('.emp-cal-day[data-day-json]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var raw = btn.getAttribute('data-day-json');
            if (!raw) {
                return;
            }
            var d;
            try {
                d = JSON.parse(raw);
            } catch (e) {
                return;
            }

            if (titleEl) {
                titleEl.textContent = d.dateLabel || d.date || 'Attendance';
            }
            if (statusEl) {
                statusEl.textContent = d.status || 'No record';
                statusEl.className = 'emp-cal-modal__status-pill ' + (d.statusClass || 'emp-att--default');
            }

            var html = '';
            html += setRow('Punch in', d.punchIn);
            html += setRow('Punch out', d.punchOut);
            html += setRow('Total hours', d.hours);
            html += setRow('Overtime', d.ot);
            html += setRow('Late arrival', d.late);
            html += setRow('Remarks', d.remarks);

            if (!html) {
                html = '<p class="text-muted mb-0">No attendance or leave recorded for this date.</p>';
            }

            if (bodyEl) {
                bodyEl.innerHTML = html;
            }
            modal.show();
        });
    });
})();
