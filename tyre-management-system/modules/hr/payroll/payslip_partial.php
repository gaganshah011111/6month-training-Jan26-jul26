<?php
declare(strict_types=1);
/** @var array $employee @var array $selectedSlip */
$deptLabel = (string)($employee['dept_label'] ?? $employee['department'] ?? '');
?>
<div class="card shadow-sm payslip-erp mx-auto" style="max-width: 960px;">
    <div class="payslip-erp__head d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="text-white mb-0">Salary Slip</h5>
            <div class="payslip-erp__sub">Pay period <?= e((string)$selectedSlip['month_year']) ?> · <?= e((string)$employee['full_name']) ?></div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="row g-0 border-bottom">
            <div class="col-md-6 p-3 border-end"><span class="text-muted small">Employee</span><div class="fw-semibold"><?= e((string)$employee['full_name']) ?></div></div>
            <div class="col-md-6 p-3"><span class="text-muted small">Department / ID</span><div class="fw-semibold"><?= e($deptLabel) ?> · <?= e((string)($employee['employee_code'] ?? '')) ?></div></div>
        </div>
        <div class="p-3">
            <div class="row">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <h6 class="text-uppercase small text-muted mb-2">Earnings</h6>
                    <table class="table table-sm table-bordered mb-0">
                        <tbody>
                        <tr><th>Basic</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['basic'] ?? 0), 2)) ?></td></tr>
                        <tr><th>DA</th><td class="text-end">₹ <?= e(number_format($slDa, 2)) ?></td></tr>
                        <tr><th>HRA (<?= e((string)($selectedSlip['hra_percentage'] ?? '0')) ?>%)</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['hra_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Medical</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['medical_allowance'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Travel (TA)</th><td class="text-end">₹ <?= e(number_format($slTa, 2)) ?></td></tr>
                        <tr><th>Special</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['special_allowance'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Other</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['other_allowances'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Overtime</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['overtime_amount'] ?? 0), 2)) ?></td></tr>
                        <tr class="table-light"><th>Gross salary</th><td class="text-end"><strong>₹ <?= e(number_format((float)($selectedSlip['gross_salary'] ?? 0), 2)) ?></strong></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-lg-6">
                    <h6 class="text-uppercase small text-muted mb-2">Deductions</h6>
                    <table class="table table-sm table-bordered mb-0">
                        <tbody>
                        <tr><th>PF (employee)</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['pf_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>ESI (employee)</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['esi_employee_amount'] ?? 0), 2)) ?></td></tr>
                        <tr><th>TDS / Tax</th><td class="text-end">₹ <?= e(number_format($slTax, 2)) ?></td></tr>
                        <tr><th>Leave</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['leave_deduction'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Half-day</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['half_day_deduction'] ?? 0), 2)) ?></td></tr>
                        <tr><th>Late entry</th><td class="text-end">₹ <?= e(number_format((float)($selectedSlip['late_entry_deduction'] ?? 0), 2)) ?></td></tr>
                        <?php if ($slManual > 0): ?>
                        <tr><th>Other</th><td class="text-end">₹ <?= e(number_format($slManual, 2)) ?></td></tr>
                        <?php endif; ?>
                        <tr class="table-light"><th>Total deductions</th><td class="text-end"><strong>₹ <?= e(number_format((float)($selectedSlip['total_deduction'] ?? 0), 2)) ?></strong></td></tr>
                        </tbody>
                    </table>
                    <div class="border rounded p-3 bg-light mt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Net salary (in-hand)</span>
                            <span class="fs-4 text-success fw-bold">₹ <?= e(number_format((float)($selectedSlip['net_salary'] ?? 0), 2)) ?></span>
                        </div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">Generated: <?= e((string)($selectedSlip['generated_at'] ?? '')) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
