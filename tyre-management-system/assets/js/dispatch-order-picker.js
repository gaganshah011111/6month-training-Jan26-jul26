/**
 * New Dispatch — searchable order picker (search + status filters).
 */
(function () {
    'use strict';

    var picker = document.getElementById('dsp-order-picker');
    if (!picker) {
        return;
    }

    var searchInput = document.getElementById('dsp-order-search');
    var countEl = document.getElementById('dsp-order-picker-count');
    var table = document.getElementById('dsp-order-picker-table');
    var selectedBar = document.getElementById('dsp-order-selected-bar');
    var selectedLabel = document.getElementById('dsp-order-selected-label');
    var changeBtn = document.getElementById('dsp-order-change-btn');
    var pickerBody = document.getElementById('dsp-order-picker-body');
    var expandBtn = document.getElementById('dsp-order-expand-btn');
    var scrollHint = document.getElementById('dsp-order-scroll-hint');

    var activeFilter = 'all';
    var filterButtons = picker.querySelectorAll('.dsp-filter-chip');

    function rows() {
        if (!table) {
            return [];
        }
        var tbody = table.querySelector('tbody');
        if (!tbody) {
            return [];
        }
        return Array.prototype.slice.call(tbody.querySelectorAll('tr.dsp-order-picker-row'));
    }

    function rowMatchesFilter(row) {
        if (activeFilter === 'all') {
            return true;
        }
        return (row.getAttribute('data-stock-status') || '') === activeFilter;
    }

    function applyFilters() {
        var q = (searchInput && searchInput.value || '').trim().toLowerCase();
        var visible = 0;
        var allRows = rows();

        allRows.forEach(function (row) {
            var hay = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
            var matchSearch = q === '' || hay.indexOf(q) !== -1;
            var matchFilter = rowMatchesFilter(row);
            var show = matchSearch && matchFilter;
            row.classList.toggle('d-none', !show);
            if (show) {
                visible += 1;
            }
        });

        if (countEl) {
            countEl.textContent = visible + ' of ' + allRows.length + ' line' + (allRows.length === 1 ? '' : 's');
        }
        if (scrollHint) {
            scrollHint.classList.toggle('d-none', visible <= 3 || allRows.length <= 3);
        }

        var tbody = table && table.querySelector('tbody');
        if (!tbody) {
            return;
        }
        var noMatch = tbody.querySelector('.dsp-table-no-match');
        if (q !== '' && visible === 0 && allRows.length > 0) {
            if (!noMatch) {
                noMatch = document.createElement('tr');
                noMatch.className = 'dsp-table-no-match';
                noMatch.innerHTML = '<td colspan="7" class="dsp-empty">No orders match your search or filter.</td>';
                tbody.appendChild(noMatch);
            }
            noMatch.classList.remove('d-none');
        } else if (noMatch) {
            noMatch.classList.add('d-none');
        }
    }

    filterButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterButtons.forEach(function (b) {
                b.classList.remove('is-active');
            });
            btn.classList.add('is-active');
            activeFilter = btn.getAttribute('data-filter') || 'all';
            applyFilters();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
        searchInput.addEventListener('search', applyFilters);
    }

    function showSelectedBar(label) {
        if (selectedLabel) {
            selectedLabel.textContent = label || '—';
        }
        if (selectedBar) {
            selectedBar.classList.remove('d-none');
        }
        picker.classList.add('dsp-order-picker--collapsed');
        if (expandBtn) {
            expandBtn.classList.remove('d-none');
        }
    }

    function clearSelectedBar() {
        if (selectedBar) {
            selectedBar.classList.add('d-none');
        }
        picker.classList.remove('dsp-order-picker--collapsed');
        if (expandBtn) {
            expandBtn.classList.add('d-none');
        }
        if (searchInput) {
            searchInput.focus();
        }
    }

    if (changeBtn) {
        changeBtn.addEventListener('click', function () {
            clearSelectedBar();
            document.dispatchEvent(new CustomEvent('dsp:order-clear'));
        });
    }

    if (expandBtn) {
        expandBtn.addEventListener('click', function () {
            picker.classList.remove('dsp-order-picker--collapsed');
            expandBtn.classList.add('d-none');
            if (pickerBody) {
                pickerBody.scrollTop = 0;
            }
        });
    }

    document.addEventListener('dsp:order-selected', function (e) {
        var d = e.detail || {};
        var label = (d.so_number || '') + ' · ' + (d.customer_name || '') + ' · ' + (d.tyre_type || '');
        showSelectedBar(label);
    });

    document.addEventListener('dsp:order-cleared', function () {
        clearSelectedBar();
        rows().forEach(function (row) {
            row.classList.remove('is-selected');
        });
    });

    window.dspOrderPicker = {
        expand: clearSelectedBar,
        refreshCount: applyFilters,
    };

    applyFilters();
})();
