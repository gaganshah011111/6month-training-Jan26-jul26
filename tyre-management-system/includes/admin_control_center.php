<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/accounts_finance.php';
require_once __DIR__ . '/department_hierarchy.php';

const ACC_ADMIN_MODULES = [
    'sales' => ['label' => 'CRM / Sales', 'route' => 'sales/dashboard', 'icon' => 'bi-people'],
    'inventory' => ['label' => 'Inventory', 'route' => 'inventory/dashboard', 'icon' => 'bi-boxes'],
    'production' => ['label' => 'Production', 'route' => 'production/dashboard', 'icon' => 'bi-building-gear'],
    'dispatch' => ['label' => 'Dispatch', 'route' => 'dispatch/dashboard', 'icon' => 'bi-truck'],
    'hr' => ['label' => 'HR', 'route' => 'hr/dashboard', 'icon' => 'bi-person-badge'],
    'accounts' => ['label' => 'Accounts', 'route' => 'accounts/dashboard', 'icon' => 'bi-bank2'],
];

const ACC_ADMIN_DEPT_CARDS = [
    'HR' => ['icon' => 'bi-people', 'match' => ['HR', 'Human Resources'], 'code' => 'DEPT_HR'],
    'Accounts' => ['icon' => 'bi-bank2', 'match' => ['Accounts', 'Finance'], 'code' => 'DEPT_ACC'],
    'Sales' => ['icon' => 'bi-graph-up', 'match' => ['Sales', 'CRM'], 'code' => 'DEPT_SALES'],
    'Inventory' => ['icon' => 'bi-boxes', 'match' => ['Inventory', 'Store', 'Warehouse'], 'code' => 'DEPT_RAW_MAT'],
    'Production' => ['icon' => 'bi-building-gear', 'match' => ['Production', 'Manufacturing'], 'code' => 'DEPT_PPC'],
    'Dispatch' => ['icon' => 'bi-truck', 'match' => ['Dispatch', 'Logistics'], 'code' => 'DEPT_LOG_DISP'],
    'Quality' => ['icon' => 'bi-shield-check', 'match' => ['Quality', 'QA'], 'code' => 'DEPT_QA_QC'],
    'Administration' => ['icon' => 'bi-gear', 'match' => ['Administration', 'Admin'], 'code' => 'DEPT_ADMIN'],
];

const ACC_ADMIN_ROLES = [
    'Admin',
    'HR Manager',
    'Accounts Manager',
    'Sales Manager',
    'Inventory Manager',
    'Production Manager',
    'Dispatch Manager',
    'Quality Manager',
    'Employee',
];

const ACC_ADMIN_PERMISSIONS = ['view', 'create', 'edit', 'delete', 'export'];

function admin_can_access(): bool
{
    return has_role(['Super Admin', 'Admin']);
}

function admin_count(PDO $pdo, string $sql): int
{
    try {
        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function admin_table_count(PDO $pdo, string $table): int
{
    if (!dh_table_exists($pdo, $table)) {
        return 0;
    }

    return admin_count($pdo, 'SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`');
}

function admin_health_level(string $status, string $health): string
{
    if ($status === 'Offline') {
        return 'red';
    }
    if ($health === 'Healthy') {
        return 'green';
    }
    if ($health === 'Idle') {
        return 'yellow';
    }

    return 'red';
}

/** @return array<string, mixed> */
function admin_dashboard_kpis(PDO $pdo): array
{
    $fin = admin_financial_snapshot($pdo);
    $activeEmployees = admin_count($pdo, "SELECT COUNT(*) FROM employees WHERE status IN ('Active','active')");
    $departments = admin_table_count($pdo, 'departments');
    if ($departments === 0) {
        $departments = (int)$pdo->query("SELECT COUNT(DISTINCT department) FROM employees WHERE department <> ''")->fetchColumn();
    }

    return [
        'users' => admin_table_count($pdo, 'users'),
        'employees' => $activeEmployees,
        'departments' => $departments,
        'revenue' => $fin['revenue'],
        'expenses' => $fin['expenses'],
        'profit' => $fin['profit'],
        'receivables' => $fin['receivables'],
        'payables' => $fin['payables'],
        'cash' => $fin['cash'],
        'today_logins' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE last_login >= CURDATE()"),
        'low_stock' => admin_count($pdo, 'SELECT COUNT(*) FROM raw_materials WHERE stock_qty <= reorder_level'),
        'pending_dispatch' => admin_count($pdo, "SELECT COUNT(*) FROM dispatch WHERE dispatch_status IN ('Created','In Transit')"),
        'customer_dues' => $fin['receivables'],
        'supplier_dues' => $fin['payables'],
    ];
}

/** @return array<string, mixed> */
function admin_financial_snapshot(PDO $pdo): array
{
    $overview = admin_company_overview($pdo);
    $receivables = 0.0;
    $payables = 0.0;
    $cash = 0.0;

    if (function_exists('sales_payment_dashboard')) {
        require_once __DIR__ . '/sales_service.php';
        $ar = sales_payment_dashboard($pdo);
        $receivables = (float)($ar['pending'] ?? 0);
    }
    if (function_exists('acc_supplier_ledger_list')) {
        require_once __DIR__ . '/accounts_ledger.php';
        foreach (acc_supplier_ledger_list($pdo, []) as $s) {
            $payables += (float)($s['pending_balance'] ?? 0);
        }
    }
    if (function_exists('acc_treasury_kpis')) {
        acc_ensure_schema($pdo);
        $tk = acc_treasury_kpis($pdo, false);
        $cash = (float)($tk['available_funds'] ?? 0);
    }

    return [
        'revenue' => $overview['revenue'],
        'expenses' => $overview['expenses'],
        'profit' => $overview['profit'],
        'receivables' => $receivables,
        'payables' => $payables,
        'cash' => $cash,
    ];
}

/** @return array<string, mixed> */
function admin_module_health(PDO $pdo): array
{
    $checks = [
        'sales' => ['table' => 'sales_customers', 'label' => 'CRM Status'],
        'accounts' => ['table' => 'accounts_treasury_ledger', 'label' => 'Accounts Status'],
        'hr' => ['table' => 'employees', 'label' => 'HR Status'],
        'production' => ['table' => 'production', 'label' => 'Production Status'],
        'inventory' => ['table' => 'raw_materials', 'label' => 'Inventory Status'],
        'dispatch' => ['table' => 'dispatch', 'label' => 'Dispatch Status'],
    ];

    $modules = [];
    foreach ($checks as $key => $cfg) {
        $exists = dh_table_exists($pdo, $cfg['table']);
        $count = $exists ? admin_table_count($pdo, $cfg['table']) : 0;
        $status = $exists ? 'Online' : 'Offline';
        $health = $exists && $count > 0 ? 'Healthy' : ($exists ? 'Idle' : 'Unavailable');
        $modules[$key] = [
            'key' => $key,
            'label' => $cfg['label'],
            'status' => $status,
            'health' => $health,
            'level' => admin_health_level($status, $health),
            'records' => $count,
            'route' => ACC_ADMIN_MODULES[$key]['route'] ?? '#',
        ];
    }

    return $modules;
}

/** @return array<string, mixed> */
function admin_system_health_full(PDO $pdo): array
{
    $modules = admin_module_health($pdo);
    $dbOk = true;
    try {
        $pdo->query('SELECT 1');
    } catch (Throwable) {
        $dbOk = false;
    }

    require_once __DIR__ . '/admin_backup_service.php';
    $backups = admin_backup_list();
    $latestBackup = $backups[0]['modified'] ?? 'Never';
    $backupAge = $backups[0]['name'] ?? null;
    $backupLevel = $backupAge ? 'green' : 'yellow';

    $modules['database'] = [
        'key' => 'database',
        'label' => 'Database Status',
        'status' => $dbOk ? 'Connected' : 'Error',
        'health' => $dbOk ? 'Healthy' : 'Critical',
        'level' => $dbOk ? 'green' : 'red',
        'records' => admin_table_count($pdo, 'users'),
        'route' => 'admin/monitoring',
    ];
    $modules['backup'] = [
        'key' => 'backup',
        'label' => 'Backup Status',
        'status' => $backupAge ? 'Available' : 'Missing',
        'health' => $backupAge ? 'Last: ' . $latestBackup : 'No backup file',
        'level' => $backupLevel,
        'records' => count($backups),
        'route' => 'admin/backup',
    ];

    return $modules;
}

/** @return array<string, mixed> */
function admin_company_overview(PDO $pdo): array
{
    $from = date('Y-m-01');
    $to = date('Y-m-d');
    $revenue = 0.0;
    $expenses = 0.0;

    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM sales_invoices WHERE invoice_date >= :f AND invoice_date <= :t');
        $st->execute(['f' => $from, 't' => $to]);
        $revenue = (float)$st->fetchColumn();
    }
    if (function_exists('acc_expense_ensure_schema')) {
        acc_expense_ensure_schema($pdo);
        $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM accounts_expenses WHERE expense_date >= :f AND expense_date <= :t');
        $st->execute(['f' => $from, 't' => $to]);
        $expenses = (float)$st->fetchColumn();
    }

    return [
        'employees' => admin_table_count($pdo, 'employees'),
        'customers' => admin_table_count($pdo, 'sales_customers'),
        'suppliers' => admin_table_count($pdo, 'suppliers'),
        'materials' => admin_table_count($pdo, 'raw_materials'),
        'sales_orders' => admin_table_count($pdo, 'sales_orders'),
        'revenue' => $revenue,
        'expenses' => $expenses,
        'profit' => round($revenue - $expenses, 2),
    ];
}

/** @return list<array<string, string>> */
function admin_operational_alerts(PDO $pdo): array
{
    $fin = admin_financial_snapshot($pdo);
    $alerts = [];

    $lockedUsers = admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'locked'");
    if ($lockedUsers > 0) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Locked Users',
            'message' => $lockedUsers . ' user account(s) locked',
            'url' => route_url('admin/users', ['status' => 'locked']),
        ];
    }

    $inactiveUsers = admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'inactive'");
    if ($inactiveUsers > 0) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Inactive Users',
            'message' => $inactiveUsers . ' deactivated account(s)',
            'url' => route_url('admin/users', ['status' => 'inactive']),
        ];
    }

    $lowStock = admin_count($pdo, 'SELECT COUNT(*) FROM raw_materials WHERE stock_qty <= reorder_level');
    if ($lowStock > 0) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Low Stock Alerts',
            'message' => $lowStock . ' material(s) at or below reorder level',
            'url' => route_url('inventory/materials'),
        ];
    }

    $pendingDispatch = admin_count($pdo, "SELECT COUNT(*) FROM dispatch WHERE dispatch_status IN ('Created','In Transit')");
    if ($pendingDispatch > 0) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Pending Dispatch',
            'message' => $pendingDispatch . ' dispatch order(s) in progress',
            'url' => route_url('dispatch/history'),
        ];
    }

    if ((float)$fin['receivables'] > 0) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Customer Overdue / Dues',
            'message' => sales_format_money((float)$fin['receivables']) . ' outstanding receivables',
            'url' => route_url('accounts/receivables'),
        ];
    }

    if ((float)$fin['payables'] > 0) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Supplier Overdue / Dues',
            'message' => sales_format_money((float)$fin['payables']) . ' outstanding payables',
            'url' => route_url('accounts/payables'),
        ];
    }

    if ((float)$fin['cash'] < 0) {
        $alerts[] = [
            'type' => 'danger',
            'title' => 'Negative Cash Balance',
            'message' => sales_format_money((float)$fin['cash']) . ' — review treasury',
            'url' => route_url('accounts/cashbook'),
        ];
    }

    $largeExpenseThreshold = 50000.0;
    if (dh_table_exists($pdo, 'accounts_expenses')) {
        $largeExp = (float)admin_count($pdo, 'SELECT COALESCE(MAX(amount),0) FROM accounts_expenses WHERE expense_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01")');
        if ($largeExp >= $largeExpenseThreshold) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Large Expenses (MTD)',
                'message' => 'Largest single expense: ' . sales_format_money($largeExp),
                'url' => route_url('accounts/expenses'),
            ];
        }
    }

    if (dh_table_exists($pdo, 'salaries') && dh_column_exists($pdo, 'salaries', 'payment_status')) {
        $payrollPending = admin_count($pdo, "SELECT COUNT(*) FROM salaries WHERE payment_status IN ('pending','Pending','Generated') AND month_year = DATE_FORMAT(CURDATE(), '%Y-%m')");
        if ($payrollPending > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Payroll Pending',
                'message' => $payrollPending . ' salary record(s) awaiting payment',
                'url' => route_url('accounts/salary-payments'),
            ];
        }
    }

    foreach (admin_system_health_full($pdo) as $h) {
        if (($h['level'] ?? '') === 'red') {
            $alerts[] = [
                'type' => 'danger',
                'title' => $h['label'],
                'message' => (string)$h['health'],
                'url' => route_url('admin/monitoring'),
            ];
        }
    }

    if ($alerts === []) {
        $alerts[] = [
            'type' => 'success',
            'title' => 'System stable',
            'message' => 'No critical alerts. Modules run independently.',
            'url' => route_url('admin/monitoring'),
        ];
    }

    return array_slice($alerts, 0, 10);
}

/** @return list<array<string, string>> */
function admin_quick_actions(): array
{
    return [
        ['label' => 'Create User', 'icon' => 'bi-person-plus', 'url' => route_url('admin/users'), 'tone' => 'primary'],
        ['label' => 'Lock User', 'icon' => 'bi-lock', 'url' => route_url('admin/users', ['status' => 'locked']), 'tone' => 'warn'],
        ['label' => 'Reset Password', 'icon' => 'bi-key', 'url' => route_url('admin/users'), 'tone' => 'neutral'],
        ['label' => 'Department Management', 'icon' => 'bi-diagram-3', 'url' => route_url('admin/departments'), 'tone' => 'neutral'],
        ['label' => 'System Backup', 'icon' => 'bi-cloud-arrow-down', 'url' => route_url('admin/backup'), 'tone' => 'neutral'],
        ['label' => 'Financial Reports', 'icon' => 'bi-graph-up', 'url' => route_url('admin/finance-oversight'), 'tone' => 'green'],
        ['label' => 'Audit Logs', 'icon' => 'bi-journal-text', 'url' => route_url('admin/activity-logs'), 'tone' => 'neutral'],
        ['label' => 'Role Management', 'icon' => 'bi-shield-lock', 'url' => route_url('admin/roles'), 'tone' => 'primary'],
    ];
}

/** @return list<array<string, string>> */
function admin_notifications(PDO $pdo): array
{
    return admin_operational_alerts($pdo);
}

/** @return list<array<string, string>> */
function admin_recent_activity(PDO $pdo, int $limit = 15): array
{
    $events = [];

    if (dh_table_exists($pdo, 'erp_activity_log')) {
        require_once __DIR__ . '/admin_audit_service.php';
        admin_audit_ensure_schema($pdo);
        foreach (admin_audit_list($pdo, [], $limit) as $r) {
            $events[] = [
                'user' => $r['user'],
                'action' => $r['action'],
                'module' => $r['module'],
                'date' => $r['date'],
                'time' => $r['time'],
                'status' => $r['status'],
                'level' => admin_timeline_level((string)$r['status']),
                'sort' => $r['date'] . ' ' . $r['time'],
            ];
        }
    }

    if (dh_table_exists($pdo, 'sales_invoices')) {
        foreach ($pdo->query("SELECT 'Sales Manager' AS actor, CONCAT('Generated invoice ', invoice_no) AS action, 'Sales' AS module, created_at AS ts FROM sales_invoices ORDER BY id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $events[] = admin_activity_row($r, 'success');
        }
    }
    if (dh_table_exists($pdo, 'accounts_treasury_ledger')) {
        foreach ($pdo->query("SELECT COALESCE(created_by,'Accounts Manager') AS actor, CONCAT('Recorded ', tx_type, ' — ', COALESCE(party,'')) AS action, 'Accounts' AS module, created_at AS ts FROM accounts_treasury_ledger ORDER BY id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $events[] = admin_activity_row($r, 'success');
        }
    }
    if (dh_table_exists($pdo, 'production')) {
        foreach ($pdo->query("SELECT 'Production Manager' AS actor, CONCAT('Recorded production batch #', id) AS action, 'Production' AS module, created_at AS ts FROM production ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $events[] = admin_activity_row($r, 'info');
        }
    }
    if (dh_table_exists($pdo, 'dispatch')) {
        foreach ($pdo->query("SELECT 'Dispatch Manager' AS actor, CONCAT('Completed dispatch ', order_no) AS action, 'Dispatch' AS module, created_at AS ts FROM dispatch WHERE dispatch_status = 'Delivered' ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $events[] = admin_activity_row($r, 'success');
        }
    }
    if (dh_table_exists($pdo, 'stock_inward')) {
        foreach ($pdo->query("SELECT 'Inventory Manager' AS actor, CONCAT('Created purchase inward PINV-', id) AS action, 'Inventory' AS module, created_at AS ts FROM stock_inward ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $events[] = admin_activity_row($r, 'info');
        }
    }

    usort($events, static fn($a, $b) => strcmp($b['sort'], $a['sort']));

    return array_slice($events, 0, $limit);
}

function admin_timeline_level(string $status): string
{
    return match ($status) {
        'success' => 'green',
        'warning' => 'yellow',
        'danger', 'failed' => 'red',
        default => 'blue',
    };
}

/** @param array<string, mixed> $r */
function admin_activity_row(array $r, string $status = 'success'): array
{
    $ts = (string)($r['ts'] ?? '');
    $sort = $ts !== '' ? $ts : date('Y-m-d H:i:s');

    return [
        'user' => (string)($r['actor'] ?? 'System'),
        'action' => (string)($r['action'] ?? 'performed an action'),
        'module' => (string)($r['module'] ?? 'ERP'),
        'date' => $ts !== '' ? substr($ts, 0, 10) : date('Y-m-d'),
        'time' => $ts !== '' ? substr($ts, 11, 5) : date('H:i'),
        'status' => $status,
        'level' => admin_timeline_level($status),
        'sort' => $sort,
    ];
}

/** @return array<string, mixed> */
function admin_executive_reports(PDO $pdo): array
{
    $fin = admin_financial_snapshot($pdo);
    $overview = admin_company_overview($pdo);
    $inventoryValue = 0.0;
    if (dh_table_exists($pdo, 'raw_materials')) {
        $inventoryValue = (float)$pdo->query('SELECT COALESCE(SUM(stock_qty * unit_price),0) FROM raw_materials')->fetchColumn();
    }

    return [
        'revenue' => $fin['revenue'],
        'expenses' => $fin['expenses'],
        'profit' => $fin['profit'],
        'customers' => $overview['customers'],
        'suppliers' => $overview['suppliers'],
        'inventory_value' => $inventoryValue,
        'payroll_cost' => admin_count($pdo, 'SELECT COALESCE(SUM(net_salary),0) FROM salaries WHERE month_year = DATE_FORMAT(CURDATE(), "%Y-%m")'),
        'receivables' => $fin['receivables'],
        'payables' => $fin['payables'],
    ];
}

/** @return list<array<string, mixed>> */
function admin_department_cards(PDO $pdo): array
{
    install_department_hierarchy($pdo);
    require_once __DIR__ . '/admin_departments_service.php';
    $loginCodes = array_flip(admin_departments_login_codes($pdo));
    $cards = [];
    foreach (ACC_ADMIN_DEPT_CARDS as $name => $cfg) {
        $deptCode = (string)($cfg['code'] ?? '');
        if ($deptCode === '' || !isset($loginCodes[$deptCode])) {
            continue;
        }
        $like = [];
        foreach ($cfg['match'] as $m) {
            $like[] = "department LIKE " . $pdo->quote('%' . $m . '%');
        }
        $where = implode(' OR ', $like);
        $empCount = admin_count($pdo, "SELECT COUNT(*) FROM employees WHERE ($where)");
        $deptName = '';
        if (dh_table_exists($pdo, 'departments')) {
            $stName = $pdo->prepare('SELECT department_name FROM departments WHERE department_code = :c LIMIT 1');
            $stName->execute(['c' => $deptCode]);
            $deptName = (string)($stName->fetchColumn() ?: '');
        }
        $userCount = admin_department_login_user_count($pdo, $deptCode, $deptName);
        $head = '—';
        if (dh_table_exists($pdo, 'departments')) {
            foreach ($cfg['match'] as $m) {
                $st = $pdo->prepare('SELECT eh.full_name FROM departments d LEFT JOIN employees eh ON eh.id = d.head_employee_id WHERE d.department_name LIKE :n LIMIT 1');
                $st->execute(['n' => '%' . $m . '%']);
                $h = $st->fetchColumn();
                if ($h) {
                    $head = (string)$h;
                    break;
                }
            }
        }
        $cards[] = [
            'name' => $name,
            'icon' => $cfg['icon'],
            'head' => $head,
            'employees' => $empCount,
            'active_users' => $userCount,
            'url' => route_url('admin/departments'),
        ];
    }

    return $cards;
}

/** @return array<string, mixed> */
function admin_dashboard_bundle(PDO $pdo): array
{
    install_department_hierarchy($pdo);
    acc_ensure_schema($pdo);

    return [
        'kpis' => admin_dashboard_kpis($pdo),
        'health' => admin_module_health($pdo),
        'financial' => admin_financial_snapshot($pdo),
        'activity' => admin_recent_activity($pdo),
        'alerts' => admin_operational_alerts($pdo),
    ];
}

function admin_page_head(string $title, string $subtitle = '', ?string $actionHtml = null): void
{
    echo '<header class="sa-head">';
    echo '<div class="sa-head__text"><h1 class="sa-head__title">' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p class="sa-head__sub">' . e($subtitle) . '</p>';
    }
    echo '</div>';
    if ($actionHtml) {
        echo '<div class="sa-head__actions">' . $actionHtml . '</div>';
    }
    echo '</header>';
}

function admin_render_timeline(array $events, bool $detailed = false): void
{
    echo '<ul class="sa-timeline">';
    if ($events === []) {
        echo '<li class="sa-timeline__empty">No activity recorded yet.</li>';
        return;
    }
    foreach ($events as $ev) {
        $level = e((string)($ev['level'] ?? 'blue'));
        echo '<li class="sa-timeline__item sa-timeline__item--' . $level . '">';
        echo '<div class="sa-timeline__dot"></div>';
        echo '<div class="sa-timeline__body">';
        echo '<div class="sa-timeline__top"><strong>' . e((string)$ev['user']) . '</strong>';
        echo '<span class="sa-chip sa-chip--' . $level . '">' . e((string)($ev['status'] ?? 'info')) . '</span></div>';
        echo '<p class="sa-timeline__action">' . e((string)$ev['action']) . '</p>';
        if ($detailed && (!empty($ev['old_value']) || !empty($ev['new_value']))) {
            echo '<p class="sa-timeline__change"><span>' . e((string)($ev['old_value'] ?? '—')) . '</span> → <span>' . e((string)($ev['new_value'] ?? '—')) . '</span></p>';
        }
        echo '<div class="sa-timeline__meta">';
        echo '<span class="sa-badge sa-badge--muted">' . e((string)$ev['module']) . '</span>';
        echo '<time>' . e((string)$ev['date']) . ' · ' . e((string)$ev['time']) . '</time>';
        if ($detailed && !empty($ev['ip'])) {
            echo '<span class="sa-timeline__ip">IP ' . e((string)$ev['ip']) . '</span>';
        }
        echo '</div></div></li>';
    }
    echo '</ul>';
}

function admin_labeled_actions(array $actions): string
{
    $html = '<div class="admin-labeled-actions">';
    foreach ($actions as $a) {
        if (!empty($a['form'])) {
            $html .= '<form method="post" class="d-inline">' . ($a['csrf'] ?? '') . $a['form'] . '<button type="submit" class="admin-action-btn admin-action-btn--' . e($a['tone'] ?? 'neutral') . '">' . e($a['label']) . '</button></form>';
        } else {
            $html .= '<a href="' . e($a['url']) . '" class="admin-action-btn admin-action-btn--' . e($a['tone'] ?? 'neutral') . '">' . e($a['label']) . '</a>';
        }
    }
    $html .= '</div>';

    return $html;
}
