<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
$jsPath = __DIR__ . '/../assets/js/app.js';
$jsVersion = is_file($jsPath) ? (string)filemtime($jsPath) : (string)time();
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="assets/js/app.js?v=<?= e($jsVersion) ?>"></script>
</body>
</html>

