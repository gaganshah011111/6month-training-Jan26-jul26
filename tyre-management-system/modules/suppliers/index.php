<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $stmt=$pdo->prepare('INSERT INTO suppliers(name,contact_person,phone,email,address) VALUES(:n,:c,:p,:e,:a)');
 $stmt->execute(['n'=>$_POST['name'],'c'=>$_POST['contact_person'],'p'=>$_POST['phone'],'e'=>$_POST['email'],'a'=>$_POST['address']]);
}
$rows=$pdo->query('SELECT * FROM suppliers ORDER BY id DESC LIMIT 100')->fetchAll();
?>
<h4>Suppliers</h4>
<form method="post" class="row g-2 mb-3"><div class="col"><input class="form-control" name="name" placeholder="Supplier" required></div><div class="col"><input class="form-control" name="contact_person" placeholder="Contact"></div><div class="col"><input class="form-control" name="phone" placeholder="Phone"></div><div class="col"><input class="form-control" name="email" placeholder="Email"></div><div class="col"><input class="form-control" name="address" placeholder="Address"></div><div class="col"><button class="btn btn-primary w-100">Add</button></div></form>
<table class="table table-sm"><tr><th>Name</th><th>Person</th><th>Phone</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['contact_person']) ?></td><td><?= e($r['phone']) ?></td></tr><?php endforeach; ?></table>
