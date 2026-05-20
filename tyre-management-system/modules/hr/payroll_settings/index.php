<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/indian_payroll.php';

if (!has_role(['Super Admin', 'Admin', 'HR Manager'])) {
    echo 'Access denied';

    return;
}

$pdo = Database::connection();
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        payroll_settings_save($pdo, $_POST);
        set_flash('success', 'Payroll settings saved.');
    } catch (Throwable $e) {
        set_flash('danger', 'Save failed: ' . $e->getMessage());
    }
    redirect('hr/payroll-settings');
}

$ps = payroll_settings_fetch($pdo);
?>
<div class="hr-page module-shell payroll-settings-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-0">Payroll Settings</h4>
            <small class="text-muted">Default Indian statutory percentages and allowances (per-employee overrides still apply where enabled)</small>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(route_url('payroll/list')) ?>">Back to Payroll</a>
    </div>

    <form method="post" class="row g-3">
        <?= csrf_input() ?>

        <div class="col-12 col-xl-6">
            <div class="card payroll-erp-card h-100">
                <div class="card-header payroll-erp-card__title">Core structure</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Basic % of gross</label>
                        <input class="form-control" type="number" step="0.01" name="basic_pct_of_gross" value="<?= e((string)$ps['basic_pct_of_gross']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gratuity % of basic (accrual)</label>
                        <input class="form-control" type="number" step="0.001" name="gratuity_pct_of_basic" value="<?= e((string)$ps['gratuity_pct_of_basic']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">DA % of basic</label>
                        <input class="form-control" type="number" step="0.01" name="da_pct_of_basic" value="<?= e((string)$ps['da_pct_of_basic']) ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="da_enabled" value="1" id="ps_da_en" <?= !empty($ps['da_enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ps_da_en">Enable DA</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">HRA % (non-metro)</label>
                        <input class="form-control" type="number" step="0.01" name="hra_pct_non_metro" value="<?= e((string)$ps['hra_pct_non_metro']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">HRA % (metro)</label>
                        <input class="form-control" type="number" step="0.01" name="hra_pct_metro" value="<?= e((string)$ps['hra_pct_metro']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card payroll-erp-card h-100">
                <div class="card-header payroll-erp-card__title">Medical & travel</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="medical_enabled" value="1" id="ps_med_en" <?= !empty($ps['medical_enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ps_med_en">Medical allowance enabled</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Medical mode</label>
                        <select class="form-select" name="medical_mode">
                            <option value="fixed" <?= (($ps['medical_mode'] ?? 'fixed') === 'fixed') ? 'selected' : '' ?>>Fixed amount</option>
                            <option value="percent" <?= (($ps['medical_mode'] ?? '') === 'percent') ? 'selected' : '' ?>>% of basic</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Medical fixed (₹)</label>
                        <input class="form-control" type="number" step="0.01" name="medical_fixed" value="<?= e((string)$ps['medical_fixed']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Medical % of basic</label>
                        <input class="form-control" type="number" step="0.01" name="medical_pct_of_basic" value="<?= e((string)$ps['medical_pct_of_basic']) ?>">
                    </div>
                    <div class="col-12 border-top pt-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="travel_enabled" value="1" id="ps_tr_en" <?= !empty($ps['travel_enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ps_tr_en">Travel allowance enabled</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Travel mode</label>
                        <select class="form-select" name="travel_mode">
                            <option value="fixed" <?= (($ps['travel_mode'] ?? 'fixed') === 'fixed') ? 'selected' : '' ?>>Fixed</option>
                            <option value="percent" <?= (($ps['travel_mode'] ?? '') === 'percent') ? 'selected' : '' ?>>% of basic</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Travel fixed (₹)</label>
                        <input class="form-control" type="number" step="0.01" name="travel_fixed" value="<?= e((string)$ps['travel_fixed']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Travel % of basic</label>
                        <input class="form-control" type="number" step="0.01" name="travel_pct_of_basic" value="<?= e((string)$ps['travel_pct_of_basic']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card payroll-erp-card h-100">
                <div class="card-header payroll-erp-card__title">PF / ESI</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">PF employee % (on basic)</label>
                        <input class="form-control" type="number" step="0.01" name="pf_employee_pct" value="<?= e((string)$ps['pf_employee_pct']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">PF employer % (on basic)</label>
                        <input class="form-control" type="number" step="0.01" name="pf_employer_pct" value="<?= e((string)$ps['pf_employer_pct']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ESI employee %</label>
                        <input class="form-control" type="number" step="0.01" name="esi_employee_pct" value="<?= e((string)$ps['esi_employee_pct']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ESI employer %</label>
                        <input class="form-control" type="number" step="0.01" name="esi_employer_pct" value="<?= e((string)$ps['esi_employer_pct']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ESI gross ceiling (₹)</label>
                        <input class="form-control" type="number" step="0.01" name="esi_gross_limit" value="<?= e((string)$ps['esi_gross_limit']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card payroll-erp-card h-100">
                <div class="card-header payroll-erp-card__title">Daily wage & attendance policy</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Default working days / month</label>
                        <input class="form-control" type="number" step="0.01" name="working_days_default" value="<?= e((string)$ps['working_days_default']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Default shift hours</label>
                        <input class="form-control" type="number" step="0.01" name="shift_hours_default" value="<?= e((string)$ps['shift_hours_default']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">OT multiplier (× hourly)</label>
                        <input class="form-control" type="number" step="0.01" name="ot_multiplier" value="<?= e((string)$ps['ot_multiplier']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Late deduction % of daily wage / late day</label>
                        <input class="form-control" type="number" step="0.01" name="late_deduction_pct_of_daily" value="<?= e((string)$ps['late_deduction_pct_of_daily']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary btn-lg px-5">Save settings</button>
        </div>
    </form>
</div>
