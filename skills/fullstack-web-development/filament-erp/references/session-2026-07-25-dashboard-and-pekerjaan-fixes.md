# Session 2026-07-25: Modern Dashboard + Pekerjaan Create Logic Fixes

## Summary
Fixed all discovered issues in the ERP dashboard and Pekerjaan create page.

## Modern Dashboard (Role-Based)

### Files Modified
- `app/Filament/Pages/ModernDashboard.php` — Custom dashboard page extending `Filament\Pages\Dashboard`
- `resources/views/filament/pages/modern-dashboard.blade.php` — Custom Blade view with Chart.js CDN

### Key Fixes

#### 1. Table/Column Name Mismatches in Raw Queries
Fixed all `DB::table()` queries to use correct table and column names:

| Wrong | Correct |
|-------|---------|
| `pengeluarans` | `pengeluaran` |
| `sparepart` | `spareparts` |
| `kliens` | `klien` |
| `kontraks` | `kontrak` |
| `pengajuan_spareparts` | `pengajuan_sparepart` |
| `pemakaian_spareparts` | `pemakaian_sparepart` |
| `tanggal_pemakaian` | `created_at` (pemakaian_sparepart) |
| `tanggal` | `tanggal_pengeluaran` (pengeluaran) |
| `nominal` | `jumlah_biaya` (pengeluaran) |

**Rule**: Always run `DESCRIBE table` before writing raw SQL queries.

#### 2. Section-Level RBAC (Not Just Filter Dropdown)
Implemented `getSectionsForRole()` to gate both data queries AND Blade rendering:

```php
protected function getSectionsForRole(string $role): array
{
    if (in_array($role, ['R00', 'R06'])) {
        return ['overview', 'proyek', 'keuangan', 'gudang', 'sdm'];
    }
    return match ($role) {
        'R01' => ['proyek'],
        'R02', 'R03' => ['proyek', 'gudang'],
        'R04' => ['gudang'],
        'R05' => ['keuangan'],
        default => ['overview'],
    };
}
```

Blade uses `@if(in_array('section', $sections))` — only queries data for visible sections.

#### 3. Custom Blade View with Chart.js (Bypasses Filament Widget System)
The `getWidgets()` override doesn't suppress panel-level widgets because `filament-panels::pages.dashboard` view calls the panel's widget registry. Solution: use raw Chart.js CDN in custom Blade view.

```html
<x-filament-panels::page>
@php $data = $this->getData(); @endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chart1'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode(array_keys($data['pekerjaanStatus'])) !!},
        datasets: [{ data: {!! json_encode(array_values($data['pekerjaanStatus'])) !!}, ... }]
    }
});
</script>
</x-filament-panels::page>
```

Enables: gradient KPI cards, donut center text, line charts with gradient fills.

#### 4. CompanySettingPage Validation Error
Fixed `statePath('data')` → `statePath('settings')` to match form field names `settings.{$setting->key}`.

---

## Pekerjaan Create Page Logic

### Files Modified
- `app/Filament/Resources/PekerjaanResource.php` — Form schema with all logic
- `app/Filament/Resources/PekerjaanResource/Pages/CreatePekerjaan.php` — mutateFormDataBeforeCreate
- `app/Models/Pekerjaan.php` — Model (minimal changes)

### Logic Implemented

| # | Logic | Implementation |
|---|-------|----------------|
| 1 | Teknisi dropdown: only R02/R03 | `->relationship('user', 'name', fn($q) => $q->whereIn('role',['R02','R03']))` |
| 2 | Kontrak dropdown: only active | `->relationship('kontrak', 'nomor_kontrak', fn($q) => $q->where('status','active'))` |
| 3 | Status workflow | `->options(fn($state) => match($state) { 'draft'=>['draft'=>'Draft','submitted'=>'Diajukan'], 'submitted'=>['submitted'=>'Diajukan','approved'=>'Disetujui','rejected'=>'Ditolak'], ... })` |
| 4 | Approval role gate | `->disabled(fn() => !in_array(auth()->user()->role, ['R00','R01','R03','R06']))` |
| 5 | Alasan penolakan visible only on rejected | `->visible(fn($get) => $get('status') === 'rejected')` + `->required(...)` |
| 6 | Auto-generate nama_pekerjaan | In `CreatePekerjaan::mutateFormDataBeforeCreate()`: `sprintf('%s - %s (%s KM %s)', $kontrak->nomor_kontrak, ucfirst($data['jenis_pekerjaan']), $data['aset'], $data['lokasi_km'])` |
| 7 | Duplicate prevention | Check `kontrak_id + user_id + lokasi_ruas` → `Notification::danger()` + `$this->halt()` |
| 8 | Auto-set approved_by/approved_at | If status=approved on create → set `approved_by = auth()->id()`, `approved_at = now()` |
| 9 | JSON column ↔ Textarea | `dokumentasi_keterangan` (array cast) + Textarea with `afterStateHydrated` (implode) + `afterStateUpdated` (explode) |
| 10 | **Show related info (Client Name) when dropdown selected** | **Use `Placeholder` with `live()` on Select + `afterStateUpdated` to set hidden field, `Placeholder::content(fn($get) => $get('klien_nama') ?? 'Pilih kontrak')`** |
| 11 | **Hardcode business defaults (jenis_pekerjaan = 'perbaikan')** | **In `mutateFormDataBeforeCreate()`: `$data['jenis_pekerjaan'] = 'perbaikan';` — don't show in form** |
| 12 | **Teknisi optional/deferred assign** | **`->required(false)` + label "Teknisi (Assign Nanti)" + helper text "Opsional — bisa di-assign nanti dari daftar pekerjaan"** |

### Show Related Info (e.g., Client Name) When Dropdown Selected
Use a `Placeholder` that updates via `live()` on the Select field. In the Select's `afterStateUpdated`, fetch related data and set a hidden field that the Placeholder reads:

```php
// In the form() method:
Select::make('kontrak_id')
    ->label('Kontrak')
    ->relationship('kontrak', 'nomor_kontrak', fn($q) => $q->where('status', 'active'))
    ->searchable()
    ->preload()
    ->required()
    ->live() // Triggers afterStateUpdated on change
    ->afterStateUpdated(fn (callable $set, $state) => $set('klien_nama', 
        $state ? Kontrak::with('klien')->find($state)?->klien?->nama : 'Klien tidak ditemukan'
    )),

Placeholder::make('klien_info')
    ->label('Klien')
    ->content(fn (callable $get) => $get('klien_nama') ?? 'Pilih kontrak terlebih dahulu')
    ->columnSpanFull(),
```
Note: The Placeholder's `content()` can use a closure with `$get` to read other form state reactively.

### Hardcode Business Defaults in CreateRecord
When a field has a fixed business value (e.g., Admin Proyek only creates "perbaikan" jobs), set it in `mutateFormDataBeforeCreate()` — NOT in the form (don't show to user):
```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['jenis_pekerjaan'] = 'perbaikan'; // Hardcoded business rule
    $data['status'] = 'draft';              // Default workflow start state
    return $data;
}
```

### Filament v3.2 Select Filtering Pattern
**Critical**: `Select::query()` and `Select::modifyQueryUsing()` don't exist. Use closure as 3rd argument to `->relationship()`:

```php
// WRONG
->query(fn($q) => $q->where('status','active'))
->modifyQueryUsing(fn($q) => $q->where('status','active'))

// RIGHT
->relationship('kontrak', 'nomor_kontrak', function ($query) {
    $query->where('status', 'active');
})
```

---

## Browser Testing Notes

### Filament User Menu Dropdown
Alpine.js `x-show` dropdowns don't reliably toggle via `browser_click` on avatar ref. Workaround: submit logout form directly via JS or click the hidden form's submit button.

```js
document.querySelector('button[type="submit"]').click()
```

### Session/Route Verification
- Admin login: `GET /admin/login` (requires `->login()` in AdminPanelProvider)
- Admin logout: `POST /admin/logout` (hidden form with CSRF)
- Both share `web` guard session

---

## Environment Commands Used
```bash
# Clear caches and publish assets
php artisan config:clear && php artisan route:clear && php artisan cache:clear && php artisan view:clear && php artisan filament:assets

# Verify MySQL
"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" -u root aplikasi_kantor -e "DESCRIBE table_name;"

# Check table names
"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" -u root aplikasi_kantor -e "SHOW TABLES LIKE '%keyword%';"
```

---

*Session completed: 2026-07-25*