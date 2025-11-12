Run the migration to create the advance_payments table:

1. Open phpMyAdmin or use the mysql CLI connected to your `salary_system` database.
2. Run the SQL file `admin/sql/create_advance_payments.sql`.

AJAX endpoints:
- `admin/ajax/save_advance.php` — POST JSON (or form) to insert/update an advance payment. Fields: id (optional), employee_id, company_tax_id, advance_date (YYYY-MM-DD), amount, note_internal, note_slip.
- `admin/ajax/delete_advance.php` — POST JSON { id } to delete an advance payment.

Notes:
- The endpoints use the local DB connection settings (root, no password). Adjust if your environment differs.
- The front-end `admin/salary_settings.php` is wired to call these endpoints when saving/deleting advances.
