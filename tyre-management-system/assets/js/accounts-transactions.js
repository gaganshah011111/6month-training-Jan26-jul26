(function () {
    'use strict';

    var form = document.getElementById('accTxFilterForm');
    if (form) {
        var debounceTimer;
        function submitFilters() {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }

        ['from', 'to', 'tx_type', 'status', 'payment_mode'].forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            el.addEventListener('change', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(submitFilters, 300);
            });
        });
    }

    var printBtn = document.getElementById('accTxPrint');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            var params = new URLSearchParams(window.location.search);
            params.set('page', 'accounts/transactions-history');
            params.set('export', 'print');
            window.open('index.php?' + params.toString(), '_blank');
        });
    }

    var drawerEl = document.getElementById('accTxDrawer');
    var drawerBody = document.getElementById('accTxDrawerBody');
    var drawerSub = document.getElementById('accTxDrawerLabel');
    var drawerSubEl = document.getElementById('accTxDrawerSub');
    var offcanvas = null;

    if (drawerEl && window.bootstrap && window.bootstrap.Offcanvas) {
        offcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function buildTxExport(type, txCode) {
        var params = new URLSearchParams(window.location.search);
        params.set('page', 'accounts/transactions-history');
        params.set('export', type);
        params.set('tx', txCode);
        return 'index.php?' + params.toString();
    }

    function renderDrawer(tx) {
        if (!drawerBody || !tx) return;

        var dir = tx.direction_meta || {};
        var src = tx.source_link || null;

        var html = '';
        html += '<div class="acc-tx-drawer-section"><h6 class="acc-tx-drawer-section__title">Transaction Details</h6>';
        html += '<dl class="acc-tx-drawer-dl">';
        html += '<div><dt>Transaction ID</dt><dd>' + esc(tx.tx_code) + '</dd></div>';
        html += '<div><dt>Date &amp; Time</dt><dd>' + esc(tx.tx_datetime) + '</dd></div>';
        html += '<div><dt>Type</dt><dd>' + esc(tx.tx_type) + '</dd></div>';
        html += '<div><dt>Direction</dt><dd>' + esc(dir.label || '—') + '</dd></div>';
        html += '<div><dt>Amount</dt><dd>' + esc(tx.amount_fmt) + '</dd></div>';
        html += '<div><dt>Payment Mode</dt><dd>' + esc(tx.payment_mode) + '</dd></div>';
        html += '<div><dt>Status</dt><dd>' + esc(tx.tx_status) + '</dd></div>';
        html += '<div class="full"><dt>Reference</dt><dd>' + esc(tx.reference_no) + '</dd></div>';
        html += '</dl></div>';

        html += '<div class="acc-tx-drawer-section"><h6 class="acc-tx-drawer-section__title">Party Information</h6>';
        html += '<dl class="acc-tx-drawer-dl">';
        html += '<div class="full"><dt>Party</dt><dd>' + esc(tx.party) + '</dd></div>';
        html += '<div><dt>Source Module</dt><dd>' + esc(tx.source_module || '—') + '</dd></div>';
        html += '<div><dt>Created By</dt><dd>' + esc(tx.created_by) + '</dd></div>';
        html += '<div class="full"><dt>Created Date</dt><dd>' + esc(tx.created_at || tx.tx_datetime) + '</dd></div>';
        html += '</dl></div>';

        if (tx.audit_trail && tx.audit_trail.length) {
            html += '<div class="acc-tx-drawer-section"><h6 class="acc-tx-drawer-section__title">Audit Trail</h6><ul class="acc-tx-audit">';
            tx.audit_trail.forEach(function (line) {
                html += '<li>' + esc(line) + '</li>';
            });
            html += '</ul></div>';
        }

        html += '<div class="acc-tx-drawer-actions">';
        html += '<a href="' + esc(buildTxExport('print', tx.tx_code)) + '" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-printer"></i> Print</a>';
        html += '<a href="' + esc(buildTxExport('pdf', tx.tx_code)) + '" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>';
        if (src && src.url) {
            html += '<a href="' + esc(src.url) + '" class="btn btn-sm btn-primary"><i class="bi bi-box-arrow-up-right"></i> ' + esc(src.label || 'Open Source') + '</a>';
        }
        html += '</div>';

        drawerBody.innerHTML = html;
        if (drawerSub) {
            drawerSub.textContent = 'Transaction Details';
        }
        if (drawerSubEl) {
            drawerSubEl.textContent = tx.tx_code + ' · ' + (tx.tx_type || '');
        }
    }

    document.querySelectorAll('.acc-tx-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('tr[data-tx]');
            if (!row || !offcanvas) return;
            try {
                var tx = JSON.parse(row.getAttribute('data-tx') || '{}');
                renderDrawer(tx);
                offcanvas.show();
            } catch (e) { /* ignore */ }
        });
    });

    document.querySelectorAll('.acc-tx-row[data-tx]').forEach(function (row) {
        row.addEventListener('dblclick', function () {
            var btn = row.querySelector('.acc-tx-view');
            if (btn) btn.click();
        });
    });

    var dataEl = document.getElementById('accTxChartData');
    if (!dataEl || typeof Chart === 'undefined') {
        return;
    }

    var payload;
    try {
        payload = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        return;
    }

    var chartOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { boxWidth: 10, font: { size: 10 } } } }
    };

    var distEl = document.getElementById('accTxChartDist');
    if (distEl) {
        var byType = payload.by_type || [];
        new Chart(distEl, {
            type: 'doughnut',
            data: {
                labels: byType.map(function (b) { return b.type; }),
                datasets: [{
                    data: byType.map(function (b) { return b.amount; }),
                    backgroundColor: ['#059669', '#ea580c', '#7c3aed', '#dc2626', '#2563eb', '#0d9488', '#64748b']
                }]
            },
            options: chartOpts
        });
    }

    var flowEl = document.getElementById('accTxChartFlow');
    if (flowEl) {
        new Chart(flowEl, {
            type: 'bar',
            data: {
                labels: ['Inflow', 'Outflow'],
                datasets: [{
                    data: [payload.inflow || 0, payload.outflow || 0],
                    backgroundColor: ['rgba(5, 150, 105, 0.8)', 'rgba(220, 38, 38, 0.8)'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { grid: { color: '#f1f5f9' } } }
            }
        });
    }

    var trendEl = document.getElementById('accTxChartTrend');
    if (trendEl) {
        var monthly = payload.monthly || [];
        new Chart(trendEl, {
            type: 'line',
            data: {
                labels: monthly.map(function (m) { return m.ym; }),
                datasets: [
                    {
                        label: 'Transactions',
                        data: monthly.map(function (m) { return m.count; }),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        fill: true,
                        tension: 0.35,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Inflow',
                        data: monthly.map(function (m) { return m.inflow; }),
                        borderColor: '#059669',
                        tension: 0.3,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'Outflow',
                        data: monthly.map(function (m) { return m.outflow; }),
                        borderColor: '#dc2626',
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
                scales: {
                    y: { position: 'left', title: { display: true, text: 'Count', font: { size: 10 } } },
                    y1: { position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Amount', font: { size: 10 } } }
                }
            }
        });
    }
})();
