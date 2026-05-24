<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $crmPendingLines */
$crmQueueRows = $crmPendingLines ?? [];
$showActions = false;
require __DIR__ . '/_crm_queue_table.php';
