<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Employee ERP login onboarding: username rules, temp passwords, session one-time reveal.
 */

const EC_CREDENTIAL_SESSION_KEY = '_employee_credential_reveal';

function ec_first_token_from_full_name(string $fullName): string
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return 'xxx';
    }
    $parts = preg_split('/\s+/u', $fullName) ?: [];
    $first = (string)($parts[0] ?? '');
    $letters = preg_replace('/[^a-zA-Z]/u', '', $first) ?? '';
    $letters = strtolower($letters);
    if ($letters === '') {
        return 'xxx';
    }
    return substr($letters, 0, 3);
}

function ec_generate_username(PDO $pdo, string $fullName, int $employeeId, string $aadhaar12): string
{
    $prefix = ec_first_token_from_full_name($fullName);
    $suffix = strlen($aadhaar12) >= 4 ? substr($aadhaar12, -4) : str_pad(substr($aadhaar12, -4), 4, '0', STR_PAD_LEFT);
    $base = $prefix . $employeeId . $suffix;
    $candidate = $base;
    $tries = 0;
    while ($tries < 50) {
        $chk = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $chk->execute(['u' => $candidate]);
        if (!(int)$chk->fetchColumn()) {
            return $candidate;
        }
        $candidate = $base . (string)random_int(1, 9999);
        $tries++;
    }
    return $base . (string)random_int(10000, 99999);
}

/**
 * Deterministic temporary password from basic info (not stored): first 2 letters of first name
 * (uppercase) + calendar month/day from DOB + @ + last 4 Aadhaar digits.
 * Example: Ravi, DOB 1990-05-15, …1245 → RA0515@1245
 */
function ec_generate_temp_password(string $fullName, string $aadhaar12, ?string $dobYmd = null): string
{
    $tok = ec_first_token_from_full_name($fullName);
    $two = strtoupper(substr($tok . 'xx', 0, 2));
    $last4 = strlen($aadhaar12) >= 4 ? substr($aadhaar12, -4) : sprintf('%04d', random_int(0, 9999));
    $mmdd = '0000';
    if ($dobYmd !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($dobYmd), $m)) {
        $mmdd = $m[2] . $m[3];
    } else {
        $mmdd = sprintf('%02d%02d', random_int(1, 12), random_int(1, 28));
    }

    return $two . $mmdd . '@' . $last4;
}

function ec_role_for_department_code(?string $departmentCode): string
{
    $c = $departmentCode ?? '';
    return match ($c) {
        'DEPT_HR' => 'HR Manager',
        'DEPT_PPC', 'DEPT_RAW_MAT', 'DEPT_MIXING', 'DEPT_COMP_PREP', 'DEPT_TIRE_BUILD', 'DEPT_CURING' => 'Production Manager',
        'DEPT_QA_QC', 'DEPT_EHS' => 'Quality Manager',
        'DEPT_LOG_DISP', 'DEPT_WH', 'DEPT_STORE' => 'Inventory Manager',
        'DEPT_PUR' => 'Inventory Manager',
        default => 'Employee',
    };
}

function ec_allowed_login_roles(): array
{
    return ['Employee', 'HR Manager', 'Production Manager', 'Inventory Manager', 'Dispatch Manager', 'Quality Manager'];
}

function ec_normalize_login_role(string $role): string
{
    $role = normalize_role_name($role);
    return in_array($role, ec_allowed_login_roles(), true) ? $role : 'Employee';
}

function ec_mask_aadhaar(?string $aadhaar): string
{
    $d = preg_replace('/\D/', '', (string)$aadhaar) ?? '';
    if (strlen($d) !== 12) {
        return '—';
    }
    return 'XXXX XXXX ' . substr($d, -4);
}

function ec_validate_father_name(string $v): ?string
{
    $v = trim($v);
    if ($v === '') {
        return 'Father name is required.';
    }
    if (!preg_match('/^[\p{L}\s.\-]+$/u', $v)) {
        return 'Father name may only contain letters, spaces, dots, and hyphens.';
    }
    return null;
}

function ec_validate_dob(string $dob): ?string
{
    $dob = trim($dob);
    if ($dob === '') {
        return 'Date of birth is required.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        return 'Invalid date of birth.';
    }
    $t = strtotime($dob . ' 00:00:00');
    if ($t === false) {
        return 'Invalid date of birth.';
    }
    if ($t > strtotime('today')) {
        return 'Date of birth cannot be in the future.';
    }
    return null;
}

function ec_validate_aadhaar(string $a, PDO $pdo, ?int $ignoreEmployeeId = null): ?string
{
    $d = preg_replace('/\D/', '', $a) ?? '';
    if (strlen($d) !== 12) {
        return 'Aadhaar must be exactly 12 digits.';
    }
    $q = 'SELECT id FROM employees WHERE aadhaar_number = :a';
    $p = ['a' => $d];
    if ($ignoreEmployeeId !== null && $ignoreEmployeeId > 0) {
        $q .= ' AND id != :id';
        $p['id'] = $ignoreEmployeeId;
    }
    $st = $pdo->prepare($q . ' LIMIT 1');
    $st->execute($p);
    if ((int)$st->fetchColumn()) {
        return 'This Aadhaar number is already registered to another employee.';
    }
    return null;
}

function ec_set_credential_reveal(array $payload): void
{
    ensure_session_started();
    $_SESSION[EC_CREDENTIAL_SESSION_KEY] = $payload;
}

function ec_take_credential_reveal(): ?array
{
    ensure_session_started();
    $v = $_SESSION[EC_CREDENTIAL_SESSION_KEY] ?? null;
    return is_array($v) ? $v : null;
}

function ec_clear_credential_reveal(): void
{
    ensure_session_started();
    unset($_SESSION[EC_CREDENTIAL_SESSION_KEY]);
}

function ec_create_user_for_employee(
    PDO $pdo,
    int $employeeId,
    string $fullName,
    string $aadhaar12,
    string $plainPassword,
    string $erpRole,
    string $accountStatus
): array {
    $username = ec_generate_username($pdo, $fullName, $employeeId, $aadhaar12);
    $erpRole = ec_normalize_login_role($erpRole);
    $status = strtolower(trim($accountStatus)) === 'inactive' ? 'inactive' : 'active';
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

    $ins = $pdo->prepare('INSERT INTO users (employee_id, full_name, email, username, password_hash, role, status, must_change_password)
        VALUES (:eid, :fn, NULL, :un, :ph, :role, :st, 1)');
    $ins->execute([
        'eid' => $employeeId,
        'fn' => $fullName,
        'un' => $username,
        'ph' => $hash,
        'role' => $erpRole,
        'st' => $status,
    ]);
    $uid = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE employees SET user_id = :uid WHERE id = :eid')->execute(['uid' => $uid, 'eid' => $employeeId]);

    return ['user_id' => $uid, 'username' => $username];
}
