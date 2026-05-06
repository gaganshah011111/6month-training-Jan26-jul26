<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if (!has_role(['Super Admin','Dispatch Manager'])) { echo 'Access denied'; return; }
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $inventoryId = post_int('inventory_id');
        $qty = post_int('qty');
        $invStmt = $pdo->prepare('SELECT qty FROM inventory WHERE id=:id FOR UPDATE');
        $invStmt->execute(['id' => $inventoryId]);
        $inv = $invStmt->fetch();
        if (!$inv || (int)$inv['qty'] < $qty) {
            throw new RuntimeException('Insufficient inventory stock.');
        }

        $stmt=$pdo->prepare('INSERT INTO dispatch(inventory_id,order_no,customer_name,invoice_no,dispatch_date,qty,dispatch_status,tracking_no) VALUES(:inv,:o,:c,:i,:d,:q,:s,:t)');
        $stmt->execute(['inv'=>$inventoryId,'o'=>post_string('order_no',60),'c'=>post_string('customer_name',150),'i'=>post_string('invoice_no',80),'d'=>$_POST['dispatch_date'],'q'=>$qty,'s'=>post_string('dispatch_status',20),'t'=>post_string('tracking_no',80)]);
        $pdo->prepare('UPDATE inventory SET qty = qty - :q WHERE id=:id')->execute(['q'=>$qty,'id'=>$inventoryId]);
        $pdo->commit();
        set_flash('success', 'Dispatch created and inventory reduced.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        set_flash('danger', 'Dispatch failed: ' . $e->getMessage());
    }
    redirect('dispatch/list');
}
$rows=$pdo->query('SELECT d.*, COALESCE(i.batch_ref, "N/A") AS batch_ref FROM dispatch d LEFT JOIN inventory i ON i.id=d.inventory_id ORDER BY d.id DESC LIMIT 100')->fetchAll();
$stocks = $pdo->query('SELECT id, product_name, batch_ref, qty FROM inventory WHERE qty > 0 ORDER BY id DESC')->fetchAll();
?>
<h4>Dispatch & Sales</h4>
<form method="post" class="row g-2 mb-3"><?= csrf_input() ?><div class="col"><select class="form-select" name="inventory_id" required><option value="">Select Batch</option><?php foreach($stocks as $stock): ?><option value="<?= (int)$stock['id'] ?>"><?= e($stock['product_name'] . ' / ' . $stock['batch_ref'] . ' / Qty: ' . $stock['qty']) ?></option><?php endforeach; ?></select></div><div class="col"><input class="form-control" name="order_no" placeholder="Order no" required></div><div class="col"><input class="form-control" name="customer_name" placeholder="Customer" required></div><div class="col"><input class="form-control" name="invoice_no" placeholder="Invoice" required></div><div class="col"><input class="form-control" type="date" name="dispatch_date" required></div><div class="col"><input class="form-control" type="number" name="qty" placeholder="Qty" required></div><div class="col"><select class="form-select" name="dispatch_status"><option>Created</option><option>In Transit</option><option>Delivered</option></select></div><div class="col"><input class="form-control" name="tracking_no" placeholder="Tracking"></div><div class="col"><button class="btn btn-primary w-100">Dispatch</button></div></form>
<table class="table table-sm"><tr><th>Order</th><th>Batch</th><th>Customer</th><th>Invoice</th><th>Date</th><th>Qty</th><th>Status</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['order_no']) ?></td><td><?= e($r['batch_ref']) ?></td><td><?= e($r['customer_name']) ?></td><td><?= e($r['invoice_no']) ?></td><td><?= e($r['dispatch_date']) ?></td><td><?= e((string)$r['qty']) ?></td><td><?= e($r['dispatch_status']) ?></td></tr><?php endforeach; ?></table>
