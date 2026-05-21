<?php
declare(strict_types=1);
/** Legacy route — production quantities belong on Production Entry. */
header('Location: index.php?page=' . rawurlencode('production/orders'));
exit;
