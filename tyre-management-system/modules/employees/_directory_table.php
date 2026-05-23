<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $rows */
/** @var array<string,mixed> $filters */
/** @var int $empScrollVisibleRows */
$win = emp_profile_month_window();
?>
<div class="emp-table-scroll-hint-bar">
    <span><i class="bi bi-arrows-expand"></i> Scroll horizontally for all columns</span>
    <span class="text-muted"><?= e($win['label']) ?> attendance &amp; OT</span>
</div>
<div class="employee-directory-scroll emp-table-viewport emp-table-viewport--wide"
     style="--emp-visible-rows: <?= (int)$empScrollVisibleRows ?>"
     tabindex="0"
     aria-label="Employee directory — scroll horizontally and vertically">
    <table class="table table-hover align-middle mb-0 employee-list-table employee-list-table--wide">
        <thead>
        <tr>
            <th class="emp-th emp-th-employee">Employee</th>
            <th class="emp-th emp-th-code">Code</th>
            <th class="emp-th emp-th-cat">Category</th>
            <th class="emp-th emp-th-dept">Department</th>
            <th class="emp-th emp-th-desig">Designation</th>
            <th class="emp-th emp-th-shift">Shift</th>
            <th class="emp-th emp-th-type">Type</th>
            <th class="emp-th emp-th-stype">Salary type</th>
            <th class="emp-th emp-th-salary text-end">Monthly salary</th>
            <th class="emp-th emp-th-phone">Phone</th>
            <th class="emp-th emp-th-status">Status</th>
            <th class="emp-th emp-th-joined">Joined</th>
            <th class="emp-th emp-th-aadhaar">Aadhaar</th>
            <th class="emp-th emp-th-machine">Machine</th>
            <th class="emp-th emp-th-att text-end">Att %</th>
            <th class="emp-th emp-th-ot text-end">OT (h)</th>
            <th class="emp-th emp-th-actions text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="17" class="text-center text-muted py-5">No employees match the selected filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <?php
            $empId = (int)$r['id'];
            $initials = strtoupper(substr((string)$r['full_name'], 0, 1));
            $deptLabel = emp_list_row_department($r);
            $desigRaw = emp_list_row_designation($r);
            $type = (string)($r['employee_type'] ?? 'Staff');
            $typeBadge = $type === 'Worker' ? 'badge-worker' : 'badge-staff';
            $rowGross = emp_list_row_gross($r);
            $shiftLabel = trim((string)($r['shift_timing'] ?? ''));
            $joined = (string)($r['joining_date'] ?? '');
            $catLabel = trim((string)($r['dept_category_name'] ?? '')) ?: '—';
            $salType = trim((string)($r['salary_type'] ?? '')) ?: '—';
            $phone = trim((string)($r['contact_no'] ?? '')) ?: '—';
            $aadhaar = ec_mask_aadhaar($r['aadhaar_number'] ?? null);
            $machine = (string)($r['emp_machine'] ?? '—');
            $attPct = (int)($r['emp_att_pct'] ?? 0);
            $otH = (float)($r['emp_ot_hours'] ?? 0);
            $status = strtolower((string)($r['status'] ?? 'active'));
            ?>
            <tr class="emp-row-clickable" data-emp-id="<?= $empId ?>" role="button" tabindex="0" title="Open full profile">
                <td class="emp-td-employee">
                    <div class="d-flex align-items-center gap-2">
                        <span class="employee-avatar"><?= e($initials) ?></span>
                        <span class="employee-name"><?= e((string)$r['full_name']) ?></span>
                    </div>
                </td>
                <td class="emp-td-code"><span class="font-monospace"><?= e((string)$r['employee_code']) ?></span></td>
                <td class="emp-td-cat"><span class="emp-cell-truncate" title="<?= e($catLabel) ?>"><?= e($catLabel) ?></span></td>
                <td class="emp-td-dept"><span class="emp-cell-truncate" title="<?= e($deptLabel) ?>"><?= e($deptLabel) ?></span></td>
                <td class="emp-td-desig"><span class="emp-cell-truncate emp-cell-truncate--muted" title="<?= e($desigRaw) ?>"><?= e($desigRaw) ?></span></td>
                <td class="emp-td-shift"><?= e($shiftLabel !== '' ? $shiftLabel : '—') ?></td>
                <td class="emp-td-type"><span class="badge <?= e($typeBadge) ?>"><?= e($type) ?></span></td>
                <td class="emp-td-stype"><?= e($salType) ?></td>
                <td class="emp-td-salary text-end"><span class="emp-gross-value">₹<?= e(number_format($rowGross, 0)) ?></span></td>
                <td class="emp-td-phone font-monospace"><?= e($phone) ?></td>
                <td class="emp-td-status"><span class="badge <?= $status === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= e(ucfirst($status)) ?></span></td>
                <td class="emp-td-joined"><?= e($joined !== '' ? $joined : '—') ?></td>
                <td class="emp-td-aadhaar font-monospace"><?= e($aadhaar) ?></td>
                <td class="emp-td-machine"><span class="emp-cell-truncate" title="<?= e($machine) ?>"><?= e($machine) ?></span></td>
                <td class="emp-td-att text-end"><?= $attPct > 0 ? e((string)$attPct) . '%' : '—' ?></td>
                <td class="emp-td-ot text-end"><?= $otH > 0 ? e(number_format($otH, 1)) : '—' ?></td>
                <td class="emp-td-actions table-actions text-end">
                    <div class="dropdown employee-row-actions-dd">
                        <button type="button" class="btn btn-sm btn-outline-secondary emp-actions-toggle" data-bs-toggle="dropdown" data-bs-display="static" aria-label="Actions"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><button type="button" class="dropdown-item" data-emp-drawer-id="<?= $empId ?>"><i class="bi bi-layout-sidebar-reverse me-2"></i>Full profile</button></li>
                            <li><a class="dropdown-item" href="<?= e(emp_profile_print_url($filters, $empId, 'pdf')) ?>" target="_blank"><i class="bi bi-file-pdf me-2"></i>Download PDF</a></li>
                            <li><a class="dropdown-item" href="<?= e(emp_profile_print_url($filters, $empId, 'print')) ?>" target="_blank"><i class="bi bi-printer me-2"></i>Print profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editEmp<?= $empId ?>"><i class="bi bi-pencil me-2"></i>Edit</button></li>
                            <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#incEmp<?= $empId ?>"><i class="bi bi-graph-up-arrow me-2"></i>Increment</button></li>
                            <?php if ((int)($r['user_id'] ?? 0) > 0): ?>
                            <li>
                                <form method="post" class="m-0" onsubmit="return confirm('Reset login password?');">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="reset_employee_login">
                                    <input type="hidden" name="id" value="<?= $empId ?>">
                                    <button type="submit" class="dropdown-item"><i class="bi bi-key me-2"></i>Reset login</button>
                                </form>
                            </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" class="m-0" onsubmit="return confirm('Delete this employee and all HR records?');">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $empId ?>">
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
