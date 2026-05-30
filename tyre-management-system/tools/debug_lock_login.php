<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/admin_users_service.php';

$pdo = Database::connection();
admin_users_ensure_schema($pdo);

echo "=== users.status column ===\n";
print_r($pdo->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch(PDO::FETCH_ASSOC));

echo "\n=== All users status ===\n";
foreach ($pdo->query('SELECT id, full_name, email, username, status FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo sprintf("#%d %-25s email=%-28s user=%-15s status=[%s]\n",
        $r['id'], $r['full_name'], $r['email'] ?? '-', $r['username'] ?? '-', $r['status']);
}

echo "\n=== Lock test on employee@ralson.local ===\n";
$st = $pdo->prepare("SELECT id, status FROM users WHERE email = :e LIMIT 1");
$st->execute(['e' => 'employee@ralson.local']);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "User not found\n";
    exit(1);
}
$id = (int)$row['id'];
$pdo->prepare("UPDATE users SET status = 'active' WHERE id = :id")->execute(['id' => $id]);

try {
    $result = admin_user_set_status($pdo, $id, 'locked', true);
    echo "Lock result: old={$result['old']} new={$result['new']}\n";
} catch (Throwable $e) {
    echo "Lock FAILED: " . $e->getMessage() . "\n";
}

$st->execute(['e' => 'employee@ralson.local']);
$dbStatus = (string)$st->fetchColumn(1);
echo "DB status after lock: [$dbStatus]\n";

echo "\n=== Simulate login.php status check ===\n";
$st2 = $pdo->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
$st2->execute(['e' => 'employee@ralson.local']);
$user = $st2->fetch(PDO::FETCH_ASSOC);
$statusValue = strtolower(trim((string)($user['status'] ?? 'active')));
echo "statusValue=$statusValue\n";
if ($statusValue === 'locked') {
    echo "LOGIN WOULD BE DENIED (locked)\n";
} elseif ($statusValue !== 'active' && $statusValue !== '1' && $user['status'] !== 1) {
    echo "LOGIN WOULD BE DENIED (inactive/other: $statusValue)\n";
} else {
    echo "LOGIN WOULD BE ALLOWED\n";
}

$pdo->prepare("UPDATE users SET status = 'active' WHERE id = :id")->execute(['id' => $id]);
echo "\nRestored employee to active\n";
