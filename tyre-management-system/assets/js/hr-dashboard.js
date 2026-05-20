(function () {
    'use strict';

    var dataEl = document.getElementById('hrDashChartData');
    if (!dataEl || typeof Chart === 'undefined') return;

    var data;
    try {
        data = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        return;
    }

    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    var red = '#b91c1c';
    var redLight = 'rgba(185, 28, 28, 0.15)';
    var navy = '#1e293b';
    var green = '#16a34a';
    var amber = '#d97706';
    var slate = '#94a3b8';

    var att = data.attendance || {};
    var attCanvas = document.getElementById('chartAttendance');
    if (attCanvas && att.labels) {
        new Chart(attCanvas, {
            type: 'line',
            data: {
                labels: att.labels,
                datasets: [
                    {
                        label: 'Present',
                        data: att.present || [],
                        borderColor: green,
                        backgroundColor: 'rgba(22, 163, 74, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        borderWidth: 2,
                    },
                    {
                        label: 'Absent',
                        data: att.absent || [],
                        borderColor: red,
                        backgroundColor: redLight,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        borderWidth: 2,
                    },
                    {
                        label: 'Leave',
                        data: att.leave || [],
                        borderColor: amber,
                        backgroundColor: 'rgba(217, 119, 6, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { boxWidth: 8, usePointStyle: true } },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } },
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                },
            },
        });
    }

    var dept = data.departments || {};
    var deptCanvas = document.getElementById('chartDepartments');
    if (deptCanvas && dept.labels && dept.labels.length) {
        var palette = ['#b91c1c', '#1e293b', '#ea580c', '#2563eb', '#7c3aed', '#0891b2', '#65a30d', '#db2777'];
        new Chart(deptCanvas, {
            type: 'doughnut',
            data: {
                labels: dept.labels,
                datasets: [{
                    data: dept.values,
                    backgroundColor: dept.labels.map(function (_, i) { return palette[i % palette.length]; }),
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 8, font: { size: 10 } } },
                },
            },
        });
    }

    var pay = data.payroll || {};
    var payCanvas = document.getElementById('chartPayroll');
    if (payCanvas && pay.labels && pay.labels.length) {
        new Chart(payCanvas, {
            type: 'bar',
            data: {
                labels: pay.labels,
                datasets: [
                    { label: 'Gross', data: pay.gross || [], backgroundColor: navy, borderRadius: 4 },
                    { label: 'OT', data: pay.ot || [], backgroundColor: amber, borderRadius: 4 },
                    { label: 'Deductions', data: pay.deductions || [], backgroundColor: slate, borderRadius: 4 },
                    { label: 'Net', data: pay.net || [], backgroundColor: red, borderRadius: 4 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 8 } } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                },
            },
        });
    }

    var leave = data.leave || {};
    var leaveCanvas = document.getElementById('chartLeave');
    if (leaveCanvas) {
        new Chart(leaveCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Paid days', 'Half paid', 'Unpaid', 'Pending reqs'],
                datasets: [{
                    data: [
                        leave.paid || 0,
                        leave.half_paid || 0,
                        leave.unpaid || 0,
                        leave.pending || 0,
                    ],
                    backgroundColor: ['#16a34a', '#ea580c', '#b91c1c', '#eab308'],
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 10 } } },
                },
            },
        });
    }
})();
