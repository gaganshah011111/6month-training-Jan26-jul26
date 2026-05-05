<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $stmt=$pdo->prepare('UPDATE settings SET setting_value=:v WHERE setting_key=:k');
 $stmt->execute(['k'=>'company_name','v'=>$_POST['company_name']]);
}
$current=$pdo->query("SELECT setting_value FROM settings WHERE setting_key='company_name' LIMIT 1")->fetchColumn();
?>
<h4>Settings</h4>
<form method="post" class="row g-2"><div class="col-md-6"><input class="form-control" name="company_name" value="<?= e((string)$current) ?>"></div><div class="col-md-2"><button class="btn btn-primary w-100">Update</button></div></form>
