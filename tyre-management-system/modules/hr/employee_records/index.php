<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
$pdo = Database::connection();
$rows = $pdo->query('SELECT employee_code, full_name, department, joining_date FROM employees ORDER BY id DESC')->fetchAll();
?>
<h4>Employee Records</h4>
<p class="text-muted">Use this section for documents and promotion history extensions.</p>
<table class="table table-sm"><tr><th>Code</th><th>Name</th><th>Department</th><th>Joining Date</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['employee_code']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['department']) ?></td><td><?= e($r['joining_date']) ?></td></tr><?php endforeach; ?>
</table>

