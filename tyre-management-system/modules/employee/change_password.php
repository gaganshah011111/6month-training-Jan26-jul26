<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['Employee']);

$pdo = Database::connection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'All password fields are required.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirm password do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $dbHash = (string)$stmt->fetchColumn();
        $isHash = preg_match('/^\$2[aby]\$/', $dbHash) === 1;
        $isValidCurrent = $isHash ? password_verify($currentPassword, $dbHash) : hash_equals($dbHash, $currentPassword);

        if (!$isValidCurrent) {
            $error = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $updateStmt->execute(['password_hash' => $newHash, 'id' => $userId]);
            $success = 'Password updated successfully.';
        }
    }
}
?>

<h4 class="mb-3">Change Password</h4>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Current Password</label>
                <input class="form-control" type="password" name="current_password" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">New Password</label>
                <input class="form-control" type="password" name="new_password" minlength="8" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Confirm New Password</label>
                <input class="form-control" type="password" name="confirm_password" minlength="8" required>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Update Password</button>
            </div>
        </form>
    </div>
</div>
