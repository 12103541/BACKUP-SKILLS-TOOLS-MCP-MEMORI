# ERP Audit Example — PT EXFERIA PUTRA INOVASI

Session date: 2026-07-28
Stack: Laravel 11 + Filament v3 + MySQL 8
Project size: 13,856 PHP files

## Execution Summary

1. **Audited** app/Models, app/Services, app/Filament/Resources, app/Filament/Traits, config/
2. **Found** 5 issues (2 critical, 1 high, 1 medium, 1 low)
3. **Fixed** 4 issues; 1 noted as dead code

## Findings by Layer

### Layer 1 — Models
**File:** `app/Traits/ValidatesProgres.php`
**Severity:** CRITICAL
**Bug:** `throw new \ValidationException($validator)` — namespace `\ValidationException` does not exist in PHP/Laravel
**Fix:** `throw new \Illuminate\Validation\ValidationException($validator)`
**Verification:** `php -l app/Traits/ValidatesProgres.php` — syntax OK

### Layer 2 — Services
**File:** `app/Services/BackupService.php`
**Severity:** MEDIUM
**Bug:** `findMysqldump()` hardcodes `"C:/laragon/bin/mysql"` — breaks on Linux deployment
**Fix:** Added priority: config('backup.mysqldump_path') → auto-scan → fallback to PATH

### Layer 3 — Filament Resources
**File:** `app/Filament/Resources/FakturResource.php`
**Two issues:**

**Issue A** — Severity: CRITICAL
- PPN auto-fill used `round($termin->nilai * 0.11)` and label `'PPN (11%)'`
- Model `Faktur::hitungTotal()` used `config('pajak.tarif_ppn_keluaran', 12)`
- **Fix:** Replaced hardcoded 0.11 with config-driven calculation; made label dynamic

**Issue B** — Severity: HIGH
- `canAccess()` used `in_array(Auth::user()->role, ['R00', 'R01', 'R05', 'R06'])`
- Bypasses permission system (`User::hasPermission()`)
- **Fix:** Changed to `auth()->user()?->hasPermission('faktur.view')`
- Also removed unused `Auth` facade import, replaced `Auth::id()` with `auth()->id()`

### Layer 4 — Traits
**File:** `app/Filament/Traits/HasDeptAccess.php`
**Severity:** LOW
**Bug:** `checkDeptAccess()` is `return true;` — used by 33 resources/pages but does nothing
**Action:** Noted but not fixed (too broad a change)

### Layer 5 — Config
**File:** `config/pajak.php`
**Status:** OK — `'tarif_ppn_keluaran' => (float) env('TARIF_PPN_KELUARAN', 12)` properly config-driven

## Patch Tool Pitfall Encountered

When editing `FakturResource.php`, the `patch` tool doubled backslashes in PHP namespace
imports (`use Illuminate\Database\Eloquent\Builder` → `use Illuminate\\Database\\Eloquent\\Builder`),
causing syntax errors.

**Resolution:** Rewrote the entire file with `write_file()` instead of using `patch`.
Verified with `php -l`.

---

## Extended Session 2026-07-29 — Full Re-Audit + RBAC + Workflow Monitoring

### Models (81 files checked)
| Model | Issue | Severity | Fix |
|-------|-------|----------|-----|
| `ActivityLog` | `$fillable` missing columns for `::create()` | CRITICAL | Use `ActivityLog::log()` static method |
| `Penawaran` | `isExpired()` null crash on `tanggal_penawaran` | CRITICAL | Null guard `??` |
| `Sparepart` | `stok`, `safety_stock`, `reorder_point` missing `integer` cast | HIGH | Added casts |
| `RabKomponen` | `saved`/`deleted` events didn't trigger `hitungTotal()` | HIGH | Event listeners |
| `FakturItem` | `quantity` not cast to integer | MEDIUM | Added cast |
| `Pekerjaan` | `foto_paths` JSON NOT NULL, no default → 1364 error | CRITICAL | Migration `JSON NULL` + model `$attributes['foto_paths']='[]'` |
| `Faktur` | `nomor_faktur` VARCHAR(20) too short | HIGH | Migration to VARCHAR(50) |
| `Kontrak` | `hitungProgresOtomatis()` used `nilai` instead of `nilai_efektif` | CRITICAL | Fixed to `nilai_efektif` |

### Services (21 files checked)
| Service | Issue | Severity | Fix |
|---------|-------|----------|-----|
| `PekerjaanService::prosesSpareparts()` | No `DB::transaction()` | HIGH | Wrapped |
| `WorkflowIndicatorService::calculate()` | Used `$kontrak->nilai` not `nilai_efektif` | CRITICAL | Fixed to `nilai_efectif` + overdue detection |

### Resources/Pages (31 files checked)
- 13 files: `in_array(role)` → `hasPermission()` (earlier session)
- 4 Pages: hardcoded `role === 'R00'` → `hasPermission()` (current session)
- 4 SDM Pages: `return false` → `hasPermission('sdm.*')` (current session)
- `HargaReferensiResource`: permission prefix mismatch (current session)

### Config & Commands
- `config/permissions.php` — added `workflow.view`, mapped `rab.view` to R04, `smart_pricing.view` to R06
- `SendJatuhTempoReminder` — `ActivityLog::create()` → `ActivityLog::log()`
- `DeptAccessService::NAV_ACCESS` — rewritten 19→47 entries to match config role_map

### Test Scripts Created & Verified
| Script | Purpose | Status |
|--------|---------|--------|
| `test_chain.php` | Full Klien→Kontrak→Termin→Pekerjaan→Faktur→Pembayaran→Aset cascade | ✅ All pass |
| `test_jatuh_tempo.php` | Partial payment + final lunas + overdue | ✅ All pass |
| `test_adendum.php` | Contract revision nilai_efektif progress | ✅ Bug found & fixed |

### RBAC Deep Audit (Current Session)
See `references/rbac-audit-20260729.md` and `references/rbac-audit-pitfalls.md`.

**Key findings:**
- Dual access control systems (`hasPermission` config vs `DeptAccessService NAV_ACCESS`) silently disagreed
- 3 critical: permission mismatch, SDM pages disabled, dual-system drift
- 3 high: 4 hardcoded role checks, NotificationsPage no permission, SDM perms exist but hidden
- 19 files modified, 6 role-permissions added via `php artisan permissions:sync`

### Workflow Monitoring Redesign
See `references/workflow-monitoring-redesign.md` and `references/workflow-monitoring-redesign-20260729.md`.

**Changes:**
- Deleted duplicate `WorkflowProyek`/`WorkflowStep`/`WorkflowTahapan` system (15+ files, 0 data)
- `WorkflowIndicatorService` — `nilai_efektif` fix + overdue detection
- `workflow-indicator.blade.php` — professional pipeline: 140px nodes, status badges, animated overdue, gradient progress, legend with counts
- `workflow-monitoring.blade.php` — 6 gradient KPI cards, search+filter, overdue badges

### Audit Commands Reference
```bash
# Dual-system drift check
grep -oP "'\K[^']+(?='\s*=>\s*\['roles')" app/Services/DeptAccessService.php | sort > /tmp/dept.txt
grep -oP "'kode'\s*=>\s*'\K[^']+" config/permissions.php | sort > /tmp/perm.txt
diff /tmp/dept.txt /tmp/perm.txt

# Hardcoded role checks
grep -rn "in_array.*role\|auth()->user()->role" app/Filament/ --include="*.php"

# Permission name cross-reference
grep -rn "hasPermission(" app/Filament/ --include="*.php" | grep -oP "hasPermission\(['\"]\K[^'\"]+"
```

### Verification After Every Fix
```bash
# Syntax check
php -l app/Path/To/File.php

# Config-driven values
grep -r "config('pajak.tarif" app/

# Permission sync
php artisan permissions:sync

# View clear
php artisan view:clear && php artisan config:clear
```