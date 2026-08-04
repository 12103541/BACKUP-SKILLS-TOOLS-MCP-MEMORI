# Backup & Restore Module (Filament ERP)

## Architecture (5-Tab Page)
| Tab | Component | Function |
|-----|-----------|----------|
| Manual Backup | Form (radio buttons) | Full/DB-only/Selective backup trigger |
| Riwayat Backup | Filament Table | List backups, download, delete |
| Jadwal Backup | Custom Form + list | CRUD schedules, frequency, toggle active |
| Restore Point | List + Restore button | System restore points (auto-created before reset) |
| Reset Sistem | Form with confirm | Selective/Factory/Total reset |

## Models
- **Backup** — `$guarded = []`, `$casts = ['ukuran'=>'integer','is_auto'=>'boolean','expires_at'=>'datetime','selected_tables'=>'array']`
  Relations: `creator() → BelongsTo(User)`, `schedule() → BelongsTo(BackupSchedule)`, `restorePoints() → HasMany(SystemRestorePoint)`
- **BackupSchedule** — `$table = 'backup_schedules'`, casts: `selected_tables=>array`, `is_active=>boolean`, `notify_on_complete=>boolean`, `last_run_at/next_run_at=>datetime`
  Has `computeNextRun()` based on frequency (daily/weekly/monthly) + time + day_of_week/month
- **SystemRestorePoint** — `$table = 'system_restore_points'`, casts: `scope=>array`
  Relations: `backup() → BelongsTo(Backup)`, `creator() → BelongsTo(User)`

## Services
- **BackupService** — `backupFull()`, `backupDatabase($tables)`, `runScheduledBackup($schedule)`, `cleanExpiredBackups()`, `getAvailableTables()` (static)
- **ResetService** — `resetModules($modules)`, `factoryReset()`, `totalReset()`, `createRestorePointBeforeReset($type, $modules)`

## Scheduler (Laravel 11)
In `bootstrap/app.php`, NOT `Console/Kernel.php` (removed in Laravel 11):
```php
Schedule::command('backup:scheduled:run')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup-scheduler.log'));
```

## Migrations
- `create_backup_schedules_table`: id, nama, frequency, time, day_of_week, day_of_month, tipe_backup, selected_tables (json), retention_days, is_active, notify_on_complete, last_run_at, next_run_at, created_by, timestamps
- `create_system_restore_points_table`: id, nama, tipe, backup_id (FK), scope (json), status, created_by, timestamps
- `add_columns_to_backups`: expires_at, is_auto, backup_schedule_id, checksum, selected_tables (json)

## Important Patterns

### Reset Mode Picker (3-Card → Form)
The reset sistem tab shows 3 cards (Per Modul / Factory / Total) with a `updateResetMode(string $mode)` method. This method MUST exist in BackupPage.php or the `wire:click` calls fail silently.
```php
public function updateResetMode(string $mode): void
{
    $this->showResetForm = true;
    $this->resetMode = $mode;
    $this->resetModules = [];
    $this->resetConfirmation = null;
    $this->resetPassword = null;
}
```

### No Filament Tabs Component (Alpine instead)
Filament 3.2 does NOT have a working `x-filament::tabs` or `x-filament-tabs` component.
Use Alpine.js: `x-data="{ tab: 'manual' }"` with tab buttons and `x-show="tab==='...'"`.

### dumpDatabase Argument Ordering (Common Bug)
mysqldump requires DB name BEFORE table names:
```php
// WRONG ❌ — tables before DB (mysqldump will try tables as DB name)
$cmd .= ' ' . implode(' ', array_map('escapeshellarg', $tables));
$cmd .= ' ' . escapeshellarg($db);

// RIGHT ✅ — DB before tables
$cmd .= ' ' . escapeshellarg($db);  // DB first
if (!empty($tables)) $cmd .= ' ' . implode(' ', array_map('escapeshellarg', $tables));
```

### Five-Tab Blade Structure
Blade template: `<x-filament-panels::page>` wrapper, inline PHP blocks for stats, Alpine.js for tab interaction. KPI cards in gradient grid, buttons with `wire:click`, forms with `wire:model`.
Riwayat tab uses plain HTML `<table>` (NOT Filament Table component).

## Key Pitfalls

### Model Rename Audit
When renaming models (e.g., `RestorePoint` → `SystemRestorePoint`):
1. Update `use App\Models\OldName;` → `use App\Models\NewName;` in ALL files
2. Check Blade views: `$this->getModel()` references
3. Check Services: `RestorePoint::create(...)` → `SystemRestorePoint::create(...)`
4. Check BackupPage: import line + all method return types
5. Check Commands: if referenced in Artisan commands
6. Run `php -l` on ALL modified files to verify syntax

### Blade Component Tag Naming
- `x-filament::button` ✅ works in Filament 3.2
- `x-filament::section` ✅ works in Filament 3.2
- `x-filament::icon-button` ✅ works in Filament 3.2
- `x-filament::tabs.tab` ❌ does NOT work — use Alpine.js instead
- `x-filament-tabs` ❌ does NOT work

### Permission Checks
All backup operations: `hasPermission('pengaturan.backup')`
Reset Sistem: should be superadmin-only (R00 only)

## Login Credentials
- Email: `superadmin@example.com`
- Password: `password123` (NOT "password")
