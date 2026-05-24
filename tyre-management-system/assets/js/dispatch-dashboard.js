(function () {
  'use strict';

  function filterTable(input, tableSelector, rowSelector, emptySelector) {
    if (!input) {
      return;
    }
    var table = document.querySelector(tableSelector);
    if (!table) {
      return;
    }
    var tbody = table.querySelector('tbody');
    if (!tbody) {
      return;
    }
    var rows = tbody.querySelectorAll(rowSelector);
    var emptyRow = tbody.querySelector(emptySelector);
    var q = '';

    function apply() {
      q = (input.value || '').trim().toLowerCase();
      var visible = 0;
      rows.forEach(function (row) {
        if (row.classList.contains('dsp-table-empty-row')) {
          return;
        }
        var hay = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
        var show = q === '' || hay.indexOf(q) !== -1;
        row.classList.toggle('d-none', !show);
        if (show) {
          visible += 1;
        }
      });
      if (emptyRow) {
        emptyRow.classList.toggle('d-none', visible > 0 || q === '');
      }
      var noMatch = tbody.querySelector('.dsp-table-no-match');
      if (q !== '' && visible === 0) {
        if (!noMatch) {
          noMatch = document.createElement('tr');
          noMatch.className = 'dsp-table-no-match';
          var colCount = table.querySelectorAll('thead th').length || 5;
          noMatch.innerHTML = '<td colspan="' + colCount + '" class="dsp-empty">No rows match your search.</td>';
          tbody.appendChild(noMatch);
        }
        noMatch.classList.remove('d-none');
      } else if (noMatch) {
        noMatch.classList.add('d-none');
      }
    }

    input.addEventListener('input', apply);
    input.addEventListener('search', apply);
    apply();
  }

  filterTable(
    document.getElementById('dsp-queue-search'),
    '#dsp-queue-table',
    'tr.dsp-queue-row',
    'tr.dsp-table-empty-row'
  );

  filterTable(
    document.getElementById('dsp-recent-search'),
    '#dsp-recent-table',
    'tr.dsp-recent-row',
    'tr.dsp-table-empty-row'
  );
})();
