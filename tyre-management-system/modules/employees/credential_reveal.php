<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee_credentials.php';

if (!has_role(['Super Admin', 'HR Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'dismiss_credentials') {
    verify_csrf();
    ec_clear_credential_reveal();
    set_flash('success', 'Credential notice closed. The temporary password is no longer stored in this session.');
    redirect('employees/list');
}

$bundle = ec_take_credential_reveal();
if (!$bundle) {
    set_flash('warning', 'No pending credential notice. Create an employee or reset a login to see credentials here.');
    redirect('employees/list');
}

$slipJson = json_encode([
    'company' => 'Ralson India Private Limited',
    'employee_name' => (string)($bundle['full_name'] ?? ''),
    'employee_code' => (string)($bundle['employee_code'] ?? ''),
    'department' => (string)($bundle['department'] ?? ''),
    'designation' => (string)($bundle['designation'] ?? ''),
    'username' => (string)($bundle['username'] ?? ''),
    'password' => (string)($bundle['password_plain'] ?? ''),
    'role' => (string)($bundle['role'] ?? 'Employee'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);

?>
<div class="module-shell">
    <div class="card border-danger border-2 shadow-sm mb-3">
        <div class="card-header bg-danger text-white section-title d-flex justify-content-between align-items-center">
            <span><i class="bi bi-shield-lock me-2"></i>Employee account created</span>
            <small class="opacity-75">Temporary password is sensitive — copy now</small>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">This screen is shown once per issuance. After you close it, the temporary password cannot be retrieved from the system.</p>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><strong>Employee ID</strong><div class="font-monospace"><?= e((string)($bundle['employee_code'] ?? '')) ?></div></div>
                <div class="col-md-4"><strong>Username</strong><div class="font-monospace text-primary"><?= e((string)($bundle['username'] ?? '')) ?></div></div>
                <div class="col-md-4"><strong>Temporary password</strong><div class="font-monospace text-danger fw-bold" id="credPlainPw"><?= e((string)($bundle['password_plain'] ?? '')) ?></div></div>
                <div class="col-md-4"><strong>Department</strong><div><?= e((string)($bundle['department'] ?? '')) ?></div></div>
                <div class="col-md-4"><strong>Designation</strong><div><?= e((string)($bundle['designation'] ?? '—')) ?></div></div>
                <div class="col-md-4"><strong>ERP role</strong><div><span class="badge bg-secondary"><?= e((string)($bundle['role'] ?? 'Employee')) ?></span></div></div>
            </div>
            <div class="credential-slip d-none d-print-block border p-3 mt-3" id="credentialSlipPrint">
                <h5 class="text-danger mb-3">Ralson ERP — Employee access slip</h5>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted w-25">Name</td><td><?= e((string)($bundle['full_name'] ?? '')) ?></td></tr>
                    <tr><td class="text-muted">Employee ID</td><td><?= e((string)($bundle['employee_code'] ?? '')) ?></td></tr>
                    <tr><td class="text-muted">Department</td><td><?= e((string)($bundle['department'] ?? '')) ?></td></tr>
                    <tr><td class="text-muted">Designation</td><td><?= e((string)($bundle['designation'] ?? '—')) ?></td></tr>
                    <tr><td class="text-muted">Username</td><td class="font-monospace"><?= e((string)($bundle['username'] ?? '')) ?></td></tr>
                    <tr><td class="text-muted">Temporary password</td><td class="font-monospace fw-bold text-danger"><?= e((string)($bundle['password_plain'] ?? '')) ?></td></tr>
                    <tr><td class="text-muted">Role</td><td><?= e((string)($bundle['role'] ?? 'Employee')) ?></td></tr>
                </table>
                <p class="small text-muted mt-3 mb-0">Keep confidential. Change password on first login.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 no-print">
                <button type="button" class="btn btn-outline-primary" id="btnCopyCred"><i class="bi bi-clipboard me-1"></i>Copy credentials</button>
                <button type="button" class="btn btn-outline-secondary" id="btnPrintCred"><i class="bi bi-printer me-1"></i>Print slip</button>
                <button type="button" class="btn btn-outline-secondary" id="btnDownloadCred"><i class="bi bi-download me-1"></i>Download slip (HTML)</button>
                <a class="btn btn-outline-dark" href="<?= e(route_url('employees/credential-slip')) ?>" target="_blank" rel="noopener"><i class="bi bi-window me-1"></i>Open printable slip</a>
            </div>
            <form method="post" class="mt-4 no-print" onsubmit="return confirm('Close this notice? You will not be able to view the temporary password again from the ERP.');">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="dismiss_credentials">
                <button class="btn btn-danger">I have saved the credentials — Close</button>
                <a class="btn btn-link" href="<?= e(route_url('employees/list')) ?>">Back to directory</a>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var data = <?= $slipJson ?>;
    var text = ['Employee: ' + (data.employee_name || ''), 'Code: ' + (data.employee_code || ''), 'Username: ' + (data.username || ''), 'Temporary password: ' + (data.password || ''), 'Department: ' + (data.department || ''), 'Role: ' + (data.role || '')].join('\n');
    document.getElementById('btnCopyCred').addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { alert('Copied to clipboard.'); }, function () { window.prompt('Copy:', text); });
        } else {
            window.prompt('Copy:', text);
        }
    });
    document.getElementById('btnPrintCred').addEventListener('click', function () {
        window.print();
    });
    document.getElementById('btnDownloadCred').addEventListener('click', function () {
        var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Credential slip</title><style>body{font-family:Segoe UI,Arial,sans-serif;padding:24px;}h1{color:#b02a2a;font-size:18px;}table{border-collapse:collapse;}td{padding:6px 12px;} .muted{color:#666;width:140px;}</style></head><body>' +
            '<h1>' + (data.company || 'Ralson ERP') + ' — Employee access slip</h1><table>' +
            '<tr><td class="muted">Name</td><td>' + escapeHtml(data.employee_name) + '</td></tr>' +
            '<tr><td class="muted">Employee ID</td><td>' + escapeHtml(data.employee_code) + '</td></tr>' +
            '<tr><td class="muted">Department</td><td>' + escapeHtml(data.department) + '</td></tr>' +
            '<tr><td class="muted">Designation</td><td>' + escapeHtml(data.designation) + '</td></tr>' +
            '<tr><td class="muted">Username</td><td><code>' + escapeHtml(data.username) + '</code></td></tr>' +
            '<tr><td class="muted">Temporary password</td><td><strong>' + escapeHtml(data.password) + '</strong></td></tr>' +
            '<tr><td class="muted">Role</td><td>' + escapeHtml(data.role) + '</td></tr></table>' +
            '<p style="margin-top:20px;font-size:12px;color:#666;">Confidential. Change password on first login.</p></body></html>';
        var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'employee-credential-slip.html';
        a.click();
        URL.revokeObjectURL(a.href);
    });
    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; });
    }
})();
</script>
<style>@media print { .no-print, .sidebar, nav, header { display: none !important; } .credential-slip.d-none { display: block !important; } }</style>
