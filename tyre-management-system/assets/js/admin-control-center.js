(function () {
    'use strict';

    document.querySelectorAll('.js-reset-pw').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-user-id');
            var input = document.getElementById('resetPwUserId');
            var modalEl = document.getElementById('resetPwModal');
            if (!id || !input || !modalEl || !window.bootstrap) return;
            input.value = id;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });

    document.querySelectorAll('.admin-confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            var btn = ev.submitter || form.querySelector('[data-confirm]');
            var msg = btn && btn.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) {
                ev.preventDefault();
            }
        });
    });

    document.querySelectorAll('button[data-confirm]').forEach(function (btn) {
        if (btn.closest('.admin-confirm-form')) return;
        btn.addEventListener('click', function (ev) {
            var msg = btn.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) {
                ev.preventDefault();
            }
        });
    });
})();
