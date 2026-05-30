<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_control_center.php';
require_once __DIR__ . '/admin_users_service.php';
require_once __DIR__ . '/admin_monitoring_service.php';
require_once __DIR__ . '/admin_backup_service.php';
require_once __DIR__ . '/admin_security_service.php';
require_once __DIR__ . '/admin_audit_service.php';

/** @return array<string, mixed> */
function admin_system_dashboard(PDO $pdo): array
{
    require_once __DIR__ . '/admin_security_service.php';
    admin_security_ensure_schema($pdo);

    $userKpis = admin_users_kpis($pdo);
    $userKpis['inactive'] = admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'inactive'");

    $dbOk = true;
    try {
        $pdo->query('SELECT 1');
    } catch (Throwable) {
        $dbOk = false;
    }

    $backups = admin_backup_list();
    $lastBackup = $backups[0] ?? null;
    $backupStatus = $lastBackup ? 'Available' : 'Missing';
    $backupLevel = $lastBackup ? 'healthy' : 'warning';

    $storageBytes = 0;
    foreach ($backups as $b) {
        $storageBytes += (int)($b['size'] ?? 0);
    }

    $moduleMonitor = admin_erp_module_monitor($pdo);
    $moduleStatus = [];
    foreach ($moduleMonitor as $m) {
        $state = match ($m['level'] ?? '') {
            'error' => 'Offline',
            'warning' => 'Warning',
            default => 'Healthy',
        };
        $moduleStatus[] = [
            'key' => $m['key'],
            'label' => str_replace('CRM / ', '', (string)$m['label']),
            'state' => $state,
            'level' => $m['level'],
        ];
    }

    admin_audit_ensure_schema($pdo);
    $adminActions = [];
    $st = $pdo->query(
        "SELECT user_name, action_text, module_name, created_at, status
         FROM erp_activity_log
         WHERE module_name IN ('User Management', 'System Administration', 'Company Configuration', 'Backup & Restore', 'Department Management')
         ORDER BY id DESC LIMIT 8"
    );
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $adminActions[] = [
            'user' => (string)$r['user_name'],
            'action' => (string)$r['action_text'],
            'module' => (string)$r['module_name'],
            'when' => substr((string)$r['created_at'], 0, 16),
            'status' => (string)$r['status'],
        ];
    }

    $securityAlerts = [];
    $failedCount = admin_count($pdo, 'SELECT COUNT(*) FROM erp_failed_logins WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
    if ($failedCount > 0) {
        $securityAlerts[] = ['level' => 'warning', 'title' => 'Failed login attempts', 'detail' => $failedCount . ' in last 7 days', 'url' => route_url('admin/security-center')];
    }
    if ((int)$userKpis['locked'] > 0) {
        $securityAlerts[] = ['level' => 'error', 'title' => 'Locked accounts', 'detail' => (int)$userKpis['locked'] . ' user(s) locked', 'url' => route_url('admin/users', ['status' => 'locked'])];
    }
    $pwdExpiry = admin_count($pdo, "SELECT COUNT(*) FROM users WHERE must_change_password = 1 AND status = 'active'");
    if ($pwdExpiry > 0) {
        $securityAlerts[] = ['level' => 'warning', 'title' => 'Password change required', 'detail' => $pwdExpiry . ' user(s) must change password', 'url' => route_url('admin/users')];
    }
    if (!$lastBackup) {
        $securityAlerts[] = ['level' => 'warning', 'title' => 'No backup on file', 'detail' => 'Create a database backup', 'url' => route_url('admin/backup')];
    }

    return [
        'overview' => $userKpis,
        'system_health' => [
            'database' => ['label' => 'Database Status', 'state' => $dbOk ? 'Connected' : 'Error', 'level' => $dbOk ? 'healthy' : 'error'],
            'storage' => ['label' => 'Storage Usage', 'state' => admin_format_bytes($storageBytes), 'level' => 'healthy'],
            'backup' => ['label' => 'Backup Status', 'state' => $backupStatus, 'level' => $backupLevel, 'detail' => $lastBackup['modified'] ?? 'Never'],
            'server' => ['label' => 'Server Status', 'state' => 'Online', 'level' => 'healthy'],
        ],
        'modules' => $moduleStatus,
        'admin_actions' => $adminActions,
        'security_alerts' => $securityAlerts,
    ];
}

function admin_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}
