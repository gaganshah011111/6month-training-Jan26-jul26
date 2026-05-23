-- Tyre ERP — realistic factory demo data
-- Prefer the PHP seeder (resolves department IDs, FKs, and idempotent upserts):
--
--   php tools/seed_demo_data.php
--   php tools/seed_demo_data.php --force    (clear and re-insert demo rows)
--   php tools/db_sync.php demo [--force]
--
-- Inserts: EMP001–EMP015, 10 machines, assignments, RM-001–RM015, suppliers,
-- stock inward/usage, production entries, customers, logistics, dispatches,
-- attendance, sample payroll — without changing any UI.

SELECT 'Use: php tools/seed_demo_data.php' AS instruction;
