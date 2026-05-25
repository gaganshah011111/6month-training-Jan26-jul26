(function () {
    'use strict';

    var form = document.getElementById('sales-invoice-filter-form');
    var searchInput = document.getElementById('sales-invoice-search');
    var fromInput = document.getElementById('sales-invoice-from');
    var toInput = document.getElementById('sales-invoice-to');
    var table = document.getElementById('sales-invoice-table');
    if (!table) {
        return;
    }

    var tbody = table.querySelector('tbody');
    var rows = tbody ? tbody.querySelectorAll('tr.sales-invoice-row') : [];
    var emptyRow = tbody ? tbody.querySelector('tr.sales-invoice-empty') : null;
    var countEl = document.getElementById('sales-invoice-count');
    var kpiCount = document.getElementById('inv-kpi-count');
    var kpiBilled = document.getElementById('inv-kpi-billed');
    var kpiCollected = document.getElementById('inv-kpi-collected');
    var kpiOutstanding = document.getElementById('inv-kpi-outstanding');
    var debounceTimer = null;

    function money(n) {
        var x = parseFloat(n) || 0;
        return '₹' + x.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function rowPending(row) {
        var p = parseFloat(row.getAttribute('data-pending') || '0');
        if (!isNaN(p) && p >= 0) {
            return p;
        }
        var total = parseFloat(row.getAttribute('data-total') || '0');
        var paid = parseFloat(row.getAttribute('data-paid') || '0');
        var diff = Math.round((total - paid) * 100) / 100;
        return diff <= 0.02 ? 0 : Math.max(0, diff);
    }

    function inDateRange(dateStr, fromVal, toVal) {
        if (!dateStr) {
            return true;
        }
        if (fromVal && dateStr < fromVal) {
            return false;
        }
        if (toVal && dateStr > toVal) {
            return false;
        }
        return true;
    }

    function applyFilters() {
        var q = (searchInput ? searchInput.value : '').trim().toLowerCase();
        var fromVal = fromInput ? fromInput.value : '';
        var toVal = toInput ? toInput.value : '';
        var visible = 0;
        var totalBilled = 0;
        var totalPaid = 0;
        var totalPending = 0;

        rows.forEach(function (row) {
            var hay = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
            var dateStr = row.getAttribute('data-date') || '';
            var textOk = q === '' || hay.indexOf(q) !== -1;
            var dateOk = inDateRange(dateStr, fromVal, toVal);
            var show = textOk && dateOk;
            row.classList.toggle('d-none', !show);
            if (show) {
                visible += 1;
                totalBilled += parseFloat(row.getAttribute('data-total') || '0');
                totalPaid += parseFloat(row.getAttribute('data-paid') || '0');
                totalPending += rowPending(row);
            }
        });

        if (emptyRow) {
            emptyRow.classList.toggle('d-none', visible > 0 || q !== '' || fromVal !== '' || toVal !== '');
        }

        var noMatch = tbody ? tbody.querySelector('.sales-invoice-no-match') : null;
        var hasFilters = q !== '' || fromVal !== '' || toVal !== '';
        if (hasFilters && visible === 0 && rows.length > 0) {
            if (!noMatch) {
                noMatch = document.createElement('tr');
                noMatch.className = 'sales-invoice-no-match';
                noMatch.innerHTML = '<td colspan="7" class="sales-empty text-center py-4">No invoices match your filters.</td>';
                tbody.appendChild(noMatch);
            }
            noMatch.classList.remove('d-none');
        } else if (noMatch) {
            noMatch.classList.add('d-none');
        }

        if (kpiCount) {
            kpiCount.textContent = String(visible);
        }
        if (kpiBilled) {
            kpiBilled.textContent = money(totalBilled);
        }
        if (kpiCollected) {
            kpiCollected.textContent = money(totalPaid);
        }
        if (kpiOutstanding) {
            kpiOutstanding.textContent = money(totalPending);
        }
        if (countEl) {
            countEl.textContent = hasFilters
                ? visible + ' shown · ' + rows.length + ' total'
                : rows.length + ' invoices';
        }
    }

    function submitServer() {
        if (!form) {
            return;
        }
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function scheduleServerSync() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(submitServer, 550);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            applyFilters();
            scheduleServerSync();
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                submitServer();
            }
        });
        searchInput.addEventListener('search', applyFilters);
    }

    function onDateInput() {
        applyFilters();
        scheduleServerSync();
    }

    if (fromInput) {
        fromInput.addEventListener('input', applyFilters);
        fromInput.addEventListener('change', onDateInput);
    }
    if (toInput) {
        toInput.addEventListener('input', applyFilters);
        toInput.addEventListener('change', onDateInput);
    }

    if (form) {
        form.querySelectorAll('select[name="customer_id"], select[name="payment_status"]').forEach(function (sel) {
            sel.addEventListener('change', scheduleServerSync);
        });
    }

    applyFilters();
})();
