<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt=$pdo->prepare('INSERT INTO dispatch(order_no,customer_name,invoice_no,dispatch_date,qty,dispatch_status,tracking_no) VALUES(:o,:c,:i,:d,:q,:s,:t)');
    $stmt->execute(['o'=>$_POST['order_no'],'c'=>$_POST['customer_name'],'i'=>$_POST['invoice_no'],'d'=>$_POST['dispatch_date'],'q'=>(int)$_POST['qty'],'s'=>$_POST['dispatch_status'],'t'=>$_POST['tracking_no']]);
    $pdo->prepare('UPDATE inventory SET qty = GREATEST(qty - :q,0) ORDER BY id DESC LIMIT 1')->execute(['q'=>(int)$_POST['qty']]);
}
$rows=$pdo->query('SELECT * FROM dispatch ORDER BY id DESC LIMIT 100')->fetchAll();
?>
<h4>Dispatch & Sales</h4>
<form method="post" class="row g-2 mb-3"><div class="col"><input class="form-control" name="order_no" placeholder="Order no" required></div><div class="col"><input class="form-control" name="customer_name" placeholder="Customer" required></div><div class="col"><input class="form-control" name="invoice_no" placeholder="Invoice" required></div><div class="col"><input class="form-control" type="date" name="dispatch_date" required></div><div class="col"><input class="form-control" type="number" name="qty" placeholder="Qty" required></div><div class="col"><select class="form-select" name="dispatch_status"><option>Created</option><option>In Transit</option><option>Delivered</option></select></div><div class="col"><input class="form-control" name="tracking_no" placeholder="Tracking"></div><div class="col"><button class="btn btn-primary w-100">Dispatch</button></div></form>
<table class="table table-sm"><tr><th>Order</th><th>Customer</th><th>Invoice</th><th>Date</th><th>Qty</th><th>Status</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['order_no']) ?></td><td><?= e($r['customer_name']) ?></td><td><?= e($r['invoice_no']) ?></td><td><?= e($r['dispatch_date']) ?></td><td><?= e((string)$r['qty']) ?></td><td><?= e($r['dispatch_status']) ?></td></tr><?php endforeach; ?></table>
