<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';

function employee_session_user_id(): int
{
    $sessionUserId = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0);
    return (int)$sessionUserId;
}

function ensure_employee_columns(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM employees')->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = [
        'user_id' => "ALTER TABLE employees ADD COLUMN user_id INT NULL UNIQUE AFTER id",
        'address' => "ALTER TABLE employees ADD COLUMN address VARCHAR(255) NULL AFTER contact_no",
        'profile_image' => "ALTER TABLE employees ADD COLUMN profile_image VARCHAR(255) NULL AFTER address",
    ];

    foreach ($requiredColumns as $column => $sql) {
        if (!in_array($column, $columns, true)) {
            $pdo->exec($sql);
        }
    }
}

function get_employee_record(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM employees WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $employee = $stmt->fetch();
    return $employee ?: null;
}

function require_employee_record(PDO $pdo): array
{
    require_auth(['Employee']);
    ensure_employee_columns($pdo);

    $userId = employee_session_user_id();
    $employee = get_employee_record($pdo, $userId);

    if (!$employee) {
        $fallbackStmt = $pdo->prepare('SELECT id FROM employees WHERE user_id IS NULL ORDER BY id ASC LIMIT 1');
        $fallbackStmt->execute();
        $fallbackEmployeeId = (int)$fallbackStmt->fetchColumn();

        if ($fallbackEmployeeId > 0) {
            $mapStmt = $pdo->prepare('UPDATE employees SET user_id = :user_id WHERE id = :id AND user_id IS NULL');
            $mapStmt->execute(['user_id' => $userId, 'id' => $fallbackEmployeeId]);
            $employee = get_employee_record($pdo, $userId);
        }
    }

    if (!$employee) {
        throw new RuntimeException('Your employee profile is not linked yet. Please ask admin to map this account in Employees module.');
    }

    return $employee;
}

