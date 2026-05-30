<?php
declare(strict_types=1);

/**
 * Central account status checks for login and live sessions.
 * Status values: active | locked | inactive
 */

function auth_normalize_account_status(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === '' || $status === '1') {
        return $status === '1' ? 'active' : 'inactive';
    }

    return match ($status) {
        'active' => 'active',
        'locked' => 'locked',
        'inactive', 'frozen', 'terminated', 'deactivated' => 'inactive',
        default => 'inactive',
    };
}

function auth_login_is_permitted(string $status): bool
{
    return auth_normalize_account_status($status) === 'active';
}

function auth_login_denial_message(string $status): string
{
    return match (auth_normalize_account_status($status)) {
        'locked' => 'Account is locked. Contact your administrator.',
        'inactive' => 'Account is inactive. Contact your administrator.',
        default => 'Account is not permitted to sign in.',
    };
}

function auth_status_log(PDO $pdo, string $phase, int $userId, string $dbStatus, string $detail = ''): void
{
    $line = sprintf(
        '[AUTH_STATUS] phase=%s user_id=%d db_status=%s detail=%s',
        $phase,
        $userId,
        $dbStatus,
        $detail
    );
    error_log($line);

    try {
        if (!function_exists('admin_security_ensure_schema')) {
            require_once __DIR__ . '/admin_security_service.php';
        }
        admin_security_ensure_schema($pdo);
        $st = $pdo->prepare(
            'INSERT INTO erp_login_history (user_id, login_name, ip_address, user_agent, success, failure_reason)
             VALUES (:uid, :ln, :ip, :ua, :ok, :r)'
        );
        $st->execute([
            'uid' => $userId > 0 ? $userId : null,
            'ln' => substr('status_check:' . $phase, 0, 150),
            'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'ok' => auth_login_is_permitted($dbStatus) ? 1 : 0,
            'r' => substr($phase . '|status=' . $dbStatus . ($detail !== '' ? '|' . $detail : ''), 0, 120),
        ]);
    } catch (Throwable) {
    }
}

function auth_fetch_user_status_by_id(PDO $pdo, int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }
    if (function_exists('admin_users_ensure_schema')) {
        admin_users_ensure_schema($pdo);
    }
    $st = $pdo->prepare('SELECT status FROM users WHERE id = :id LIMIT 1');
    $st->execute(['id' => $userId]);
    $raw = $st->fetchColumn();
    if ($raw === false) {
        return null;
    }

    return auth_normalize_account_status((string)$raw);
}

function auth_evaluate_login_user(PDO $pdo, array $user, string $loginName): ?string
{
    $userId = (int)($user['id'] ?? 0);
    $dbStatus = auth_fetch_user_status_by_id($pdo, $userId);
    if ($dbStatus === null) {
        auth_status_log($pdo, 'login', $userId, 'missing', 'user_not_found');
        return 'Account not found.';
    }

    auth_status_log($pdo, 'login', $userId, $dbStatus, 'login=' . $loginName);

    if (!auth_login_is_permitted($dbStatus)) {
        if (function_exists('admin_record_login')) {
            admin_record_login($pdo, $userId, $loginName, false, 'Status: ' . $dbStatus);
        }
        return auth_login_denial_message($dbStatus);
    }

    return null;
}

function auth_enforce_live_session(PDO $pdo): void
{
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        return;
    }

    $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    if (!function_exists('admin_security_ensure_schema')) {
        require_once __DIR__ . '/admin_security_service.php';
    }
    admin_security_ensure_schema($pdo);

    $st = $pdo->prepare('SELECT status, force_logout_at FROM users WHERE id = :id LIMIT 1');
    $st->execute(['id' => $userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        auth_status_log($pdo, 'session', $userId, 'missing', 'user_row_not_found');
        if (function_exists('logout_user')) {
            logout_user();
        }
        header('Location: login.php');
        exit;
    }

    $dbStatus = auth_normalize_account_status((string)($row['status'] ?? ''));
    auth_status_log($pdo, 'session', $userId, $dbStatus, 'session_id=' . session_id());

    if (!auth_login_is_permitted($dbStatus)) {
        if (function_exists('logout_user')) {
            logout_user();
        }
        header('Location: login.php?blocked=1');
        exit;
    }

    $_SESSION['account_status'] = 'active';
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $_SESSION['user']['status'] = 'active';
    }

    $forceAt = (string)($row['force_logout_at'] ?? '');
    if ($forceAt !== '') {
        $sid = session_id();
        $sessCreated = '';
        if ($sid !== '') {
            $st2 = $pdo->prepare(
                'SELECT created_at FROM erp_user_sessions WHERE session_id = :s AND user_id = :u LIMIT 1'
            );
            $st2->execute(['s' => $sid, 'u' => $userId]);
            $sessCreated = (string)($st2->fetchColumn() ?: '');
        }
        if ($sessCreated === '' || strtotime($sessCreated) <= strtotime($forceAt)) {
            auth_status_log($pdo, 'session', $userId, $dbStatus, 'force_logout_at=' . $forceAt);
            if ($sid !== '') {
                $pdo->prepare('UPDATE erp_user_sessions SET revoked_at = NOW() WHERE session_id = :s')
                    ->execute(['s' => $sid]);
            }
            if (function_exists('logout_user')) {
                logout_user();
            }
            header('Location: login.php?logged_out=1');
            exit;
        }
    }

    $sid = session_id();
    if ($sid !== '') {
        $st3 = $pdo->prepare(
            'SELECT revoked_at FROM erp_user_sessions WHERE session_id = :s AND user_id = :u LIMIT 1'
        );
        $st3->execute(['s' => $sid, 'u' => $userId]);
        $revoked = $st3->fetchColumn();
        if ($revoked) {
            auth_status_log($pdo, 'session', $userId, $dbStatus, 'session_revoked');
            if (function_exists('logout_user')) {
                logout_user();
            }
            header('Location: login.php?logged_out=1');
            exit;
        }
    }

    if (function_exists('admin_touch_session')) {
        admin_touch_session($pdo);
    }
}

function auth_bootstrap_account_checks(): void
{
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        return;
    }
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/admin_users_service.php';
    auth_enforce_live_session(Database::connection());
}
