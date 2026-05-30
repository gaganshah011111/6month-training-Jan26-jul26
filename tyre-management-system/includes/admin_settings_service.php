<?php
declare(strict_types=1);

require_once __DIR__ . '/department_hierarchy.php';

const ADMIN_COMPANY_KEYS = [
    'company_name' => ['label' => 'Company Name', 'type' => 'text'],
    'company_address' => ['label' => 'Address', 'type' => 'textarea'],
    'company_logo' => ['label' => 'Logo URL', 'type' => 'text'],
    'gst_number' => ['label' => 'GST Number', 'type' => 'text'],
    'company_email' => ['label' => 'Email', 'type' => 'email'],
    'company_phone' => ['label' => 'Phone', 'type' => 'text'],
    'financial_year_start' => ['label' => 'Financial Year Start (MM-DD)', 'type' => 'text'],
    'currency' => ['label' => 'Default Currency', 'type' => 'text'],
    'default_tax_rate' => ['label' => 'Default Tax Rate (%)', 'type' => 'text'],
    'timezone' => ['label' => 'Timezone', 'type' => 'text'],
];

const ADMIN_SYSTEM_KEYS = [
    'password_min_length' => ['label' => 'Minimum Password Length', 'type' => 'number'],
    'password_require_upper' => ['label' => 'Require Uppercase (1=yes, 0=no)', 'type' => 'text'],
    'login_max_attempts' => ['label' => 'Max Failed Login Attempts', 'type' => 'number'],
    'session_timeout_minutes' => ['label' => 'Session Timeout (minutes)', 'type' => 'number'],
    'login_lockout_minutes' => ['label' => 'Login Lockout Duration (minutes)', 'type' => 'number'],
    'notify_low_stock' => ['label' => 'Low Stock Notifications (1=yes)', 'type' => 'text'],
    'notify_backup' => ['label' => 'Backup Reminder Notifications (1=yes)', 'type' => 'text'],
];

/** @return array<string, array<string, string>> */
function admin_all_setting_keys(): array
{
    return array_merge(ADMIN_COMPANY_KEYS, ADMIN_SYSTEM_KEYS);
}

function admin_settings_ensure(PDO $pdo): void
{
    foreach (admin_all_setting_keys() as $key => $_meta) {
        $default = match ($key) {
            'company_name' => 'Ralson India Private Limited - Tyre ERP',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'financial_year_start' => '04-01',
            'default_tax_rate' => '18',
            'password_min_length' => '6',
            'password_require_upper' => '0',
            'login_max_attempts' => '5',
            'session_timeout_minutes' => '480',
            'login_lockout_minutes' => '15',
            'notify_low_stock' => '1',
            'notify_backup' => '1',
            default => '',
        };
        $st = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (:k, :v)');
        $st->execute(['k' => $key, 'v' => $default]);
    }
}

/** @return array<string, string> */
function admin_settings_load(PDO $pdo, ?array $keys = null): array
{
    admin_settings_ensure($pdo);
    $keyMap = $keys ?? admin_all_setting_keys();
    $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    $out = [];
    foreach ($keyMap as $key => $_meta) {
        $out[$key] = (string)($rows[$key] ?? '');
    }

    return $out;
}

function admin_settings_save_keys(PDO $pdo, array $data, array $allowedKeys): void
{
    admin_settings_ensure($pdo);
    verify_csrf();
    $st = $pdo->prepare('UPDATE settings SET setting_value = :v WHERE setting_key = :k');
    foreach ($allowedKeys as $key => $_meta) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        $st->execute(['k' => $key, 'v' => trim((string)$data[$key])]);
    }
}

function admin_company_settings_save(PDO $pdo, array $data): void
{
    admin_settings_save_keys($pdo, $data, ADMIN_COMPANY_KEYS);
}

function admin_system_settings_save(PDO $pdo, array $data): void
{
    admin_settings_save_keys($pdo, $data, ADMIN_SYSTEM_KEYS);
}
