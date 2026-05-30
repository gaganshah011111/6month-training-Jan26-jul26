<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_control_center.php';
require_once __DIR__ . '/admin_audit_service.php';

/** @return list<array<string, mixed>> */
function admin_erp_module_monitor(PDO $pdo): array
{
    $defs = [
        'sales' => ['label' => 'CRM / Sales', 'table' => 'sales_customers', 'activity_table' => 'sales_invoices', 'activity_label' => 'Last invoice'],
        'accounts' => ['label' => 'Accounts', 'table' => 'accounts_treasury_ledger', 'activity_table' => 'accounts_treasury_ledger', 'activity_label' => 'Last transaction'],
        'hr' => ['label' => 'HR', 'table' => 'employees', 'activity_table' => 'employees', 'activity_label' => 'Last employee update'],
        'inventory' => ['label' => 'Inventory', 'table' => 'raw_materials', 'activity_table' => 'stock_inward', 'activity_label' => 'Last purchase inward'],
        'production' => ['label' => 'Production', 'table' => 'production', 'activity_table' => 'production', 'activity_label' => 'Last production entry'],
        'dispatch' => ['label' => 'Dispatch', 'table' => 'dispatch', 'activity_table' => 'dispatch', 'activity_label' => 'Last dispatch'],
    ];

    $modules = [];
    foreach ($defs as $key => $cfg) {
        $exists = dh_table_exists($pdo, $cfg['table']);
        $count = $exists ? admin_table_count($pdo, $cfg['table']) : 0;
        $lastActivity = '—';
        $issues = [];

        if ($exists && dh_table_exists($pdo, $cfg['activity_table'])) {
            try {
                $col = $cfg['activity_table'] === 'employees' ? 'created_at' : 'created_at';
                $lastActivity = (string)($pdo->query('SELECT MAX(' . $col . ') FROM `' . str_replace('`', '', $cfg['activity_table']) . '`')->fetchColumn() ?: '—');
                if ($lastActivity !== '—') {
                    $lastActivity = substr($lastActivity, 0, 16);
                }
            } catch (Throwable) {
            }
        }

        if (!$exists) {
            $state = 'Error';
            $level = 'error';
            $issues[] = 'Module tables missing';
        } elseif ($count === 0) {
            $state = 'Warning';
            $level = 'warning';
            $issues[] = 'No records yet';
        } else {
            $state = 'Healthy';
            $level = 'healthy';
        }

        if ($key === 'inventory' && $exists) {
            $low = admin_count($pdo, 'SELECT COUNT(*) FROM raw_materials WHERE stock_qty <= reorder_level');
            if ($low > 0) {
                $state = 'Warning';
                $level = 'warning';
                $issues[] = $low . ' material(s) low stock';
            }
        }
        if ($key === 'dispatch' && $exists) {
            $pending = admin_count($pdo, "SELECT COUNT(*) FROM dispatch WHERE dispatch_status IN ('Created','In Transit')");
            if ($pending > 0) {
                $issues[] = $pending . ' dispatch(es) pending';
                if ($level === 'healthy') {
                    $state = 'Warning';
                    $level = 'warning';
                }
            }
        }
        if ($key === 'accounts' && $exists && function_exists('admin_financial_snapshot')) {
            $fin = admin_financial_snapshot($pdo);
            if ((float)$fin['cash'] < 0) {
                $issues[] = 'Negative cash balance';
                $state = 'Error';
                $level = 'error';
            }
        }

        $modules[] = [
            'key' => $key,
            'label' => $cfg['label'],
            'state' => $state,
            'level' => $level,
            'records' => $count,
            'last_activity' => $lastActivity,
            'issues' => $issues,
        ];
    }

    return $modules;
}

/** @return array{healthy: int, warning: int, error: int} */
function admin_monitoring_summary(PDO $pdo): array
{
    $mods = admin_erp_module_monitor($pdo);
    $summary = ['healthy' => 0, 'warning' => 0, 'error' => 0];
    foreach ($mods as $m) {
        ++$summary[$m['level'] === 'healthy' ? 'healthy' : ($m['level'] === 'warning' ? 'warning' : 'error')];
    }

    return $summary;
}
