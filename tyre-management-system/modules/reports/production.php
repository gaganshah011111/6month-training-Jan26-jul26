<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Super Admin','Production Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));

$prod = 0; $rawUsed = 0.0; $activeMachines = 0;
try {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(output_quantity),0), COALESCE(SUM(material_used_qty),0) FROM production WHERE production_date BETWEEN :f AND :t');
    $stmt->execute(['f' => $from, 't' => $to]);
    $row = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
    $prod = (int)($row[0] ?? 0);
    $rawUsed = (float)($row[1] ?? 0);
    $activeMachines = (int)$pdo->query("SELECT COUNT(*) FROM machines WHERE status='Active'")->fetchColumn();
} catch (Throwable $e) {
    set_flash('danger', 'Production report failed: ' . $e->getMessage());
}
?>
<h4>Production Reports</h4>
<form class="row g-2 mb-3"><input type="hidden" name="page" value="reports/production"><div class="col-md-3"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div><div class="col-md-3"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Apply</button></div></form>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="card"><div class="card-body"><small>Output Qty</small><h3><?= e((string)$prod) ?></h3></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><small>Raw Used</small><h3><?= e((string)$rawUsed) ?></h3></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><small>Active Machines</small><h3><?= e((string)$activeMachines) ?></h3></div></div></div></div>
