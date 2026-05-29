<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
$jsPath = __DIR__ . '/../assets/js/app.js';
$jsVersion = is_file($jsPath) ? (string)filemtime($jsPath) : (string)time();
$user = current_user();
$footerRole = (string)($user['role'] ?? '');
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<?php
$erpTsJs = __DIR__ . '/../assets/js/erp-searchable-select.js';
if (is_file($erpTsJs)) {
    echo '<script src="assets/js/erp-searchable-select.js?v=' . e((string)filemtime($erpTsJs)) . '"></script>' . "\n";
}
?>
<script src="assets/js/app.js?v=<?= e($jsVersion) ?>"></script>
<?php
if (in_array($footerRole, ['Super Admin', 'HR Manager', 'Admin', 'Employee'], true)) {
    $notifyJs = __DIR__ . '/../assets/js/app-notifications.js';
    $notifyVer = is_file($notifyJs) ? (string)filemtime($notifyJs) : (string)time();
    echo '<script src="assets/js/app-notifications.js?v=' . e($notifyVer) . '"></script>' . "\n";
}
$footerPage = (string)($_GET['page'] ?? '');
if ($footerPage === 'reports/hr') {
    $hrRptJs = __DIR__ . '/../assets/js/hr-reports.js';
    $hrRptVer = is_file($hrRptJs) ? (string)filemtime($hrRptJs) : (string)time();
    echo '<script src="assets/js/hr-reports.js?v=' . e($hrRptVer) . '"></script>' . "\n";
}
if ($footerPage === 'quality/inspect') {
    $qcJs = __DIR__ . '/../assets/js/qc-inspect.js';
    $qcVer = is_file($qcJs) ? (string)filemtime($qcJs) : (string)time();
    echo '<script src="assets/js/qc-inspect.js?v=' . e($qcVer) . '"></script>' . "\n";
}
if ($footerPage === 'employee/attendance') {
    $empCalJs = __DIR__ . '/../assets/js/employee-attendance-calendar.js';
    $empCalVer = is_file($empCalJs) ? (string)filemtime($empCalJs) : (string)time();
    echo '<script src="assets/js/employee-attendance-calendar.js?v=' . e($empCalVer) . '"></script>' . "\n";
}
if (in_array($footerPage, ['production/mixing', 'production/building', 'production/curing'], true)) {
    $machProdJs = __DIR__ . '/../assets/js/machine-production.js';
    $machProdVer = is_file($machProdJs) ? (string)filemtime($machProdJs) : (string)time();
    echo '<script src="assets/js/machine-production.js?v=' . e($machProdVer) . '"></script>' . "\n";
    $prodEntryJs = __DIR__ . '/../assets/js/production-entry-table.js';
    $prodEntryVer = is_file($prodEntryJs) ? (string)filemtime($prodEntryJs) : (string)time();
    echo '<script src="assets/js/production-entry-table.js?v=' . e($prodEntryVer) . '"></script>' . "\n";
}
if ($footerPage === 'reports/production') {
    $prodRptJs = __DIR__ . '/../assets/js/production-entry-table.js';
    $prodRptVer = is_file($prodRptJs) ? (string)filemtime($prodRptJs) : (string)time();
    echo '<script src="assets/js/production-entry-table.js?v=' . e($prodRptVer) . '"></script>' . "\n";
}
if ($footerPage === 'sales/order') {
    $soJs = __DIR__ . '/../assets/js/sales-order-form.js';
    $soVer = is_file($soJs) ? (string)filemtime($soJs) : (string)time();
    echo '<script src="assets/js/sales-order-form.js?v=' . e($soVer) . '"></script>' . "\n";
}
if (str_starts_with($footerPage, 'machines/')) {
    $machModJs = __DIR__ . '/../assets/js/machine-module.js';
    $machModVer = is_file($machModJs) ? (string)filemtime($machModJs) : (string)time();
    echo '<script src="assets/js/machine-module.js?v=' . e($machModVer) . '"></script>' . "\n";
}
if ($footerPage === 'employees/list') {
    $erdJs = __DIR__ . '/../assets/js/employee-record-drawer.js';
    $erdVer = is_file($erdJs) ? (string)filemtime($erdJs) : (string)time();
    echo '<script src="assets/js/employee-record-drawer.js?v=' . e($erdVer) . '"></script>' . "\n";
}
if (in_array($footerPage, ['accounts/cashbook', 'accounts/bankbook'], true)) {
    $treasuryJs = __DIR__ . '/../assets/js/accounts-treasury.js';
    $treasuryVer = is_file($treasuryJs) ? (string)filemtime($treasuryJs) : (string)time();
    echo '<script src="assets/js/accounts-treasury.js?v=' . e($treasuryVer) . '"></script>' . "\n";
}
if ($footerPage === 'accounts/reports') {
    $accRptJs = __DIR__ . '/../assets/js/accounts-reports.js';
    $accRptVer = is_file($accRptJs) ? (string)filemtime($accRptJs) : (string)time();
    echo '<script src="assets/js/accounts-reports.js?v=' . e($accRptVer) . '"></script>' . "\n";
}
if ($footerPage === 'accounts/transactions-history') {
    $accTxJs = __DIR__ . '/../assets/js/accounts-transactions.js';
    $accTxVer = is_file($accTxJs) ? (string)filemtime($accTxJs) : (string)time();
    echo '<script src="assets/js/accounts-transactions.js?v=' . e($accTxVer) . '"></script>' . "\n";
}
if ($footerPage === 'super/dashboard' || str_starts_with($footerPage, 'admin/')) {
    $adminJs = __DIR__ . '/../assets/js/admin-control-center.js';
    $adminVer = is_file($adminJs) ? (string)filemtime($adminJs) : (string)time();
    echo '<script src="assets/js/admin-control-center.js?v=' . e($adminVer) . '"></script>' . "\n";
}
?>
</body>
</html>

