# PPE Lapsing System

`PPE Lapsing Schedule Management System` built with `PHP`, `MySQL`, and `Bootstrap`.

## Included

- Login with a seeded `Admin` account
- PPE asset register with add, edit, view, delete, and filtering
- Automatic straight-line depreciation schedule generation
- Dashboard, reports, and audit-style record checks
- Asset transfer history with department and location updates
- Alert queue for near-end, fully depreciated active, and unusual records
- CSV export and print-friendly views for assets, alerts, transfers, and schedules

## Local setup

1. Make sure Apache and MySQL are running in XAMPP.
2. Import [db.sql](/c:/xampp/htdocs/ppe_lapsing/db.sql) into MySQL or phpMyAdmin.
3. Open `http://localhost/ppe_lapsing/`.
4. Sign in with the seeded admin account:

- `admin@ppe.local` / `admin123`

## Optional environment setup

You can place local database settings in a `.env` file in the project root.

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=ppe_ai_system
DB_USER=root
DB_PASS=
```

This project auto-loads `.env` on each request.

You can override defaults with these environment variables:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

## Main entry points

- [index.php](/c:/xampp/htdocs/ppe_lapsing/index.php)
- [auth/login.php](/c:/xampp/htdocs/ppe_lapsing/auth/login.php)
- [modules/dashboard.php](/c:/xampp/htdocs/ppe_lapsing/modules/dashboard.php)
- [modules/assets.php](/c:/xampp/htdocs/ppe_lapsing/modules/assets.php)
