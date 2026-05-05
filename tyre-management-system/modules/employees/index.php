<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Super Admin', 'Admin'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
$columns = $pdo->query('SHOW COLUMNS FROM employees')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('user_id', $columns, true)) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN user_id INT NULL UNIQUE AFTER id");
}
if (!in_array('address', $columns, true)) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN address VARCHAR(255) NULL AFTER contact_no");
}
if (!in_array('profile_image', $columns, true)) {
    $pdo->exec("ALTER TABLE employees ADD COLUMN profile_image VARCHAR(255) NULL AFTER address");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['map_employee_id'])) {
        $mapStmt = $pdo->prepare('UPDATE employees SET user_id = :user_id WHERE id = :employee_id');
        $mapStmt->execute([
            'user_id' => (int)$_POST['map_user_id'],
            'employee_id' => (int)$_POST['map_employee_id'],
        ]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO employees(employee_code,full_name,department,contact_no,joining_date,basic_salary,user_id) VALUES(:c,:n,:d,:p,:j,:s,:u)');
        $stmt->execute([
            'c' => trim($_POST['employee_code']),
            'n' => trim($_POST['full_name']),
            'd' => trim($_POST['department']),
            'p' => trim($_POST['contact_no']),
            'j' => $_POST['joining_date'],
            's' => (float)$_POST['basic_salary'],
            'u' => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null,
        ]);
    }
}
$employeeUsers = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'Employee' ORDER BY full_name")->fetchAll();
$rows = $pdo->query('SELECT e.*, u.email AS user_email FROM employees e LEFT JOIN users u ON u.id = e.user_id ORDER BY e.id DESC LIMIT 100')->fetchAll();
?>
<h4>Employees</h4>
<form method="post" class="row g-2 mb-3">
    <div class="col"><input class="form-control" name="employee_code" placeholder="Code" required></div>
    <div class="col"><input class="form-control" name="full_name" placeholder="Name" required></div>
    <div class="col"><input class="form-control" name="department" placeholder="Department" required></div>
    <div class="col"><input class="form-control" name="contact_no" placeholder="Contact"></div>
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
<table class="table table-sm table-bordered"><thead><tr><th>Code</th><th>Name</th><th>Dept</th><th>Join</th><th>Salary</th><th>Linked User</th><th>Map</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['employee_code']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['department']) ?></td><td><?= e($r['joining_date']) ?></td><td><?= e((string)$r['basic_salary']) ?></td><td><?= e((string)($r['user_email'] ?? 'Not linked')) ?></td><td>
    <form method="post" class="d-flex gap-2">
        <input type="hidden" name="map_employee_id" value="<?= e((string)$r['id']) ?>">
        <select class="form-select form-select-sm" name="map_user_id" required>
            <option value="">Select user</option>
            <?php foreach ($employeeUsers as $employeeUser): ?>
                <option value="<?= e((string)$employeeUser['id']) ?>"><?= e($employeeUser['email']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary">Save</button>
    </form>
</td></tr><?php endforeach; ?>
</tbody></table>

