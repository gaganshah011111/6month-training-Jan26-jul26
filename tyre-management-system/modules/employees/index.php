<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Super Admin', 'HR Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
verify_csrf();
$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO employees(employee_code,full_name,department,role_name,contact_no,address,joining_date,basic_salary,user_id) VALUES(:c,:n,:d,:r,:p,:a,:j,:s,:u)');
        $stmt->execute([
            'c' => post_string('employee_code', 50),
            'n' => post_string('full_name', 150),
            'd' => post_string('department', 100),
            'r' => post_string('role_name', 100) ?: 'Employee',
            'p' => post_string('contact_no', 20),
            'a' => post_string('address'),
            'j' => $_POST['joining_date'],
            's' => post_float('basic_salary'),
            'u' => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null,
        ]);
        set_flash('success', 'Employee created successfully.');
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare('UPDATE employees SET full_name=:n, department=:d, role_name=:r, contact_no=:p, address=:a, joining_date=:j, basic_salary=:s, user_id=:u WHERE id=:id');
        $stmt->execute([
            'id' => post_int('id'),
            'n' => post_string('full_name', 150),
            'd' => post_string('department', 100),
            'r' => post_string('role_name', 100),
            'p' => post_string('contact_no', 20),
            'a' => post_string('address'),
            'j' => $_POST['joining_date'],
            's' => post_float('basic_salary'),
            'u' => !empty($_POST['user_id']) ? post_int('user_id') : null,
        ]);
        set_flash('success', 'Employee updated successfully.');
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM employees WHERE id=:id');
        $stmt->execute(['id' => post_int('id')]);
        set_flash('warning', 'Employee deleted successfully.');
    }
    redirect('employees/list');
}

$search = trim((string)($_GET['q'] ?? ''));
$pageNo = max(1, (int)($_GET['p'] ?? 1));
$limit = 15;
$offset = ($pageNo - 1) * $limit;
$employeeUsers = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'Employee' ORDER BY full_name")->fetchAll();
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE full_name LIKE :q1 OR employee_code LIKE :q2 OR department LIKE :q3');
$countStmt->execute([
    'q1' => '%' . $search . '%',
    'q2' => '%' . $search . '%',
    'q3' => '%' . $search . '%',
]);
$total = (int)$countStmt->fetchColumn();
$rowsStmt = $pdo->prepare('SELECT e.*, u.email AS user_email FROM employees e LEFT JOIN users u ON u.id = e.user_id WHERE e.full_name LIKE :q1 OR e.employee_code LIKE :q2 OR e.department LIKE :q3 ORDER BY e.id DESC LIMIT :lim OFFSET :off');
$rowsStmt->bindValue(':q1', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':q2', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':q3', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$rowsStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll();
$totalPages = max(1, (int)ceil($total / $limit));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Employees</h4>
    <form method="get" class="d-flex gap-2">
        <input type="hidden" name="page" value="employees/list">
        <input class="form-control form-control-sm" name="q" value="<?= e($search) ?>" placeholder="Search employee...">
        <button class="btn btn-sm btn-outline-secondary">Search</button>
    </form>
</div>
<form method="post" class="row g-2 mb-3">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="create">
    <div class="col"><input class="form-control" name="employee_code" placeholder="Code" required></div>
    <div class="col"><input class="form-control" name="full_name" placeholder="Name" required></div>
    <div class="col"><input class="form-control" name="department" placeholder="Department" required></div>
    <div class="col"><input class="form-control" name="role_name" placeholder="Role" value="Employee" required></div>
    <div class="col"><input class="form-control" name="contact_no" placeholder="Contact"></div>
    <div class="col"><input class="form-control" name="address" placeholder="Address"></div>
    <div class="col"><input class="form-control" type="date" name="joining_date" required></div>
    <div class="col"><input class="form-control" type="number" step="0.01" name="basic_salary" placeholder="Salary" required></div>
    <div class="col">
        <select class="form-select" name="user_id">
            <option value="">Link Employee User (optional)</option>
            <?php foreach ($employeeUsers as $employeeUser): ?>
                <option value="<?= e((string)$employeeUser['id']) ?>"><?= e($employeeUser['full_name'] . ' - ' . $employeeUser['email']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col"><button class="btn btn-primary w-100">Add</button></div>
</form>
<table class="table table-sm table-bordered align-middle"><thead><tr><th>Code</th><th>Name</th><th>Dept/Role</th><th>Join</th><th>Salary</th><th>Linked User</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['employee_code']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['department'] . ' / ' . ($r['role_name'] ?? 'Employee')) ?></td><td><?= e($r['joining_date']) ?></td><td><?= e((string)$r['basic_salary']) ?></td><td><?= e((string)($r['user_email'] ?? 'Not linked')) ?></td><td class="d-flex gap-1">
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editEmp<?= (int)$r['id'] ?>">Edit</button>
    <form method="post" onsubmit="return confirm('Delete employee?')">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="btn btn-sm btn-outline-danger">Delete</button>
    </form>
</td></tr>
<div class="modal fade" id="editEmp<?= (int)$r['id'] ?>" tabindex="-1"><div class="modal-dialog modal-lg"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Employee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body row g-2">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <div class="col-md-6"><input class="form-control" name="full_name" value="<?= e($r['full_name']) ?>" required></div>
    <div class="col-md-6"><input class="form-control" name="department" value="<?= e($r['department']) ?>" required></div>
    <div class="col-md-6"><input class="form-control" name="role_name" value="<?= e((string)($r['role_name'] ?? 'Employee')) ?>" required></div>
    <div class="col-md-6"><input class="form-control" name="contact_no" value="<?= e((string)($r['contact_no'] ?? '')) ?>"></div>
    <div class="col-md-6"><input class="form-control" name="address" value="<?= e((string)($r['address'] ?? '')) ?>"></div>
    <div class="col-md-3"><input class="form-control" type="date" name="joining_date" value="<?= e($r['joining_date']) ?>" required></div>
    <div class="col-md-3"><input class="form-control" type="number" step="0.01" name="basic_salary" value="<?= e((string)$r['basic_salary']) ?>" required></div>
    <div class="col-md-6"><select class="form-select" name="user_id"><option value="">No linked user</option><?php foreach ($employeeUsers as $employeeUser): ?><option value="<?= e((string)$employeeUser['id']) ?>" <?= (int)$employeeUser['id'] === (int)($r['user_id'] ?? 0) ? 'selected' : '' ?>><?= e($employeeUser['full_name'] . ' - ' . $employeeUser['email']) ?></option><?php endforeach; ?></select></div>
</div><div class="modal-footer"><button class="btn btn-primary">Save Changes</button></div></form></div></div>
<?php endforeach; ?>
</tbody></table>
<nav><ul class="pagination pagination-sm">
<?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>"><a class="page-link" href="<?= e(route_url('employees/list') . '&q=' . urlencode($search) . '&p=' . $i) ?>"><?= $i ?></a></li>
<?php endfor; ?>
</ul></nav>

