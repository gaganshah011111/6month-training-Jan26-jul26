<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if (!has_role(['Super Admin'])) { echo 'Access denied'; return; }
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $stmt=$pdo->prepare('UPDATE settings SET setting_value=:v WHERE setting_key=:k');
 $stmt->execute(['k'=>'company_name','v'=>post_string('company_name')]);
 set_flash('success', 'Profile settings updated.');
 redirect('settings/profile');
}
$current=$pdo->query("SELECT setting_value FROM settings WHERE setting_key='company_name' LIMIT 1")->fetchColumn();
?>
<h4>Settings / Profile</h4>
<form method="post" class="row g-2"><?= csrf_input() ?><div class="col-md-6"><input class="form-control" name="company_name" value="<?= e((string)$current) ?>"></div><div class="col-md-2"><button class="btn btn-primary w-100">Update</button></div></form>
