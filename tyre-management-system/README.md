# Ralson India Private Limited - Tyre Manufacturing ERP System

Industrial training project ERP built with Core PHP + MySQL + Bootstrap 5.

## Tech Stack
- Backend: Core PHP
- Database: MySQL
- Frontend: HTML, CSS, Bootstrap 5
- JS/Charts: Vanilla JS, Chart.js
- Server: XAMPP

## Setup
1. Copy `tyre-management-system` into `htdocs`.
2. Import `sql/database.sql` in phpMyAdmin.
3. Start Apache + MySQL in XAMPP.
4. Open: `http://localhost/tyre-management-system/login.php`

## Default Login (password: `password`)
- Super Admin: `superadmin@ralson.local`
- Admin: `admin@ralson.local`
- Employee: `employee@ralson.local`

## Implemented Workflow
1. Secure authentication with role-based redirection.
2. Employee + HR flows (attendance, payroll, leave, records).
3. Supplier and raw material lifecycle.
4. Production entry with material consumption.
5. Quality checks with pass/fail + defects.
6. Inventory update from QC passed output.
7. Dispatch orders and stock movement.
8. Reports with KPI cards + Chart.js.

## Routing
- Central router: `routes/web.php`
- Query routes: `index.php?page=module/action`
- Shared middleware/session in `includes/auth.php`

## Security
- `password_hash` / `password_verify`
- Prepared statements (PDO)
- Session regeneration on login
- Basic output escaping helper (`e()`)

## Notes
- This project is modular and easy to scale with dedicated controllers/models per module.
- For production hardening, add CSRF tokens, audit logs, API layer, and PDF export service.

