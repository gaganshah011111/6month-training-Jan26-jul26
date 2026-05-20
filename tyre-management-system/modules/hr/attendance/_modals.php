<div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="post" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="save_holiday">
            <input type="hidden" name="holiday_type" value="Company Holiday">
            <input type="hidden" name="ret_att_section" value="mark">
            <input type="hidden" name="ret_att_date" value="<?= e($att_date) ?>">
            <input type="hidden" name="ret_q_emp" value="<?= e($q_emp) ?>">
            <input type="hidden" name="ret_q_name" value="<?= e($q_name) ?>">
            <input type="hidden" name="ret_dept" value="<?= e($dept) ?>">
            <input type="hidden" name="ret_emp_type" value="<?= e($emp_type) ?>">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">Mark holiday</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2">
                <div class="col-12">
                    <label class="form-label small">Date</label>
                    <input type="date" class="form-control form-control-sm" name="holiday_date" required value="<?= e($att_date) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label small">Holiday name</label>
                    <input type="text" class="form-control form-control-sm" name="holiday_name" required placeholder="e.g. Diwali">
                </div>
                <div class="col-12">
                    <label class="form-label small">Department (optional)</label>
                    <select class="form-select form-select-sm" name="holiday_department">
                        <option value="">All employees</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= e((string)$d) ?>"><?= e((string)$d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="submit" class="btn btn-ralson-primary btn-sm w-100">Apply to all staff</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="bulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="post" class="modal-content" onsubmit="return confirm('Apply bulk status to all filtered employees?');">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="bulk_mark">
            <input type="hidden" name="attendance_date" value="<?= e($att_date) ?>">
            <input type="hidden" name="ret_att_section" value="mark">
            <input type="hidden" name="ret_att_date" value="<?= e($att_date) ?>">
            <input type="hidden" name="ret_q_emp" value="<?= e($q_emp) ?>">
            <input type="hidden" name="ret_q_name" value="<?= e($q_name) ?>">
            <input type="hidden" name="ret_dept" value="<?= e($dept) ?>">
            <input type="hidden" name="ret_emp_type" value="<?= e($emp_type) ?>">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">Bulk entry</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small">Status for filtered employees</label>
                <select class="form-select form-select-sm att-status-select" name="bulk_status" required>
                    <?php foreach ($markStatusOpts as $st): ?>
                        <option value="<?= e($st) ?>"><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer py-2">
                <button type="submit" class="btn btn-ralson-primary btn-sm w-100">Apply</button>
            </div>
        </form>
    </div>
</div>

<!-- Single-row save -->
<form method="post" id="att-single-save-form" class="d-none">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="mark_attendance">
    <input type="hidden" name="employee_id" id="att-single-emp-id" value="">
    <input type="hidden" name="attendance_date" value="<?= e($att_date) ?>">
    <input type="hidden" name="punch_in" id="att-single-pi" value="">
    <input type="hidden" name="punch_out" id="att-single-po" value="">
    <input type="hidden" name="status" id="att-single-st" value="">
    <input type="hidden" name="ret_att_section" value="mark">
    <input type="hidden" name="ret_att_date" value="<?= e($att_date) ?>">
    <input type="hidden" name="ret_q_emp" value="<?= e($q_emp) ?>">
    <input type="hidden" name="ret_q_name" value="<?= e($q_name) ?>">
    <input type="hidden" name="ret_dept" value="<?= e($dept) ?>">
    <input type="hidden" name="ret_emp_type" value="<?= e($emp_type) ?>">
</form>
