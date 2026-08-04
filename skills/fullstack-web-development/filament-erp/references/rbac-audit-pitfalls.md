# RBAC Audit Pitfalls — 2026-07-29 Session

## Core Problem: Dual Access Control Systems

The ERP has **two independent access control systems** that silently disagree:

| System | Location | What it controls |
|--------|----------|------------------|
| **Permission Config** | `config/permissions.php` → `role_map` | `hasPermission('kode')` checks in Resources/Pages |
| **DeptAccessService** | `app/Services/DeptAccessService.php` → `NAV_ACCESS` | Sidebar navigation visibility, Page `HasDeptAccess` trait |

**They are never auto-synced.** A role can have permission in config but be denied in DeptAccessService, or vice versa. This caused:
- HRD (R07) having `sdm.*` permissions in config but seeing 0 SDM menus (DeptAccessService lacked `sdm-*` keys)
- Admin Proyek (R01) having `faktur.*` in config but hidden by DeptAccessService
- Keuangan (R05) missing `aset`, `penawaran`, `rab` despite config permissions

## Audit Procedure

```bash
# 1. Extract DeptAccessService slugs
grep -oP "'\K[^']+(?='\s*=>\s*\['roles'\s*=>)" app/Services/DeptAccessService.php | sort

# 2. Extract config permission kodes
grep -oP "'kode'\s*=>\s*'\K[^']+" config/permissions.php | sort

# 3. Cross-reference: every DeptAccessService key should map to config permissions
# 4. Check every hasPermission() call against config kodes
grep -rn "hasPermission(" app/Filament/ --include="*.php" | grep -oP "hasPermission\(['\"]\K[^'\"]+"

# 5. Find hardcoded role checks
grep -rn "in_array.*role\|auth\(\)->user\(\)->role" app/Filament/ --include="*.php"
```

## Key Findings (Fixed)

### 1. Permission Name Mismatch
**File:** `HargaReferensiResource.php:24`
```php
// BEFORE: hasPermission('penawaran.smart_pricing.view')
// AFTER:  hasPermission('smart_pricing.view')
```
Config has `smart_pricing.view` (no `penawaran.` prefix). Mismatch → always false for non-R00.

### 2. SDM Pages Hard-Disabled
**Files:** `SdmAbsensiPage`, `SdmCutiPage`, `SdmKinerjaPage`, `SdmStrukturPage`
```php
// BEFORE: return false;
// AFTER:  return auth()->user()?->hasPermission('sdm.absensi') ?? false;
```
HRD had 5 `sdm.*` permissions in config but 0 SDM menus visible.

### 3. Hardcoded Role Checks (4 Pages)
| Page | Before | After |
|------|--------|-------|
| `CompanySettingPage` | `role === 'R00'` | `hasPermission('admin.settings')` |
| `ManajemenPeranPage` | `role === 'R00'` | `hasPermission('admin.settings')` |
| `WorkflowDetailPage` | `role === 'R00'` | `hasPermission('workflow.view')` |
| `TeknisiDashboard` | `role === 'R02'` | (kept — specific to technician role) |

### 4. DeptAccessService NAV_ACCESS Rewrite (19→47 entries)
Added missing keys: `sdm-absensi`, `sdm-cuti`, `sdm-kinerja`, `sdm-struktur`, `workflow-detail`, all dashboard/RAB tools keys, expanded role arrays to match config.

### 5. Config Permissions for R01/R03
Added `sdm.absensi`, `sdm.cuti`, `sdm.kinerja` to R01 and R03 in `role_map`.

## Result: Menu Count by Role

| Role | Before | After | Delta |
|------|--------|-------|-------|
| R01 Admin Proyek | 40 | 48 | +8 (Faktur, SDM, Petty Cash, etc.) |
| R02 Teknisi | 21 | 9 | -12 (over-granted cleaned) |
| R03 Supervisor | 30 | 17 | -13 (cleaned) + SDM |
| R04 Gudang | 18 | 12 | -6 (cleaned) |
| R05 Keuangan | 13 | 24 | +11 (Aset, Penawaran, RAB) |
| R06 Manajer | 45 | 29 | -16 (cleaned) |
| **R07 HRD** | **2** | **7** | **+5 (SDM pages work!)** |

## Prevention Checklist

- [ ] Every new Filament Page/Resource: use `hasPermission('kode')`, never `in_array(role)`
- [ ] Every new menu module: add to BOTH `config/permissions.php role_map` AND `DeptAccessService NAV_ACCESS`
- [ ] Before releasing: run audit script above to catch drift
- [ ] Config permission kodes must match exactly (no prefix unless explicitly in config)
- [ ] SDM/HRD modules: ensure Pages have `canAccess()` with `hasPermission()`, not `return false`