# ERP Audit Batch 3 — PT EXFERIA PUTRA INOVASI

Session date: 2026-07-29
Stack: Laravel 11 + Filament v3 + MySQL 8
Project size: 13,856 PHP files

## Execution Summary

1. **Audited** Traits, Services, Widgets, Controllers, Console Commands, Dashboard Pages
2. **Found** 7 issues (3 critical, 3 high, 1 medium)
3. **Fixed** all 7

## Findings by Layer

### Layer 3.5 — Traits (shared behavior)

**File:** `app/Traits/GeneratesCodeNumber.php`
**Severity:** CRITICAL
**Bug:** `$existsQuery` built ONCE outside `while` loop — when `$nextNum` incremented inside loop, the query variable still referenced the old `$nomor` value, causing `->exists()` to always return true → **infinite loop**, crashing the process.
**Fix:** Moved query construction inside the `while` loop so each iteration builds a fresh query with the current `$nomor`:

```php
// BEFORE — BUG
$existsQuery = $modelClass::where($field, $nomor);
while ($existsQuery->exists()) {
    $nextNum++;
    $nomor = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

// AFTER — FIXED
while (true) {
    $nomor = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    $exists = $modelClass::where($field, $nomor)->exists();
    if (!$exists) break;
    $nextNum++;
}
```

**Pattern found in:** Nomor RAB, nomor faktur, nomor pembayaran — any sequential code generation.

### Layer 5 — Console Commands

**File:** `app/Console/Commands/SendJatuhTempoReminder.php`
**Severity:** CRITICAL
**Bug:** `\App\Models\Activity::create([...])` — class `Activity` does not exist. The correct model is `ActivityLog`. Two call sites (sendReminder + sendTerminReminder).
**Fix:** Replaced with `\App\Models\ActivityLog::create(...)`.
**Note:** The `caused_by` and `properties` columns used in the create call also don't match `ActivityLog`'s `$fillable` (`user_id`, `deskripsi`, `data_lama`, `data_baru`) — so even with the class fix, the create may partially fail or store empty data. See remaining work below.
**Dampak:** Cron job `faktur:reminder-jatuh-tempo` crashes silently on every scheduled run.

### Layer 6 — Filament Widgets

**File:** `app/Filament/Widgets/DivisionDashboardWidget.php`
**Severity:** CRITICAL + HIGH (two bugs)

**Bug A — Syntax error:**
```php
->whereColumn('stok', '<= ')  // ← missing second column argument
```
PHP parse error. Fix:
```php
->whereColumn('stok', '<=', 'minimum_stok')
```

**Bug B — Wrong column name:**
```php
Pengeluaran::selectRaw("DATE_FORMAT(tanggal, '%Y-%m') as bulan")
```
Column `tanggal` does not exist in `pengeluaran` table. Actual column: `tanggal_pengeluaran`. Compare with `ModernDashboard.php` (line 90) which uses `tanggal_pengeluaran` correctly — inconsistency between dashboards.
**Dampak:** Pengeluaran line chart always returns zero/empty data.

### Layer 4 — Controllers (Web)

**File:** `app/Http/Controllers/Web/SdmController.php`
**Severity:** HIGH
**Bug — KPI formula inversion:**
```php
$onTimeRate = ($approved + $lateApproved) > 0
    ? round(($approved / ($approved + $lateApproved)) * 100, 1)
    : 100;
```
`$lateApproved` is a SUBSET of `$approved` (both count from the same `approved_at`-filtered query, with `lateApproved` adding a `DATEDIFF > 3` condition). Formula treats them as independent sets.
- Example: 10 approved, 3 late → current: 10/(10+3) = 76.9%  ✗
- Expected: (10-3)/10 = 70%  ✓
**Fix:**
```php
$onTimeRate = ($approved + $lateApproved) > 0
    ? round((($approved - $lateApproved) / max(1, $approved)) * 100, 1)
    : 100;
```

### Layer 3 — Filament Resources/Pages (Controllers too)

**File:** `app/Http/Controllers/Web/ProjectCommandCenterController.php`
**Severity:** HIGH
**Bug:** `Faktur::where('status', 'terkirim')` — status `'terkirim'` does not exist in Faktur model. Valid statuses: `draft`, `terbit`, `lunas`, `jatuh_tempo`.
**Fix:** Changed to `Faktur::where('status', 'terbit')`.
**Dampak:** Outstanding invoice total in Command Center always shows Rp 0.

### Layer 5 — Services (Security)

**File:** `app/Services/DeptAccessService.php`
**Severity:** MEDIUM
**Bug:** `if (!$access) return true;` — any navigation slug not mapped in `NAV_ACCESS` array is visible to ALL roles by default.
**Fix:** Changed to `return false` — deny by default.

## Remaining Work (noted, not fixed)

**SendJatuhTempoReminder still broken after class name fix:**
The `ActivityLog::create()` call passes: `caused_by`, `properties` (JSON), `log_name`.
But `ActivityLog::$fillable = ['user_id', 'user_name', 'user_role', 'action', 'modul', 'subject_type', 'subject_id', 'deskripsi', 'data_lama', 'data_baru', 'ip_address', 'user_agent']`.
Columns `caused_by`, `properties`, `log_name` do not match `$fillable` → those fields will be silently discarded by mass-assignment protection.
Needs either:
- Rewrite to use `ActivityLog::log()` static method instead of `::create()`
- Or restructure data to match `$fillable` + add `user_id`, `deskripsi`, `data_lama`/`data_baru`

## Key Lesson for Future Audits

When auditing commands that use `Model::create(...)` or `Model::update(...)` directly (not through a dedicated service or static method), always **cross-reference the `$fillable` array** of the target model. Silent mass-assignment failures are invisible at runtime.
