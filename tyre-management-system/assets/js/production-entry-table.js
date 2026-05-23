(function () {
  'use strict';

  function syncScrollHint(scrollWrap, scrollHint) {
    if (!scrollWrap || !scrollHint) {
      return;
    }
    var needsH = scrollWrap.scrollWidth > scrollWrap.clientWidth + 2;
    var needsV = scrollWrap.scrollHeight > scrollWrap.clientHeight + 2;
    scrollHint.classList.toggle('is-hidden', !needsH && !needsV);
  }

  document.querySelectorAll('[data-prod-entry-scroll]').forEach(function (scrollWrap) {
    var card = scrollWrap.closest('.prod-entry-recent, .prod-rpt-table-card, .prod-card');
    if (!card) {
      return;
    }
    var scrollHint = card.querySelector('[data-prod-entry-scroll-hint]');
    if (!scrollHint) {
      return;
    }
    function update() {
      syncScrollHint(scrollWrap, scrollHint);
    }
    update();
    scrollWrap.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
  });

  document.querySelectorAll('.prod-entry-recent').forEach(function (card) {
    var searchInput = card.querySelector('[data-prod-entry-search]');
    var tbody = card.querySelector('[data-prod-entry-tbody]');
    var countEl = card.querySelector('[data-prod-entry-count]');
    var noMatch = card.querySelector('[data-prod-entry-no-match]');

    if (!searchInput || !tbody) {
      return;
    }

    var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-prod-entry-row]'));

    function applyFilter() {
      var q = searchInput.value.trim().toLowerCase();
      var visible = 0;

      rows.forEach(function (row) {
        var hay = row.getAttribute('data-search') || '';
        var show = q === '' || hay.indexOf(q) !== -1;
        row.classList.toggle('d-none', !show);
        if (show) {
          visible += 1;
        }
      });

      if (noMatch) {
        noMatch.classList.toggle('d-none', q === '' || visible > 0 || rows.length === 0);
      }
      if (countEl) {
        countEl.textContent = rows.length
          ? visible + ' of ' + rows.length + ' shown'
          : '0 shown';
      }
    }

    searchInput.addEventListener('input', applyFilter);
    searchInput.addEventListener('search', applyFilter);
    applyFilter();
  });
})();
