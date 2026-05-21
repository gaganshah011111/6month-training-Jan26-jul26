<?php
/**
 * Stage execution panel — locked | start | running | completed.
 * @var array<string, mixed> $order
 * @var array<string, mixed> $stage
 * @var list<array<string, mixed>> $stages
 * @var list<array<string, mixed>> $machines
 * @var list<array<string, mixed>> $operators
 * @var bool $expanded
 */
$order = $order ?? [];
$stage = $stage ?? [];
$stages = $stages ?? [];
$machines = $machines ?? [];
$operators = $operators ?? [];
$expanded = $expanded ?? false;

$stageName = (string)($stage['stage_name'] ?? '');
$meta = production_stage_visual_meta($stage, $order, $stages);
$isShop = production_is_shop_stage($stageName);
$stageId = (int)($stage['id'] ?? 0);
$status = (string)($stage['status'] ?? PROD_STAGE_PENDING);
$openAttr = $expanded ? ' open' : '';

$machineStatus = '';
$machineAlert = '';
if (!empty($stage['machine_id'])) {
    foreach ($machines as $m) {
        if ((int)$m['id'] === (int)$stage['machine_id']) {
            $mb = production_machine_status_badge((string)($m['status'] ?? ''));
            $machineStatus = $mb['label'];
            if (in_array($m['status'] ?? '', [MACHINE_STATUS_MAINTENANCE, MACHINE_STATUS_BREAKDOWN], true)) {
                $machineAlert = 'Cannot use machine in ' . $machineStatus . ' status.';
            }
            break;
        }
    }
}
$opAbsent = false;
$opName = $stage['operator_name'] ?? '—';
foreach ($operators as $op) {
    if ((int)($op['id'] ?? 0) === (int)($stage['operator_id'] ?? 0)) {
        $opAbsent = (int)($op['is_absent'] ?? 0) === 1;
        $opName = (string)$op['full_name'];
        break;
    }
}
?>
<details class="pw-stage pw-stage--<?= e($meta['visual']) ?>"<?= $openAttr ?>>
    <summary class="pw-stage__summary">
        <div class="pw-stage__summary-left">
            <span class="<?= e($meta['pill_class']) ?> pw-stage__pill">
                <i class="bi <?= e($meta['icon']) ?>"></i>
            </span>
            <div>
                <strong class="pw-stage__title"><?= e($stageName) ?></strong>
                <span class="<?= e($meta['badge_class']) ?>"><?= e($meta['label']) ?></span>
                <?php if ($meta['is_current']): ?><span class="pw-stage__current">Active</span><?php endif; ?>
            </div>
        </div>
        <?php if ($isShop && $status !== PROD_STAGE_PENDING): ?>
            <span class="pw-stage__qty text-muted small"><?= e((string)($stage['produced_qty'] ?? 0)) ?> pcs</span>
        <?php endif; ?>
    </summary>

    <div class="pw-stage__body">
        <?php if ($meta['locked'] && $meta['visual'] === 'locked'): ?>
            <div class="pw-stage__locked">
                <i class="bi bi-lock-fill"></i>
                <p><strong>Stage locked</strong> — complete the previous stage to unlock <?= e($stageName) ?>.</p>
            </div>

        <?php elseif ($isShop && $meta['can_start']): ?>
            <p class="pw-stage__hint small text-muted mb-2">Assign machine and operator, then start <?= e($stageName) ?> execution.</p>
            <?php if ($machineAlert): ?><div class="alert alert-warning py-1 small"><?= e($machineAlert) ?></div><?php endif; ?>
            <form method="post" class="pw-stage__form">
                <?= csrf_input() ?>
                <input type="hidden" name="stage_id" value="<?= $stageId ?>">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small">Machine (Running only)</label>
                        <select class="form-select form-select-sm" name="machine_id" required>
                            <option value="">Select machine</option>
                            <?php foreach ($machines as $m): ?>
                                <?php if (!production_machine_can_run((string)($m['status'] ?? ''))) {
                                    continue;
                                } ?>
                                <option value="<?= (int)$m['id'] ?>"><?= e($m['machine_code']) ?> — <?= e($m['machine_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Operator</label>
                        <select class="form-select form-select-sm" name="operator_id" required>
                            <option value="">Select operator</option>
                            <?php foreach ($operators as $op): ?>
                                <option value="<?= (int)$op['id'] ?>" <?= (int)($op['is_absent'] ?? 0) ? 'disabled' : '' ?>>
                                    <?= e($op['full_name']) ?><?= (int)($op['is_absent'] ?? 0) ? ' (Absent)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Shift</label>
                        <select class="form-select form-select-sm" name="shift">
                            <?php foreach (PRODUCTION_SHIFTS as $sh): ?>
                                <option <?= ($stage['shift'] ?? 'Morning') === $sh ? 'selected' : '' ?>><?= e($sh) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="action" value="stage_start" class="btn btn-primary btn-sm mt-2">
                    <i class="bi bi-play-fill me-1"></i>Start <?= e($stageName) ?>
                </button>
            </form>

        <?php elseif ($isShop && $meta['can_save_fields']): ?>
            <?php if ($opAbsent): ?>
                <div class="alert alert-danger py-1 small"><i class="bi bi-exclamation-triangle me-1"></i>Operator absent today.</div>
            <?php endif; ?>
            <?php if ($machineAlert): ?><div class="alert alert-warning py-1 small"><?= e($machineAlert) ?></div><?php endif; ?>
            <form method="post" class="pw-stage__form">
                <?= csrf_input() ?>
                <input type="hidden" name="stage_id" value="<?= $stageId ?>">
                <input type="hidden" name="machine_id" value="<?= (int)($stage['machine_id'] ?? 0) ?>">
                <input type="hidden" name="operator_id" value="<?= (int)($stage['operator_id'] ?? 0) ?>">
                <input type="hidden" name="shift" value="<?= e($stage['shift'] ?? 'Morning') ?>">
                <div class="pw-stage__grid mb-2">
                    <div><span class="pw-stage__k">Machine</span><span><?= e($stage['machine_code'] ?? '—') ?> <?php if ($machineStatus): ?><span class="pw-badge pw-badge--running"><?= e($machineStatus) ?></span><?php endif; ?></span></div>
                    <div><span class="pw-stage__k">Operator</span><span><?= e($opName) ?></span></div>
                    <div><span class="pw-stage__k">Shift</span><span><?= e($stage['shift'] ?? '—') ?></span></div>
                    <div><span class="pw-stage__k">Started</span><span><?= e(production_format_workflow_time($stage['started_at'] ?? null)) ?></span></div>
                </div>
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label small">Produced qty</label>
                        <input class="form-control form-control-sm" type="number" name="produced_qty" min="0" value="<?= (int)($stage['produced_qty'] ?? 0) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Rejected qty</label>
                        <input class="form-control form-control-sm" type="number" name="rejected_qty" min="0" value="<?= (int)($stage['rejected_qty'] ?? 0) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Downtime (min)</label>
                        <input class="form-control form-control-sm" type="number" name="downtime_minutes" min="0" value="<?= (int)($stage['downtime_minutes'] ?? 0) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Notes</label>
                        <input class="form-control form-control-sm" name="remarks" value="<?= e((string)($stage['remarks'] ?? '')) ?>">
                    </div>
                </div>
                <div class="pw-stage__actions">
                    <?php if ($meta['can_pause']): ?>
                        <button type="submit" name="action" value="stage_pause" class="btn btn-warning btn-sm"><i class="bi bi-pause-fill"></i> Pause</button>
                    <?php endif; ?>
                    <?php if ($meta['can_complete']): ?>
                        <button type="submit" name="action" value="stage_complete" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Complete <?= e($stageName) ?></button>
                    <?php endif; ?>
                    <button type="submit" name="action" value="update_stage" class="btn btn-outline-secondary btn-sm ms-auto">Save progress</button>
                </div>
            </form>

        <?php elseif ($isShop && $status === PROD_STAGE_COMPLETED): ?>
            <div class="pw-stage__grid">
                <div><span class="pw-stage__k">Machine</span><span><?= e($stage['machine_code'] ?? '—') ?></span></div>
                <div><span class="pw-stage__k">Operator</span><span><?= e($opName) ?></span></div>
                <div><span class="pw-stage__k">Produced</span><span><?= e((string)($stage['produced_qty'] ?? 0)) ?></span></div>
                <div><span class="pw-stage__k">Rejected</span><span><?= e((string)($stage['rejected_qty'] ?? 0)) ?></span></div>
                <div><span class="pw-stage__k">Downtime</span><span><?= e((string)($stage['downtime_minutes'] ?? 0)) ?> min</span></div>
                <div><span class="pw-stage__k">Completed</span><span><?= e(production_format_workflow_time($stage['ended_at'] ?? null)) ?></span></div>
            </div>
            <p class="text-success small mb-0 mt-2"><i class="bi bi-check-circle me-1"></i><?= e($stageName) ?> completed — next stage unlocked.</p>

        <?php elseif ($stageName === 'QC'): ?>
            <?php if ($meta['locked']): ?>
                <div class="pw-stage__locked"><i class="bi bi-lock-fill"></i><p>QC locked until <strong>Curing</strong> is completed.</p></div>
            <?php elseif (($order['status'] ?? '') === PROD_ORDER_QC_PENDING): ?>
                <p class="mb-0 small">Batch at Quality Manager — approve/reject on <a href="<?= e(route_url('quality/list')) ?>">QC module</a>.</p>
            <?php elseif ($meta['qc_ready'] ?? false): ?>
                <p class="small text-muted mb-0">Curing complete. Use <strong>Move to QC</strong> above to release batch for inspection.</p>
            <?php endif; ?>

        <?php elseif ($stageName === 'Finished'): ?>
            <?php if ($meta['locked']): ?>
                <div class="pw-stage__locked"><i class="bi bi-lock-fill"></i><p>Finished goods locked until QC approves the batch.</p></div>
            <?php else: ?>
                <p class="text-success small mb-0"><i class="bi bi-box-seam me-1"></i><?= e((string)($order['qc_passed_qty'] ?? 0)) ?> tyres added to finished goods inventory.</p>
            <?php endif; ?>

        <?php else: ?>
            <p class="text-muted small mb-0">Waiting for prior stages.</p>
        <?php endif; ?>
    </div>
</details>
