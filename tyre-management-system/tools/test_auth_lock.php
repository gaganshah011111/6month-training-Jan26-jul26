<?php
declare(strict_types=1);
/**
 * End-to-end lock/login auth test. Run: php tools/test_auth_lock.php
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/admin_users_service.php';
require __DIR__ . '/../includes/user_account_status.php';

$pdo = Database::connection();
admin_users_ensure_schema($pdo);

$email = 'employee@ralson.local';
$st = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
$st->execute(['e' => $email]);
$id = (int)$st->fetchColumn();
if ($id <= 0) {
    fwrite(STDERR, "Test user not found\n");
    exit(1);
}

$fail = 0;
function ok(bool $cond, string $msg): void
{
    global $fail;
    if (!$cond) {
        echo "FAIL: $msg\n";
        $fail++;
    } else {
        echo "OK: $msg\n";
    }
}

$pdo->prepare("UPDATE users SET status = 'active', force_logout_at = NULL WHERE id = :id")->execute(['id' => $id]);

$result = admin_user_set_status($pdo, $id, 'locked', true);
ok($result['new'] === 'locked', 'lock sets status locked');
$dbStatus = auth_fetch_user_status_by_id($pdo, $id);
ok($dbStatus === 'locked', 'DB read returns locked after lock');
ok(!auth_login_is_permitted((string)$dbStatus), 'locked user cannot login');

$block = auth_evaluate_login_user($pdo, ['id' => $id, 'email' => $email], $email);
ok($block !== null && str_contains($block, 'locked'), 'login evaluation denies locked user');

$result = admin_user_set_status($pdo, $id, 'active');
ok($result['new'] === 'active', 'unlock sets status active');
$dbStatus = auth_fetch_user_status_by_id($pdo, $id);
ok($dbStatus === 'active', 'DB read returns active after unlock');
ok(auth_login_is_permitted((string)$dbStatus), 'active user can login');

$block = auth_evaluate_login_user($pdo, ['id' => $id, 'email' => $email], $email);
ok($block === null, 'login evaluation allows active user');

$result = admin_user_set_status($pdo, $id, 'inactive', true);
ok($result['new'] === 'inactive', 'deactivate sets inactive');
$block = auth_evaluate_login_user($pdo, ['id' => $id, 'email' => $email], $email);
ok($block !== null && str_contains(strtolower($block), 'inactive'), 'login evaluation denies inactive user');

$pdo->prepare("UPDATE users SET status = 'active', force_logout_at = NULL WHERE id = :id")->execute(['id' => $id]);

echo $fail === 0 ? "ALL AUTH TESTS PASSED\n" : "FAILED: $fail\n";
exit($fail > 0 ? 1 : 0);
