<?php
declare(strict_types=1);

/**
 * Shared employee punch UI — expects $punchUi (staff_punch_ui_state), $todayRow, $shiftDisplay.
 * Optional: $punchFormIn, $punchFormOut (hidden field values), $punchRedirectMonth, $punchCompact (bool).
 */
if (!isset($punchUi) || !is_array($punchUi)) {
    throw new RuntimeException('employee_punch_panel requires $punchUi from staff_punch_ui_state().');
}
$row = $punchUi['row'] ?? $todayRow ?? null;
$hasIn = staff_has_punch_in($row);
$hasOut = staff_has_punch_out($row);
$locked = (bool)($punchUi['locked'] ?? false);
$canIn = (bool)($punchUi['can_punch_in'] ?? false);
$canOut = (bool)($punchUi['can_punch_out'] ?? false);
$state = (string)($punchUi['state'] ?? 'none');
$msg = (string)($punchUi['message'] ?? '');
$shiftDisplay = $shiftDisplay ?? '09:00 – 18:00';
$punchFormIn = $punchFormIn ?? 'punch_in';
$punchFormOut = $punchFormOut ?? 'punch_out';
$punchFieldName = $punchFieldName ?? 'action';
$punchCompact = !empty($punchCompact);
$statusLabel = attendance_status_display_label(
    $row['status'] ?? null,
    $hasIn,
    $hasOut
);
$workedLabel = staff_format_worked_duration($row['total_hours'] ?? null);
$inLabel = $hasIn ? date('h:i A', strtotime((string)$row['punch_in_time'])) : '—';
$outLabel = $hasOut ? date('h:i A', strtotime((string)$row['punch_out_time'])) : '—';
?>
<section class="emp-att-punch card<?= $punchCompact ? ' emp-att-punch--compact' : '' ?>">
    <div class="emp-att-punch__head">
        <div>
            <h2 class="emp-att-punch__title"><i class="bi bi-clock-history me-2"></i>Today — Punch</h2>
            <p class="emp-att-punch__meta mb-0">Shift <?= e($shiftDisplay) ?></p>
        </div>
        <?php if (!$locked): ?>
        <div class="emp-att-punch__actions">
            <form method="post" class="m-0">
                <?= csrf_input() ?>
                <input type="hidden" name="<?= e($punchFieldName) ?>" value="<?= e($punchFormIn) ?>">
                <?php if (!empty($punchRedirectMonth)): ?>
                    <input type="hidden" name="redirect_month" value="<?= e((string)$punchRedirectMonth) ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn-success<?= $punchCompact ? ' btn-sm' : '' ?>" <?= !$canIn ? 'disabled' : '' ?>><i class="bi bi-box-arrow-in-right me-1"></i>Punch In</button>
            </form>
            <form method="post" class="m-0">
                <?= csrf_input() ?>
                <input type="hidden" name="<?= e($punchFieldName) ?>" value="<?= e($punchFormOut) ?>">
                <?php if (!empty($punchRedirectMonth)): ?>
                    <input type="hidden" name="redirect_month" value="<?= e((string)$punchRedirectMonth) ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn-primary<?= $punchCompact ? ' btn-sm' : '' ?>" <?= !$canOut ? 'disabled' : '' ?>><i class="bi bi-box-arrow-right me-1"></i>Punch Out</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($locked && $hasIn): ?>
    <div class="emp-punch-complete">
        <div class="emp-punch-complete__badge"><i class="bi bi-check-circle-fill"></i> Attendance completed</div>
        <p class="emp-punch-complete__msg mb-2"><?= e($msg) ?></p>
        <dl class="emp-punch-complete__dl">
            <div><dt>In</dt><dd><?= e($inLabel) ?></dd></div>
            <div><dt>Out</dt><dd><?= e($outLabel) ?></dd></div>
            <div><dt>Worked</dt><dd><?= e($workedLabel) ?></dd></div>
            <div><dt>Status</dt><dd><strong><?= e($statusLabel) ?></strong></dd></div>
        </dl>
    </div>
    <?php else: ?>
    <?php if ($msg !== ''): ?>
        <div class="alert alert-<?= $state === 'active' ? 'info' : 'secondary' ?> py-2 mx-3 mt-2 mb-0 small"><?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($state === 'active'): ?>
        <div class="emp-punch-active mx-3 mt-2 mb-0 small text-primary"><i class="bi bi-record-circle me-1"></i>Session active since <?= e($inLabel) ?> — remember to punch out.</div>
    <?php endif; ?>
    <div class="emp-att-punch__grid">
        <div class="emp-att-punch__item">
            <span>Status</span>
            <strong class="emp-att-punch__status"><?= e($statusLabel) ?></strong>
        </div>
        <div class="emp-att-punch__item">
            <span>Punch in</span>
            <strong><?= e($inLabel) ?></strong>
        </div>
        <div class="emp-att-punch__item">
            <span>Punch out</span>
            <strong><?= e($outLabel) ?></strong>
        </div>
        <div class="emp-att-punch__item">
            <span>Worked</span>
            <strong><?= $hasOut ? e($workedLabel) : '—' ?></strong>
        </div>
        <div class="emp-att-punch__item">
            <span>OT</span>
            <strong><?= e((string)($row['overtime_hours'] ?? 0)) ?> h</strong>
        </div>
        <div class="emp-att-punch__item">
            <span>Late</span>
            <strong><?= $row && (int)($row['is_late'] ?? 0) === 1 ? 'Yes' : 'No' ?></strong>
        </div>
    </div>
    <?php endif; ?>
    <?php if (($row['status'] ?? '') === ATTENDANCE_STATUS_PENDING_VERIFICATION): ?>
        <div class="emp-att-punch__alert"><i class="bi bi-info-circle me-1"></i>Working duration requires HR verification before payroll.</div>
    <?php endif; ?>
</section>
