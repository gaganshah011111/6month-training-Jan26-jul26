<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if (!has_role(['Super Admin','Quality Manager'])) { echo 'Access denied'; return; }
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $pdo->beginTransaction();
 try {
    $prodId = post_int('production_id');
    $passed = post_int('passed_qty');
    $failed = post_int('failed_qty');
    $status = $failed > 0 ? 'Fail' : 'Pass';
    $stmt=$pdo->prepare('INSERT INTO quality_checks(production_id,inspection_date,inspector_name,passed_qty,failed_qty,quality_status,defects) VALUES(:p,:d,:i,:pa,:f,:qs,:de)');
    $stmt->execute(['p'=>$prodId,'d'=>$_POST['inspection_date'],'i'=>post_string('inspector_name',150),'pa'=>$passed,'f'=>$failed,'qs'=>$status,'de'=>post_string('defects')]);
    $qcId = (int)$pdo->lastInsertId();
    $batchRef = 'PRD-' . $prodId;

    $existsStmt = $pdo->prepare('SELECT id FROM inventory WHERE batch_ref=:batch LIMIT 1');
    $existsStmt->execute(['batch' => $batchRef]);
    $existing = $existsStmt->fetch();
    if ($existing) {
        $pdo->prepare('UPDATE inventory SET qty = qty + :q, warehouse_location=:w WHERE id=:id')->execute(['q' => $passed, 'w' => post_string('warehouse_location',120), 'id' => (int)$existing['id']]);
    } else {
        $pdo->prepare('INSERT INTO inventory(product_name,batch_ref,qty,reorder_level,warehouse_location) VALUES("Tyre",:b,:q,50,:w)')->execute(['b'=>$batchRef,'q'=>$passed,'w'=>post_string('warehouse_location',120)]);
    }

    if ($failed > 0) {
        $pdo->prepare('INSERT INTO defect_logs(quality_check_id, production_id, failed_qty, defect_notes) VALUES(:qc,:p,:f,:d)')->execute(['qc' => $qcId, 'p' => $prodId, 'f' => $failed, 'd' => post_string('defects')]);
    }
    $pdo->commit();
    set_flash('success', 'Quality inspection recorded and inventory updated.');
 } catch (Throwable $e) {
    $pdo->rollBack();
    set_flash('danger', 'Quality submission failed: ' . $e->getMessage());
 }
 redirect('quality/list');
}
$production=$pdo->query('SELECT id, production_date, output_quantity FROM production ORDER BY id DESC LIMIT 100')->fetchAll();
$rows=$pdo->query('SELECT q.*, p.production_date FROM quality_checks q JOIN production p ON p.id=q.production_id ORDER BY q.id DESC LIMIT 100')->fetchAll();
?>
<h4>Quality Control</h4>
<?php if (empty($production)): ?>
<div class="alert alert-warning">No production batch found. Add production entries before quality inspection.</div>
<?php else: ?>
<form method="post" class="row g-2 mb-3"><?= csrf_input() ?><div class="col"><select class="form-select" name="production_id" required><?php foreach($production as $p): ?><option value="<?= $p['id'] ?>">#<?= $p['id'] ?> - <?= e($p['production_date']) ?> (<?= e((string)$p['output_quantity']) ?>)</option><?php endforeach; ?></select></div><div class="col"><input class="form-control" type="date" name="inspection_date" required></div><div class="col"><input class="form-control" name="inspector_name" placeholder="Inspector" required></div><div class="col"><input class="form-control" type="number" name="passed_qty" placeholder="Pass" required></div><div class="col"><input class="form-control" type="number" name="failed_qty" placeholder="Fail" required></div><div class="col"><input class="form-control" name="warehouse_location" placeholder="Warehouse" required></div><div class="col"><input class="form-control" name="defects" placeholder="Defects"></div><div class="col"><button class="btn btn-primary w-100">Inspect</button></div></form>
<?php endif; ?>
<table class="table table-sm"><tr><th>Production</th><th>Date</th><th>Pass</th><th>Fail</th><th>Status</th><th>Defects</th></tr><?php foreach($rows as $r): ?><tr><td>#<?= e((string)$r['production_id']) ?></td><td><?= e($r['inspection_date']) ?></td><td><?= e((string)$r['passed_qty']) ?></td><td><?= e((string)$r['failed_qty']) ?></td><td><?= e((string)($r['quality_status'] ?? '')) ?></td><td><?= e($r['defects']) ?></td></tr><?php endforeach; ?></table>
