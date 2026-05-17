<?php
declare(strict_types=1);

/** @return bool */
function erp_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1'
    );
    $st->execute(['t' => $table]);

    return (bool)$st->fetchColumn();
}

/**
 * Remove employee and dependent HR rows (works even when DB FK lacks ON DELETE CASCADE).
 */
function employee_delete_safe(PDO $pdo, int $employeeId): void
{
    if ($employeeId < 1) {
        throw new InvalidArgumentException('Invalid employee.');
    }

    $check = $pdo->prepare('SELECT id, user_id FROM employees WHERE id = :id LIMIT 1');
    $check->execute(['id' => $employeeId]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Employee not found.');
    }

    $pdo->beginTransaction();
    try {
        $childTables = ['salary_increments', 'salaries', 'payroll', 'attendance', 'leaves'];
        foreach ($childTables as $table) {
            if (!erp_table_exists($pdo, $table)) {
                continue;
            }
            $pdo->prepare("DELETE FROM `{$table}` WHERE employee_id = :id")->execute(['id' => $employeeId]);
        }

        if (erp_table_exists($pdo, 'users')) {
            $pdo->prepare('UPDATE users SET employee_id = NULL WHERE employee_id = :id')->execute(['id' => $employeeId]);
            $userId = (int)($row['user_id'] ?? 0);
            if ($userId > 0) {
                $pdo->prepare('UPDATE users SET employee_id = NULL WHERE id = :uid')->execute(['uid' => $userId]);
            }
        }

        $pdo->prepare('DELETE FROM employees WHERE id = :id')->execute(['id' => $employeeId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
