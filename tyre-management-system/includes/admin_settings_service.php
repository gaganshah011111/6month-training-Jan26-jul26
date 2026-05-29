<?php
declare(strict_types=1);

require_once __DIR__ . '/department_hierarchy.php';

const ADMIN_SETTING_KEYS = [
    'company_name' => ['label' => 'Company Name', 'type' => 'text', 'group' => 'company'],
    'company_logo' => ['label' => 'Logo URL', 'type' => 'text', 'group' => 'company'],
    'gst_number' => ['label' => 'GST Number', 'type' => 'text', 'group' => 'tax'],
    'company_address' => ['label' => 'Address', 'type' => 'textarea', 'group' => 'company'],
    'company_email' => ['label' => 'Email', 'type' => 'email', 'group' => 'company'],
    'company_phone' => ['label' => 'Phone', 'type' => 'text', 'group' => 'company'],
    'financial_year_start' => ['label' => 'Financial Year Start (MM-DD)', 'type' => 'text', 'group' => 'finance'],
    'currency' => ['label' => 'Currency', 'type' => 'text', 'group' => 'finance'],
    'timezone' => ['label' => 'Timezone', 'type' => 'text', 'group' => 'company'],
    'password_min_length' => ['label' => 'Minimum Password Length', 'type' => 'number', 'group' => 'security'],
    'password_require_upper' => ['label' => 'Require Uppercase Letter (1/0)', 'type' => 'text', 'group' => 'security'],
    'login_max_attempts' => ['label' => 'Max Failed Login Attempts', 'type' => 'number', 'group' => 'security'],
    'session_timeout_minutes' => ['label' => 'Session Timeout (minutes)', 'type' => 'number', 'group' => 'security'],
    'login_lockout_minutes' => ['label' => 'Login Lockout Duration (minutes)', 'type' => 'number', 'group' => 'security'],
];

function admin_settings_ensure(PDO $pdo): void
{
    foreach (array_keys(ADMIN_SETTING_KEYS) as $key) {
        $default = match ($key) {
            'company_name' => 'Ralson India Private Limited - Tyre ERP',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'financial_year_start' => '04-01',
            'password_min_length' => '6',
            'password_require_upper' => '0',
            'login_max_attempts' => '5',
            'session_timeout_minutes' => '480',
            'login_lockout_minutes' => '15',
            default => '',
        };
        $st = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (:k, :v)');
        $st->execute(['k' => $key, 'v' => $default]);
    }
}

/** @return array<string, string> */
function admin_settings_load(PDO $pdo): array
{
    admin_settings_ensure($pdo);
    $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    $out = [];
    foreach (ADMIN_SETTING_KEYS as $key => $_meta) {
        $out[$key] = (string)($rows[$key] ?? '');
    }

    return $out;
}

function admin_settings_save(PDO $pdo, array $data): void
{
    admin_settings_ensure($pdo);
    verify_csrf();
    $st = $pdo->prepare('UPDATE settings SET setting_value = :v WHERE setting_key = :k');
    foreach (ADMIN_SETTING_KEYS as $key => $_meta) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        $st->execute(['k' => $key, 'v' => trim((string)$data[$key])]);
    }
}
