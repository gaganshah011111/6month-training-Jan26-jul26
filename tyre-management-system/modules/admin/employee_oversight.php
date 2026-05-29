<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_control_center.php';
require_once __DIR__ . '/../../includes/admin_oversight_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
$q = trim((string)($_GET['q'] ?? ''));
$empId = (int)($_GET['id'] ?? 0);
$detail = $empId > 0 ? admin_oversight_employee_detail($pdo, $empId) : null;
$employees = $detail ? [] : admin_oversight_employees($pdo, $q);
?>
<div class="admin-cc module-shell">
    <?php if ($detail): ?>
        <?php admin_page_head($detail['full_name'], 'Employee oversight · read-only drill-down', '<a href="' . e(route_url('admin/employee-oversight')) . '" class="btn btn-sm btn-outline-secondary">Back</a>'); ?>
        <div class="admin-cc__grid admin-cc__grid--2">
            <section class="admin-card">
                <h2 class="admin-card__title">Profile</h2>
                <dl class="admin-dl">
                    <div><dt>Code</dt><dd><?= e((string)($detail['employee_code'] ?? '—')) ?></dd></div>
                    <div><dt>Department</dt><dd><?= e((string)($detail['department'] ?? '—')) ?></dd></div>
                    <div><dt>Designation</dt><dd><?= e((string)($detail['designation'] ?? '—')) ?></dd></div>
                    <div><dt>Status</dt><dd><?= e((string)($detail['status'] ?? '—')) ?></dd></div>
                    <div><dt>Joined</dt><dd><?= e((string)($detail['joining_date'] ?? '—')) ?></dd></div>
                    <div><dt>User Account</dt><dd><?= !empty($detail['user_id']) ? '<a href="' . e(route_url('admin/user', ['id' => $detail['user_id']])) . '">View user</a>' : '—' ?></dd></div>
                </dl>
            </section>
            <section class="admin-card">
                <h2 class="admin-card__title">Records Summary</h2>
                <div class="admin-cc__kpis admin-cc__kpis--2">
                    <div class="admin-kpi"><span>Attendance Records</span><strong><?= (int)$detail['attendance_count'] ?></strong><a href="<?= e(route_url('attendance/list')) ?>">HR Attendance</a></div>
                    <div class="admin-kpi"><span>Leave Requests</span><strong><?= (int)$detail['leave_count'] ?></strong><a href="<?= e(route_url('leave/list')) ?>">HR Leave</a></div>
                    <div class="admin-kpi"><span>Payroll Records</span><strong><?= (int)$detail['payroll_count'] ?></strong><a href="<?= e(route_url('payroll/list')) ?>">HR Payroll</a></div>
                </div>
            </section>
        </div>
        <section class="admin-card">
            <h2 class="admin-card__title">Quick Links</h2>
            <div class="admin-oversight-links">
                <a href="<?= e(route_url('employees/list', ['q' => $detail['full_name']])) ?>" class="admin-oversight-link">Employee Master</a>
                <a href="<?= e(route_url('attendance/list')) ?>" class="admin-oversight-link">Attendance</a>
                <a href="<?= e(route_url('leave/list')) ?>" class="admin-oversight-link">Leave History</a>
                <a href="<?= e(route_url('payroll/list')) ?>" class="admin-oversight-link">Payroll</a>
                <a href="<?= e(route_url('admin/activity-logs', ['q' => $detail['full_name']])) ?>" class="admin-oversight-link">Activity Logs</a>
            </div>
        </section>
    <?php else: ?>
        <?php admin_page_head('Employee Oversight', 'View workforce profiles, attendance, leave, and payroll — monitoring only'); ?>
        <form method="get" class="admin-cc__filters">
            <input type="hidden" name="page" value="admin/employee-oversight">
            <div class="admin-field"><label>Search</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Name, code, department"></div>
            <button class="btn btn-sm btn-primary">Search</button>
        </form>
        <section class="admin-card admin-table-wrap">
            <table class="table table-sm admin-table mb-0">
                <thead><tr><th>Employee</th><th>Department</th><th>Designation</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($employees as $e): ?>
                    <tr>
                        <td><strong><?= e((string)$e['full_name']) ?></strong><small><?= e((string)($e['employee_code'] ?? '')) ?></small></td>
                        <td><?= e((string)($e['department'] ?? '—')) ?></td>
                        <td><?= e((string)($e['designation'] ?? '—')) ?></td>
                        <td><?= e((string)$e['status']) ?></td>
                        <td><?= e((string)($e['last_login'] ?? '—')) ?></td>
                        <td><a href="<?= e(route_url('admin/employee-oversight', ['id' => $e['id']])) ?>" class="admin-action-btn admin-action-btn--primary">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</div>
