<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';

if (has_role(['Inventory Manager', 'Super Admin', 'Admin'])) {
    header('Location: index.php?page=' . rawurlencode('inventory/suppliers'));
    exit;
}

require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if (!has_role(['Super Admin', 'Production Manager'])) {
    echo 'Access denied';
    return;
}
// Legacy production-manager supplier form — redirect inventory roles above
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO suppliers(name,contact_person,phone,email,address) VALUES(:n,:c,:p,:e,:a)');
    $stmt->execute([
        'n' => post_string('name', 150),
        'c' => post_string('contact_person', 100),
        'p' => post_string('phone', 20),
        'e' => post_string('email', 120),
        'a' => post_string('address'),
    ]);
    set_flash('success', 'Supplier added.');
    redirect('suppliers/list');
}
$rows = $pdo->query('SELECT * FROM suppliers ORDER BY id DESC LIMIT 100')->fetchAll();
?>
<h4>Suppliers</h4>
<p class="small text-muted">Use <a href="<?= e(route_url('inventory/suppliers')) ?>">Inventory → Suppliers</a> for full supplier management.</p>
<form method="post" class="row g-2 mb-3"><?= csrf_input() ?>
<div class="col"><input class="form-control" name="name" placeholder="Supplier" required></div>
<div class="col"><input class="form-control" name="contact_person" placeholder="Contact"></div>
<div class="col"><input class="form-control" name="phone" placeholder="Phone"></div>
<div class="col"><input class="form-control" name="email" placeholder="Email"></div>
<div class="col"><input class="form-control" name="address" placeholder="Address"></div>
<div class="col"><button class="btn btn-primary w-100">Add</button></div>
</form>
<table class="table table-sm"><tr><th>Name</th><th>Person</th><th>Phone</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['contact_person']) ?></td><td><?= e($r['phone']) ?></td></tr><?php endforeach; ?>
</table>
