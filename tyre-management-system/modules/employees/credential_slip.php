<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/employee_credentials.php';
require_once __DIR__ . '/../../config/db.php';

if (!has_role(['Super Admin', 'HR Manager', 'Admin'])) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$bundle = ec_take_credential_reveal();
if (!$bundle) {
    http_response_code(404);
    echo 'No credential data in this session. Use the credential notice from employee creation or password reset.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Employee credential slip — Ralson ERP</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 32px; color: #1a1a1a; }
        .brand { color: #b02a2a; font-weight: 800; letter-spacing: 0.06em; font-size: 14px; text-transform: uppercase; }
        h1 { font-size: 20px; margin: 8px 0 24px; color: #b02a2a; }
        table { border-collapse: collapse; width: 100%; max-width: 520px; }
        td { padding: 10px 12px; border-bottom: 1px solid #e8e8e8; }
        td.k { color: #6b7280; width: 38%; font-size: 13px; }
        .pw { font-family: ui-monospace, monospace; font-weight: 700; color: #b02a2a; font-size: 15px; }
        .foot { margin-top: 28px; font-size: 12px; color: #6b7280; }
        @media print { body { padding: 16px; } }
    </style>
</head>
<body>
    <div class="brand">Ralson India Private Limited</div>
    <h1>Employee ERP access slip</h1>
    <table>
        <tr><td class="k">Employee name</td><td><?= e((string)($bundle['full_name'] ?? '')) ?></td></tr>
        <tr><td class="k">Employee ID</td><td><?= e((string)($bundle['employee_code'] ?? '')) ?></td></tr>
        <tr><td class="k">Department</td><td><?= e((string)($bundle['department'] ?? '')) ?></td></tr>
        <tr><td class="k">Designation</td><td><?= e((string)($bundle['designation'] ?? '—')) ?></td></tr>
        <tr><td class="k">Username</td><td class="pw"><?= e((string)($bundle['username'] ?? '')) ?></td></tr>
        <tr><td class="k">Temporary password</td><td class="pw"><?= e((string)($bundle['password_plain'] ?? '')) ?></td></tr>
        <tr><td class="k">ERP role</td><td><?= e((string)($bundle['role'] ?? 'Employee')) ?></td></tr>
    </table>
    <p class="foot">Confidential — for employee onboarding only. User must change password on first login.</p>
    <script>window.onload = function () { window.print(); };</script>
</body>
</html>
