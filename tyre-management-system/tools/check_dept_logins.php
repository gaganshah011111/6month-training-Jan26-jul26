<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/admin_departments_service.php';

$pdo = Database::connection();
$codes = admin_departments_login_codes($pdo);
echo "Login department codes: " . implode(', ', $codes) . "\n\n";
echo "Filtered departments:\n";
foreach (admin_departments_list($pdo, true) as $r) {
    $n = admin_department_login_user_count($pdo, (string)$r['department_code'], (string)$r['department_name']);
    echo sprintf("  %-45s logins=%d\n", $r['department_name'], $n);
}
