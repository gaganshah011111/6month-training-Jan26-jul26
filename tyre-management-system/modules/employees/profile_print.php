<?php
declare(strict_types=1);
/** @var array<string,mixed> $profile */
/** @var string $companyName */
$e = $profile['employee'];
$att = $profile['attendance'];
$leave = $profile['leave'];
$autoPrint = ($_GET['profile_export'] ?? '') === 'pdf';
$generated = date('d M Y, h:i A');
$logoUrl = emp_list_company_logo_url();
$initials = strtoupper(substr((string)($e['full_name'] ?? '?'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Profile — <?= e((string)$e['full_name']) ?></title>
    <style>
        @page { size: A4; margin: 14mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 16px; }
        .head { display: flex; gap: 16px; border-bottom: 2px solid #b91c1c; padding-bottom: 12px; margin-bottom: 14px; }
        .head img { max-height: 52px; }
        .photo { width: 72px; height: 72px; border-radius: 8px; background: #f1f5f9; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; color: #b91c1c; flex-shrink: 0; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #991b1b; }
        .meta { font-size: 10px; color: #64748b; }
        h2 { font-size: 12px; margin: 14px 0 6px; color: #334155; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; }
        .grid div { display: flex; gap: 8px; }
        .grid dt { min-width: 110px; color: #64748b; margin: 0; }
        .grid dd { margin: 0; font-weight: 500; }
        .full { grid-column: 1 / -1; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 6px; }
        th, td { border: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; }
        th { background: #f8fafc; }
        .footer { margin-top: 20px; font-size: 9px; color: #94a3b8; display: flex; justify-content: space-between; }
        @media print { .no-print { display: none !important; } }
    </style>
    <?php if ($autoPrint): ?><script>window.onload = function () { window.print(); };</script><?php endif; ?>
</head>
<body>
    <div class="head">
        <div class="photo"><?= e($initials) ?></div>
        <div class="flex-grow-1">
            <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt="Logo" style="max-height:36px;margin-bottom:6px"><?php endif; ?>
            <div style="font-weight:600;font-size:13px"><?= e($companyName) ?></div>
            <h1><?= e((string)$e['full_name']) ?></h1>
            <div class="meta">
                Code: <strong><?= e((string)$e['employee_code']) ?></strong> ·
                Status: <?= e(ucfirst((string)($e['status'] ?? 'active'))) ?> ·
                Generated: <?= e($generated) ?>
            </div>
        </div>
    </div>

    <h2>Personal Information</h2>
    <dl class="grid">
        <div><dt>Father name</dt><dd><?= e((string)($e['father_name'] ?? '—')) ?></dd></div>
        <div><dt>Date of birth</dt><dd><?= e((string)($e['dob'] ?? '—')) ?></dd></div>
        <div><dt>Phone</dt><dd><?= e((string)($e['contact_no'] ?? '—')) ?></dd></div>
        <div><dt>Aadhaar</dt><dd><?= e(ec_mask_aadhaar($e['aadhaar_number'] ?? null)) ?></dd></div>
        <div class="full"><dt>Address</dt><dd><?= e((string)($e['address'] ?? '—')) ?></dd></div>
    </dl>

    <h2>Organization & Work</h2>
    <dl class="grid">
        <div><dt>Category</dt><dd><?= e((string)($e['dept_category_name'] ?? '—')) ?></dd></div>
        <div><dt>Department</dt><dd><?= e(emp_list_row_department($e)) ?></dd></div>
        <div><dt>Designation</dt><dd><?= e(emp_list_row_designation($e)) ?></dd></div>
        <div><dt>Employee type</dt><dd><?= e((string)($e['employee_type'] ?? '—')) ?></dd></div>
        <div><dt>Shift</dt><dd><?= e((string)($e['shift_timing'] ?? '—')) ?></dd></div>
        <div><dt>Joining date</dt><dd><?= e((string)($e['joining_date'] ?? '—')) ?></dd></div>
        <div><dt>Assigned machine</dt><dd><?= e((string)$profile['machine']) ?></dd></div>
    </dl>

    <h2>Payroll Summary</h2>
    <dl class="grid">
        <div><dt>Salary type</dt><dd><?= e((string)($e['salary_type'] ?? '—')) ?></dd></div>
        <div><dt>Monthly gross</dt><dd>₹<?= e(number_format((float)$profile['gross'], 0)) ?></dd></div>
        <div><dt>Basic / DA</dt><dd>₹<?= e(number_format((float)($e['basic_salary'] ?? 0), 0)) ?> / ₹<?= e(number_format((float)($e['dearness_allowance'] ?? 0), 0)) ?></dd></div>
        <div><dt>PF / ESI</dt><dd><?= !empty($e['pf_applicable']) ? 'Yes' : 'No' ?> / <?= !empty($e['esi_applicable']) ? 'Yes' : 'No' ?></dd></div>
    </dl>

    <h2>Attendance Summary (<?= e((string)$att['label']) ?>)</h2>
    <dl class="grid">
        <div><dt>Present days</dt><dd><?= e((string)$att['present_days']) ?> / <?= e((string)$att['total_days']) ?></dd></div>
        <div><dt>Attendance %</dt><dd><?= e((string)$att['present_pct']) ?>%</dd></div>
        <div><dt>Overtime hours</dt><dd><?= e(number_format((float)$att['ot_hours'], 1)) ?> h</dd></div>
    </dl>

    <h2>Leave Balance (<?= e(date('F Y')) ?>)</h2>
    <dl class="grid">
        <div><dt>Paid leave</dt><dd><?= e(number_format((float)$leave['paid_used'], 1)) ?> used · <?= e(number_format((float)$leave['paid_balance'], 1)) ?> balance</dd></div>
        <div><dt>Half paid leave</dt><dd><?= e(number_format((float)$leave['half_paid_used'], 1)) ?> used · <?= e(number_format((float)$leave['half_paid_balance'], 1)) ?> balance</dd></div>
    </dl>

    <h2>Login Access</h2>
    <dl class="grid">
        <div><dt>Username</dt><dd class="font-monospace"><?= e((string)($e['login_username'] ?: '—')) ?></dd></div>
        <div><dt>Email</dt><dd><?= e((string)($e['user_email'] ?? $e['email'] ?? '—')) ?></dd></div>
    </dl>

    <?php if (!empty($profile['increments'])): ?>
    <h2>Recent Salary Increments</h2>
    <table>
        <thead><tr><th>Old</th><th>New</th><th>%</th><th>Effective</th><th>Reason</th></tr></thead>
        <tbody>
        <?php foreach ($profile['increments'] as $inc): ?>
            <tr>
                <td><?= e(number_format((float)$inc['old_salary'], 0)) ?></td>
                <td><?= e(number_format((float)$inc['new_salary'], 0)) ?></td>
                <td><?= e((string)$inc['increment_percentage']) ?></td>
                <td><?= e((string)$inc['effective_date']) ?></td>
                <td><?= e((string)($inc['reason'] ?? '—')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="footer">
        <span><?= e($companyName) ?> · Employee Profile</span>
        <span><?= e($generated) ?></span>
    </div>
    <p class="no-print" style="margin-top:14px"><button type="button" onclick="window.print()">Print</button></p>
</body>
</html>
