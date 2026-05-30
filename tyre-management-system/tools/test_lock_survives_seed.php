<?php
declare(strict_types=1);
/**
 * Verify lock survives Database::connection() seed (the bug that reset status every request).
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/admin_users_service.php';

$pdo = Database::connection();
$id = (int)$pdo->query("SELECT id FROM users WHERE email='accounts@ralson.local' LIMIT 1")->fetchColumn();

$pdo->prepare("UPDATE users SET status='active', force_logout_at=NULL WHERE id=:id")->execute(['id' => $id]);
admin_user_set_status($pdo, $id, 'locked', true);

$afterLock = (string)$pdo->query("SELECT status FROM users WHERE id=$id")->fetchColumn();
echo "After lock: $afterLock\n";

// Simulate new HTTP request: reset static initialized flag and reconnect
$ref = new ReflectionClass(Database::class);
foreach (['instance', 'initialized'] as $prop) {
    $p = $ref->getProperty($prop);
    $p->setAccessible(true);
    $p->setValue(null, $prop === 'initialized' ? false : null);
}

$pdo2 = Database::connection();
$afterReconnect = (string)$pdo2->query("SELECT status FROM users WHERE id=$id")->fetchColumn();
echo "After reconnect (seed): $afterReconnect\n";

$pdo2->prepare("UPDATE users SET status='active', force_logout_at=NULL WHERE id=:id")->execute(['id' => $id]);

exit($afterLock === 'locked' && $afterReconnect === 'locked' ? 0 : 1);
