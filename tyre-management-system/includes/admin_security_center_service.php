<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_users_service.php';
require_once __DIR__ . '/admin_security_service.php';
require_once __DIR__ . '/admin_audit_service.php';
require_once __DIR__ . '/admin_settings_service.php';

/** @return array<string, mixed> */
function admin_security_center_data(PDO $pdo): array
{
    admin_security_ensure_schema($pdo);
    admin_audit_ensure_schema($pdo);

    $lockedUsers = $pdo->query(
        "SELECT u.id, u.full_name, u.email, u.role, u.status, u.last_login
         FROM users u WHERE u.status = 'locked' ORDER BY u.full_name"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $failedLogins = admin_failed_logins($pdo, 30);

    $st = $pdo->query(
        "SELECT user_name, action_text, detail, created_at, ip_address
         FROM erp_activity_log
         WHERE action_text LIKE '%password%' OR action_text LIKE '%Locked%' OR action_text LIKE '%logout%'
         ORDER BY id DESC LIMIT 20"
    );
    $passwordResets = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $st2 = $pdo->query(
        "SELECT user_name, action_text, module_name, status, created_at, ip_address
         FROM erp_activity_log
         WHERE module_name IN ('User Management', 'System Administration') AND status IN ('warning','danger','error')
         ORDER BY id DESC LIMIT 15"
    );
    $securityEvents = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'locked_users' => $lockedUsers,
        'failed_logins' => $failedLogins,
        'password_history' => $passwordResets,
        'security_events' => $securityEvents,
        'stats' => [
            'locked' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'locked'"),
            'inactive' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'inactive'"),
            'failed_7d' => admin_count($pdo, 'SELECT COUNT(*) FROM erp_failed_logins WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            'must_change_pw' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE must_change_password = 1"),
        ],
    ];
}

function admin_security_center_handle_post(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_can_access()) {
        return;
    }
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_security_policy') {
        admin_system_settings_save($pdo, $_POST);
        admin_audit_log($pdo, 'Updated security policy', 'Security Center', 'success');
        set_flash('success', 'Security policy saved.');
        redirect('admin/security-center');
    }
}
