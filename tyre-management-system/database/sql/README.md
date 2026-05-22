# Tyre ERP — Database SQL (Portable Restore)

This folder is the **source of truth** for database structure. After XAMPP reinstall or corruption, restore the ERP by importing SQL from here—not only from local MySQL.

## Disaster recovery (failure / data loss)

**Use this file to restore the whole database:**

| File | Purpose |
|------|---------|
| **`FULL_DATABASE_BACKUP.sql`** | **Primary backup** — import to recover all tables and data |

**Create backup (MySQL must be running):**

```bash
# Windows: double-click
tools\backup_database.bat

# Or command line
php tools/db_sync.php backup
```

A copy is also saved as `full_latest_backup.sql`.

See **`BACKUP_AND_RESTORE.md`** for step-by-step restore instructions.

## Folder layout

```
database/sql/
├── FULL_DATABASE_BACKUP.sql  ← PRIMARY: full DB backup (schema + data after export)
├── BACKUP_AND_RESTORE.md     ← How to backup & restore if MySQL fails
├── full_latest_backup.sql    ← Copy of FULL_DATABASE_BACKUP.sql (same content)
├── restore_fresh.sql         ← Schema + default users only (clean XAMPP install)
├── schema_version.txt        ← Latest applied migration name + timestamp
├── migrations/               ← Ordered schema changes (run in filename order)
│   ├── 000_create_database.sql
│   ├── 001_core_erp_tables.sql
│   ├── 002_department_hierarchy.sql
│   ├── 003_attendance_update.sql
│   ├── 004_salary_structure_update.sql
│   ├── 005_create_payroll_tables.sql
│   ├── 006_leave_module_update.sql
│   ├── 007_employee_department_links.sql
│   ├── 008_hr_notifications_and_leave_audit.sql
│   ├── 009_production_workflow.sql
│   ├── 010_production_orders_workflow.sql
│   ├── 011_production_stage_logs.sql
│   ├── 012_inventory_warehouse_module.sql
│   ├── 013_dispatch_logistics_module.sql
│   └── 014_production_entry_tables.sql
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
3. Import **one** of these files:

   | File | When to use |
   |------|-------------|
   | **`FULL_DATABASE_BACKUP.sql`** | **Restore everything** (employees, dispatch, payroll, all data) |
   | `restore_fresh.sql` | New empty database + default logins only |

   **phpMyAdmin:** Import → choose the file above

   **Command line (full restore):**

   ```bash
   c:\xampp\mysql\bin\mysql.exe -u root < database\sql\FULL_DATABASE_BACKUP.sql
   ```

   **Command line (fresh install):**

   ```bash
   c:\xampp\mysql\bin\mysql.exe -u root < database\sql\restore_fresh.sql
   ```

4. Open the ERP in the browser (default DB: `tyre_erp`, user `root`, no password).

   After `restore_fresh.sql`, load the app once so PHP seeds department/designation master data.

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
| 008 | `hr_notification_reads`, leave `entry_source` / `recorded_by`, HR notification settings |
| 009 | Production & machines workflow columns |
| 010 | Production orders, stages, BOM, downtime |
| 011 | `production_stage_logs` audit trail |
| 012 | Inventory warehouse: `stock_inward`, `stock_usage`, `stock_adjustments`, raw material extensions |
| 013 | Dispatch module: customers, drivers, vehicles, transport, dispatch weights & status |
| 014 | Daily production entry tables: mixing, building, curing, QC |

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
4. Run: `php tools/db_sync.php backup` to refresh **`FULL_DATABASE_BACKUP.sql`**
5. Update seed SQL if demo data must reflect the change.

Auto-export on migrate: set environment variable `ERP_AUTO_DB_BACKUP=1` or use `APP_ENV=local` in `config/app.php` (exports when new migrations apply).

## Notes

- PHP runtime migrations in `config/db.php` still run for backward compatibility; file migrations are the portable record.
- Duplicate column/index errors during migrate on an already-upgraded DB are ignored safely.
- Commit `database/sql/` to version control so the team always has the latest structure.
