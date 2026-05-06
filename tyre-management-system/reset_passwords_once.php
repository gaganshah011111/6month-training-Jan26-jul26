<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

// Simple protection so this cannot be run accidentally.
$setupKey = 'RESET_2026_RALSON';
$providedKey = (string)($_GET['key'] ?? '');

if (!hash_equals($setupKey, $providedKey)) {
    http_response_code(403);
    exit('Forbidden. Missing or invalid key.');
}

try {
    $pdo = Database::connection();
    $pdo->beginTransaction();

    $users = [
        'superadmin@ralson.local' => 'Super@123',
        'hr@ralson.local' => 'HR@123',
        'production@ralson.local' => 'Production@123',
        'inventory@ralson.local' => 'Inventory@123',
        'dispatch@ralson.local' => 'Dispatch@123',
        'quality@ralson.local' => 'Quality@123',
        'employee@ralson.local' => 'Emp@123',
    ];

    $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash, status = :status WHERE email = :email');

    foreach ($users as $email => $plainPassword) {
        $stmt->execute([
            'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'status' => 'active',
            'email' => $email,
        ]);
    }

    $pdo->commit();

    echo 'Passwords reset successfully. Script will delete itself now.';

    // Delete this script after successful one-time use.
    @unlink(__FILE__);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo 'Password reset failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
