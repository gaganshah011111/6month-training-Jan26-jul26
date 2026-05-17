<?php
declare(strict_types=1);

/**
 * File-based SQL migrations for portable ERP database restores.
 * Migrations live in database/sql/migrations/ (NNN_name.sql).
 */
final class DatabaseMigrationRunner
{
    public const SCHEMA_VERSION_FILE = 'schema_version.txt';
    private const MIGRATIONS_DIR = 'migrations';
    private const SQL_ROOT = 'database/sql';

    public static function projectRoot(): string
    {
        return dirname(__DIR__);
    }

    public static function sqlRoot(): string
    {
        return self::projectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::SQL_ROOT);
    }

    public static function migrationsPath(): string
    {
        return self::sqlRoot() . DIRECTORY_SEPARATOR . self::MIGRATIONS_DIR;
    }

    public static function fullBackupPath(): string
    {
        return self::sqlRoot() . DIRECTORY_SEPARATOR . 'full_latest_backup.sql';
    }

    public static function ensureMetaTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            migration VARCHAR(191) NOT NULL PRIMARY KEY,
            batch INT NOT NULL DEFAULT 1,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    /** @return list<string> */
    public static function listMigrationFiles(): array
    {
        $dir = self::migrationsPath();
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files, SORT_NATURAL);

        return $files;
    }

    public static function appliedMigrations(PDO $pdo): array
    {
        self::ensureMetaTable($pdo);
        $rows = $pdo->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);

        return array_map('strval', $rows ?: []);
    }

    public static function runPending(PDO $pdo, bool $exportBackupAfter = false): array
    {
        self::ensureMetaTable($pdo);
        $applied = self::appliedMigrations($pdo);
        $ran = [];
        $batch = (int)$pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations')->fetchColumn();

        foreach (self::listMigrationFiles() as $path) {
            $name = basename($path);
            if (in_array($name, $applied, true)) {
                continue;
            }
            self::executeSqlFile($pdo, $path);
            $ins = $pdo->prepare('INSERT INTO schema_migrations (migration, batch) VALUES (:m, :b)');
            $ins->execute(['m' => $name, 'b' => $batch]);
            $ran[] = $name;
        }

        if ($ran !== []) {
            self::writeSchemaVersion($ran);
        }

        if ($exportBackupAfter && $ran !== []) {
            self::exportFullBackup($pdo);
        }

        return $ran;
    }

    public static function executeSqlFile(PDO $pdo, string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('Migration file not found: ' . $path);
        }
        $sql = (string)file_get_contents($path);
        $sql = self::stripSqlComments($sql);
        foreach (self::splitStatements($sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                if (self::isIgnorableMigrationError($e)) {
                    continue;
                }
                throw new RuntimeException('Migration failed in ' . basename($path) . ': ' . $e->getMessage(), 0, $e);
            }
        }
    }

    private static function stripSqlComments(string $sql): string
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if (str_starts_with($trim, '--')) {
                continue;
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }

    /** @return list<string> */
    private static function splitStatements(string $sql): array
    {
        $parts = preg_split('/;\s*\n/', $sql) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn(string $s): bool => $s !== ''));
    }

    private static function isIgnorableMigrationError(PDOException $e): bool
    {
        $code = (string)$e->getCode();
        $msg = $e->getMessage();
        if ($code === '42S21' || str_contains($msg, 'Duplicate column')) {
            return true;
        }
        if ($code === '42S01' || str_contains($msg, 'already exists')) {
            return true;
        }
        if (str_contains($msg, 'Duplicate key name') || str_contains($msg, 'Duplicate entry')) {
            return true;
        }

        return false;
    }

    /** @param list<string> $ran */
    public static function writeSchemaVersion(array $ran = []): void
    {
        $files = self::listMigrationFiles();
        $latest = $files !== [] ? basename(end($files)) : 'none';
        $version = preg_replace('/\.sql$/', '', $latest) ?: '0';
        $path = self::sqlRoot() . DIRECTORY_SEPARATOR . self::SCHEMA_VERSION_FILE;
        $content = "schema_version={$version}\nupdated_at=" . date('c') . "\n";
        if ($ran !== []) {
            $content .= 'last_applied=' . implode(',', $ran) . "\n";
        }
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $content);
    }

    public static function exportFullBackup(PDO $pdo): bool
    {
        $out = self::fullBackupPath();
        $dir = dirname($out);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return false;
        }

        $dumped = self::tryMysqldump($dbName, $out);
        if (!$dumped) {
            $dumped = self::phpDumpDatabase($pdo, $dbName, $out);
        }

        if ($dumped) {
            self::prependBackupHeader($out, $dbName);
            self::writeSchemaVersion();
        }

        return $dumped;
    }

    private static function tryMysqldump(string $dbName, string $outPath): bool
    {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump',
            'mysqldump',
        ];
        $host = '127.0.0.1';
        $user = 'root';
        $pass = '';

        foreach ($candidates as $bin) {
            if ($bin !== 'mysqldump' && !is_file($bin)) {
                continue;
            }
            $cmd = escapeshellarg($bin)
                . ' --host=' . escapeshellarg($host)
                . ' --user=' . escapeshellarg($user)
                . ' --default-character-set=utf8mb4'
                . ' --routines --triggers --single-transaction'
                . ' --add-drop-table'
                . ($pass !== '' ? ' --password=' . escapeshellarg($pass) : '')
                . ' ' . escapeshellarg($dbName);
            $full = $cmd . ' > ' . escapeshellarg($outPath) . ' 2>&1';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $full = 'cmd /C ' . $full;
            }
            @exec($full, $output, $code);
            if ($code === 0 && is_file($outPath) && filesize($outPath) > 200) {
                return true;
            }
        }

        return false;
    }

    private static function phpDumpDatabase(PDO $pdo, string $dbName, string $outPath): bool
    {
        $fh = fopen($outPath, 'wb');
        if ($fh === false) {
            return false;
        }
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        fwrite($fh, "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n");
        fwrite($fh, "USE `{$dbName}`;\n\n");

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($tables as $table) {
            $table = (string)$table;
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $ddl = (string)($create['Create Table'] ?? '');
            if ($ddl === '') {
                continue;
            }
            fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n{$ddl};\n\n");
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                continue;
            }
            $cols = array_keys($rows[0]);
            $colList = implode('`, `', $cols);
            foreach ($rows as $row) {
                $vals = [];
                foreach ($cols as $c) {
                    $vals[] = self::sqlValue($row[$c]);
                }
                fwrite($fh, "INSERT INTO `{$table}` (`{$colList}`) VALUES (" . implode(', ', $vals) . ");\n");
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);

        return is_file($outPath) && filesize($outPath) > 100;
    }

    private static function sqlValue(mixed $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_int($v) || is_float($v)) {
            return (string)$v;
        }

        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$v) . "'";
    }

    private static function prependBackupHeader(string $outPath, string $dbName): void
    {
        $header = "-- Tyre ERP full_latest_backup.sql\n"
            . '-- Generated: ' . date('c') . "\n"
            . '-- Database: ' . $dbName . "\n"
            . "-- Import: mysql -u root < full_latest_backup.sql\n\n";
        $body = (string)file_get_contents($outPath);
        file_put_contents($outPath, $header . $body);
    }

    public static function markAllMigrationsApplied(PDO $pdo): int
    {
        self::ensureMetaTable($pdo);
        $batch = (int)$pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations')->fetchColumn();
        $count = 0;
        foreach (self::listMigrationFiles() as $path) {
            $name = basename($path);
            $chk = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = :m');
            $chk->execute(['m' => $name]);
            if ($chk->fetchColumn()) {
                continue;
            }
            $pdo->prepare('INSERT INTO schema_migrations (migration, batch) VALUES (:m, :b)')->execute(['m' => $name, 'b' => $batch]);
            $count++;
        }
        self::writeSchemaVersion();

        return $count;
    }
}
