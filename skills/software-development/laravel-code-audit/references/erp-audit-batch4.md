# ERP Audit Batch 4 — Workflow Chain + RAB Import + Performance

**Date:** 2026-07-29
**Project:** PT EXFERIA PUTRA INOVASI (`C:\laragon\www\PT.EXFERIA PUTRA INOVASI`)
**Stack:** Laravel 11 + PHP 8.2 + Filament v3.3.54 + MySQL 8.0

## Files Modified (7 fixes from Batch 3 + 6 new fixes)

### From Batch 3 (carried forward)
| File | Fix |
|------|-----|
| `app/Traits/GeneratesCodeNumber.php` | Infinite loop: query builder moved inside while |
| `app/Console/Commands/SendJatuhTempoReminder.php` | `Activity` → `ActivityLog` class name |
| `app/Filament/Widgets/DivisionDashboardWidget.php` | Syntax `whereColumn('stok', '<= ')` + column `tanggal` → `tanggal_pengeluaran` |
| `app/Http/Controllers/Web/SdmController.php` | KPI formula `$approved/($approved+$lateApproved)` → `($approved-$lateApproved)/$approved*100` |
| `app/Http/Controllers/Web/ProjectCommandCenterController.php` | Status `terkirim` → `terbit` (invalid Faktur status) |
| `app/Services/DeptAccessService.php` | Default allow → default deny |
| `app/Models/Kontrak.php` | Added missing `STATUS_ACTIVE`, `STATUS_COMPLETED`, `STATUS_TERMINATED` constants |

### From Batch 4 (this session)
| File | Fix | Severity |
|------|-----|----------|
| `app/Models/Kontrak.php` | Added missing constants (`STATUS_ACTIVE=active`, `STATUS_COMPLETED=completed`, `STATUS_TERMINATED=terminated`) | 🔴 Fatal — workflow broken |
| `app/Filament/Resources/FakturResource/Pages/CreateFaktur.php` | PPN `0.11` → `config('pajak.tarif_ppn_keluaran', 12)/100` | 🔴 Data loss — wrong PPN |
| `app/Filament/Resources/FakturResource/Pages/EditFaktur.php` | Same PPN fix | 🔴 Data loss |
| `app/Console/Commands/CheckFakturJatuhTempo.php` | Batch `where()->update()` → foreach individual model update (events fire) | 🔴 Termin sync broken |
| `app/Filament/Resources/KontrakResource/RelationManagers/TerminRelationManager.php` | Badge color keys: `pending`→`belum_tertagih`, `billed`→`tertagih`, `paid`→`lunas`, `overdue`→`terlambat` | 🟠 UI — badges gray |
| `app/Filament/Resources/FakturResource/RelationManagers/PembayaranRelationManager.php` | Removed duplicate sync in `after()` — model event already handles | 🟠 Double processing |

## Workflow Chain Bugs Found (Kontrak → Pembayaran 100%)

### Chain: KONTRAK → TERMIN → PEKERJAAN → FAKTUR → PEMBAYARAN → PROGRES 100%

| Step | Status after fix | Issue before fix |
|------|-----------------|-----------------|
| Kontrak dibuat | ✅ | — |
| Termin dibuat | ✅ | Badge mapping wrong (gray) |
| Pekerjaan → approved | ✅ | — |
| Faktur dibuat dari termin | ✅ | PPN 11% hardcode vs 12% config; items empty → subtotal=0 |
| Faktur diterbitkan | ✅ | — |
| Faktur jatuh tempo auto (cron) | ✅ | Batch `where()->update()` skipped Eloquent events → termin not synced |
| Pembayaran masuk | ✅ | 2x trigger `hitungProgresOtomatis()` (model + RelationManager) |
| Kontrak auto-complete (100%) | ✅ | `STATUS_COMPLETED` undefined → **PHP Fatal Error**, never completed |
| Aset auto-created | ✅ | Never reached due to fatal error above |

### Root Cause Chain
1. `Kontrak` model used `self::STATUS_COMPLETED` in `hitungProgresOtomatis()` but never defined it → **PHP Fatal Error** on any faktur payment
2. `CheckFakturJatuhTempo` used `Faktur::where()->update()` (direct SQL) → `updated` event not fired → termin status never synced to `terlambat`
3. `mutateFormDataBeforeCreate` recalculated total from `items[]` even when items was empty (faktur from termin auto-fill) → overwrote correct values with 0
4. PPN hardcoded at 11% while config has 12% — form display showed correct 12% but save overwrote with 11%

## RAB Import Fix

**Bug:** Section 2a (`Detail RAB Baru`) and 2b (`Pilih RAB Target`) had `->visible(fn () => ... && !$this->previewData)`. After user clicked "Preview & Validasi File", `$this->previewData` was set → sections disappeared → `form->getState()` returned null for `nomor_rab`, `nama_proyek` → `confirmImportNew()` returned "Isi Nomor RAB dan Nama Proyek" → no save.

**Fix:** Removed `&& !$this->previewData` from both section visibility conditions.

**Verification:** Test script parsed CSV (6 items), imported to new RAB, verified view page renders all komponen with correct totals + markup.

## Edit RAB Lambat — Performance Fixes

| File | Before | After |
|------|--------|-------|
| `RabResource.php` — `helperText` on `harga_satuan` | Called `HargaReferensi::cariHarga()` per row per render (4 queries × N rows) | Removed — analysis available via ViewRab "Jalankan Analisa" |
| `RabResource.php` — `volume` field | `->live()` (1 request/keystroke) | `->live(onBlur: true)` (1 request/blur) |
| `ViewRab.php` — `reapplyAnalisa()` after save | Called `cariHargaMultiSumber()` per item on save (6 queries × N items) | Removed — user clicks "Jalankan Analisa" manually when needed |
| `kirimKeGudang()` | Called `saveAll()` first (50+ queries) | Only updates `markup_persen` if changed (1 query) |

## RAB → Gudang (BOM) Lambat — Performance Fixes

| File | Before | After |
|------|--------|-------|
| `RabMaterialPlanService.php` — matching | Called `SparepartMatchingService::matchToSparepart()` per komponen → N× DB queries with LIKE scans | Preload all spareparts once, match in-memory — 0 DB queries in loop |
| `RabMaterialPlanService.php` — insert | `RabMaterialPlanItem::create()` per komponen → N INSERT queries | Collect array, `RabMaterialPlanItem::insert()` → 1 batch INSERT |

## Login Username Override (Filament v3)

**File:** `app/Filament/Pages/Auth/Login.php` (new)

Pattern for overriding Filament v3 login to use username instead of email:

```php
namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    protected function getForms(): array { /* full schema with username */ }
    protected function getUsernameFormComponent(): Component { /* TextInput: username */ }
    protected function getCredentialsFromFormData(array $data): array { /* ['username' => $data['username'], ...] */ }
    protected function throwFailureValidationException(): never { /* error on data.username */ }
    public function getTitle(): string { /* custom title */ }
    public function getHeading(): string { /* custom heading */ }
}
```

**Registration:** `->login(\App\Filament\Pages\Auth\Login::class)` in AdminPanelProvider.

**Cache:** `php artisan optimize:clear && php artisan filament:cache-components`

## Command Reference for DB Ops

```bash
# Reset all user passwords
php artisan tinker --execute="foreach(User::all() as \$u){ \$u->password = bcrypt('password123'); \$u->save(); } echo 'OK';"

# Find superadmin
php artisan tinker --execute="User::where('role','R00')->first(['id','username','name','email']);"

# List all users
php artisan tinker --execute="User::all(['id','username','name','email','role'])->toArray();"

# Syntax check all modified files
cd "C:\laragon\www\PT.EXFERIA PUTRA INOVASI"
php -l app/Models/Kontrak.php
php -l app/Console/Commands/CheckFakturJatuhTempo.php
# ...etc
```

## Key Config Files Referenced
- `config/pajak.php` — PPN rates (12%)
- `config/database.php` — MySQL credentials
