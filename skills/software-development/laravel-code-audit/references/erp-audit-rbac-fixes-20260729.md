# RBAC Audit Fixes Applied — Session 2026-07-29

## Context
Full RBAC audit + fixes for ERP app. Two access systems: `config/permissions.role_map` (hasPermission via DB) and `DeptAccessService::NAV_ACCESS` (hardcoded array). Fixed 5 critical issues.

## Fixes Applied

### 1. HargaReferensiResource — Permission Name Mismatch
**File:** `app/Filament/Resources/HargaReferensiResource.php:24`
```php
// Before
hasPermission('penawaran.smart_pricing.view')

// After
hasPermission('smart_pricing.view')
```
Config defines `smart_pricing.view` (no `penawaran.` prefix). Non-R00 users (R05, R06) couldn't access despite having the permission.

### 2. SDM Pages — Enabled for HRD (R07)
**Files:** 4 pages, all changed from `return false` to `hasPermission()`
| File | Permission |
|------|-----------|
| `SdmAbsensiPage.php` | `sdm.absensi` |
| `SdmCutiPage.php` | `sdm.cuti` |
| `SdmKinerjaPage.php` | `sdm.kinerja` |
| `SdmStrukturPage.php` | `sdm.karyawan` |

HRD users now see 7 menus (was 2): Absensi, Cuti, Kinerja, Struktur, Karyawan, Departemen, Workflow.

### 3. DeptAccessService — Full Sync with Config
**File:** `app/Services/DeptAccessService.php` — `NAV_ACCESS` rewritten (19 → 47 entries)
Key additions matching `config/permissions.role_map`:
- SDM modules: `sdm-absensi`, `sdm-cuti`, `sdm-kinerja`, `sdm-struktur` → R01, R03, R06, R07
- `faktur` → added R01
- `sparepart` → added R01, R02, R03
- `pengajuan-sparepart` → added R01, R06
- `pemakaian-sparepart` → added R01, R03
- `departemen` → added R01, R07
- `karyawan` → added R07
- `pengeluaran`, `petty-cash`, `permintaan-pembelian` → added R01
- Settings modules: `permission-resource`, `role-permissions`, `user-resource`, `backup`, `activity-log` → added R06
- Dashboard modules: `gudang-dashboard`, `supervisor-dashboard`, `dashboard-smart-pricing`, `teknisi-dashboard`
- RAB tools: `batch-material-mapping`, `bom-gudang`, `dashboard-analisa-ai`, `verifikasi-sparepart`
- Workflow: `workflow-proyek` → added R04, R05; `workflow-detail`

### 4. Hardcoded Role Checks → hasPermission()
| File | Before | After |
|------|--------|-------|
| `CompanySettingPage.php` | `role === 'R00'` | `hasPermission('admin.settings')` |
| `ManajemenPeranPage.php` | `role === 'R00'` | `hasPermission('admin.settings')` |
| `WorkflowDetailPage.php` | `role === 'R00'` | `hasPermission('workflow.view')` |
| `TeknisiDashboard.php` | `role === 'R02'` | `hasPermission('pekerjaan.view')` |

### 5. Config Permissions — Added SDM for R01, R03
**File:** `config/permissions.php`
- R01 (Admin Proyek): + `sdm.karyawan`, `sdm.departemen`, `sdm.absensi`, `sdm.cuti`, `sdm.kinerja`
- R03 (Supervisor): + `sdm.absensi`, `sdm.cuti`, `sdm.kinerja`

`php artisan permissions:sync` — 6 new role-permission links registered.

### 6. NotificationsPage
**File:** `NotificationsPage.php` — left as `return true` (universal access, own notifications only)

## Post-Fix Role Menu Counts

| Role | Before | After | Key Changes |
|------|--------|-------|-------------|
| R00 | 46 | 46 | Unchanged |
| R01 | 40 | 48 | + SDM (5), + Faktur, + Sparepart tools |
| R02 | 21 | 21 | Minor DeptAccess sync |
| R03 | 30 | 33 | + SDM (3) |
| R04 | 18 | 20 | + Workflow Proyek |
| R05 | 13 | 14 | + Workflow Proyek |
| R06 | 45 | 47 | + Settings modules |
| R07 | **2** | **7** | **SDM fully enabled** |

## Verification
```bash
php artisan permissions:sync
php -l app/Filament/Resources/HargaReferensiResource.php
php -l app/Filament/Pages/SdmAbsensiPage.php
php -l app/Filament/Pages/SdmCutiPage.php
php -l app/Filament/Pages/SdmKinerjaPage.php
php -l app/Filament/Pages/SdmStrukturPage.php
php -l app/Filament/Pages/CompanySettingPage.php
php -l app/Filament/Pages/ManajemenPeranPage.php
php -l app/Filament/Pages/WorkflowDetailPage.php
php -l app/Filament/Pages/NotificationsPage.php
php -l app/Services/DeptAccessService.php
php -l config/permissions.php
# All pass
```