<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Dispatch Manager','Super Admin'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
$today = (int)$pdo->query('SELECT COUNT(*) FROM dispatch WHERE dispatch_date = CURDATE()')->fetchColumn();
$pending = (int)$pdo->query("SELECT COUNT(*) FROM dispatch WHERE dispatch_status IN ('Created','In Transit')")->fetchColumn();
$delivered = (int)$pdo->query("SELECT COUNT(*) FROM dispatch WHERE dispatch_status='Delivered'")->fetchColumn();
?>
<header class="erp-page__top"><div><h1 class="erp-page__title">Dispatch Dashboard<span>Orders & delivery</span></h1></div></header>
<div class="row g-3">
    <div class="col-md-4"><div class="card kpi-card"><div class="card-body"><small>Today Orders</small><h4><?= e((string)$today) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card kpi-card"><div class="card-body"><small>Pending Delivery</small><h4><?= e((string)$pending) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card kpi-card"><div class="card-body"><small>Delivered</small><h4><?= e((string)$delivered) ?></h4></div></div></div>
</div>
