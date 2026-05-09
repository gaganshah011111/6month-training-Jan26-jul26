<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_auth(['Super Admin']);
$pdo = Database::connection();
$kpis = [
    'Total Employees' => (int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn(),
    'Present Today' => (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status IN ('Present','Late','Half Day','Emergency Duty')")->fetchColumn(),
    'Absent Today' => (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'Absent'")->fetchColumn(),
    'Total Raw Materials' => (int)$pdo->query('SELECT COUNT(*) FROM raw_materials')->fetchColumn(),
    'Today Production' => (int)$pdo->query('SELECT COALESCE(SUM(output_quantity),0) FROM production WHERE production_date = CURDATE()')->fetchColumn(),
    'Low Stock Alerts' => (int)$pdo->query('SELECT COUNT(*) FROM raw_materials WHERE stock_qty <= reorder_level')->fetchColumn(),
    'Pending Dispatch' => (int)$pdo->query("SELECT COUNT(*) FROM dispatch WHERE dispatch_status IN ('Created','In Transit')")->fetchColumn(),
    'Leave Requests' => (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Applied'")->fetchColumn(),
    'Machines Active' => (int)$pdo->query("SELECT COUNT(*) FROM machines WHERE status = 'Active'")->fetchColumn(),
];
$todayProductionByShift = $pdo->query("SELECT shift, COALESCE(SUM(output_quantity), 0) AS total FROM production WHERE production_date = CURDATE() GROUP BY shift")->fetchAll();
$shiftLabels = [];
$shiftValues = [];
foreach ($todayProductionByShift as $row) {
    $shiftLabels[] = $row['shift'];
    $shiftValues[] = (int)$row['total'];
}
$recentDispatch = $pdo->query("SELECT order_no, customer_name, dispatch_status FROM dispatch ORDER BY id DESC LIMIT 5")->fetchAll();
$recentLeaves = $pdo->query("SELECT e.full_name, l.from_date, l.to_date, l.status FROM leaves l JOIN employees e ON e.id = l.employee_id ORDER BY l.id DESC LIMIT 5")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Admin ERP Dashboard</h3>
    <div class="small text-muted">Ralson India Private Limited - Tyre Manufacturing ERP</div>
</div>
<div class="row g-3 mb-4">
    <?php foreach ($kpis as $title => $value): ?>
        <div class="col-xl-4 col-lg-6">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="text-muted small"><?= e($title) ?></div>
                    <h4 class="mb-0"><?= e((string)$value) ?></h4>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Today Production by Shift</div>
            <div class="card-body"><canvas id="dashboardProductionChart" height="130"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Recent Leave Requests</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Employee</th><th>Duration</th><th>Status</th></tr></thead>
                    <tbody><?php foreach ($recentLeaves as $leave): ?><tr><td><?= e($leave['full_name']) ?></td><td><?= e($leave['from_date'] . ' to ' . $leave['to_date']) ?></td><td><?= e($leave['status']) ?></td></tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Recent Dispatch Orders</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Order</th><th>Customer</th><th>Status</th></tr></thead>
                    <tbody><?php foreach ($recentDispatch as $dispatch): ?><tr><td><?= e($dispatch['order_no']) ?></td><td><?= e($dispatch['customer_name']) ?></td><td><?= e($dispatch['dispatch_status']) ?></td></tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
new Chart(document.getElementById('dashboardProductionChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($shiftLabels) ?>,
        datasets: [{ label: 'Output Quantity', data: <?= json_encode($shiftValues) ?>, backgroundColor: '#0d6efd' }]
    }
});
</script>

