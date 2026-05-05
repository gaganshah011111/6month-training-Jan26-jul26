<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_auth(['Admin', 'Super Admin']);
$pdo = Database::connection();

$kpis = [
    'Employees' => (int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn(),
    'Raw Materials' => (int)$pdo->query('SELECT COUNT(*) FROM raw_materials')->fetchColumn(),
    'Today Production' => (int)$pdo->query('SELECT COALESCE(SUM(output_quantity),0) FROM production WHERE production_date = CURDATE()')->fetchColumn(),
    'Pending Dispatch' => (int)$pdo->query("SELECT COUNT(*) FROM dispatch WHERE dispatch_status IN ('Created','In Transit')")->fetchColumn(),
];
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-4">
    <h2>Admin Dashboard</h2>
    <a class="btn btn-sm btn-outline-primary mb-3" href="index.php?page=dashboard">Open Full ERP Panel</a>
    <div class="row g-3">
        <?php foreach ($kpis as $title => $value): ?>
            <div class="col-md-3"><div class="card"><div class="card-body"><small><?= e($title) ?></small><h3><?= e((string)$value) ?></h3></div></div></div>
        <?php endforeach; ?>
    </div>
</div>
</body></html>

