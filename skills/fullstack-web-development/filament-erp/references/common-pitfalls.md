# Filament ERP Common Pitfalls

## Table Reference

| Issue | Cause | Fix |
|-------|-------|-----|
| Page blank, no errors | SPA mode + footer widgets | Use getHeaderWidgets() |
| write_file path `C:\c\laragon\...` | workspace root vs terminal differ | Use `C:\laragon\www\...` or PHP helper |
| Migration creates empty table | `make:migration` only generates skeleton | Verify with `grep Schema` before migrate |
| Column not found after migrate | Migration ran but columns empty | Delete from migrations table, drop table, re-run |
| `$view must not be accessed before init` | Filament Page missing `$view` | Add `protected static string $view = 'filament.pages.xxx';` |
| `Unable to locate [filament-tabs]` | Used non-existent `x-filament-tabs` | Use Alpine.js tabs: `x-data="{ tab: 'x' }"` |
| BackupPage model mismatch | `use App\Models\RestorePoint` (old) | Use `SystemRestorePoint` after migration |
| canAccess() signature error | `canAccess(): bool` without params | Use `canAccess(array $parameters = []): bool` to match parent |
| canAccess() can't access $this->record | canAccess() is static, runs before mount() | Use `authorizeAccess()` instance method for record-level checks |
| canAccess() hardcodes role array | `in_array(Auth::user()->role, ['R00','R01',...])` bypasses permission system | Use `auth()->user()?->hasPermission('module.view')` so RBAC is centralized |
| Table shows all rows despite getEloquentQuery() | modifyQueryUsing not set | Use BOTH getEloquentQuery() AND modifyQueryUsing() on table |
| Code uses status not in DB ENUM | `status='assigned'` but ENUM only has limited values | ALTER TABLE to add missing values |
| Sidebar shows TWO groups "Pengaturan" | `navigationGroup` uses `\u2699\ufe0f` (literal escape) instead of actual emoji `⚙️` | Always use literal emoji char in PHP source: `'⚙️ Pengaturan'` not `'\\u2699\\ufe0f Pengaturan'`. If already broken: `str_replace(chr(92).'u2699', '⚙️', $c)` |
| write_file writes to wrong FS | `write_file` resolves differently than terminal CWD | Use `C:\laragon\www\...` (native Windows) in write_file, OR use `php -r "file_put_contents()"` from terminal |
| Blade template has no actual columns | Migration `up()` just has `$table->id(); timestamps()` | Always write the real schema columns in migration files |
| Password wrong for login | Assumed "password" but seeders use "password123" | Verify: `Hash::check($pw, $user->password)` in tinker |
| File not found (Filament/Livewire) | Searched OneDrive copy (B) which lacks vendor/ and Filament Resources | ALWAYS search/edit at Apache root `C:\laragon\www\PT.EXFERIA PUTRA INOVASI\`. The OneDrive copy is missing Filament files. After editing, sync and run `php artisan view:clear` |
| `\ValidationException` fatal error | `throw new \ValidationException(...)` — namespace doesn't exist in PHP | Use `throw new \Illuminate\Validation\ValidationException($validator)` |
| PPN rate inconsistent between form & model | Form hardcodes `0.11` (or wrong %), model reads config | Form must read same config: `$tarifPpn = (float) config('pajak.tarif_ppn_keluaran', 12)` |
| Permission checker N+1 query on every nav check | `hasPermission()` queries Permission/RolePermission per call | Add request-level cache (`$permCache` array) + preload middleware |
| HasDeptAccess trait does nothing | `checkDeptAccess()` returns `true` unconditionally | Implement real slug resolution and delegation to DeptAccessService, or remove unused trait |
| BackupService password leak via tempfile | MySQL password written to temp file `my_XXXX` before dump | Use `MYSQL_PWD` env var + `--user=...` CLI args instead — no tempfile needed |
| BackupService mysqldump fails on Linux | Paths hardcoded to `C:/laragon/bin/mysql` etc. | Check `config('backup.mysqldump_path')` first, fallback to PATH, then Windows paths |
