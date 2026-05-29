(function () {
    'use strict';

    var form = document.getElementById('accReportsFilterForm');
    if (form) {
        var debounceTimer;
        function submitFilters() {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }

        ['from', 'to', 'department', 'report'].forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            el.addEventListener('change', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(submitFilters, 250);
            });
        });
    }

    var printBtn = document.getElementById('accReportPrint');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            var params = new URLSearchParams(window.location.search);
            params.set('page', 'accounts/reports');
            params.set('export', 'print');
            window.open('index.php?' + params.toString(), '_blank');
        });
    }

    var dataEl = document.getElementById('accReportsChartData');
    if (!dataEl || typeof Chart === 'undefined') {
        return;
    }

    var payload;
    try {
        payload = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        return;
    }

    var monthly = payload.monthly || [];
    var labels = monthly.map(function (m) { return m.ym; });
    var revenue = monthly.map(function (m) { return m.revenue; });
    var expense = monthly.map(function (m) { return m.expense; });
    var profit = monthly.map(function (m) { return m.profit; });

    var chartDefaults = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            y: { ticks: { font: { size: 10 } }, grid: { color: '#f1f5f9' } }
        }
    };

    var revExp = document.getElementById('accChartRevExp');
    if (revExp) {
        new Chart(revExp, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Revenue', data: revenue, backgroundColor: 'rgba(5, 150, 105, 0.75)', borderRadius: 4 },
                    { label: 'Expenses', data: expense, backgroundColor: 'rgba(220, 38, 38, 0.75)', borderRadius: 4 }
                ]
            },
            options: chartDefaults
        });
    }

    var profitEl = document.getElementById('accChartProfit');
    if (profitEl) {
        new Chart(profitEl, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Net Profit',
                    data: profit,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3
                }]
            },
            options: chartDefaults
        });
    }

    var expenseEl = document.getElementById('accChartExpense');
    if (expenseEl) {
        var breakdown = payload.expense_breakdown || [];
        new Chart(expenseEl, {
            type: 'doughnut',
            data: {
                labels: breakdown.map(function (b) { return b.category; }),
                datasets: [{
                    data: breakdown.map(function (b) { return b.amount; }),
                    backgroundColor: [
                        '#2563eb', '#dc2626', '#059669', '#ea580c', '#7c3aed',
                        '#0d9488', '#f59e0b', '#64748b', '#ec4899', '#14b8a6'
                    ],
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } }
            }
        });
    }

    var recvPay = document.getElementById('accChartRecvPay');
    if (recvPay) {
        var rp = payload.receivable_vs_payable || {};
        new Chart(recvPay, {
            type: 'bar',
            data: {
                labels: ['Receivables', 'Payables'],
                datasets: [{
                    label: 'Amount',
                    data: [rp.receivable || 0, rp.payable || 0],
                    backgroundColor: ['rgba(234, 88, 12, 0.8)', 'rgba(124, 58, 237, 0.8)'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: '#f1f5f9' } }
                }
            }
        });
    }

    var cashEl = document.getElementById('accChartCash');
    if (cashEl) {
        var cf = payload.cash_flow || {};
        new Chart(cashEl, {
            type: 'line',
            data: {
                labels: cf.labels || [],
                datasets: [{
                    label: 'Closing Balance',
                    data: cf.values || [],
                    borderColor: '#0d9488',
                    backgroundColor: 'rgba(13, 148, 136, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2
                }]
            },
            options: chartDefaults
        });
    }
})();
