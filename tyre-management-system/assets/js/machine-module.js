(function () {
  'use strict';

  document.querySelectorAll('.mach-table-wrap').forEach(function (wrap) {
    var hint = wrap.previousElementSibling;
    if (!hint || !hint.classList.contains('mach-table-scroll-hint')) {
      return;
    }
    function syncHint() {
      var needsScroll = wrap.scrollWidth > wrap.clientWidth + 2;
      hint.classList.toggle('is-hidden', !needsScroll);
    }
    syncHint();
    wrap.addEventListener('scroll', syncHint, { passive: true });
    window.addEventListener('resize', syncHint);
  });

  var editPanel = document.getElementById('machEditPanel');
  if (editPanel) {
    editPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    editPanel.focus({ preventScroll: true });
  }
})();
