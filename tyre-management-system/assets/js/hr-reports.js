(function () {
    'use strict';

    var form = document.getElementById('hrReportsFilterForm');
    if (form) {
        var debounceTimer;
        function submitFilters() {
            form.requestSubmit();
        }

        ['from', 'to', 'department_id', 'employee_type'].forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            el.addEventListener('change', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(submitFilters, 200);
            });
        });
    }

    var printBtn = document.getElementById('hrReportPrint');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            var params = new URLSearchParams(window.location.search);
            params.set('page', 'reports/hr');
            params.set('export', 'print');
            window.open('index.php?' + params.toString(), '_blank');
        });
    }
})();
