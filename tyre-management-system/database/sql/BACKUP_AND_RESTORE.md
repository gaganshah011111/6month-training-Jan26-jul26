# Full database backup & restore (disaster recovery)

If MySQL fails, XAMPP is reinstalled, or data is lost, use the files in this folder to recover.

## Main backup file (use this first)

| File | What it contains |
|------|------------------|
| **`FULL_DATABASE_BACKUP.sql`** | **Complete database** — all tables + all your live data (employees, dispatch, payroll, etc.) |

A copy is also written to `full_latest_backup.sql` (same content).

## Create / refresh backup (do this regularly)

1. Start **MySQL** in XAMPP.
2. Run **one** of:

   **Double-click (Windows):**
   ```
   tools/backup_database.bat
   ```

   **Command line (project root):**
   ```bash
   c:\xampp\php\php.exe tools\db_sync.php backup
   ```
   or:
   ```bash
   c:\xampp\php\php.exe tools\db_sync.php export
   ```

3. Copy `database/sql/FULL_DATABASE_BACKUP.sql` to USB, cloud, or another PC.

**Tip:** Run backup weekly or after important data entry.

## Restore after failure

1. Install/start XAMPP (Apache + MySQL).
2. Import the backup:

   **Command line:**
   ```bash
   c:\xampp\mysql\bin\mysql.exe -u root < database\sql\FULL_DATABASE_BACKUP.sql
   ```

   **phpMyAdmin:** Import → choose `FULL_DATABASE_BACKUP.sql` (can take a few minutes).

3. Open the ERP in the browser (`tyre_erp` database, user `root`, no password by default).

## If you only need empty ERP (no old data)

Use `restore_fresh.sql` instead — schema + default login users only (password: **password**).

## Files summary

| File | Use when |
|------|----------|
| `FULL_DATABASE_BACKUP.sql` | **Failure recovery — full data restore** |
| `full_latest_backup.sql` | Same as above (auto copy) |
| `restore_fresh.sql` | New empty system, no business data |
| `migrations/*.sql` | Schema history / upgrades only |

See also: `README.md`, `schema_version.txt`
