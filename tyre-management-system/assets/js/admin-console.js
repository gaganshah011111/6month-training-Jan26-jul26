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
            if (msg && !window.confirm(msg)) ev.preventDefault();
        });
    });

    document.querySelectorAll('button[data-confirm]').forEach(function (btn) {
        if (btn.closest('.admin-confirm-form')) return;
        btn.addEventListener('click', function (ev) {
            var msg = btn.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) ev.preventDefault();
        });
    });

    var biz = document.getElementById('saBusinessOverview');
    if (biz && window.Chart) {
        try {
            var data = JSON.parse(biz.getAttribute('data-charts') || '{}');
            var labels = data.labels || [];
            var common = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };
            var mk = function (id, label, values, color) {
                var el = document.getElementById(id);
                if (!el) return;
                new Chart(el, {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: label, data: values, borderColor: color, backgroundColor: color + '22', fill: true, tension: 0.3 }] },
                    options: common
                });
            };
            mk('chartRevenue', 'Revenue', data.revenue || [], '#15803d');
            mk('chartExpenses', 'Expenses', data.expenses || [], '#b91c1c');
            mk('chartProfit', 'Profit', data.profit || [], '#1e40af');
        } catch (e) { /* ignore */ }
    }
})();
