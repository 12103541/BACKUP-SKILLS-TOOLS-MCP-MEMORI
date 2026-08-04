# Backup/Restore Antipattern: UI Exists But Backend Missing

## Context
Found in `app/Filament/Pages/BackupPage.php` — the Restore tab shows restore points with "Restore" buttons, but clicking only triggers a notification.

## The Bug
```php
// BackupPage.php line 174-182
public function performRestore(int $id): void
{
    $rp = SystemRestorePoint::with('backup')->find($id);
    if (!$rp || !$rp->backup) {
        Notification::make()->title('Restore point tidak valid')->danger()->send();
        return;
    }
    Notification::make()->title('Restore point dipilih: ' . $rp->nama)->info()->send();  // ❌ ONLY NOTIFICATION
}
```

**BackupService** has `backupFull()`, `backupDatabase()`, `runScheduledBackup()` — **no `restore()` method**.

## Root Cause
- Restore UI built but backend logic never implemented
- Common pattern: "restore point" created after each backup, but no restore execution path
- `SystemRestorePoint` model exists with `status` field (`available`, `restored`, etc.) but no state transition logic

## Fix Required
1. Add `restore(Backup $backup)` method to `BackupService`:
   - Extract ZIP (full) or read SQL (database-only)
   - Drop/recreate tables from dump
   - Optional: restore source files (full backup)
   - Update `SystemRestorePoint` status → `restored`
2. Call from `BackupPage::performRestore()` with confirmation modal
3. Add progress notification (long-running operation)

## Prevention
- Every "create X" feature must have corresponding "restore/apply X" before shipping UI
- Write integration test: backup → restore → verify data integrity
- Checklist: if model has `status` with `restored` value, there must be code that sets it