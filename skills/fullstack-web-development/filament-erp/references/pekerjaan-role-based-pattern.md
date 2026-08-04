# Pekerjaan Module — Role-Based Pattern (PT EXFERIA)

## Status ENUM (6 values)
```sql
ENUM('draft','assigned','in_progress','submitted','approved','rejected')
```

## Flow
```
Admin buat → draft
Admin assign teknisi → assigned
Teknisi mulai kerja → in_progress (tahap 50%)
Teknisi submit laporan → submitted (tahap 100%)
Supervisor approve → approved
Supervisor reject → rejected → revisi ke draft
```

## Role → Visibility Matrix

| Role | Table View | Form | Actions | Status Control |
|------|-----------|------|---------|---------------|
| R00 Super Admin | All rows | Full form | Create, Edit, Delete, Assign, Approve/Reject | Full |
| R01 Admin Proyek | All rows | Full form (jadwal) | Create, Edit, Assign Teknisi, Submit Review | draft→assigned→submitted |
| R02 Teknisi | Own rows only | Read-only info + Dokumentasi | Execute (buka ExecutePekerjaan) | assigned→in_progress→submitted |
| R03 Supervisor | Own + submitted | Read-only | Approve/Reject | submitted→approved/rejected |
| R04 Gudang | N/A | N/A | N/A | N/A |
| R05 Keuangan | N/A | N/A | N/A | N/A |
| R06 Manajer | All rows | Full form | All actions | Full |

## Key Code Patterns

### Table filtering (belt-and-suspenders)
```php
// Both methods needed for reliable filtering
public static function getEloquentQuery(): Builder { /* same filter */ }
public static function table(Table $table): Table {
    return $table->modifyQueryUsing(function ($query) { /* same filter */ })
}
```

### ExecutePekerjaan access control
```php
public function authorizeAccess(): void  // NOT canAccess() — needs $this->record
{
    parent::authorizeAccess();
    if (Auth::user()->role === 'R02' && $this->record->user_id === Auth::id()) return;
    if (in_array(Auth::user()->role, ['R00','R01','R06','R03'])) return;
    abort(403);
}
```

### Form visibility by role
```php
Select::make('kontrak_id')->disabled(!$isAdmin),      // See value, can't change
Select::make('user_id')->visible($isAdmin),            // Hidden entirely
FileUpload::make('foto_paths')->disabled(!$isTeknisi), // Only teknisi uploads
```

### Status transition in actions
```php
Action::make('execute')
    ->visible(fn ($record) => in_array($record->status, ['assigned','in_progress'])
        && $record->user_id === auth()->id())
```

## CRITICAL: Actions Placement (Verified 2026-07-25)

**`ListXxx::getTableActions()` does NOT work in Filament v3.2.** Actions defined there are silently ignored. ALL table row actions MUST be defined in the Resource's `table()` method via `->actions([...])`.

```php
// In PekerjaanResource::table()
return $table
    ->actions([
        // ASSIGN TEKNISI (Admin: draft + no teknisi yet)
        Action::make('assign_teknisi')
            ->label('Assign')
            ->icon('heroicon-o-user-plus')
            ->color('info')
            ->visible(fn ($record) => $isAdmin && $record->status === 'draft' && empty($record->user_id))
            ->form([
                Select::make('user_id')
                    ->label('Pilih Teknisi')
                    ->options(fn () => User::where('role', 'R02')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->required()
                    ->native(false),
            ])
            ->action(function ($record, array $data): void {
                $record->update(['user_id' => $data['user_id'], 'status' => 'assigned']);
                Notification::make()->title('Teknisi Di-assign')->success()->send();
            }),

        // SUBMIT REVIEW (Admin: draft/assigned + has teknisi)
        Action::make('submit_review')
            ->label('Submit')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->visible(fn ($record) => $isAdmin && in_array($record->status, ['draft', 'assigned']) && !empty($record->user_id))
            ->requiresConfirmation()
            ->action(function ($record): void {
                $record->update(['status' => 'submitted']);
                Notification::make()->title('Diajukan untuk Review')->success()->send();
            }),

        // EKSEKUSI (Teknisi: assigned/in_progress + own record)
        Action::make('execute')
            ->label('Eksekusi')
            ->icon('heroicon-o-play')
            ->color('success')
            ->visible(fn ($record) => $isTeknisi && in_array($record->status, ['assigned', 'in_progress']) && $record->user_id == $user->id)
            ->url(fn ($record): string => PekerjaanResource::getUrl('execute', ['record' => $record])),

        // APPROVE (Supervisor/Admin: submitted + tahap 100%)
        Action::make('approve')
            ->label('Approve')
            ->visible(fn ($record) => ($isSupervisor || $isAdmin) && $record->status === 'submitted' && $record->dokumentasi_tahap === '100%')
            ->requiresConfirmation()
            ->action(function ($record): void {
                $record->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            }),

        // REJECT (Supervisor/Admin: submitted)
        Action::make('reject')
            ->label('Tolak')
            ->color('danger')
            ->visible(fn ($record) => ($isSupervisor || $isAdmin) && $record->status === 'submitted')
            ->form([Textarea::make('alasan')->label('Alasan Penolakan')->required()->rows(3)])
            ->action(function ($record, array $data): void {
                $record->update(['status' => 'rejected', 'alasan_penolakan' => $data['alasan']]);
            }),

        // VIEW + EDIT + DELETE (standard)
        Tables\Actions\ViewAction::make(),
        Tables\Actions\EditAction::make()->visible(fn () => $isAdmin),
        Tables\Actions\DeleteAction::make()->visible(fn () => $user->role === 'R00')->requiresConfirmation(),
    ])
```

## Testing Checklist (Verified 2026-07-25)

### Browser test per role:
| Role | Login | Sidebar | Table | Actions | Expected |
|------|-------|---------|-------|---------|----------|
| R01 Admin Proyek | admin.proyek@example.com | Full menu | All 10 rows | Assign, Submit, Lihat, Ubah, Hapus | Assign button on draft+unassigned, Submit on assigned |
| R02 Teknisi | teknisi1@example.com | Pekerjaan only | Own rows (filtered) | Lihat only | No Create, no Edit |
| R03 Supervisor | supervisor@example.com | + approve | Own + submitted | Approve/Reject | Only on submitted status |

### Status ENUM verification:
```sql
-- Verify ENUM values match code
SHOW COLUMNS FROM pekerjaan WHERE Field = 'status';
-- Expected: draft,assigned,in_progress,submitted,approved,rejected

-- Count per status
SELECT status, COUNT(*) FROM pekerjaan GROUP BY status;
```

### Action visibility verification:
- Row with `status=draft, user_id=NULL` → shows "Assign" button
- Row with `status=assigned, user_id=4` → shows "Submit" button (admin) or "Eksekusi" (teknisi)
- Row with `status=submitted` → shows "Approve"/"Reject" (supervisor)
- Row with `status=approved` → no action buttons besides View/Edit

## CSRF Issue on Execute Page (Verified 2026-07-25)

The ExecutePekerjaan custom Page loads successfully (title renders) but Livewire immediately shows "This page has expired" dialog **on cloud browsers only**.

### Root cause
1. **APP_URL mismatch** — `.env` has `APP_URL=http://192.168.0.6` but browser accesses via `http://localhost`. Session cookie is bound to wrong domain.
2. **Cloud browser limitation** — Browserbase/remote browsers cannot persist session cookies for `localhost` properly. This is NOT a code issue.

### Fix
```env
# In .env — match the URL users actually access
APP_URL=http://localhost
```
```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Key finding: Works on local browser (2026-07-25)
After APP_URL fix, user's LOCAL Chrome browser showed the execute page correctly (test page with Record ID, Status, Nama). The "page expired" error is **cloud-browser-only** — do NOT waste time debugging blade templates if the page works locally.

### If CSRF persists on LOCAL browser (rare)
If the page fails even on local browser after APP_URL fix:
1. Clear all caches: `php artisan config:cache && php artisan route:cache && php artisan view:cache`
2. Restart Apache: `taskkill //F //IM httpd.exe && /c/laragon/bin/apache/httpd-2.4.54-win64-VS16/bin/httpd.exe`
3. Check `00-default.conf` hasn't reappeared in `sites-enabled/`
4. As last resort: switch to `EditRecord` base class

### Verification
After fix, test:
1. Login as teknisi1
2. Navigate to `/admin/pekerjaans/10/execute`
3. Page should load WITHOUT "This page has expired" dialog
4. Try changing Tahap dropdown → Livewire should update without error

## Pitfall: Patch Tool Double-Escaping Namespaces

When using the `patch` tool on PHP files with namespace references like `Tables\Actions\Action`, the tool double-escapes backslashes: `Tables\\Actions\\Action` → `Tables\\\\Actions\\\\Action` → PHP parse error.

**Recovery pattern** (verified working):
```python
# 1. Read file with read_file()
# 2. Fix all double-escaped backslashes: replace('\\\\', '\\')
# 3. Also fix FilamentNotificationsNotification → Filament\\Notifications\\Notification
# 4. Write clean file with write_file()
```

**Prevention**: For PHP files with 3+ namespace references, use `write_file()` (full overwrite) instead of `patch()`/`replace()`.

## ExecutePekerjaan — Custom Page Blade Pattern (Verified 2026-07-25)

ExecutePekerjaan is a custom `Page` (not EditRecord) that renders a form via Blade. This requires a specific approach:

### PHP class: `ExecutePekerjaan extends Page`
```php
class ExecutePekerjaan extends Page
{
    protected static string $resource = PekerjaanResource::class;
    protected static string $view = 'filament.resources.pekerjaan-resource.pages.execute-pekerjaan';
    public Pekerjaan $record;
    public ?array $data = [];

    public function mount(Pekerjaan $record): void
    {
        $this->record = $record;
        // CRITICAL: fill form state in mount — without this, form fields are empty
        $this->form->fill([
            'foto_paths' => $record->foto_paths ?? [],
            'dokumentasi_tahap' => $record->dokumentasi_tahap ?? '0%',
            'dokumentasi_keterangan' => is_array($record->dokumentasi_keterangan)
                ? implode("\n", $record->dokumentasi_keterangan)
                : ($record->dokumentasi_keterangan ?? ''),
            'gps_latitude' => $record->gps_latitude ?? null,
            'gps_longitude' => $record->gps_longitude ?? null,
            'gps_accuracy' => $record->gps_accuracy ?? null,
        ]);
    }

    public function form(Form $form): Form {
        return $form->schema([...])->statePath('data')->model($this->record);
    }
    protected function getFormActions(): array { return []; }
}
```

### Blade: `<x-filament-panels::page>` NOT `@extends`
```blade
<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}
        <x-filament::button type="submit">Simpan</x-filament::button>
    </form>
</x-filament-panels::page>
```

### URL generation in table action:
```php
Action::make('execute')
    ->url(fn ($record): string => PekerjaanResource::getUrl('execute', ['record' => $record]))
```

### Verified pitfalls:
- `@extends('filament-panels::pages/page')` → View not found
- `$form->render()` → Undefined variable
- `$getTitle()` / `$getSubheading()` → Undefined in Blade
- `static::getResource()` → method not found on Resource
- `getUrl('index')` → TypeError, needs array `getUrl(['index'])`

---

*Last updated: 2026-07-25 (CSRF fix, APP_URL mismatch, custom Page + form() lifecycle)*
