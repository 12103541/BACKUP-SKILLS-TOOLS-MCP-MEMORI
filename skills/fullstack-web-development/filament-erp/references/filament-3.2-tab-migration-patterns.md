# Tab Navigation & Page Migration Patterns for Filament ERP

## Alpine.js Tabs (Filament 3.2 — No `x-filament-tabs`)

Filament 3.2 does NOT have tab blade components. Use Alpine.js:

```html
<div x-data="{ tab: 'manual' }">
    <nav class="flex gap-6 -mb-px border-b border-gray-200 mb-6">
        <button @click="tab='manual'"
            :class="tab==='manual' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="pb-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
            <svg class="w-4 h-4" ... />Tab Name
        </button>
        <button @click="tab='riwayat'" :class="...">Tab 2</button>
    </nav>
    <div x-show="tab==='manual'" x-cloak>Content A</div>
    <div x-show="tab==='riwayat'" x-cloak>Content B</div>
</div>
```

Use `x-show` (not `@if`/`@switch`) so Alpine handles tab state client-side. The `x-cloak` attribute prevents flash.

## Page Must Declare `$view`

Every Filament Page subclass must have:
```php
protected static string $view = 'filament.pages.your-page';
```

Without it: `Typed static property BasePage::$view must not be accessed before initialization`

## Migration Re-run Pattern

When a migration creates a table with only `id()` + `timestamps()` (the skeleton):

```php
// Delete migration record
DB::table('migrations')->where('migration', 'LIKE', '%2026_07_27_204253%')->delete();
// Drop incomplete table
Schema::dropIfExists('backup_schedules');
// Run fixed migration
artisan migrate --path=database/migrations/2026_07_27_204253_create_backup_schedules_table.php --force
```

## Windows Path Resolution

| Tool | Project Root | Example |
|------|-------------|---------|
| `write_file` | `C:\laragon\www\PT.EXFERIA PUTRA INOVASI` | `C:\laragon\www\...` |
| `terminal` (bash/MSYS) | `/c/laragon/www/PT.EXFERIA PUTRA INOVASI` | `/c/laragon/www/...` |

When `write_file` uses `/c/laragon/...` it creates `C:\c\laragon\...` (WRONG). Always use native Windows path in write_file. For large files, write a PHP writer script with write_file, then exec via terminal.

## BackupPage Permission Pattern

```php
public static function canAccess(): bool
{
    return auth()->user()?->hasPermission('settings', 'view') ?? false;
}
```

## Available Tables for Selective Backup

From `BackupService::getAvailableTables()` — lists all ERP tables for user selection in backup UI. Returns `[table_name => label]` pairs.

## Reset Modules Structure

From `ResetService::getModules()` — module groupings for selective reset. Each module maps to specific tables to truncate.
