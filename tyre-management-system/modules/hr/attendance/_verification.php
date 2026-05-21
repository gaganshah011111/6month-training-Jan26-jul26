<?php
declare(strict_types=1);
/** @var PDO $pdo */
/** @var string $verifyMonth */
/** @var list<array<string,mixed>> $verificationQueue */
?>
<div class="att-bar mb-3">
    <form method="get" class="d-flex flex-wrap align-items-end gap-2">
        <input type="hidden" name="page" value="attendance/list">
        <input type="hidden" name="att_section" value="verify">
        <div>
            <label class="form-label small mb-0">Month</label>
            <input type="month" class="form-control form-control-sm" name="verify_month" value="<?= e($verifyMonth) ?>">
        </div>
        <button type="submit" class="btn btn-ralson-primary btn-sm">Filter</button>
    </form>
    <p class="small text-muted mb-0 mt-2">Short or incomplete staff punches appear here. Resolve before payroll — excluded from salary until approved.</p>
</div>

<div class="att-panel">
    <div class="att-panel__head d-flex justify-content-between">
        <span>Attendance Verification Queue</span>
        <span class="badge bg-warning text-dark"><?= count($verificationQueue) ?> pending</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm att-table mb-0">
            <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Department</th>
                <th>Punch In</th>
                <th>Punch Out</th>
                <th>Hours</th>
                <th>Remarks</th>
                <th>Resolve as</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$verificationQueue): ?>
                <tr><td colspan="8"><div class="att-empty-card">No records pending verification for this month.</div></td></tr>
            <?php else: ?>
                <?php foreach ($verificationQueue as $vq): ?>
                    <tr>
                        <td><?= e((string)$vq['attendance_date']) ?></td>
                        <td>
                            <strong><?= e((string)$vq['full_name']) ?></strong>
                            <small class="d-block text-muted"><?= e((string)$vq['employee_code']) ?></small>
                        </td>
                        <td><?= e((string)($vq['department'] ?? '—')) ?></td>
                        <td><?= !empty($vq['punch_in_time']) ? e(date('H:i', strtotime((string)$vq['punch_in_time']))) : '—' ?></td>
                        <td><?= !empty($vq['punch_out_time']) ? e(date('H:i', strtotime((string)$vq['punch_out_time']))) : '—' ?></td>
                        <td><?= e((string)($vq['total_hours'] ?? '—')) ?></td>
                        <td class="small"><?= e((string)($vq['remarks'] ?? '')) ?></td>
                        <td>
                            <form method="post" class="d-flex flex-wrap gap-1 align-items-center">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="verify_attendance">
                                <input type="hidden" name="attendance_id" value="<?= (int)$vq['id'] ?>">
                                <input type="hidden" name="ret_att_section" value="verify">
                                <input type="hidden" name="ret_att_date" value="<?= e((string)$vq['attendance_date']) ?>">
                                <select name="verify_status" class="form-select form-select-sm" style="min-width:7rem" required>
                                    <option value="Present">Present</option>
                                    <option value="Half Day">Half Day</option>
                                    <option value="Late">Late</option>
                                    <option value="Absent">Absent</option>
                                </select>
                                <input type="text" name="verify_notes" class="form-control form-control-sm" placeholder="Note" style="max-width:6rem">
                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
