# RBAC Audit — ERP Session 2026-07-29

## Context
Full menu-per-role audit of ERP app (Laravel/Filament). 12 users, 8 roles,
31 Resources, 35 Pages. Two access systems running in parallel:
`config/permissions.role_map` (hasPermission via DB) and
`DeptAccessService::NAV_ACCESS` (hardcoded array).

## Key Findings

### 1. Permission Name Mismatch: HargaReferensiResource
`app/Filament/Resources/HargaReferensiResource.php:24`
```php
hasPermission('penawaran.smart_pricing.view')
```
Config defines `smart_pricing.view` (no `penawaran.` prefix). Lookup fails
for all non-R00 users → Resource invisible to R05, R06 despite them having
`smart_pricing.view`.

### 2. SDM Pages Hard-Disabled for HRD
Four SDM pages all `return false`:
- `SdmAbsensiPage.php` → `return false; // SDM disembunyikan`
- `SdmCutiPage.php` → `return false`
- `SdmKinerjaPage.php` → `return false`
- `SdmStrukturPage.php` → `return false; // SDM disembunyikan`

R07 (HRD) has 6 permissions: `sdm.karyawan, sdm.absensi, sdm.cuti,
sdm.departemen, sdm.kinerja`. But sees only 2 menus (Departemen, WorkflowProyek).

### 3. DeptAccessService vs Config Drift

| Modul | role_map allows | DeptAccessService allows | Gap |
|-------|----------------|--------------------------|-----|
| karyawan | R00,R01,R02,R03,R06,R07 | R00,R01,R03,R06 | R02,R07 denied |
| departemen | R00,R01,R06,R07 | R00,R06 | R01,R07 denied |
| pemakaian-sparepart | R00,R01,R02,R03,R04 | R00,R02,R04 | R01,R03 denied |
| faktur | R00,R01,R03,R05,R06 | R00,R03,R05,R06 | R01 denied |
| pengajuan-sparepart | R00,R01,R02,R03,R04,R06 | R00,R02,R04,R03 | R01,R06 denied |

### 4. Hardcoded Role Checks
- `CompanySettingPage.php`: `role === 'R00'` → should be `hasPermission('admin.settings')`
- `ManajemenPeranPage.php`: `role === 'R00'` → should be `hasPermission('admin.settings')`
- `TeknisiDashboard.php`: `role === 'R02'` → should be `hasPermission('pekerjaan.view')`
- `WorkflowDetailPage.php`: `role === 'R00'` → should be `hasPermission('workflow.view')`

### 5. NotificationsPage
`NotifiationsPage.php:20` → `return true` (visible to ALL users). No permission
check. Should use `hasPermission('dashboard.view')` or keep universal.

## Per-Role Menu Count (actual vs expected based on role_map)

| Role | Description | Menus Shown | Expected Moduls | Gap |
|------|-------------|-------------|-----------------|-----|
| R00 | Super Admin | 46 | all 22 | None |
| R01 | Admin Proyek | 40 | 14 moduls managed | Minor |
| R02 | Teknisi | 21 | 5 moduls basic | Extra via DeptAccess |
| R03 | Supervisor | 30 | 11 moduls managed | Extra via DeptAccess |
| R04 | Gudang | 18 | 7 moduls managed | Extra via DeptAccess |
| R05 | Keuangan | 13 | 10 moduls managed | Missing Aset, Penawaran, RAB via DeptAccess |
| R06 | Manajer | 45 | 14 moduls | Extra via DeptAccess |
| R07 | HRD | **2** | 6 moduls | **4 missing — all SDM pages disabled** |

## Remediation Priority

1. Fix config: add `penawaran.smart_pricing.view` as alias OR rename in code
2. Enable SDM pages for R07 — add `hasPermission('sdm.*')` checks
3. Sync `DeptAccessService.php` with `config/permissions.role_map`
4. Replace hardcoded role checks with hasPermission() in 4 Pages
5. Add permission check to NotificationsPage or leave universal
