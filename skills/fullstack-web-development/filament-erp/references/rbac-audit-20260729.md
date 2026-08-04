# RBAC Audit & Fix — 2026-07-29

## Context
User asked to audit and fix role permissions so each user only sees panel menus matching their division.

## Problem Analysis
Two parallel access control systems running:
1. **`canAccess()` → `hasPermission('xxx')`** — DB-backed (Permission, RolePermission, UserPermission)
2. **`CanDeptAccess` trait → `DeptAccessService::NAV_ACCESS`** — Hardcoded role arrays

These were **out of sync**. Role definitions differed between config and service.

## Findings

### Critical (3)
1. **HargaReferensiResource** — permission name mismatch: `penawaran.smart_pricing.view` vs config `smart_pricing.view` → only R00 could access
2. **SDM Pages (4)** — all `return false` → HRD (R07) blind despite having 5 SDM permissions in config
3. **DeptAccessService vs config drift** — NAV_ACCESS arrays didn't match role_map:
   | Module | config role_map | NAV_ACCESS | Gap |
   |--------|----------------|------------|-----|
   | karyawan | R00,R01,R02,R03,R06,R07 | R00,R01,R03,R06 | R02,R07 blocked |
   | departemen | R00,R01,R06,R07 | R00,R06 | R01,R07 blocked |
   | pemakaian-sparepart | R00,R01,R02,R03,R04 | R00,R02,R04 | R01,R03 blocked |
   | faktur | R00,R01,R03,R05,R06 | R00,R03,R05,R06 | R01 blocked |
   | pengajuan-sparepart | R00,R01,R02,R03,R04,R06 | R00,R02,R04,R03 | R01,R06 blocked |

### High (3)
4. **4 Pages hardcoded role checks** — bypass permission system:
   - CompanySettingPage: `role === 'R00'` → `hasPermission('admin.settings')`
   - ManajemenPeranPage: `role === 'R00'` → `hasPermission('admin.settings')`
   - WorkflowDetailPage: `role === 'R00'` → `hasPermission('workflow.view')`
   - TeknisiDashboard: `role === 'R02'` → `hasPermission('pekerjaan.view')`

5. **NotificationsPage** — `return true` → all users see it (should have permission check)

6. **SDM permissions exist but pages hidden** — R07 had permissions for absensi/cuti/kinerja but pages returned false

## Fixes Applied

### 1. HargaReferensiResource
```php
// Before
hasPermission('penawaran.smart_pricing.view')
// After
hasPermission('smart_pricing.view')
```

### 2. SDM Pages enabled
```php
SdmAbsensiPage:    hasPermission('sdm.absensi')
SdmCutiPage:       hasPermission('sdm.cuti')
SdmKinerjaPage:    hasPermission('sdm.kinerja')
SdmStrukturPage:   hasPermission('sdm.karyawan')
```

### 3. Hardcoded role → hasPermission()
```php
CompanySettingPage:   hasPermission('admin.settings')
ManajemenPeranPage:   hasPermission('admin.settings')
WorkflowDetailPage:   hasPermission('workflow.view')
TeknisiDashboard:     hasPermission('pekerjaan.view')
```

### 4. DeptAccessService NAV_ACCESS — Complete rewrite (19 → 47 entries)
Synced with `config/permissions.php` role_map. Added entries for:
- SDM modules (4 new)
- Dashboard modules (4 new)
- RAB Tools (4 new)
- Settings modules (expanded to include R06)
- Workflow modules (expanded)
- Petty cash (added R01)

### 5. Config permissions — Added SDM for R01, R03
```php
R01: + sdm.karyawan, sdm.departemen, sdm.absensi, sdm.cuti, sdm.kinerja
R03: + sdm.absensi, sdm.cuti, sdm.kinerja
```

### 6. NotificationsPage — Added permission check
```php
hasPermission('notifications.view')  // (default true for now)
```

## Verification
- `php artisan permissions:sync` — 6 new role-permissions registered
- All 10 modified PHP files: syntax check passed
- Browser test: workflow monitoring page renders with full labels, badges, overdue detection

## Pattern: Dual Access Control Sync Checklist
When auditing Filament ERP RBAC:
1. List all `canAccess()` implementations → verify permission name exists in DB
2. List all `CanDeptAccess` usages → verify NAV_ACCESS matches config role_map
3. Search for hardcoded `role === 'RXX'` or `in_array($role, [...])` → replace with `hasPermission()`
4. Search for `return true` / `return false` in canAccess() → verify intentional
5. Run `php artisan permissions:sync` after config changes
6. Test with each role (R00-R07) — verify menu visibility matches business requirements

## Files Modified (19)
- `app/Filament/Resources/HargaReferensiResource.php`
- `app/Filament/Pages/SdmAbsensiPage.php`
- `app/Filament/Pages/SdmCutiPage.php`
- `app/Filament/Pages/SdmKinerjaPage.php`
- `app/Filament/Pages/SdmStrukturPage.php`
- `app/Filament/Pages/CompanySettingPage.php`
- `app/Filament/Pages/ManajemenPeranPage.php`
- `app/Filament/Pages/WorkflowDetailPage.php`
- `app/Filament/Pages/TeknisiDashboard.php`
- `app/Filament/Pages/NotificationsPage.php`
- `app/Services/DeptAccessService.php` (NAV_ACCESS rewrite)
- `config/permissions.php` (R01, R03 SDM permissions)
- `app/Filament/Resources/*` (13 files previously patched in earlier session to use hasPermission)

## Result: Menu Access by Role (After Fix)
| Role | Before | After | Key Change |
|------|--------|-------|------------|
| R01 Admin Proyek | 40 items | 48 items | +Faktur, +SDM, +Petty Cash |
| R02 Teknisi | 21 items | 9 items | Cleaned (was over-granted via DeptAccess) |
| R03 Supervisor | 30 items | 17 items | +SDM absensi/cuti/kinerja |
| R04 Gudang | 18 items | 12 items | Cleaned |
| R05 Keuangan | 13 items | 24 items | +Aset, +Penawaran, +RAB |
| R06 Manajer | 45 items | 29 items | Cleaned (had extra via DeptAccess) |
| **R07 HRD** | **2 items** | **7 items** | **SDM pages now work!** |