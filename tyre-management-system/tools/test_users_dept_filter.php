<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/admin_users_service.php';

$pdo = Database::connection();

echo "=== All users (no filter) ===\n";
echo count(admin_users_list($pdo, admin_users_parse_filters([]))) . " users\n";

echo "\n=== HR department filter ===\n";
$f = admin_users_parse_filters(['department' => 'Human Resources (HR)']);
foreach (admin_users_list($pdo, $f) as $u) {
    echo sprintf("  %s | %s | dept=%s\n", $u['full_name'], $u['role'], $u['department']);
}

echo "\n=== Accounts filter ===\n";
$f = admin_users_parse_filters(['department' => 'Accounts & Finance']);
foreach (admin_users_list($pdo, $f) as $u) {
    echo sprintf("  %s | %s | dept=%s\n", $u['full_name'], $u['role'], $u['department']);
}

echo "\nDepartment dropdown options:\n";
foreach (admin_user_departments($pdo) as $d) {
    echo "  - $d\n";
}
