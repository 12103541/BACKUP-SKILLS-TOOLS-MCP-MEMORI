# Exferia ERP — concrete restore recipe (2026-08-01)

App: `C:\laragon\www\PT.EXFERIA PUTRA INOVASI` (Laravel 11, PHP 8.2, MySQL 8.0, Laragon).
DB: `aplikasi_kantor`. Backup: `aplikasi_kantor_backup.sql` (project root, mysqldump format).

## Symptom
superadmin login OK (credentials valid) but dashboard crashes:
`SQLSTATE[42S22] Unknown column 'status' in 'field list'` on `select status, COUNT(*) from kontrak group by status` (ModernDashboard::getData).

Root: DB had been wiped (users table 0 rows) → restored Jun-6 backup, but 96 migrations (Jun 6 → Aug 1) were Pending. Schema lag = crash.

## Steps used
1. `php artisan migrate:status` → ~45 Pending; DB already had tables incl. `client_devices` not in migrations table → inconsistent.
2. Drop all tables (SQL file, see below) → `mysql -uroot aplikasi_kantor < aplikasi_kantor_backup.sql` → `php artisan migrate --force`.
3. Fixed 2 broken migrations:
   - Created `2026_07_29_110000_add_dokumentasi_steps_to_pekerjaan_table.php` (column referenced by Pekerjaan model + `2026_07_29_120000_fix_nullable_and_length.php` but never created by any migration — manual DB change; confirmed via `git log -S "dokumentasi_steps"`).
   - `2026_07_29_120000_fix_nullable_and_length.php`: removed `->unique()` from `nomor_faktur` change (duplicate key `faktur_nomor_faktur_unique` already existed from original CREATE TABLE).
4. Reset superadmin: `User::where('username','superadmin')->update(['password'=>bcrypt('password123')])`. Login: superadmin / password123.

## Drop-all SQL (backticks break in bash -e; run as file)
```sql
USE aplikasi_kantor;
SET FOREIGN_KEY_CHECKS=0;
SET GROUP_CONCAT_MAX_LEN=1000000;
SET @tables = NULL;
SELECT GROUP_CONCAT('`', table_name, '`') INTO @tables FROM information_schema.tables WHERE table_schema='aplikasi_kantor';
SET @sql = IFNULL(CONCAT('DROP TABLE IF EXISTS ', @tables), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET FOREIGN_KEY_CHECKS=1;
```

## Notes
- MySQL client not on git-bash PATH: `"C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe"`.
- Backup is old (Jun 6): all later schema (kontrak.status, termin, dokumentasi_steps, dsb.) comes from migrations — the backup is DATA only, schema comes from code.
- After restore+migrate, backup again (`backup_db.bat` exists in project root) to capture the full Aug-1 schema.
