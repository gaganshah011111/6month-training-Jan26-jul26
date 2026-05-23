<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/qc_service.php';
require_once __DIR__ . '/../../includes/production_service.php';
require_once __DIR__ . '/_flow.php';

if (!has_role(['Quality Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$curingId = (int)($_GET['id'] ?? $_POST['curing_id'] ?? 0);
$batch = $curingId > 0 ? qc_get_curing_batch($pdo, $curingId) : null;

if (!$batch) {
    echo '<div class="alert alert-warning">Batch not found. <a href="' . e(route_url('quality/pending')) . '">Back to pending</a></div>';
    return;
}

if (!in_array((string)($batch['qc_status'] ?? ''), [QC_STATUS_PENDING, QC_STATUS_INSPECTING], true)) {
    echo '<div class="alert alert-info">This batch is already inspected (status: ' . e((string)$batch['qc_status']) . ').</div>';
}

$existing = qc_get_inspection_by_curing($pdo, $curingId);
$inspectorDefault = (string)($existing['inspector_name'] ?? qc_current_inspector_default());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');
    $defectLines = [];
    $types = $_POST['defect_type'] ?? [];
    $qtys = $_POST['defect_qty'] ?? [];
    if (is_array($types) && is_array($qtys)) {
        foreach ($types as $i => $t) {
            $defectLines[] = ['defect_type' => (string)$t, 'qty' => (int)($qtys[$i] ?? 0)];
        }
    }
    try {
        qc_save_inspection($pdo, $curingId, $_POST, $defectLines, $action === 'rework' ? 'rework' : 'approve');
        set_flash('success', 'Inspection saved. Passed stock added to inventory; dispatch uses QC-passed stock only.');
        redirect('quality/pending');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        redirect('quality/inspect&id=' . $curingId);
    }
}

$produced = (int)$batch['produced_qty'];
$prefill = $existing ?: [];
?>

<div class="qc-page qc-inspect">
    <?php qc_render_flow_bar('qc'); ?>

    <header class="qc-page__head">
        <div>
            <h1 class="qc-page__title">Inspection Entry</h1>
            <p class="qc-page__sub">Batch <strong><?= e((string)($batch['batch_code'] ?? '')) ?></strong> — record pass, reject, rework and defects</p>
        </div>
        <a href="<?= e(route_url('quality/pending')) ?>" class="btn btn-sm btn-outline-secondary">← Pending list</a>
    </header>

    <form method="post" id="qc-inspect-form" class="qc-inspect-form" data-produced="<?= $produced ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="curing_id" value="<?= $curingId ?>">

        <section class="qc-card qc-card--section">
            <header class="qc-card__head"><span class="qc-step">1</span><h2 class="qc-card__title">Batch information</h2></header>
            <div class="qc-card__body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="qc-label">Batch ID</label>
                        <input class="form-control" readonly value="<?= e((string)($batch['batch_code'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="qc-label">Tyre type</label>
                        <input class="form-control" readonly value="<?= e((string)$batch['tyre_type']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="qc-label">Production date</label>
                        <input class="form-control" readonly value="<?= e((string)$batch['production_date']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="qc-label">Machine</label>
                        <input class="form-control" readonly value="<?= e((string)($batch['machine_code'] ?? '—')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="qc-label">Shift</label>
                        <input class="form-control" readonly value="<?= e((string)$batch['shift']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="qc-label">Produced qty</label>
                        <input class="form-control" readonly value="<?= e(qc_format_qty($produced)) ?>">
                    </div>
                </div>
            </div>
        </section>

        <section class="qc-card qc-card--section">
            <header class="qc-card__head"><span class="qc-step">2</span><h2 class="qc-card__title">Inspection result</h2></header>
            <div class="qc-card__body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="qc-label" for="qc-inspected">Inspected qty</label>
                        <input class="form-control" type="number" id="qc-inspected" name="inspected_qty" min="1" max="<?= $produced ?>" required
                               value="<?= (int)($prefill['inspected_qty'] ?? $produced) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="qc-label" for="qc-passed">Passed qty</label>
                        <input class="form-control" type="number" id="qc-passed" name="passed_qty" min="0" required
                               value="<?= (int)($prefill['passed_qty'] ?? $produced) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="qc-label" for="qc-rejected">Reject qty</label>
                        <input class="form-control" type="number" id="qc-rejected" name="rejected_qty" min="0"
                               value="<?= (int)($prefill['rejected_qty'] ?? 0) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="qc-label" for="qc-rework">Rework qty</label>
                        <input class="form-control" type="number" id="qc-rework" name="rework_qty" min="0"
                               value="<?= (int)($prefill['rework_qty'] ?? 0) ?>">
                    </div>
                </div>
                <p id="qc-qty-error" class="text-danger small mt-2 d-none"></p>
                <p class="qc-hint mt-2">Pass + Reject + Rework must not exceed inspected quantity.</p>
            </div>
        </section>

        <section class="qc-card qc-card--section">
            <header class="qc-card__head"><span class="qc-step">3</span><h2 class="qc-card__title">Defect details</h2></header>
            <div class="qc-card__body">
                <div id="qc-defect-rows">
                    <?php
                    $lines = $prefill['defect_lines'] ?? [['defect_type' => '', 'qty' => '']];
                    if ($lines === []) {
                        $lines = [['defect_type' => '', 'qty' => '']];
                    }
                    foreach ($lines as $idx => $line):
                    ?>
                    <div class="row g-2 mb-2 qc-defect-row">
                        <div class="col-md-7">
                            <select class="form-select" name="defect_type[]">
                                <option value="">— Defect type —</option>
                                <?php foreach (QC_DEFECT_TYPES as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= ($line['defect_type'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" class="form-control" name="defect_qty[]" min="0" placeholder="Qty" value="<?= (int)($line['qty'] ?? 0) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 qc-remove-defect" <?= $idx === 0 ? 'disabled' : '' ?>>Remove</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="qc-add-defect">+ Add defect line</button>
            </div>
        </section>

        <section class="qc-card qc-card--section">
            <header class="qc-card__head"><span class="qc-step">4</span><h2 class="qc-card__title">Inspector information</h2></header>
            <div class="qc-card__body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="qc-label" for="qc-inspector">Inspector name</label>
                        <input class="form-control" id="qc-inspector" name="inspector_name" required value="<?= e($inspectorDefault) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="qc-label" for="qc-insp-date">Inspection date</label>
                        <input class="form-control" type="date" id="qc-insp-date" name="inspection_date" required
                               value="<?= e((string)($prefill['inspection_date'] ?? date('Y-m-d'))) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="qc-label" for="qc-shift">Shift</label>
                        <select class="form-select" id="qc-shift" name="shift">
                            <?php foreach (PRODUCTION_SHIFTS as $sh): ?>
                                <option value="<?= e($sh) ?>" <?= ($prefill['inspection_shift'] ?? $batch['shift']) === $sh ? 'selected' : '' ?>><?= e($sh) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="qc-label" for="qc-remarks">Remarks</label>
                        <textarea class="form-control" id="qc-remarks" name="remarks" rows="2" maxlength="500"><?= e((string)($prefill['remarks'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </section>

        <section class="qc-card qc-card--actions">
            <header class="qc-card__head"><span class="qc-step">5</span><h2 class="qc-card__title">Final QC action</h2></header>
            <div class="qc-card__body qc-inspect-actions">
                <p class="qc-hint mb-0">Passed tyres → finished goods (dispatch). Reject → scrap. Rework → rework stock.</p>
                <div class="qc-inspect-actions__btns">
                    <button type="submit" name="action" value="save" class="btn qc-btn-primary">Save inspection</button>
                    <button type="submit" name="action" value="approve" class="btn btn-success">Approve batch</button>
                    <button type="submit" name="action" value="rework" class="btn btn-outline-warning">Send to rework</button>
                </div>
            </div>
        </section>
    </form>
</div>
