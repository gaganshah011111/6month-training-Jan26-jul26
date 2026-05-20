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
<script src="assets/js/app.js?v=<?= e($jsVersion) ?>"></script>
<?php
if (in_array($footerRole, ['Super Admin', 'HR Manager', 'Admin'], true)) {
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
?>
</body>
</html>

