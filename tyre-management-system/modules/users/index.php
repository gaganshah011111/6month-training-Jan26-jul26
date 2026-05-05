<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!has_role(['Super Admin', 'Admin'])) { echo 'Access denied'; return; }
$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $stmt = $pdo->prepare('INSERT INTO users(full_name,email,password_hash,role,status) VALUES(:n,:e,:p,:r,"active")');
        $stmt->execute([
            'n' => trim($_POST['full_name']),
            'e' => trim($_POST['email']),
            'p' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'r' => $_POST['role'],
        ]);
    }
    if (isset($_POST['toggle_status'])) {
        $stmt = $pdo->prepare('UPDATE users SET status = IF(status="active","inactive","active") WHERE id = :id');
        $stmt->execute(['id' => (int)$_POST['user_id']]);
    }
}

$users = $pdo->query('SELECT id, full_name, email, role, status, created_at FROM users ORDER BY id DESC')->fetchAll();
?>
<h4>Users Management</h4>
<form method="post" class="row g-2 mb-3">
    <div class="col-md-3"><input class="form-control" name="full_name" placeholder="Full name" required></div>
    <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
    <div class="col-md-2"><input class="form-control" type="password" name="password" placeholder="Password" required></div>
    <div class="col-md-2">
        <select class="form-select" name="role">
            <option>Super Admin</option><option>Admin</option><option>Employee</option>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-primary w-100" name="create_user">Add User</button></div>
</form>
<div class="table-responsive">
<table class="table table-sm table-striped">
    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($users as $u): ?><tr>
        <td><?= e((string)$u['id']) ?></td><td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td>
        <td><?= e($u['role']) ?></td><td><?= e($u['status']) ?></td>
        <td><form method="post"><input type="hidden" name="user_id" value="<?= e((string)$u['id']) ?>"><button class="btn btn-sm btn-outline-secondary" name="toggle_status">Toggle</button></form></td>
    </tr><?php endforeach; ?>
    </tbody>
</table>
</div>

