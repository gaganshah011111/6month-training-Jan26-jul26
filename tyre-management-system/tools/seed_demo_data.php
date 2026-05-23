<?php
declare(strict_types=1);

/**
 * Insert realistic tyre-factory demo data for testing.
 *
 * Usage (from project root):
 *   php tools/seed_demo_data.php
 *   php tools/seed_demo_data.php --force
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/demo_seed_service.php';

$force = in_array('--force', $argv ?? [], true);

try {
    $pdo = Database::connection();
    $result = demo_seed_run($pdo, $force);

    if (!empty($result['skipped'])) {
        echo $result['message'] . "\n";
        echo "Run: php tools/seed_demo_data.php --force\n";
        exit(0);
    }

    echo "Demo seed completed successfully.\n";
    echo "  Employees: {$result['employees']}\n";
    echo "  Machines: {$result['machines']}\n";
    echo "  Materials: {$result['materials']}\n";
    echo "  Customers: {$result['customers']}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Demo seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}
