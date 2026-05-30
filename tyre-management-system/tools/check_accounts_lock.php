<?php
require __DIR__ . '/../config/db.php';
$p = Database::connection();
echo "=== Accounts Manager ===\n";
foreach ($p->query("SELECT id, full_name, email, username, status, force_logout_at FROM users WHERE email='accounts@ralson.local' OR username='accounts_manager'") as $r) {
    print_r($r);
}
echo "\n=== Recent lock audit ===\n";
foreach ($p->query("SELECT action_text, detail, old_value, new_value, created_at FROM erp_activity_log WHERE action_text LIKE '%Lock%' ORDER BY id DESC LIMIT 5") as $r) {
    print_r($r);
}
echo "\n=== Recent auth status logs ===\n";
foreach ($p->query("SELECT login_name, success, failure_reason, created_at FROM erp_login_history WHERE login_name LIKE 'status_check%' OR failure_reason LIKE '%status%' ORDER BY id DESC LIMIT 10") as $r) {
    print_r($r);
}
