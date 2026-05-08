<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$cssPath = __DIR__ . '/assets/css/style.css';
$cssVersion = is_file($cssPath) ? (string)filemtime($cssPath) : (string)time();

if (isset($_SESSION['user'])) {
    $role = (string)($_SESSION['user']['role'] ?? '');
    $target = role_home_page($role);
    if ($target !== 'login.php') {
        header('Location: ' . route_url($target));
        exit;
    }
    logout_user();
}

$error = '';
$debugOutput = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember_me']);
    $isDebugMode = APP_ENV === 'local' && isset($_GET['debug']) && $_GET['debug'] === '1';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($isDebugMode) {
            $debugOutput = [
                'email_from_form' => $email,
                'password_from_form' => $password,
                'db_user_data' => $user ?: null,
            ];
        }

        if (!$user) {
            $error = 'User not found.';
        } else {
            $statusValue = $user['status'] ?? null;
            $isActive = $statusValue === 1
                || $statusValue === '1'
                || (is_string($statusValue) && strtolower(trim($statusValue)) === 'active');

            if (!$isActive) {
                $error = 'Account inactive.';
            } else {
                $dbPassword = (string)($user['password_hash'] ?? $user['password'] ?? '');
                $isHashedPassword = is_string($dbPassword) && preg_match('/^\$2[aby]\$/', $dbPassword) === 1;
                $passwordMatches = $isHashedPassword
                    ? password_verify($password, $dbPassword)
                    : hash_equals($dbPassword, $password);

                if (!$passwordMatches) {
                    $error = 'Wrong password.';
                } else {
                    if (!isset($user['full_name']) && isset($user['name'])) {
                        $user['full_name'] = $user['name'];
                    }

                    login_user($user, $remember);
                    $target = role_home_page((string)($user['role'] ?? ''));
                    if ($target === 'login.php') {
                        logout_user();
                        $error = 'Role is not configured for dashboard access.';
                    } else {
                        header('Location: ' . route_url($target));
                        exit;
                    }
                }
            }
        }
    }

}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ralson ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= e($cssVersion) ?>" rel="stylesheet">
</head>
<body class="app-body d-flex align-items-center" style="min-height: 100vh;">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7 col-sm-10">
            <div class="card login-card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="brand-mark">R</span>
                        <h4 class="mb-0">Ralson ERP Login</h4>
                    </div>
                    <p class="text-muted small mb-3">Sign in to access your ERP dashboard.</p>
                    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                    <?php if (!empty($debugOutput)): ?>
                        <div class="alert alert-warning">
                            <strong>Debug output (temporary):</strong>
                            <pre class="mb-0 mt-2"><?= e((string)json_encode($debugOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                        </div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <div class="input-group login-input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input class="form-control" type="email" name="email" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group login-input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input class="form-control" id="loginPassword" type="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remember_me" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('togglePassword');
    var input = document.getElementById('loginPassword');
    if (!btn || !input) return;
    btn.addEventListener('click', function () {
        var isPassword = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPassword ? 'text' : 'password');
        var icon = btn.querySelector('i');
        if (icon) {
            icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        }
    });
});
</script>
</body>
</html>

