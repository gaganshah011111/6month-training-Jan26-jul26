<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/user_account_status.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function require_auth(array $roles = []): void
{
    ensure_session_started();
    if (!is_logged_in()) {
        redirect('login.php');
    }

    auth_bootstrap_account_checks();

    if ($roles && !has_role($roles)) {
        $user = current_user();
        $target = role_home_page((string)($user['role'] ?? ''));
        $currentPage = (string)($_GET['page'] ?? '');
        if ($currentPage !== '' && $target === $currentPage) {
            redirect('403.php');
        }
        redirect($target);
    }
}

function login_user(array $user, bool $rememberMe): void
{
    if (!function_exists('auth_normalize_account_status')) {
        require_once __DIR__ . '/user_account_status.php';
    }
    $status = auth_normalize_account_status((string)($user['status'] ?? 'active'));
    if (!auth_login_is_permitted($status)) {
        throw new RuntimeException('Cannot log in: account status is ' . $status);
    }

    session_regenerate_id(true);
    $displayName = (string)($user['full_name'] ?? $user['name'] ?? '');
    $role = normalize_role_name((string)($user['role'] ?? ''));
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $displayName,
        'email' => $user['email'] ?? '',
        'username' => $user['username'] ?? '',
        'role' => $role,
        'must_change_password' => (int)($user['must_change_password'] ?? 0),
        'status' => $status,
    ];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $role;
    $_SESSION['account_status'] = $status;
    $_SESSION['must_change_password'] = (int)($user['must_change_password'] ?? 0);

    if ($rememberMe) {
        $token = bin2hex(random_bytes(32));
        setcookie('remember_token', $token, [
            'expires' => time() + (60 * 60 * 24 * 30),
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_SESSION['remember_token'] = hash('sha256', $token);
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    setcookie('remember_token', '', time() - 3600, '/');
    session_destroy();
}
