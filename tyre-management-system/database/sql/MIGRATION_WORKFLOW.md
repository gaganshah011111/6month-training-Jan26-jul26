# Migration workflow for developers & Cursor

When changing database schema in this ERP:

1. **Add** `database/sql/migrations/NNN_short_description.sql` (next number after latest).
2. **Run** `php tools/db_sync.php migrate`
3. **Export** `php tools/db_sync.php export` → updates `full_latest_backup.sql`
4. **Update** `database/sql/seed/*.sql` if demo/test data needs the new columns.
5. **Commit** all files under `database/sql/`

Do not rely on local MySQL only—every schema change must exist in this folder.
