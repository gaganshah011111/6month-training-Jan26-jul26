-- =============================================================================
-- Tyre ERP — Database restore (portable SQL)
-- Updated: 2026-05-21 | Schema version: 014_production_entry_tables
-- =============================================================================
--
-- DISASTER RECOVERY — restore whole database if MySQL fails:
--
--   File:  database/sql/FULL_DATABASE_BACKUP.sql
--   Guide: database/sql/BACKUP_AND_RESTORE.md
--
--   Create backup (XAMPP MySQL running):
--     Double-click:  tools/backup_database.bat
--     Or:          php tools/db_sync.php backup
--
--   Restore:
--     c:\xampp\mysql\bin\mysql.exe -u root < database\sql\FULL_DATABASE_BACKUP.sql
--
-- CHOOSE ONE:
--
-- 1) FULL RESTORE (schema + all your data — use after failure)
--    File: database/sql/FULL_DATABASE_BACKUP.sql
--
-- 2) FRESH INSTALL (empty ERP + default logins, no business data)
--    File: database/sql/restore_fresh.sql
--
-- 3) MIGRATIONS ONLY (if database exists but schema is old)
--    php tools/db_sync.php migrate
--    php tools/db_sync.php backup
--
-- Default logins (password: password): database/sql/seed/demo_users.sql
-- Documentation: database/sql/README.md
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `tyre_erp` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `tyre_erp`;

SELECT 'Import database/sql/FULL_DATABASE_BACKUP.sql for full restore' AS restore_instruction;
