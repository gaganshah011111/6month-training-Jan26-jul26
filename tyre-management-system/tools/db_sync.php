<?php
declare(strict_types=1);

/**
 * Database sync CLI
 *
 * Usage (from project root):
 *   php tools/db_sync.php migrate          Run pending SQL migrations
 *   php tools/db_sync.php baseline         Mark all migration files as applied (existing DB)
 *   php tools/db_sync.php export           Export full_latest_backup.sql
 *   php tools/db_sync.php seed             Run all seed/*.sql files
 *   php tools/db_sync.php all              migrate + export + seed
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/database_migration.php';

$action = $argv[1] ?? 'help';
$pdo = Database::connection();

function seedAll(PDO $pdo): void
{
    $dir = DatabaseMigrationRunner::sqlRoot() . DIRECTORY_SEPARATOR . 'seed';
    $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    sort($files, SORT_NATURAL);
    foreach ($files as $file) {
        echo "Seed: " . basename($file) . "\n";
        DatabaseMigrationRunner::executeSqlFile($pdo, $file);
    }
}

switch ($action) {
    case 'migrate':
        $ran = DatabaseMigrationRunner::runPending($pdo, false);
        echo $ran === [] ? "No pending migrations.\n" : "Applied: " . implode(', ', $ran) . "\n";
        break;
    case 'baseline':
        $n = DatabaseMigrationRunner::markAllMigrationsApplied($pdo);
        echo "Marked {$n} migration(s) as applied.\n";
        break;
    case 'export':
        $ok = DatabaseMigrationRunner::exportFullBackup($pdo);
        echo $ok ? "Exported: " . DatabaseMigrationRunner::fullBackupPath() . "\n" : "Export failed.\n";
        exit($ok ? 0 : 1);
    case 'seed':
        seedAll($pdo);
        echo "Seed complete.\n";
        break;
    case 'all':
        $ran = DatabaseMigrationRunner::runPending($pdo, false);
        echo $ran === [] ? "Migrations up to date.\n" : "Applied: " . implode(', ', $ran) . "\n";
        DatabaseMigrationRunner::exportFullBackup($pdo);
        seedAll($pdo);
        echo "Done: migrate + export + seed.\n";
        break;
    default:
        echo "Tyre ERP — database/sql sync\n\n";
        echo "  php tools/db_sync.php migrate\n";
        echo "  php tools/db_sync.php baseline\n";
        echo "  php tools/db_sync.php export\n";
        echo "  php tools/db_sync.php seed\n";
        echo "  php tools/db_sync.php all\n";
}
