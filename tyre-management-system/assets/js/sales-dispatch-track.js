(function () {
    'use strict';
    var input = document.getElementById('crm-dispatch-search');
    var table = document.getElementById('crm-dispatch-table');
    if (!input || !table) {
        return;
    }
    var tbody = table.querySelector('tbody');
    var rows = tbody ? tbody.querySelectorAll('tr.crm-dispatch-row') : [];

    function apply() {
        var q = (input.value || '').trim().toLowerCase();
        rows.forEach(function (row) {
            var hay = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
            row.classList.toggle('d-none', q !== '' && hay.indexOf(q) === -1);
        });
    }

    input.addEventListener('input', apply);
})();
