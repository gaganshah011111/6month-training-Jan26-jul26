<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/admin_security_service.php';
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
$info = '';
if (isset($_GET['logged_out'])) {
    $info = 'You have been logged out by an administrator.';
}
if (isset($_GET['blocked'])) {
    $error = 'Your account is not permitted to sign in.';
}
$debugOutput = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember_me']);
    $isDebugMode = APP_ENV === 'local' && isset($_GET['debug']) && $_GET['debug'] === '1';

    if ($login === '' || $password === '') {
        $error = 'Username (or email) and password are required.';
    } else {
        $pdo = Database::connection();
        $isEmail = str_contains($login, '@');
        if ($isEmail && !filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!$isEmail && !preg_match('/^[a-zA-Z0-9._-]{2,80}$/', $login)) {
            $error = 'Invalid username format.';
        } else {
            if ($isEmail) {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
                $stmt->execute(['e' => $login]);
            } else {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
                $stmt->execute(['u' => $login]);
            }
            $user = $stmt->fetch();

            if ($isDebugMode) {
                $debugOutput = [
                    'login_from_form' => $login,
                    'password_from_form' => $password,
                    'db_user_data' => $user ?: null,
                ];
            }

            if (!$user) {
                admin_record_login($pdo, null, $login, false, 'Account not found');
                $error = 'Account not found.';
            } else {
                $statusValue = strtolower(trim((string)($user['status'] ?? 'active')));
                $blockedStatuses = ['inactive', 'locked', 'frozen', 'terminated'];
                if (in_array($statusValue, $blockedStatuses, true)) {
                    admin_record_login($pdo, (int)$user['id'], $login, false, 'Status: ' . $statusValue);
                    $error = admin_login_status_message($statusValue);
                } else {
                    $isActive = $statusValue === 'active' || $statusValue === '1' || $user['status'] === 1;

                if (!$isActive) {
                    admin_record_login($pdo, (int)$user['id'], $login, false, 'Inactive');
                    $error = 'Account inactive.';
                } else {
                    $dbPassword = (string)($user['password_hash'] ?? $user['password'] ?? '');
                    $isHashedPassword = is_string($dbPassword) && preg_match('/^\$2[aby]\$/', $dbPassword) === 1;
                    $passwordMatches = $isHashedPassword
                        ? password_verify($password, $dbPassword)
                        : hash_equals($dbPassword, $password);

                    if (!$passwordMatches) {
                        admin_record_login($pdo, (int)$user['id'], $login, false, 'Wrong password');
                        $error = 'Wrong password.';
                    } else {
                        if (!isset($user['full_name']) && isset($user['name'])) {
                            $user['full_name'] = $user['name'];
                        }

                        login_user($user, $remember);
                        try {
                            admin_record_login($pdo, (int)$user['id'], $login, true, null);
                            admin_register_session($pdo, (int)$user['id']);
                            $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute(['id' => (int)$user['id']]);
                        } catch (Throwable) {
                        }
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
                    <p class="text-muted small mb-3">Sign in with your <strong>username</strong> or corporate <strong>email</strong>.</p>
                    <?php if ($info): ?><div class="alert alert-info"><?= e($info) ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                    <?php if (!empty($debugOutput)): ?>
                        <div class="alert alert-warning">
                            <strong>Debug output (temporary):</strong>
                            <pre class="mb-0 mt-2"><?= e((string)json_encode($debugOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                        </div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Username or email</label>
                            <div class="input-group login-input-group">
                                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                <input class="form-control" type="text" name="login" autocomplete="username" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group login-input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input class="form-control" id="loginPassword" type="password" name="password" autocomplete="current-password" required>
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
