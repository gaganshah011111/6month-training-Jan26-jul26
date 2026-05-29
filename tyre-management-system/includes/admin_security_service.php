<?php
declare(strict_types=1);

require_once __DIR__ . '/department_hierarchy.php';

function admin_security_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS erp_login_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        login_name VARCHAR(150) NOT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        failure_reason VARCHAR(120) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_user (user_id),
        INDEX idx_login_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS erp_failed_logins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        login_name VARCHAR(150) NOT NULL,
        ip_address VARCHAR(45) NULL,
        failure_reason VARCHAR(120) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_failed_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS erp_user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_id VARCHAR(128) NOT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        last_active DATETIME NOT NULL,
        revoked_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_session (session_id),
        INDEX idx_sess_user (user_id),
        INDEX idx_sess_active (last_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!dh_column_exists($pdo, 'users', 'force_logout_at')) {
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN force_logout_at DATETIME NULL AFTER must_change_password');
        } catch (Throwable) {
        }
    }
}

function admin_client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    return substr($ip, 0, 45);
}

function admin_client_agent(): string
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function admin_record_login(PDO $pdo, ?int $userId, string $loginName, bool $success, ?string $reason = null): void
{
    admin_security_ensure_schema($pdo);
    $st = $pdo->prepare(
        'INSERT INTO erp_login_history (user_id, login_name, ip_address, user_agent, success, failure_reason)
         VALUES (:uid, :ln, :ip, :ua, :ok, :r)'
    );
    $st->execute([
        'uid' => $userId,
        'ln' => substr($loginName, 0, 150),
        'ip' => admin_client_ip(),
        'ua' => admin_client_agent(),
        'ok' => $success ? 1 : 0,
        'r' => $reason,
    ]);
    if (!$success) {
        $st2 = $pdo->prepare(
            'INSERT INTO erp_failed_logins (login_name, ip_address, failure_reason) VALUES (:ln, :ip, :r)'
        );
        $st2->execute(['ln' => substr($loginName, 0, 150), 'ip' => admin_client_ip(), 'r' => $reason]);
    }
}

function admin_register_session(PDO $pdo, int $userId): void
{
    admin_security_ensure_schema($pdo);
    $sid = session_id();
    if ($sid === '') {
        return;
    }
    $st = $pdo->prepare(
        'INSERT INTO erp_user_sessions (user_id, session_id, ip_address, user_agent, last_active)
         VALUES (:u, :s, :ip, :ua, NOW())
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ip_address = VALUES(ip_address),
             user_agent = VALUES(user_agent), last_active = NOW(), revoked_at = NULL'
    );
    $st->execute([
        'u' => $userId,
        's' => $sid,
        'ip' => admin_client_ip(),
        'ua' => admin_client_agent(),
    ]);
}

function admin_touch_session(PDO $pdo): void
{
    if (!is_logged_in()) {
        return;
    }
    admin_security_ensure_schema($pdo);
    $sid = session_id();
    if ($sid === '') {
        return;
    }
    try {
        $pdo->prepare('UPDATE erp_user_sessions SET last_active = NOW() WHERE session_id = :s AND revoked_at IS NULL')
            ->execute(['s' => $sid]);
    } catch (Throwable) {
    }
}

function admin_revoke_all_sessions(PDO $pdo, int $userId): void
{
    admin_security_ensure_schema($pdo);
    $pdo->prepare('UPDATE erp_user_sessions SET revoked_at = NOW() WHERE user_id = :u AND revoked_at IS NULL')
        ->execute(['u' => $userId]);
    $pdo->prepare('UPDATE users SET force_logout_at = NOW() WHERE id = :id')->execute(['id' => $userId]);
}

/** @return list<array<string, mixed>> */
function admin_login_history(PDO $pdo, int $userId, int $limit = 50): array
{
    admin_security_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT login_name, ip_address, success, failure_reason, created_at
         FROM erp_login_history WHERE user_id = :u ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
    );
    $st->execute(['u' => $userId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function admin_failed_logins(PDO $pdo, int $limit = 50): array
{
    admin_security_ensure_schema($pdo);
    $st = $pdo->query(
        'SELECT login_name, ip_address, failure_reason, created_at
         FROM erp_failed_logins ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
    );

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function admin_active_sessions(PDO $pdo, int $userId): array
{
    admin_security_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT session_id, ip_address, user_agent, last_active, created_at
         FROM erp_user_sessions
         WHERE user_id = :u AND revoked_at IS NULL AND last_active >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY last_active DESC"
    );
    $st->execute(['u' => $userId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_enforce_session_policy(PDO $pdo): void
{
    if (!is_logged_in()) {
        return;
    }
    admin_security_ensure_schema($pdo);
    $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    try {
        $st = $pdo->prepare('SELECT status, force_logout_at FROM users WHERE id = :id LIMIT 1');
        $st->execute(['id' => $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            logout_user();
            header('Location: login.php');
            exit;
        }
        $status = strtolower(trim((string)($row['status'] ?? '')));
        if (in_array($status, ['inactive', 'locked', 'frozen', 'terminated'], true)) {
            logout_user();
            header('Location: login.php?blocked=1');
            exit;
        }
        $forceAt = (string)($row['force_logout_at'] ?? '');
        if ($forceAt !== '') {
            $sid = session_id();
            $st2 = $pdo->prepare(
                'SELECT created_at FROM erp_user_sessions WHERE session_id = :s AND user_id = :u LIMIT 1'
            );
            $st2->execute(['s' => $sid, 'u' => $userId]);
            $sessCreated = (string)($st2->fetchColumn() ?: '');
            if ($sessCreated !== '' && strtotime($sessCreated) <= strtotime($forceAt)) {
                $pdo->prepare('UPDATE erp_user_sessions SET revoked_at = NOW() WHERE session_id = :s')
                    ->execute(['s' => $sid]);
                logout_user();
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
                logout_user();
                header('Location: login.php?logged_out=1');
                exit;
            }
        }
        admin_touch_session($pdo);
    } catch (Throwable) {
    }
}

function admin_login_status_message(string $status): string
{
    return match (strtolower($status)) {
        'locked' => 'Account is locked. Contact administrator.',
        'frozen' => 'Account is frozen. Contact administrator.',
        'inactive' => 'Account is deactivated.',
        'terminated' => 'Account has been terminated.',
        default => 'Account inactive.',
    };
}
