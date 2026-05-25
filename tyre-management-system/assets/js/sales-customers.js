(function () {
    'use strict';

    var form = document.getElementById('crm-customers-filter');
    var input = document.getElementById('crm-customers-search');
    var table = document.getElementById('crm-customers-table');
    if (!input || !table) {
        return;
    }

    var tbody = table.querySelector('tbody');
    var rows = tbody ? tbody.querySelectorAll('tr.crm-customer-row') : [];
    var emptyRow = tbody ? tbody.querySelector('tr.crm-customers-empty') : null;
    var countEl = document.getElementById('crm-customers-count');
    function applyClientFilter() {
        var q = (input.value || '').trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
            var hay = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
            var show = q === '' || hay.indexOf(q) !== -1;
            row.classList.toggle('d-none', !show);
            if (show) {
                visible += 1;
            }
        });

        if (emptyRow) {
            emptyRow.classList.toggle('d-none', visible > 0 || q !== '');
        }

        var noMatch = tbody ? tbody.querySelector('.crm-customers-no-match') : null;
        if (q !== '' && visible === 0 && rows.length > 0) {
            if (!noMatch) {
                noMatch = document.createElement('tr');
                noMatch.className = 'crm-customers-no-match';
                noMatch.innerHTML = '<td colspan="7" class="sales-empty">No customers match your search.</td>';
                tbody.appendChild(noMatch);
            }
            noMatch.classList.remove('d-none');
        } else if (noMatch) {
            noMatch.classList.add('d-none');
        }

        if (countEl) {
            var total = rows.length;
            countEl.textContent = q ? visible + ' shown · ' + total + ' total' : total + ' customers';
        }
    }

    function submitServerSearch() {
        if (!form) {
            return;
        }
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    input.addEventListener('input', applyClientFilter);

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitServerSearch();
        }
    });

    if (form) {
        form.querySelectorAll('select[name="status"], select[name="type"]').forEach(function (sel) {
            sel.addEventListener('change', submitServerSearch);
        });
    }

    input.addEventListener('search', applyClientFilter);

    applyClientFilter();
})();
