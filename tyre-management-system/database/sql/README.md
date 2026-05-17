# Tyre ERP — Database SQL (Portable Restore)

This folder is the **source of truth** for database structure. After XAMPP reinstall or corruption, restore the ERP by importing SQL from here—not only from local MySQL.

## Folder layout

```
database/sql/
├── full_latest_backup.sql    ← Full dump (schema + data). Import this for fastest restore.
├── schema_version.txt        ← Latest applied migration name + timestamp
├── migrations/               ← Ordered schema changes (run in filename order)
│   ├── 000_create_database.sql
│   ├── 001_core_erp_tables.sql
│   ├── 002_department_hierarchy.sql
│   ├── 003_attendance_update.sql
│   ├── 004_salary_structure_update.sql
│   ├── 005_create_payroll_tables.sql
│   ├── 006_leave_module_update.sql
│   └── 007_employee_department_links.sql
└── seed/                     ← Optional demo/test data (not required for production)
    ├── demo_users.sql
    ├── demo_employees.sql
    ├── test_attendance.sql
    ├── test_leaves.sql
    └── test_payroll_settings.sql
```

## Quick restore (after XAMPP reinstall)

1. Copy the project folder to `htdocs`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Import the full backup:

   **phpMyAdmin:** Import → choose `database/sql/full_latest_backup.sql`

   **Command line:**

   ```bash
   c:\xampp\mysql\bin\mysql.exe -u root < database\sql\full_latest_backup.sql
   ```

4. Open the ERP in the browser (default DB: `tyre_erp`, user `root`, no password).

Default logins are in `seed/demo_users.sql` (password: **password**).

## Migration order

Migrations run automatically when the app connects (`config/db.php` → `DatabaseMigrationRunner`).

| File | Purpose |
|------|---------|
| 000 | Create `tyre_erp` database |
| 001 | Core tables (users, employees, attendance, leaves, salaries, manufacturing, settings) |
| 002 | Department hierarchy |
| 003 | Attendance status & punch fields |
| 004 | Salary / payslip structure columns |
| 005 | `payroll_settings`, `payroll` |
| 006 | Smart leave module + notifications |
| 007 | Employee `department_id` / `designation_id` |

Applied migrations are recorded in table **`schema_migrations`**.

## CLI tools

From project root:

```bash
php tools/db_sync.php migrate    # Run pending migrations only
php tools/db_sync.php baseline   # Mark all files applied (existing DB, no DDL)
php tools/db_sync.php export     # Regenerate full_latest_backup.sql
php tools/db_sync.php seed       # Load seed/*.sql
php tools/db_sync.php all        # migrate + export + seed
```

## Test / demo data

After a fresh import of `full_latest_backup.sql` (or migrations only):

```bash
php tools/db_sync.php seed
```

Or import individual files from `seed/` in phpMyAdmin.

- **demo_users.sql** — Super Admin, HR, department managers, employee login
- **demo_employees.sql** — 3 sample employees
- **test_attendance.sql** — Today’s present marks for demo staff
- **test_leaves.sql** — One pending leave request
- **test_payroll_settings.sql** — Indian payroll defaults

## Latest schema version

See **`schema_version.txt`** (updated when migrations run or backup is exported).

## Developer workflow (required)

Whenever you change the database in code (`config/db.php`, new modules, etc.):

1. Add a new numbered file under `migrations/` (e.g. `008_my_change.sql`).
2. Use idempotent SQL where possible (`CREATE TABLE IF NOT EXISTS`, etc.).
3. Run: `php tools/db_sync.php migrate`
4. Run: `php tools/db_sync.php export` to refresh **`full_latest_backup.sql`**
5. Update seed SQL if demo data must reflect the change.

Auto-export on migrate: set environment variable `ERP_AUTO_DB_BACKUP=1` or use `APP_ENV=local` in `config/app.php` (exports when new migrations apply).

## Notes

- PHP runtime migrations in `config/db.php` still run for backward compatibility; file migrations are the portable record.
- Duplicate column/index errors during migrate on an already-upgraded DB are ignored safely.
- Commit `database/sql/` to version control so the team always has the latest structure.
