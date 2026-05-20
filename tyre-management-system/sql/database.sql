-- =============================================================================
-- Tyre ERP — Database restore (portable SQL)
-- Updated: 2026-05-20 | Schema version: 008_hr_notifications_and_leave_audit
-- =============================================================================
--
-- This project keeps all restore SQL under database/sql/
--
-- CHOOSE ONE:
--
-- 1) FULL RESTORE (schema + your data — recommended after XAMPP failure)
--    File: database/sql/full_latest_backup.sql
--    Command:
--      c:\xampp\mysql\bin\mysql.exe -u root < database\sql\full_latest_backup.sql
--
-- 2) FRESH INSTALL (empty ERP + default logins, no employees/attendance data)
--    File: database/sql/restore_fresh.sql
--    Command:
--      c:\xampp\mysql\bin\mysql.exe -u root < database\sql\restore_fresh.sql
--    Then open the app once (loads department master data automatically).
--
-- 3) MIGRATIONS ONLY (if database exists but schema is old)
--    From project root:
--      c:\xampp\php\php.exe tools\db_sync.php migrate
--      c:\xampp\php\php.exe tools\db_sync.php export
--
-- Default logins (password: password): see database/sql/seed/demo_users.sql
-- Documentation: database/sql/README.md
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `tyre_erp` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `tyre_erp`;

-- Minimal marker so phpMyAdmin import does not fail on an empty file.
SELECT 'Import database/sql/full_latest_backup.sql or database/sql/restore_fresh.sql' AS restore_instruction;
