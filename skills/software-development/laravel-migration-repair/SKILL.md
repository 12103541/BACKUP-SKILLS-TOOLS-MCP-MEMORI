---
name: laravel-migration-repair
description: Use when Laravel migrate fails after DB restore.
---

# Laravel Migration Repair (restore + migrate fresh)

## Trigger
- `Unknown column 'X' in 'field list'` on a page that worked before → schema older than code
- Login fails for a KNOWN user while tinker `Auth::attempt` returns true → check `DB::table('users')->count()`; if 0 (or every table 0 rows), DB was wiped — restore backup + migrate
- `Base table or view already exists: 1050` during migrate → table exists but migration not recorded (drifted state)
- `Duplicate key name '...'` during migrate
- `php artisan migrate:status` shows many Pending while DB already has data/tables

## Root cause
Old SQL backup (or drifted DB) restored into a codebase with newer migration files. The `migrations` table and actual schema disagree. Backup restore ≠ code update — you must migrate after import.

## Procedure
1. Confirm mismatch: `php artisan migrate:status | grep -c Pending`; compare backup file date vs newest migration filename.
2. When state is inconsistent: drop ALL tables → reimport backup → migrate fresh. Do NOT run `migrate` on inconsistent state — fails midway (`1050 already exists`), leaves half-applied mess.
3. Fix any broken migrations before rerunning (see pitfalls).
4. `php artisan migrate --force` until all migrations Ran.
5. Reset known-user password (`User::where(...)->update(['password'=>bcrypt('password123')])` via tinker) — old backup hash may be stale or from different app version. Verify with `Auth::attempt`.
6. End-to-end login in browser — dashboard groupBy queries are the real schema test (they surface missing columns fast).

## Pitfalls
- **Backticks in bash**: `mysql -e "... GROUP_CONCAT(\`table_name\`) ..."` — bash eats backticks. Write SQL to a .sql file, run `mysql -uroot < file.sql`. Include `USE dbname;` (mysql invoked without a db arg errors `No database selected`).
- **MySQL client not on PATH** in git-bash (Windows/Laragon): use full path `C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe`.
- **Column used in code but in NO migration** (manual DB change never captured as a migration): confirm with `git log -S "colname" --oneline -- database/migrations/` — if only app/ files show, it was a manual change. Add a new migration.
- **Timestamp ordering**: Laravel runs migrations by filename order. A new migration must sort BEFORE the migration that references its column (e.g. create `2026_07_29_110000_add_x` when the consumer is `2026_07_29_120000_fix_nullable`). Renaming a file before first run is safe.
- **`->unique()->change()`** re-adds a unique index already present from the original CREATE TABLE → `Duplicate key name '...'`. Drop `->unique()`, keep `->change()`.
- Read the backup's CREATE TABLE for the columns/enum values code expects — backups predate schema evolution (missing `status` on kontrak is the classic).

## Verify
- `php artisan migrate:status | grep -c Ran` == number of migration files
- Browser login + dashboard render

## Support files
- `references/exferia-erp-restore.md` — this ERP's concrete restore recipe + exact errors
