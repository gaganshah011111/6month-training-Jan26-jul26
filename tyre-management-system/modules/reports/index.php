<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
$prod=(int)$pdo->query('SELECT COALESCE(SUM(output_quantity),0) FROM production')->fetchColumn();
$pass=(int)$pdo->query('SELECT COALESCE(SUM(passed_qty),0) FROM quality_checks')->fetchColumn();
$dispatch=(int)$pdo->query('SELECT COALESCE(SUM(qty),0) FROM dispatch')->fetchColumn();
$emp=(int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
?>
<h4>Reports & Analytics</h4>
<div class="row g-3 mb-3"><div class="col-md-3"><div class="card"><div class="card-body"><small>Total Production</small><h3><?= e((string)$prod) ?></h3></div></div></div><div class="col-md-3"><div class="card"><div class="card-body"><small>QC Passed</small><h3><?= e((string)$pass) ?></h3></div></div></div><div class="col-md-3"><div class="card"><div class="card-body"><small>Dispatched</small><h3><?= e((string)$dispatch) ?></h3></div></div></div><div class="col-md-3"><div class="card"><div class="card-body"><small>Employees</small><h3><?= e((string)$emp) ?></h3></div></div></div></div>
<canvas id="mainReportChart" height="80"></canvas>
<script>
new Chart(document.getElementById('mainReportChart'), {type:'bar',data:{labels:['Production','QC Passed','Dispatch','Employees'],datasets:[{label:'ERP Metrics',data:[<?= $prod ?>,<?= $pass ?>,<?= $dispatch ?>,<?= $emp ?>],backgroundColor:['#0d6efd','#198754','#ffc107','#6f42c1']}]}});
</script>
