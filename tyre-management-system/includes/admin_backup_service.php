<?php
declare(strict_types=1);

require_once __DIR__ . '/database_migration.php';
require_once __DIR__ . '/admin_audit_service.php';

function admin_backup_list(): array
{
    $dir = DatabaseMigrationRunner::sqlRoot();
    $files = [];
    foreach (['FULL_DATABASE_BACKUP.sql', 'full_latest_backup.sql'] as $name) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            $files[] = [
                'name' => $name,
                'path' => $path,
                'size' => filesize($path),
                'size_fmt' => admin_backup_format_bytes((int)filesize($path)),
                'modified' => date('Y-m-d H:i', (int)filemtime($path)),
            ];
        }
    }

    return $files;
}

function admin_backup_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }

    return $bytes . ' B';
}

function admin_backup_handle_post(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !has_role('Super Admin')) {
        return;
    }
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $ok = DatabaseMigrationRunner::exportFullBackup($pdo);
        admin_audit_log($pdo, $ok ? 'Created database backup' : 'Backup failed', 'Backup & Restore', $ok ? 'success' : 'danger');
        set_flash($ok ? 'success' : 'danger', $ok ? 'Backup created successfully.' : 'Backup failed. Check MySQL is running.');
    }
    redirect('admin/backup');
}

function admin_backup_download(string $file): void
{
    if (!has_role(['Super Admin', 'Admin'])) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
    $allowed = ['FULL_DATABASE_BACKUP.sql', 'full_latest_backup.sql'];
    if (!in_array($file, $allowed, true)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    $path = DatabaseMigrationRunner::sqlRoot() . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . (string)filesize($path));
    readfile($path);
    exit;
}
