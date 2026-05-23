<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/department_hierarchy.php';
require_once __DIR__ . '/../../includes/employee_credentials.php';
require_once __DIR__ . '/../../includes/payroll_logic.php';
require_once __DIR__ . '/../../includes/indian_payroll.php';
if (!has_role(['Super Admin', 'HR Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
verify_csrf();

$nextEmployeeCode = static function (PDO $pdo): string {
    $next = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM employees')->fetchColumn();
    return sprintf('EMP%03d', $next);
};

$predictEmpId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM employees')->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $deptId = post_int('department_id');
        $desId = post_int('designation_id');
        if (!dh_validate_org_assignment($pdo, $deptId, $desId)) {
            set_flash('danger', 'Please select a valid department (and designation, if chosen).');
            redirect('employees/create');
        }
        $dRow = dh_fetch_department_row($pdo, $deptId);
        $deptName = $dRow['department_name'] ?? 'Unclassified';
        $deptCode = (string)($dRow['department_code'] ?? '');
        $desBind = $desId > 0 ? $desId : null;
        $desName = null;
        if ($desBind !== null) {
            $dn = $pdo->prepare('SELECT designation_name FROM designations WHERE id = :id LIMIT 1');
            $dn->execute(['id' => $desBind]);
            $desName = $dn->fetchColumn() ?: null;
        }

        $father = post_string('father_name', 120);
        if ($err = ec_validate_father_name($father)) {
            set_flash('danger', $err);
            redirect('employees/create');
        }
        $dob = (string)($_POST['dob'] ?? '');
        if ($err = ec_validate_dob($dob)) {
            set_flash('danger', $err);
            redirect('employees/create');
        }
        $aadhaarRaw = preg_replace('/\D/', '', (string)($_POST['aadhaar_number'] ?? '')) ?? '';
        if ($err = ec_validate_aadhaar($aadhaarRaw, $pdo, null)) {
            set_flash('danger', $err);
            redirect('employees/create');
        }

        $employeeType = post_string('employee_type', 20) ?: 'Staff';
        $wantLogin = $employeeType === 'Staff' ? true : isset($_POST['create_login_access']);
        $erpRole = ec_normalize_login_role(post_string('erp_account_role', 50) ?: ec_role_for_department_code($deptCode));
        $accountStatus = post_string('account_status', 20) ?: 'active';

        $employeeCode = $nextEmployeeCode($pdo);
        $fullName = post_string('full_name', 150);
        $tempPassword = $wantLogin ? ec_generate_temp_password($fullName, $aadhaarRaw, $dob) : '';
        $erpEmployeeRole = $erpRole;

        $payrollAuto = isset($_POST['payroll_auto_indian']);
        $metro = isset($_POST['metro']) ? 1 : 0;
        $grossMonthly = post_float('gross_monthly_salary');

        if ($payrollAuto) {
            $grossStored = max(0.0, round($grossMonthly, 2));
            if ($grossStored <= 0) {
                set_flash('danger', 'Please enter a positive gross monthly salary.');
                redirect('employees/create');
            }
            $bindBasic = 0.0;
            $bindDa = 0.0;
            $bindTa = 0.0;
            $bindGr = 0.0;
            $bindHrp = 0.0;
            $bindHra = 0.0;
            $bindMa = 0.0;
            $bindSa = 0.0;
            $bindOa = 0.0;
            $bindOtr = 0.0;
            $bindDw = 0.0;
            $bindHr = 0.0;
        } else {
            $bindBasic = post_float('basic_salary');
            $bindDa = post_float('dearness_allowance');
            $bindTa = post_float('travel_allowance');
            $bindHrp = post_float('hra_percentage');
            $bindHra = post_float('hra_amount');
            $bindMa = post_float('medical_allowance');
            $bindSa = post_float('special_allowance');
            $bindOa = post_float('other_allowances');
            $bindOtr = post_float('overtime_rate');
            $bindDw = post_float('daily_wage');
            $bindHr = post_float('hourly_rate');
            $bindGr = post_float('gratuity_monthly');
            $salaryMerge = [
                'basic_salary' => $bindBasic,
                'dearness_allowance' => $bindDa,
                'hra_percentage' => $bindHrp,
                'hra_amount' => $bindHra,
                'medical_allowance' => $bindMa,
                'travel_allowance' => $bindTa,
                'special_allowance' => $bindSa,
                'other_allowances' => $bindOa,
                'payroll_auto_indian' => 0,
                'gross_salary' => 0.0,
            ];
            $grossStored = employee_fixed_gross_monthly($salaryMerge);
            if ($bindBasic <= 0) {
                set_flash('danger', 'Manual salary requires a positive basic amount.');
                redirect('employees/create');
            }
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO employees(employee_code,full_name,father_name,dob,aadhaar_number,email,employee_type,department,designation,department_id,designation_id,role,salary_type,shift_timing,shift_start,shift_end,contact_no,address,joining_date,basic_salary,paid_leave_limit,half_paid_leave_limit,hra_percentage,hra_amount,pf_applicable,pf_percentage,esi_applicable,esi_percentage,medical_allowance,special_allowance,other_allowances,metro,payroll_auto_indian,dearness_allowance,travel_allowance,gratuity_monthly,gross_salary,overtime_rate,daily_wage,hourly_rate,status,user_id) VALUES(:c,:n,:fn,:dob,:aad,:em,:et,:d,:des,:did,:dsid,:r,:st,:sh,:ss,:se,:p,:a,:j,:s,:pll,:hpll,:hrp,:hra,:pfa,:pfp,:esia,:esip,:ma,:sa,:oa,:metro,:pai,:da,:ta,:gr,:gs,:otr,:dw,:hr,:status,NULL)');
        $stmt->execute([
            'c' => $employeeCode,
            'n' => $fullName,
            'fn' => $father,
            'dob' => $dob,
            'aad' => $aadhaarRaw,
            'em' => post_string('email', 150) ?: null,
            'et' => $employeeType,
            'd' => $deptName,
            'des' => $desName,
            'did' => $deptId,
            'dsid' => $desBind,
            'r' => $erpEmployeeRole,
            'st' => post_string('salary_type', 50) ?: 'Monthly',
            'sh' => post_string('shift_timing', 80),
            'ss' => post_string('shift_start', 8) ?: null,
            'se' => post_string('shift_end', 8) ?: null,
            'p' => post_string('contact_no', 20),
            'a' => post_string('address'),
            'j' => $_POST['joining_date'],
            's' => $bindBasic,
            'pll' => post_float('paid_leave_limit'),
            'hpll' => post_float('half_paid_leave_limit'),
            'hrp' => $bindHrp,
            'hra' => $bindHra,
            'pfa' => isset($_POST['pf_applicable']) ? 1 : 0,
            'pfp' => post_float('pf_percentage'),
            'esia' => isset($_POST['esi_applicable']) ? 1 : 0,
            'esip' => post_float('esi_percentage'),
            'ma' => $bindMa,
            'sa' => $bindSa,
            'oa' => $bindOa,
            'metro' => $metro,
            'pai' => $payrollAuto ? 1 : 0,
            'da' => $bindDa,
            'ta' => $bindTa,
            'gr' => $bindGr,
            'gs' => $grossStored,
            'otr' => $bindOtr,
            'dw' => $bindDw,
            'hr' => $bindHr,
            'status' => post_string('status', 20) ?: 'active',
        ]);
        $employeeId = (int)$pdo->lastInsertId();

        if ($payrollAuto && $grossStored > 0) {
            indian_apply_components_to_employee($pdo, $employeeId);
        } elseif (!$payrollAuto) {
            employee_sync_gross_salary($pdo, $employeeId);
        }

        dh_sync_employee_org_labels($pdo, $employeeId);

        $username = null;
        if ($wantLogin) {
            $acc = ec_create_user_for_employee($pdo, $employeeId, $fullName, $aadhaarRaw, $tempPassword, $erpRole, $accountStatus);
            $username = $acc['username'];
            ec_set_credential_reveal([
                'full_name' => $fullName,
                'employee_code' => $employeeCode,
                'department' => $deptName,
                'designation' => (string)($desName ?? ''),
                'username' => $username,
                'password_plain' => $tempPassword,
                'role' => $erpRole,
            ]);
        }

        $pdo->commit();

        if ($wantLogin && $username) {
            redirect('employees/credentials');
        }
        set_flash('success', 'Employee added successfully (no ERP login created — optional for workers).');
        redirect('employees/list');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Create employee failed: ' . $e->getMessage());
        redirect('employees/create');
    }
}

$autoEmployeeCode = $nextEmployeeCode($pdo);
$payrollSettingsClient = payroll_settings_for_client(payroll_settings_fetch($pdo));
$payrollSettingsApiUrl = route_url('api/payroll-settings');
?>
<div class="hr-page module-shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">Add Employee</h4>
            <small class="text-muted">Industrial onboarding with optional ERP access</small>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(route_url('employees/list')) ?>">Back to Employees</a>
    </div>
    <form method="post" class="row g-3" id="employeeCreateForm" data-payroll-preview autocomplete="off">
        <?= csrf_input() ?>
        <div class="col-12">
            <div class="card">
                <div class="card-header section-title">Basic Information</div>
                <div class="card-body row g-3">
                    <div class="col-md-4"><label class="form-label">Employee Name <span class="text-danger">*</span></label><input class="form-control" name="full_name" id="inp_full_name" required></div>
                    <div class="col-md-4"><label class="form-label">Father name <span class="text-danger">*</span></label><input class="form-control" name="father_name" required pattern="[\p{L}\s.\-]+" title="Letters, spaces, dot, hyphen"></div>
                    <div class="col-md-4"><label class="form-label">Date of birth <span class="text-danger">*</span></label><input class="form-control" type="date" name="dob" id="inp_dob" max="<?= e(date('Y-m-d')) ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Aadhaar (12 digits) <span class="text-danger">*</span></label><input class="form-control" name="aadhaar_number" id="inp_aadhaar" inputmode="numeric" maxlength="14" pattern="\d{12}" title="12 digits" required></div>
                    <div class="col-md-4"><label class="form-label">Employee Code</label><input class="form-control employee-code-auto" type="text" name="employee_code" value="<?= e($autoEmployeeCode) ?>" readonly title="Generated from next ID"></div>
                    <div class="col-md-4"><label class="form-label">Email (optional)</label><input class="form-control" type="email" name="email"></div>
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
                    <div class="col-md-4">
                        <label class="form-label">Department category <span class="text-danger">*</span></label>
                        <select id="create_org_category_id" class="form-select" data-dept-cascade="1" required></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <select id="create_org_department_id" name="department_id" class="form-select" data-dept-cascade="1" required></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Designation</label>
                        <select id="create_org_designation_id" name="designation_id" class="form-select" data-dept-cascade="1"></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Employee Type</label>
                        <select class="form-select" name="employee_type" id="selEmpType" data-payroll-emp-type><option value="Staff" selected>Staff</option><option value="Worker">Worker</option></select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Salary Type</label><select class="form-select" name="salary_type"><option>Monthly</option><option>Daily Wage</option><option>Hourly</option></select></div>
                    <div class="col-md-2"><label class="form-label">Joining Date</label><input class="form-control" type="date" name="joining_date" required></div>
                    <div class="col-md-3"><label class="form-label">Shift</label><select class="form-select" id="shift_preset" name="shift_preset_select" aria-label="Shift preset"><option value="">Custom / other</option><option value="morning">Morning (09:00–18:00)</option><option value="evening">Evening (14:00–22:00)</option><option value="night">Night (22:00–06:00)</option></select></div>
                    <div class="col-md-3"><label class="form-label">Shift name (saved)</label><input class="form-control" name="shift_timing" id="shift_timing_input" placeholder="e.g. Morning Shift"></div>
                    <div class="col-md-2"><label class="form-label">Shift Start</label><input class="form-control" type="time" name="shift_start" id="shift_start_input"></div>
                    <div class="col-md-2"><label class="form-label">Shift End</label><input class="form-control" type="time" name="shift_end" id="shift_end_input"></div>
                    <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card payroll-erp-card">
                <div class="card-header payroll-erp-card__title d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>Payroll package (Indian structure)</span>
                    <span class="badge text-bg-secondary small">Staff: monthly split · Workers: daily wage from gross</span>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="payroll_auto_indian" value="1" id="chkPayrollAuto" data-payroll-auto checked>
                        <label class="form-check-label" for="chkPayrollAuto"><strong>Automatic salary split</strong> from gross using current <a href="<?= e(route_url('hr/payroll-settings')) ?>">Payroll Settings</a> (Special allowance = remaining balance)</label>
                    </div>
                    <div id="payrollAutoBlock" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Gross monthly salary (₹) <span class="text-danger">*</span></label>
                            <input class="form-control" type="number" step="0.01" name="gross_monthly_salary" id="inpGrossMonthly" data-payroll-gross required min="0" placeholder="e.g. 45000">
                            <small class="text-muted">Fixed earnings per month before overtime (PF/ESI calculated in payroll run)</small>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="metro" value="1" id="chkMetro" data-payroll-metro>
                                <label class="form-check-label" for="chkMetro">Metro city (higher HRA % from Payroll Settings)</label>
                            </div>
                        </div>
                        <div class="col-md-2"><label class="form-label">PF %</label><input class="form-control" type="number" step="0.01" name="pf_percentage" value="<?= e((string)$payrollSettingsClient['pf_employee_pct']) ?>"></div>
                        <div class="col-md-2"><label class="form-label">ESI % (employee)</label><input class="form-control" type="number" step="0.01" name="esi_percentage" value="<?= e((string)$payrollSettingsClient['esi_employee_pct']) ?>"></div>
                        <div class="col-md-1 d-flex flex-column justify-content-end">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="pf_applicable" id="chkPf" checked><label class="form-check-label" for="chkPf">PF</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="esi_applicable" id="chkEsi" checked><label class="form-check-label" for="chkEsi">ESI</label></div>
                        </div>
                        <div class="col-12">
                            <div class="row g-2 small text-muted payroll-preview-grid" id="payrollPreviewRow">
                                <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">Basic (est.)</span><span class="payroll-kv__v" data-pv="basic">—</span></div></div>
                                <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">DA</span><span class="payroll-kv__v" data-pv="da">—</span></div></div>
                                <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">HRA</span><span class="payroll-kv__v" data-pv="hra">—</span></div></div>
                                <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">Medical</span><span class="payroll-kv__v" data-pv="medical">—</span></div></div>
                                <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">Travel</span><span class="payroll-kv__v" data-pv="travel">—</span></div></div>
                                <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">Special</span><span class="payroll-kv__v" data-pv="special">—</span></div></div>
                                <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">Daily wage</span><span class="payroll-kv__v" data-pv="dw">—</span></div></div>
                                <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">Hourly (OT base)</span><span class="payroll-kv__v" data-pv="hw">—</span></div></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center gap-2 py-2 px-3 rounded border border-danger border-opacity-25 bg-light">
                                <span class="small text-muted mb-0">Declared gross</span>
                                <strong class="text-danger fs-5 mb-0" data-payroll-ctc>₹0</strong>
                                <span class="small text-muted mb-0">Live preview from saved Payroll Settings (refreshed on each change).</span>
                            </div>
                        </div>
                    </div>
                    <div id="payrollManualBlock" class="row g-3 d-none border-top pt-3 mt-2">
                        <p class="small text-muted">Manual entry — totals are summed as gross; PF is on Basic only in payroll.</p>
                        <div class="col-md-2"><label class="form-label">Basic</label><input class="form-control" type="number" step="0.01" name="basic_salary" id="sal_inp_basic" value="0"></div>
                        <div class="col-md-2"><label class="form-label">DA</label><input class="form-control" type="number" step="0.01" name="dearness_allowance" id="sal_inp_da" value="0"></div>
                        <div class="col-md-2"><label class="form-label">HRA %</label><input class="form-control" type="number" step="0.01" name="hra_percentage" id="sal_inp_hra_pct" value="40"></div>
                        <div class="col-md-2"><label class="form-label">HRA ₹</label><input class="form-control" type="number" step="0.01" name="hra_amount" id="sal_inp_hra_amt" value="0"></div>
                        <div class="col-md-2"><label class="form-label">Medical</label><input class="form-control" type="number" step="0.01" name="medical_allowance" id="sal_inp_medical" value="0"></div>
                        <div class="col-md-2"><label class="form-label">Travel</label><input class="form-control" type="number" step="0.01" name="travel_allowance" id="sal_inp_travel" value="0"></div>
                        <div class="col-md-2"><label class="form-label">Special</label><input class="form-control" type="number" step="0.01" name="special_allowance" id="sal_inp_special" value="0"></div>
                        <div class="col-md-2"><label class="form-label">Other</label><input class="form-control" type="number" step="0.01" name="other_allowances" id="sal_inp_other" value="0"></div>
                        <div class="col-md-2"><label class="form-label">Gratuity accrual ₹</label><input class="form-control" type="number" step="0.01" name="gratuity_monthly" value="0"></div>
                        <div class="col-md-2"><label class="form-label">OT rate</label><input class="form-control" type="number" step="0.01" name="overtime_rate" value="0"></div>
                        <div class="col-md-2"><label class="form-label">Daily wage</label><input class="form-control" type="number" step="0.01" name="daily_wage" value="0"></div>
                        <div class="col-md-2"><label class="form-label">Hourly rate</label><input class="form-control" type="number" step="0.01" name="hourly_rate" value="0"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header section-title">Leave Policy</div>
                <div class="card-body row g-3">
                    <div class="col-md-2"><label class="form-label">Paid Leave</label><input class="form-control" type="number" step="0.01" name="paid_leave_limit" value="12"></div>
                    <div class="col-md-2"><label class="form-label">Half Paid Leave</label><input class="form-control" type="number" step="0.01" name="half_paid_leave_limit" value="6"></div>
                    <div class="col-md-2"><label class="form-label">Weekly Off</label><select class="form-select" name="weekly_off"><option>Sunday</option><option>Saturday</option></select></div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-danger border-opacity-25 shadow-sm">
                <div class="card-header section-title text-bg-light border-danger border-opacity-25">Employee Login Access</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <div class="form-check form-switch mb-2" id="wrapLoginSwitch">
                            <input class="form-check-input" type="checkbox" name="create_login_access" value="1" id="chkCreateLogin" checked>
                            <label class="form-check-label" for="chkCreateLogin"><strong>Create ERP login</strong> (username + temporary password)</label>
                        </div>
                        <p class="small text-muted mb-0" id="loginHelpStaff">Staff members normally require self-service access (attendance, payslips). Login is enabled by default.</p>
                        <p class="small text-muted mb-0 d-none" id="loginHelpWorker">Workers usually do not log in; enable only if this person needs portal access.</p>
                    </div>
                    <div class="col-md-4" id="rowUsernamePreview">
                        <label class="form-label">Username <small class="text-muted">(preview)</small></label>
                        <input class="form-control font-monospace bg-light fw-bold text-danger border-danger border-opacity-25" type="text" id="usernamePreview" readonly value="">
                        <small class="text-muted">Format: first 3 letters of first name + employee ID + last 4 Aadhaar digits</small>
                    </div>
                    <div class="col-md-4" id="rowTempPw">
                        <label class="form-label">Temporary password <small class="text-muted">(preview)</small></label>
                        <input class="form-control font-monospace bg-light fw-bold text-danger border-danger border-opacity-25" type="text" id="inpTempPw" readonly autocomplete="off" value="" aria-readonly="true">
                        <small class="text-muted">Auto-generated from name, date of birth, and last 4 Aadhaar digits. Shown once after save; stored as hash only.</small>
                    </div>
                    <div class="col-md-4" id="rowErpRole">
                        <label class="form-label">ERP role</label>
                        <select class="form-select" name="erp_account_role" id="selErpRole">
                            <?php foreach (ec_allowed_login_roles() as $r): ?>
                                <option value="<?= e($r) ?>" <?= $r === 'Employee' ? 'selected' : '' ?>><?= e($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Suggested from department after you pick department below.</small>
                    </div>
                    <div class="col-md-4" id="rowAccStatus">
                        <label class="form-label">Account status</label>
                        <select class="form-select" name="account_status">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary w-100">Create Employee</button>
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
<?php
$dcVer = is_file(__DIR__ . '/../../assets/js/department-cascade.js') ? (int)filemtime(__DIR__ . '/../../assets/js/department-cascade.js') : (int)time();
$ipsVer = is_file(__DIR__ . '/../../assets/js/indian-payroll-split.js') ? (int)filemtime(__DIR__ . '/../../assets/js/indian-payroll-split.js') : (int)time();
$ppVer = is_file(__DIR__ . '/../../assets/js/payroll-preview.js') ? (int)filemtime(__DIR__ . '/../../assets/js/payroll-preview.js') : (int)time();
?>
<script src="assets/js/department-cascade.js?v=<?= e((string)$dcVer) ?>"></script>
<script src="assets/js/indian-payroll-split.js?v=<?= e((string)$ipsVer) ?>"></script>
<script src="assets/js/payroll-preview.js?v=<?= e((string)$ppVer) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.DepartmentCascade) {
        DepartmentCascade.init({
            categorySelectId: 'create_org_category_id',
            departmentSelectId: 'create_org_department_id',
            designationSelectId: 'create_org_designation_id'
        });
    }
    var predictId = <?= (int)$predictEmpId ?>;
    function firstNameKey(name) {
        var p = (name || '').trim().split(/\s+/);
        var w = (p[0] || 'xxx').replace(/[^a-zA-Z]/g, '');
        return (w.toLowerCase().substring(0, 3) || 'xxx');
    }
    function digitsAadhaar(v) {
        return String(v || '').replace(/\D/g, '').substring(0, 12);
    }
    function updateUsernamePreview() {
        var fn = document.getElementById('inp_full_name').value;
        var ad = digitsAadhaar(document.getElementById('inp_aadhaar').value);
        var suf = ad.length >= 4 ? ad.slice(-4) : '????';
        document.getElementById('usernamePreview').value = firstNameKey(fn) + predictId + suf;
    }
    function twoLettersFromFirstName(name) {
        var key = firstNameKey(name);
        var two = (key.substring(0, 2) || 'xx').toUpperCase();
        if (two.length < 2) {
            two = (two + 'X').substring(0, 2);
        }
        return two;
    }
    function tempPasswordPreview(name, aadhaar, dob) {
        var two = twoLettersFromFirstName(name);
        var ad = digitsAadhaar(aadhaar);
        var last4 = ad.length >= 4 ? ad.slice(-4) : '????';
        var mmdd = '----';
        if (/^\d{4}-\d{2}-\d{2}$/.test(dob || '')) {
            mmdd = dob.substring(5, 7) + dob.substring(8, 10);
        }
        return two + mmdd + '@' + last4;
    }
    function updateTempPasswordPreview() {
        var el = document.getElementById('inpTempPw');
        if (!el) return;
        el.value = tempPasswordPreview(
            document.getElementById('inp_full_name').value,
            document.getElementById('inp_aadhaar').value,
            document.getElementById('inp_dob').value
        );
    }
    document.getElementById('inp_full_name').addEventListener('input', function () {
        updateUsernamePreview();
        updateTempPasswordPreview();
    });
    document.getElementById('inp_aadhaar').addEventListener('input', function () {
        updateUsernamePreview();
        updateTempPasswordPreview();
    });
    document.getElementById('inp_dob').addEventListener('change', updateTempPasswordPreview);
    document.getElementById('inp_dob').addEventListener('input', updateTempPasswordPreview);
    updateUsernamePreview();
    updateTempPasswordPreview();

    var chkAuto = document.getElementById('chkPayrollAuto');
    var manualBlock = document.getElementById('payrollManualBlock');
    var inpGross = document.getElementById('inpGrossMonthly');
    function syncAutoManual() {
        if (!chkAuto || !manualBlock) return;
        var auto = chkAuto.checked;
        manualBlock.classList.toggle('d-none', auto);
        if (inpGross) inpGross.required = auto;
        var sb = document.getElementById('sal_inp_basic');
        if (sb) sb.required = !auto;
    }
    if (chkAuto) {
        chkAuto.addEventListener('change', function () {
            syncAutoManual();
            if (window.PayrollPreview) {
                PayrollPreview.refreshAll();
            }
        });
    }
    syncAutoManual();
    if (window.PayrollPreview) {
        PayrollPreview.init({ settingsUrl: <?= json_encode($payrollSettingsApiUrl, JSON_THROW_ON_ERROR) ?> });
    }

    var selType = document.getElementById('selEmpType');
    var chkLogin = document.getElementById('chkCreateLogin');
    function syncTypeLogin() {
        var worker = selType.value === 'Worker';
        document.getElementById('loginHelpStaff').classList.toggle('d-none', worker);
        document.getElementById('loginHelpWorker').classList.toggle('d-none', !worker);
        if (worker) {
            chkLogin.disabled = false;
            chkLogin.checked = false;
        } else {
            chkLogin.disabled = true;
            chkLogin.checked = true;
        }
        var show = chkLogin.checked;
        ['rowUsernamePreview','rowTempPw','rowErpRole','rowAccStatus'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.classList.toggle('d-none', !show);
        });
    }
    selType.addEventListener('change', syncTypeLogin);
    chkLogin.addEventListener('change', function () {
        if (selType.value === 'Worker') {
            ['rowUsernamePreview','rowTempPw','rowErpRole','rowAccStatus'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.classList.toggle('d-none', !chkLogin.checked);
            });
        }
    });
    syncTypeLogin();
})();
</script>
