<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if (!has_role(['Super Admin','HR Manager','Production Manager','Inventory Manager'])) { echo 'Access denied'; return; }
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$reportType = (string)($_GET['report'] ?? 'hr');
$attendance = 0; $payroll = 0.0; $prod = 0; $pass = 0; $fail = 0; $dispatch = 0; $lowStock = 0;
try {
    $attStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date BETWEEN :f AND :t");
    $attStmt->execute(['f'=>$from,'t'=>$to]);
    $attendance = (int)$attStmt->fetchColumn();

    $payStmt = $pdo->prepare("SELECT COALESCE(SUM(net_salary),0) FROM salaries WHERE month_year BETWEEN DATE_FORMAT(:f, '%Y-%m') AND DATE_FORMAT(:t, '%Y-%m')");
    $payStmt->execute(['f'=>$from,'t'=>$to]);
    $payroll = (float)$payStmt->fetchColumn();

    $prodStmt = $pdo->prepare("SELECT COALESCE(SUM(output_quantity),0) FROM production WHERE production_date BETWEEN :f AND :t");
    $prodStmt->execute(['f'=>$from,'t'=>$to]);
    $prod=(int)$prodStmt->fetchColumn();

    $qcStmt = $pdo->prepare("SELECT COALESCE(SUM(passed_qty),0), COALESCE(SUM(failed_qty),0) FROM quality_checks WHERE inspection_date BETWEEN :f AND :t");
    $qcStmt->execute(['f'=>$from,'t'=>$to]);
    $qc = $qcStmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
    $pass=(int)($qc[0] ?? 0);
    $fail=(int)($qc[1] ?? 0);

    $dispatchStmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM dispatch WHERE dispatch_date BETWEEN :f AND :t");
    $dispatchStmt->execute(['f'=>$from,'t'=>$to]);
    $dispatch=(int)$dispatchStmt->fetchColumn();

    $lowStock=(int)$pdo->query('SELECT COUNT(*) FROM inventory WHERE qty <= reorder_level')->fetchColumn();
} catch (Throwable $e) {
    set_flash('danger', 'Report load failed: ' . $e->getMessage());
}
?>
<h4>Reports & Analytics</h4>
<form class="row g-2 mb-3"><input type="hidden" name="page" value="reports/<?= e($reportType) ?>"><div class="col-md-3"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div><div class="col-md-3"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div><div class="col-md-3"><select class="form-select" name="report" onchange="this.form.page.value='reports/'+this.value"><option value="hr" <?= $reportType==='hr'?'selected':'' ?>>HR</option><option value="production" <?= $reportType==='production'?'selected':'' ?>>Production</option><option value="inventory" <?= $reportType==='inventory'?'selected':'' ?>>Inventory</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Apply</button></div></form>
<div class="row g-3 mb-3"><div class="col-md-3"><div class="card"><div class="card-body"><small>Attendance Records</small><h3><?= e((string)$attendance) ?></h3></div></div></div><div class="col-md-3"><div class="card"><div class="card-body"><small>Payroll Generated</small><h3><?= e((string)$payroll) ?></h3></div></div></div><div class="col-md-3"><div class="card"><div class="card-body"><small>Total Production</small><h3><?= e((string)$prod) ?></h3></div></div></div><div class="col-md-3"><div class="card"><div class="card-body"><small>Dispatched Qty</small><h3><?= e((string)$dispatch) ?></h3></div></div></div></div>
<canvas id="mainReportChart" height="80"></canvas>
<script>
new Chart(document.getElementById('mainReportChart'), {type:'bar',data:{labels:['Attendance','Payroll','Production','QC Passed','QC Failed','Dispatch','Low Stock'],datasets:[{label:'ERP Metrics',data:[<?= $attendance ?>,<?= (int)$payroll ?>,<?= $prod ?>,<?= $pass ?>,<?= $fail ?>,<?= $dispatch ?>,<?= $lowStock ?>],backgroundColor:['#0d6efd','#6610f2','#198754','#20c997','#dc3545','#ffc107','#fd7e14']}]}});
</script>
