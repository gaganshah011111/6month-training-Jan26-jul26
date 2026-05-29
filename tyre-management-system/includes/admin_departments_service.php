<?php

declare(strict_types=1);



require_once __DIR__ . '/functions.php';

require_once __DIR__ . '/admin_control_center.php';

require_once __DIR__ . '/department_hierarchy.php';

require_once __DIR__ . '/admin_audit_service.php';



function admin_departments_ensure(PDO $pdo): void

{

    install_department_hierarchy($pdo);

    if (!dh_column_exists($pdo, 'departments', 'head_employee_id')) {

        try {

            $pdo->exec('ALTER TABLE departments ADD COLUMN head_employee_id INT NULL AFTER status');

        } catch (Throwable) {

        }

    }

}



function admin_departments_handle_post(PDO $pdo): void

{

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_can_access()) {

        return;

    }

    verify_csrf();

    admin_departments_ensure($pdo);

    $action = (string)($_POST['action'] ?? '');



    try {

        if ($action === 'add') {

            $name = trim((string)($_POST['department_name'] ?? ''));

            $code = trim((string)($_POST['department_code'] ?? ''));

            $catId = (int)($_POST['category_id'] ?? 0);

            if ($name === '' || $code === '' || $catId <= 0) {

                throw new InvalidArgumentException('Department name, code, and category are required.');

            }

            $st = $pdo->prepare('INSERT INTO departments (category_id, department_name, department_code, status) VALUES (:c, :n, :code, "active")');

            $st->execute(['c' => $catId, 'n' => $name, 'code' => strtoupper($code)]);

            admin_audit_log($pdo, 'Added department ' . $name, 'Department Management', 'success', $code);

            set_flash('success', 'Department added.');

        } elseif ($action === 'update') {

            $id = (int)($_POST['department_id'] ?? 0);

            $name = trim((string)($_POST['department_name'] ?? ''));

            $headId = (int)($_POST['head_employee_id'] ?? 0);

            $status = (string)($_POST['status'] ?? 'active');

            if ($id <= 0 || $name === '') {

                throw new InvalidArgumentException('Invalid department.');

            }

            $old = $pdo->prepare('SELECT department_name, status FROM departments WHERE id = :id');

            $old->execute(['id' => $id]);

            $oldRow = $old->fetch(PDO::FETCH_ASSOC) ?: [];

            $st = $pdo->prepare('UPDATE departments SET department_name = :n, head_employee_id = :h, status = :s WHERE id = :id');

            $st->execute(['n' => $name, 'h' => $headId > 0 ? $headId : null, 'id' => $id, 's' => $status]);

            admin_audit_log($pdo, 'Updated department ' . $name, 'Department Management', 'success', '#' . $id, (string)($oldRow['department_name'] ?? ''), $name, 'department', $id);

            set_flash('success', 'Department updated.');

        } elseif ($action === 'disable') {

            $id = (int)($_POST['department_id'] ?? 0);

            $st = $pdo->prepare('UPDATE departments SET status = "inactive" WHERE id = :id');

            $st->execute(['id' => $id]);

            admin_audit_log($pdo, 'Disabled department', 'Department Management', 'warning', '#' . $id, 'active', 'inactive', 'department', $id);

            set_flash('success', 'Department disabled.');

        } elseif ($action === 'transfer_employee') {

            $empId = (int)($_POST['employee_id'] ?? 0);

            $deptId = (int)($_POST['target_department_id'] ?? 0);

            if ($empId <= 0 || $deptId <= 0) {

                throw new InvalidArgumentException('Employee and target department required.');

            }

            $dst = $pdo->prepare('SELECT department_name FROM departments WHERE id = :id LIMIT 1');

            $dst->execute(['id' => $deptId]);

            $deptName = (string)$dst->fetchColumn();

            $old = $pdo->prepare('SELECT department FROM employees WHERE id = :id');

            $old->execute(['id' => $empId]);

            $oldDept = (string)$old->fetchColumn();

            $st = $pdo->prepare('UPDATE employees SET department_id = :did, department = :dn WHERE id = :id');

            $st->execute(['did' => $deptId, 'dn' => $deptName, 'id' => $empId]);

            admin_audit_log($pdo, 'Transferred employee between departments', 'Department Management', 'success', '#' . $empId, $oldDept, $deptName, 'employee', $empId);

            set_flash('success', 'Employee transferred to ' . $deptName . '.');

        } elseif ($action === 'merge') {

            $sourceId = (int)($_POST['source_department_id'] ?? 0);

            $targetId = (int)($_POST['target_department_id'] ?? 0);

            if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {

                throw new InvalidArgumentException('Select two different departments to merge.');

            }

            $src = $pdo->prepare('SELECT department_name FROM departments WHERE id = :id LIMIT 1');
            $src->execute(['id' => $sourceId]);
            $sourceName = (string)$src->fetchColumn();
            $tgt = $pdo->prepare('SELECT department_name FROM departments WHERE id = :id LIMIT 1');
            $tgt->execute(['id' => $targetId]);
            $targetName = (string)$tgt->fetchColumn();
            $pdo->prepare('UPDATE employees SET department_id = :tid, department = :tn WHERE department_id = :sid OR department = :sn')
                ->execute(['tid' => $targetId, 'tn' => $targetName, 'sid' => $sourceId, 'sn' => $sourceName]);
            $pdo->prepare('UPDATE departments SET status = "merged" WHERE id = :id')->execute(['id' => $sourceId]);
            admin_audit_log($pdo, 'Merged departments into ' . $targetName, 'Department Management', 'warning', $sourceName . ' → ' . $targetName);

            set_flash('success', 'Departments merged.');

        } else {

            throw new InvalidArgumentException('Unknown action.');

        }

    } catch (Throwable $e) {

        set_flash('danger', $e->getMessage());

    }

    redirect('admin/departments');

}



/** @return array<string, mixed>|null */

function admin_department_get(PDO $pdo, int $id): ?array

{

    admin_departments_ensure($pdo);

    $st = $pdo->prepare(

        'SELECT d.*, dc.category_name, eh.full_name AS head_name

         FROM departments d

         JOIN department_categories dc ON dc.id = d.category_id

         LEFT JOIN employees eh ON eh.id = d.head_employee_id

         WHERE d.id = :id LIMIT 1'

    );

    $st->execute(['id' => $id]);

    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {

        return null;

    }

    $row['emp_count'] = admin_count($pdo, 'SELECT COUNT(*) FROM employees WHERE department_id = ' . $id . ' OR department = ' . $pdo->quote((string)$row['department_name']));

    $row['active_users'] = admin_count($pdo, 'SELECT COUNT(*) FROM users u JOIN employees e ON e.user_id = u.id WHERE u.status = "active" AND (e.department_id = ' . $id . ' OR e.department = ' . $pdo->quote((string)$row['department_name']) . ')');



    return $row;

}



/** @return list<array<string, mixed>> */

function admin_departments_list(PDO $pdo): array

{

    admin_departments_ensure($pdo);

    if (!dh_table_exists($pdo, 'departments')) {

        return [];

    }

    $rows = $pdo->query(

        'SELECT d.*, dc.category_name,

                (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id OR e.department = d.department_name) AS emp_count,

                eh.full_name AS head_name

         FROM departments d

         JOIN department_categories dc ON dc.id = d.category_id

         LEFT JOIN employees eh ON eh.id = d.head_employee_id

         ORDER BY dc.category_name, d.department_name'

    )->fetchAll(PDO::FETCH_ASSOC) ?: [];



    return $rows;

}



/** @return list<array<string, mixed>> */

function admin_department_categories(PDO $pdo): array

{

    admin_departments_ensure($pdo);



    return $pdo->query('SELECT id, category_name FROM department_categories WHERE status = "active" ORDER BY category_name')->fetchAll(PDO::FETCH_ASSOC) ?: [];

}



/** @return list<array<string, mixed>> */

function admin_department_employees(PDO $pdo, ?int $departmentId = null): array

{

    if ($departmentId !== null && $departmentId > 0) {

        $st = $pdo->prepare(

            "SELECT id, full_name, department, designation FROM employees

             WHERE department_id = :id OR department = (SELECT department_name FROM departments WHERE id = :id2 LIMIT 1)

             ORDER BY full_name LIMIT 500"

        );

        $st->execute(['id' => $departmentId, 'id2' => $departmentId]);



        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    }



    return $pdo->query("SELECT id, full_name, department FROM employees WHERE status IN ('Active','active') ORDER BY full_name LIMIT 500")->fetchAll(PDO::FETCH_ASSOC) ?: [];

}



/** @return array<string, mixed> */

function admin_department_performance(PDO $pdo, int $departmentId): array

{

    $dept = admin_department_get($pdo, $departmentId);

    if (!$dept) {

        return ['employees' => 0, 'attendance_today' => 0, 'leave_pending' => 0];

    }

    $name = (string)$dept['department_name'];

    $empIds = $pdo->query(

        'SELECT id FROM employees WHERE department_id = ' . $departmentId . ' OR department = ' . $pdo->quote($name)

    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $attendance = 0;

    $leavePending = 0;

    if ($empIds !== []) {

        $idList = implode(',', array_map('intval', $empIds));

        $attendance = admin_count($pdo, "SELECT COUNT(*) FROM attendance WHERE employee_id IN ($idList) AND attendance_date = CURDATE()");

        $leavePending = admin_count($pdo, "SELECT COUNT(*) FROM leaves WHERE employee_id IN ($idList) AND status = 'Pending'");

    }



    return [

        'employees' => (int)($dept['emp_count'] ?? 0),

        'active_users' => (int)($dept['active_users'] ?? 0),

        'attendance_today' => $attendance,

        'leave_pending' => $leavePending,

        'head' => (string)($dept['head_name'] ?? '—'),

    ];

}


