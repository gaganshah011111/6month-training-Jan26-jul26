<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Super Admin', 'HR Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare('INSERT INTO employees(employee_code,full_name,email,employee_type,department,designation,role,salary_type,shift_timing,shift_start,shift_end,contact_no,address,joining_date,basic_salary,paid_leave_limit,half_paid_leave_limit,hra_percentage,hra_amount,pf_applicable,pf_percentage,esi_applicable,esi_percentage,medical_allowance,other_allowances,overtime_rate,daily_wage,hourly_rate,status,user_id) VALUES(:c,:n,:em,:et,:d,:des,:r,:st,:sh,:ss,:se,:p,:a,:j,:s,:pll,:hpll,:hrp,:hra,:pfa,:pfp,:esia,:esip,:ma,:oa,:otr,:dw,:hr,:status,:u)');
        $stmt->execute([
            'c' => post_string('employee_code', 50),
            'n' => post_string('full_name', 150),
            'em' => post_string('email', 150) ?: null,
            'et' => post_string('employee_type', 20) ?: 'Staff',
            'd' => post_string('department', 100),
            'des' => post_string('designation', 120),
            'r' => post_string('role', 100) ?: 'Employee',
            'st' => post_string('salary_type', 50) ?: 'Monthly',
            'sh' => post_string('shift_timing', 80),
            'ss' => post_string('shift_start', 8) ?: null,
            'se' => post_string('shift_end', 8) ?: null,
            'p' => post_string('contact_no', 20),
            'a' => post_string('address'),
            'j' => $_POST['joining_date'],
            's' => post_float('basic_salary'),
            'pll' => post_float('paid_leave_limit'),
            'hpll' => post_float('half_paid_leave_limit'),
            'hrp' => post_float('hra_percentage'),
            'hra' => post_float('hra_amount'),
            'pfa' => isset($_POST['pf_applicable']) ? 1 : 0,
            'pfp' => post_float('pf_percentage'),
            'esia' => isset($_POST['esi_applicable']) ? 1 : 0,
            'esip' => post_float('esi_percentage'),
            'ma' => post_float('medical_allowance'),
            'oa' => post_float('other_allowances'),
            'otr' => post_float('overtime_rate'),
            'dw' => post_float('daily_wage'),
            'hr' => post_float('hourly_rate'),
            'status' => post_string('status', 20) ?: 'active',
            'u' => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null,
        ]);
        set_flash('success', 'Employee added successfully.');
        redirect('employees/list');
    } catch (Throwable $e) {
        set_flash('danger', 'Create employee failed: ' . $e->getMessage());
        redirect('employees/create');
    }
}

$employeeUsers = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'Employee' ORDER BY full_name")->fetchAll();
?>
<div class="module-shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">Add Employee</h4>
            <small class="text-muted">Create a new employee profile with salary and leave policy</small>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(route_url('employees/list')) ?>">Back to Employees</a>
    </div>
    <form method="post" class="row g-3">
        <?= csrf_input() ?>
        <div class="col-12">
            <div class="card">
                <div class="card-header section-title">Basic Information</div>
                <div class="card-body row g-3">
                    <div class="col-md-4"><label class="form-label">Employee Name</label><input class="form-control" name="full_name" required></div>
                    <div class="col-md-4"><label class="form-label">Employee Code</label><input class="form-control" name="employee_code" required></div>
                    <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div>
                    <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="contact_no"></div>
                    <div class="col-md-4"><label class="form-label">Gender</label><select class="form-select" name="gender"><option>Male</option><option>Female</option><option>Other</option></select></div>
                    <div class="col-md-4"><label class="form-label">Address</label><input class="form-control" name="address"></div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header section-title">Work Information</div>
                <div class="card-body row g-3">
                    <div class="col-md-3"><label class="form-label">Department</label><input class="form-control" name="department" required></div>
                    <div class="col-md-3"><label class="form-label">Designation</label><input class="form-control" name="designation"></div>
                    <div class="col-md-2"><label class="form-label">Employee Type</label><select class="form-select" name="employee_type"><option>Staff</option><option>Worker</option></select></div>
                    <div class="col-md-2"><label class="form-label">Role</label><input class="form-control" name="role" value="Employee"></div>
                    <div class="col-md-2"><label class="form-label">Joining Date</label><input class="form-control" type="date" name="joining_date" required></div>
                    <div class="col-md-3"><label class="form-label">Shift</label><select class="form-select" id="shift_preset" name="shift_preset_select" aria-label="Shift preset"><option value="">Custom / other</option><option value="morning">Morning (09:00–18:00)</option><option value="evening">Evening (14:00–22:00)</option><option value="night">Night (22:00–06:00)</option></select><small class="text-muted d-block mt-1">Sets shift name and times; you can edit times below.</small></div>
                    <div class="col-md-3"><label class="form-label">Shift name (saved)</label><input class="form-control" name="shift_timing" id="shift_timing_input" placeholder="e.g. Morning Shift"></div>
                    <div class="col-md-2"><label class="form-label">Shift Start</label><input class="form-control" type="time" name="shift_start" id="shift_start_input"></div>
                    <div class="col-md-2"><label class="form-label">Shift End</label><input class="form-control" type="time" name="shift_end" id="shift_end_input"></div>
                    <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header section-title">Salary Structure</div>
                <div class="card-body row g-3">
                    <div class="col-md-2"><label class="form-label">Salary Type</label><select class="form-select" name="salary_type"><option>Monthly</option><option>Daily Wage</option><option>Hourly</option></select></div>
                    <div class="col-md-2"><label class="form-label">Basic Salary</label><input class="form-control" type="number" step="0.01" name="basic_salary" required></div>
                    <div class="col-md-2"><label class="form-label">HRA %</label><input class="form-control" type="number" step="0.01" name="hra_percentage" value="40"></div>
                    <div class="col-md-2"><label class="form-label">PF %</label><input class="form-control" type="number" step="0.01" name="pf_percentage" value="12"></div>
                    <div class="col-md-2"><label class="form-label">ESI %</label><input class="form-control" type="number" step="0.01" name="esi_percentage" value="0.75"></div>
                    <div class="col-md-2"><label class="form-label">OT Rate</label><input class="form-control" type="number" step="0.01" name="overtime_rate" value="0"></div>
                    <div class="col-md-2"><label class="form-label">HRA Amount</label><input class="form-control" type="number" step="0.01" name="hra_amount" value="0"></div>
                    <div class="col-md-2"><label class="form-label">Medical Allowance</label><input class="form-control" type="number" step="0.01" name="medical_allowance" value="0"></div>
                    <div class="col-md-2"><label class="form-label">Other Allowance</label><input class="form-control" type="number" step="0.01" name="other_allowances" value="0"></div>
                    <div class="col-md-2"><label class="form-label">Daily Wage</label><input class="form-control" type="number" step="0.01" name="daily_wage" value="0"></div>
                    <div class="col-md-2"><label class="form-label">Hourly Rate</label><input class="form-control" type="number" step="0.01" name="hourly_rate" value="0"></div>
                    <div class="col-md-1 form-check mt-4"><input class="form-check-input" type="checkbox" name="pf_applicable" checked><label class="form-check-label">PF</label></div>
                    <div class="col-md-1 form-check mt-4"><input class="form-check-input" type="checkbox" name="esi_applicable" checked><label class="form-check-label">ESI</label></div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header section-title">Leave Policy & Account Linking</div>
                <div class="card-body row g-3">
                    <div class="col-md-2"><label class="form-label">Paid Leave</label><input class="form-control" type="number" step="0.01" name="paid_leave_limit" value="12"></div>
                    <div class="col-md-2"><label class="form-label">Half Paid Leave</label><input class="form-control" type="number" step="0.01" name="half_paid_leave_limit" value="6"></div>
                    <div class="col-md-2"><label class="form-label">Weekly Off</label><select class="form-select" name="weekly_off"><option>Sunday</option><option>Saturday</option></select></div>
                    <div class="col-md-3"><label class="form-label">Linked User</label><select class="form-select" name="user_id"><option value="">No linked user</option><?php foreach ($employeeUsers as $employeeUser): ?><option value="<?= (int)$employeeUser['id'] ?>"><?= e($employeeUser['full_name'] . ' - ' . $employeeUser['email']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100">Create Employee</button></div>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
(function () {
    var preset = document.getElementById('shift_preset');
    var nameIn = document.getElementById('shift_timing_input');
    var startIn = document.getElementById('shift_start_input');
    var endIn = document.getElementById('shift_end_input');
    var map = {
        morning: { label: 'Morning Shift', start: '09:00', end: '18:00' },
        evening: { label: 'Evening Shift', start: '14:00', end: '22:00' },
        night: { label: 'Night Shift', start: '22:00', end: '06:00' }
    };
    preset.addEventListener('change', function () {
        var m = map[preset.value];
        if (!m) return;
        nameIn.value = m.label;
        startIn.value = m.start;
        endIn.value = m.end;
    });
})();
</script>
