# ERP Audit Batch 5 — Re-Audit + Skenario Jatuh Tempo & Partial Payment

**Date:** 2026-07-29
**Project:** PT EXFERIA PUTRA INOVASI
**Stack:** Laravel 11 + PHP 8.2 + Filament v3.3.54 + MySQL 8.0

## Execution Summary

1. **Full re-audit** 81 models, 21 services, 31 resources, 28 pages, 15 widgets, 12 commands
2. **Verified previous fixes** intact: Kontrak constants, PPN config, CheckFakturJatuhTempo loop, KPI formula, DeptAccess deny
3. **Found 7 bugs** (2 critical, 2 high, 2 medium, 1 enhancement)

## Findings by Layer

### Layer 1 — Models
| File | Severity | Bug | Fix |
|------|----------|-----|-----|
| `Sparepart.php` (casts) | 🟠 HIGH | `stok`, `safety_stock`, `reorder_point` not cast to `integer`. `isStokKritis()` uses `<=` — string comparison "9" ≤ "10" = false | Added `'stok' => 'integer', 'safety_stock' => 'integer', 'reorder_point' => 'integer'` |
| `Penawaran.php:47` | 🟠 HIGH | `$this->tanggal_penawaran->copy()` crashes FatalError when `tanggal_penawaran` is null | Added `if (!$this->tanggal_penawaran || !$this->masa_berlaku) return false;` guard |
| `FakturItem.php` (casts) | 🟡 MEDIUM | `quantity` in `$fillable` but not cast | Added `'quantity' => 'integer'` |

### Layer 2 — Services
| File | Severity | Bug | Fix |
|------|----------|-----|-----|
| `PekerjaanService.php:67` | 🟡 MEDIUM | `prosesSpareparts()` writes to 3 tables without `DB::transaction()`. Partial commit risk if stok decrement fails after create | Wrapped body in `return \DB::transaction(function() use (...))` |

### Layer 3 — Filament Resources & Pages
| File | Severity | Bug | Fix |
|------|----------|-----|-----|
| `PermintaanPembelianResource.php:43` | 🟡 MEDIUM | `canAccess()` used `in_array(role, ['R00','R04'])` bypassing RBAC | Changed to `hasPermission('gudang.view')` |
| **14 custom Pages** | 🟠 HIGH | All `canAccess()` used `in_array(Auth::user()->role, [...])` — bypasses permission DB system. Resources use `hasPermission()` correctly but Pages did not | Changed each to appropriate `hasPermission()` call |

Pages fixed: BackupPage, BatchMaterialMapping, BomGudang, BudgetMonitorDashboard, DashboardAnalisaAi (×2), DashboardSmartPricing, GudangDashboard, LaporanStok, ModernDashboard, NotificationPreferencesPage, SupervisorDashboard (×2), VerifikasiSparepart, WorkflowMonitoringPage.

### Layer 5 — Console Commands (re-fix)

| File | Severity | Bug | Fix |
|------|----------|-----|-----|
| `SendJatuhTempoReminder.php` | 🔴 CRITICAL | Batch 3 fix corrected class name `Activity`→`ActivityLog` but `::create()` still passed `caused_by`, `properties`, `log_name` — none in `$fillable`. Silent discard = data lost | Replaced both `::create()` calls with `ActivityLog::log()` static method (which already exists and uses correct columns) |

### Layer 5bis — Model boot events
| File | Severity | Bug | Fix |
|------|----------|-----|-----|
| `RabKomponen.php` | 🟠 HIGH | No `boot()` events — CRUD on komponen never recalculates parent Rab total. Only `Rab::boot()::saved()` triggers on Rab itself, not on children | Added `static::saved()` and `static::deleted()` → `$k->rab?->hitungTotal()` |

## Schema Fixes (migration)

| Table | Column | Before | After | Reason |
|-------|--------|--------|-------|--------|
| `pekerjaan` | `foto_paths`, `dokumentasi_steps`, `dokumentasi_keterangan` | `JSON NOT NULL` | `JSON NULL` | `$fillable` not required, no default → error 1364 |
| `faktur` | `nomor_faktur` | `VARCHAR(20)` | `VARCHAR(50)` | Generated format `FAK-TEST/20260729/...` truncated |

Model-level fix: `Pekerjaan.php` added `$attributes = ['foto_paths' => '[]', 'dokumentasi_steps' => '[]', 'dokumentasi_keterangan' => '[]']` so new records get empty JSON arrays by default even if column is not in create array.

## Workflow Chain Simulation Verified

Ran end-to-end test: Klien → Kontrak → Termin → Pekerjaan (draft→approved) → Faktur (draft→terbit) → Pembayaran (partial→lunas) → Termin sync → Kontrak complete → Aset auto-create.

**All cascade events verified:**
1. Pekerjaan approved → `$kontrak->hitungProgresOtomatis()` → fisik 100%
2. Faktur terbit → termin status `tertagih`, PPN Keluaran tercatat di `pajak` table
3. Pembayaran created → `Pembayaran::updateFakturStatus()` → faktur `lunas`
4. Faktur lunas → `Faktur::boot()::updated()` → termin `lunas` → `kontrak->hitungProgresOtomatis()`
5. Keuangan 100% + fisik 100% → `isCompleted()` → `complete()` → `buatAsetDariPekerjaan()`

**Partial payment scenario validated:**
- Bayar 50% → faktur tetap `jatuh_tempo` (total < tagihan, tidak direvert)
- Bayar sisanya → faktur `lunas`, termin `lunas`, kontrak progress update

**Jatuh tempo scenario validated:**
- `faktur:check-jatuh-tempo` cron detects overdue `terbit` → updates to `jatuh_tempo`
- Cascade: termin status → `terlambat` (via `updateQuietly` in Faktur boot)
- Notifikasi sent to role R05 (Keuangan)

## Permission Config Updates

Added `workflow.view` permission for WorkflowMonitoringPage. Added `rab.view` to R04 (Staff Gudang) role_map — needed for GudangDashboard. Added `smart_pricing.view` to R06 (Manajer) role_map. Ran `php artisan permissions:sync` — 3 new permissions + 18 role-permission mappings created.

## Key Takeaways for Future Audits

1. **Class name fix ≠ working fix** — after `Activity`→`ActivityLog`, re-check every `::create()` key against `$fillable`. The original author may have copied columns from a completely different model's schema.
2. **Pages bypass Resources** — Resources may all use `hasPermission()`, but custom Pages (`Filament\Pages\Page`) often use hardcoded `in_array(role, [...])`. Always audit Pages separately from Resources.
3. **JSON columns without default** — if `$fillable` doesn't include a JSON column and the migration has `NOT NULL`, create without that key fails. Fix: `$attributes` default OR migration `->nullable()`.
4. **Integer casts on inventory fields** — MySQL returns integers as strings without cast. `<=` comparison breaks. Always verify `stok`, `safety_stock`, `reorder_point`, `quantity` etc. are `'integer'` cast.
5. **Model boot events on children** — if parent aggregate (e.g. Rab.total) depends on child records (RabKomponen), the child model needs boot events too, not just the parent.
