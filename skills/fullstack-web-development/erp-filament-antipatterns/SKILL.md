---
name: erp-filament-antipatterns
category: fullstack-web-development
description: Use when Filament fields or badges break. ERP audit bugs.
tags: [filament, erp, antipatterns, debugging, laravel]
---

# ERP Filament Anti-Patterns

Collection of real bugs found auditing the ERP codebase. Each entry: symptom → root cause → fix.

## Rule of Thumb for All Progress/Percent Calculations

**Any progress calculation using kontrak `nilai` is WRONG if adendum exists.** Always use `nilai_efektif` (=$nilai + sum of adendum values) as denominator.

```php
// ❌ BROKEN — ignores adendum, overstates progress
$progress = round(($paid / $kontrak->nilai) * 100);

// ✅ FIXED — includes adendum value
$total = $kontrak->nilai_efektif ?: $kontrak->nilai ?: 1;
$progress = min(100, round(($paid / $total) * 100));
```

Check every spot that computes percentage of kontrak value:
- `Kontrak::hitungProgresOtomatis()` — ✅ fixed (line 173)
- `WorkflowIndicatorService::getStages()` — ✅ fixed (line 44)
- Any KPI/report widget — ⚠️ verify on sight

## 1. Form `->visible(fn)` Destroys Field State

**Symptom:** Form fields return null after a page action, even though user filled them in.

**Root cause:** Filament + Livewire: when `->visible(fn () => !$this->someData)` evaluates to `false`, Livewire strips the field from its schema. On next request (`wire:click`, form submit), the field is absent → `null` returned.

**Affected:** RAB Import page (`ImportRab.php`). Section 2a (nomor_rab, nama_proyek) and 2b (rab_id) hidden after preview generates data → user clicks Import → fields null → "Isi Nomor RAB dan Nama Proyek".

**Fix:** Keep sections always visible. Control presence via `import_mode` only, not `previewData`:
```php
// ❌ BROKEN
->visible(fn () => $this->import_mode === 'new' && !$this->previewData)

// ✅ FIXED
->visible(fn () => $this->import_mode === 'new')
```

## 2. BadgeColumn::colors() Value Mismatch

**Symptom:** Badge/color column in Filament table shows no color (gray default).

**Root cause:** `BadgeColumn::make('status')->colors([...])` — the array VALUE must match the EXACT database value. Array key = Tailwind color class, value = DB string.

```php
// ❌ BROKEN — DB stores 'belum_tertagih', key is 'pending'
'warning' => 'pending',
'info' => 'billed',
'success' => 'paid',

// ✅ FIXED — match DB values exactly
'warning' => 'belum_tertagih',
'info' => 'tertagih',
'success' => 'lunas',
'danger' => 'terlambat',
```

## 3. Batch `where()->update()` Skips Eloquent Events

**Symptom:** Related records don't sync after bulk status update (e.g., termin stays 'tertagih' after faktur auto-marked 'jatuh_tempo').

**Root cause:** `Model::where('x', 'y')->update([...])` runs raw SQL. Does NOT fire `saved`/`updated` events. Model event listeners never execute.

```php
// ❌ BROKEN — no events fired
Faktur::where('status', 'terbit')
    ->update(['status' => 'jatuh_tempo']);

// ✅ FIXED — loop triggers events per record
Faktur::where('status', 'terbit')->get()
    ->each->update(['status' => 'jatuh_tempo']);
```

## 4. Duplicate Sync: RelationManager + Model Event

**Symptom:** Business logic runs twice per action (e.g., `hitungProgresOtomatis()` fires 2x per pembayaran).

**Root cause:** Both RelationManager `->after()` and Model `boot::saved()` do same sync logic.

**Fix:** Keep core logic in Model events. RelationManager handles UI only.

```php
// ✅ FIXED — RelationManager trusts model events
->after(function () {
    // UI only: sync by Pembayaran::boot::saved()
})
```

## 5. Model Constants Referenced But Undefined

**Symptom:** Fatal error on specific code path: PHP Fatal Error: Undefined class constant.

**Root cause:** Code references `self::STATUS_COMPLETED` in `Kontrak.php` but no `const` defined.

**Fix:** Define all constants at top of referenced model class.

## 6. PPN Hardcoded vs Config

**Symptom:** Form display shows PPN 12% but saved value is 11%.

**Fix:** Read rate from config (default 11 — UU HPP, keputusan user 2026-07-31; JANGAN fallback 12):
```php
$ppnRate = (float) config('pajak.tarif_ppn_keluaran', 11);
$data['ppn'] = round($itemsTotal * ($ppnRate / 100));
```

## 7. Controller KPI/Percent Formula Wrong

**Symptom:** KPI metric shows inflated or impossible values (e.g., on-time approval rate > 100%).

**Root cause:** Formula logic doesn't account for subset relationships. E.g., `$lateApproved` is a subset of `$approved`, not a separate group.

```php
// ❌ BROKEN — $lateApproved counted as separate from $approved total
$approved / ($approved + $lateApproved) * 100

// ✅ FIXED — $lateApproved is subset of $approved
(($approved - $lateApproved) / max(1, $approved)) * 100
```

**Checklist for any KPI/percent calculation:**
1. Are the numerator and denominator from same population?
2. Is one value a subset of the other?
3. Is `max(1, $denominator)` used to prevent division by zero?

## 8. RAB Import: Upload File Form Persistence

**Symptom:** File upload resets when navigating steps (back/forward), or file path lost after preview.

**Root cause:** Filament file upload stores temporary path in Livewire component state. If component re-renders from scratch (form fill, redirect), path is lost.

**Fix:** Store uploaded file path in component property (`$this->uploadedFilePath`) — NOT only in form state. The form's `file` field is for upload widget display, while component property holds the actual path for import processing.

```php
// Component property stores actual path for processing
public ?string $uploadedFilePath = null;

// In upload handler:
$this->uploadedFilePath = $file->store('rab-imports', 'public');

// In preview/import, use component property:
$service = new RabImportService(Storage::disk('public')->path($this->uploadedFilePath));
```

## 9. Progress Calculation Uses `nilai` Not `nilai_efektif`

**Symptom:** Progress percentage higher than reality when kontrak has adendum. E.g., kontrak Rp100jt + adendum Rp20jt, payment received Rp67.2jt → shows 67.2% instead of correct 56.0%.

**Root cause:** `$nilai` = kontrak original value only. `$nilai_efektif` = `$nilai + sum(adendum->nilai)`. Any formula dividing by `$kontrak->nilai` excludes adendum scope, inflating progress.

**Affected spots (all now fixed):**
- `Kontrak::hitungProgresOtomatis()` — denominator changed to `$this->nilai_efektif`
- `WorkflowIndicatorService::getStages()` — denominator changed to `$kontrak->nilai_efektif ?: $kontrak->nilai`

**Fix checklist (apply to any new progress calculation):**
1. Is `nilai_efektif` used as denominator? If `->nilai` alone → BUG
2. Is `max(1, $denominator)` or `?: 1` fallback present? If raw division → BUG
3. Is `min(100, ...)` wrapping result? If not → can exceed 100%

```php
// ✅ SAFE TEMPLATE
$total = match (true) {
    (float) $kontrak->nilai_efektif > 0 => (float) $kontrak->nilai_efektif,
    (float) $kontrak->nilai > 0         => (float) $kontrak->nilai,
    default                             => 1,
};
$progress = min(100, round(($paid / $total) * 100));
```

## 10. Permission Name Mismatch Between canAccess() and Config

**Symptom:** User has permission in `config/permissions.php` role_map but canAccess() returns false. Resource visible only to R00 (bypass).

**Root cause:** String passed to `hasPermission('...')` does not match any `kode` in `permissions` DB table, or has a wrong prefix. Only R00 bypass covers the gap.

```php
// ❌ BROKEN — 'penawaran.smart_pricing.view' not in any role_map
hasPermission('penawaran.smart_pricing.view')

// ✅ FIXED — matches 'smart_pricing.view' in config for R05,R06
hasPermission('smart_pricing.view')
```

**Debug:** `select * from permissions where kode like '%smart_pricing%'` to see exact kode.

## 11. Hardcoded Role Check Bypasses Permission System

**Symptom:** Page visible/filtered by hardcoded role string, ignoring user_permissions overrides and role_map changes. Adding a new role (like R07) does not grant access.

**Root cause:** `Auth::user()->role === 'R00'` instead of `hasPermission('kode')`.

```php
// ❌ BROKEN — hardcoded role
return Auth::user()->role === 'R00';

// ✅ FIXED — uses permission system (R00 bypass built-in)
return auth()->user()?->hasPermission('admin.settings') ?? false;
```

**Affected (all fixed):**
| Page | Was | Now |
|------|-----|-----|
| `CompanySettingPage` | `role === 'R00'` | `hasPermission('admin.settings')` |
| `ManajemenPeranPage` | `role === 'R00'` | `hasPermission('admin.settings')` |
| `WorkflowDetailPage` | `role === 'R00'` | `hasPermission('workflow.view')` |
| `TeknisiDashboard` | `role === 'R02'` | OK for role-specific dashboard |

## 12. SDM Page `return false` Dead-Locks HRD Role

**Symptom:** HRD (R07) has `sdm.absensi`, `sdm.cuti`, `sdm.kinerja` permissions in config but sees no SDM menu items.

**Root cause:** Pages had `canAccess(): bool { return false; }` — explicitly disabled irrespective of permissions.

**Affected (all fixed):**
| Page | Was | Now |
|------|-----|-----|
| `SdmAbsensiPage` | `return false` | `hasPermission('sdm.absensi')` |
| `SdmCutiPage` | `return false` | `hasPermission('sdm.cuti')` |
| `SdmKinerjaPage` | `return false` | `hasPermission('sdm.kinerja')` |
| `SdmStrukturPage` | `return false` | `hasPermission('sdm.karyawan')` |

**Rule:** Never `return false` in `canAccess()` for a page with a valid permission. Only use `return false` for truly disabled pages (like ActivityLog hidden from non-admin).

## 13. DeptAccessService NAV_ACCESS Diverges From role_map

**Symptom:** User's role_map says they can access a module, but DeptAccessService blocks it. Two parallel access systems disagree.

**Root cause:** `DeptAccessService::NAV_ACCESS` has its own hardcoded role arrays per module slug. When config/permissions.php role_map changes, NAV_ACCESS must be manually updated — no sync mechanism exists.

**Key divergences (fixed July 2026):**

| Module Slug | Before NAV_ACCESS | After NAV_ACCESS | Why |
|-------------|-------------------|------------------|-----|
| `faktur` | R00,R03,R05,R06 | +R01 | Admin Proyek sees faktur |
| `pekerjaan` | R00,R01,R02,R03,R05,R06 | -R05 | Keuangan no pek. perms |
| `sparepart` | R00,R04,R06 | +R01,R02,R03 | Teknisi needs sparepart view |
| `pengajuan-sparepart` | R00,R02,R03,R04 | +R01,R06 | Requesters added |
| `departemen` | R00,R06 | +R01,R07 | HRD + Admin Proyek |
| `karyawan` | R00,R01,R03,R06 | +R07 | HRD needs employee mgmt |
| `permission/role/user-resource` | R00 only | +R06 | Manager can manage users |
| `petty-cash` | R00,R05,R06 | +R01 | Admin Proyek needs topup |
| `workflow-proyek` | R00,R01,R03,R06 | +R04,R05 | Gudang+Keuangan see workflow |

**Every new module needs BOTH a `role_map` entry in config/permissions.php AND a NAV_ACCESS entry.** There is no single source of truth — always update both.

## 14. Overdue Detection Missing From Workflow Pipeline

**Symptom:** Workflow monitoring shows all active kontrak with same icon/color regardless of whether they've passed `tgl_akhir`. Cannot distinguish on-track from late projects.

**Pattern:**
```php
$isOverdue = $kontrak->tgl_akhir
    && $kontrak->tgl_akhir < now()
    && !in_array($kontrak->status, ['completed', 'dibatalkan']);
```

Pass `overdue` flag through stage data. Overdue stage renders: red circle, warning icon, pulsing ring, red border on card. Prevent false positives: don't flag completed/cancelled.

## 15. toArray() Strips Carbon → Blade format() Fails on String

**Symptom:** `Call to a member function format() on string` on a Blade template that previously worked.

**Root cause:** `->toArray()` on an Eloquent collection converts `Carbon` date fields to ISO strings. If the Blade template then calls `$record['created_at']->format('H:i')`, it fails because `$record['created_at']` is now a string, not a Carbon instance.

**Affected:** `gudang-dashboard.blade.php` — `getTransaksiHariIni()` returns `->toArray()`, blade at line 536 calls `->format()` on the string.

```php
// ❌ BROKEN — $tk['created_at'] is a string after toArray()
{{ $tk['created_at']->format('H:i') }}

// ✅ FIXED — re-parse as Carbon before formatting
{{ \Carbon\Carbon::parse($tk['created_at'])->format('H:i') }}
```

**Pattern:**
When a Filament Page method returns `->toArray()` and the Blade template accesses date fields, either:
1. Use `Carbon::parse()` in the template (quick fix), OR
2. Return the collection without `->toArray()` and access `$record->created_at` instead of `$record['created_at']`, OR
3. Cast dates back to Carbon in the array return.

**Detection:** Search Blade templates for `->format(` on array access patterns `$var['field']->format(` — these are at risk when the source data comes from `->toArray()`.

## 16. Repeater `orderColumn` Causes SQL Error When Column Missing

**Symptom:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'urutan' in 'order clause'` when opening a form with a Repeater.

**Root cause:** `Repeater::make('items')->orderColumn('urutan')` generates `ORDER BY urutan ASC` in the SQL query. If the child table doesn't have that column, MySQL throws 42S22.

```php
// ❌ BROKEN — 'urutan' column doesn't exist in penawaran_items table
Forms\Components\Repeater::make('items')
    ->relationship('items')
    ->orderColumn('urutan')

// ✅ FIXED — omit orderColumn when no sort column exists
Forms\Components\Repeater::make('items')
    ->relationship('items')
```

**Prevention:** Before adding `->orderColumn('col')`, verify the column exists:
```sql
DESCRIBE penawaran_items;
```
Either add the column via migration or drop `->orderColumn()` + `->reorderable()`.

## 17. `Select::badge()` Doesn't Exist in Filament Forms

**Symptom:** `BadMethodCallException: Method Filament\Forms\Components\Select::badge does not exist.`

**Root cause:** `->badge()` and `->color(fn)`) are **Table Column** methods only — `TextColumn::make()->badge()`. They do not exist on `Forms\Components\Select`.

```php
// ❌ BROKEN — badge() is a table column method, not a form field
Forms\Components\Select::make('status')
    ->options([...])
    ->badge()
    ->color(fn () => match(...))

// ✅ FIXED — in forms, just use options + default
Forms\Components\Select::make('status')
    ->options([...])
    ->default('draft')
    ->native(false)
    ->required()

// ✅ badge() belongs in the table definition
Tables\Columns\TextColumn::make('status')
    ->badge()
    ->color(fn (string $state): string => match ($state) { ... })
```

**Rule:** `badge()` + `color(fn)` only work on `TextColumn` in table definitions. In forms, use `Select` with `->options()`.

## 18. Navigation Group Duplicate Entries & Missing Icons

**Symptom:** Sidebar has groups without icons, same-label pages in different groups (e.g., "Keuangan" under both `💰 Keuangan` and `Pengaturan`), or duplicate menu entries for the same module (e.g., "Harga Referensi" appearing twice in `📋 Pipeline`).

**Root cause:** Multiple sources register in Filament sidebar independently:
- Pages (`extends Page`) register via `navigationGroup` + `navigationLabel`
- Resources register via same properties
- Sub-pages under a Resource (e.g., `HargaReferensiDashboard`) can also register as standalone nav items if `shouldRegisterNavigation` is not `false`
- Groups without emoji icons look inconsistent

### Fix Patterns

**A. Add icon to a navigation group:**
```php
// ❌ Was — no icon
protected static ?string $navigationGroup = 'Pengaturan';

// ✅ Fixed — emoji prefix
protected static ?string $navigationGroup = '⚙️ Pengaturan';
```
Prepend group name with a relevant emoji. The EXACT same string including emoji must be used across ALL pages/resources in that group. A mismatch creates TWO SEPARATE GROUPS in the sidebar.

**B. Hide a Resource sub-page from sidebar (deduplicate):**
```php
// ✅ Add to Page that duplicates its parent Resource
protected static bool $shouldRegisterNavigation = false;
```
Page stays accessible via Resource routes but its duplicate sidebar entry disappears.

**C. Hard-disable a page entirely:**
```php
public static function canAccess(): bool
{
    return false; // Hides from sidebar + blocks URL
}
```
Use for pages that should not exist in the sidebar for any role (including R00 bypass). For role-based access, use `hasPermission()` instead.

Contrast with antipattern #12: SDM pages had `return false` when they SHOULD have had `hasPermission()`. This pattern (`return false`) is correct only when you genuinely want NO ONE to access the page.

**D. Rename ambiguous labels:**
```php
// ❌ Was — "Keuangan" in ⚙️ Pengaturan is identical to group 💰 Keuangan
protected static ?string $navigationLabel = 'Keuangan';

// ✅ Fixed — specific label
protected static ?string $navigationLabel = 'Preferensi Keuangan';
```
Never use a page label that matches a different group name.

**E. Compact single-item groups:**
```php
// ❌ Was — "📋 Proyek & Operasional" with only 1 page inside
protected static ?string $navigationGroup = '📋 Proyek & Operasional';

// ✅ Fixed — concise, describes the page
protected static ?string $navigationGroup = '📋 Monitoring';
```
If a group has only one page, name it for the page's function, not an umbrella concept.

### Real-World Application (July 2026 — PT EXFERIA PUTRA INOVASI)

Changes applied across 15 files:

| Group | Before | After |
|-------|--------|-------|
| `Dashboard Manajer` | no icon | `📊 Manajerial` |
| `Pengaturan` | no icon | `⚙️ Pengaturan` |
| `Dokumen` | no icon | `📄 Dokumen` |
| `📋 Proyek & Operasional` | 1 page, too broad | `📋 Monitoring` |
| Label "Keuangan" in Pengaturan | identical to group `💰 Keuangan` | `Preferensi Keuangan` |
| Label "Dokumen & Notifikasi" | too long for one page | `Template & Notifikasi` |
| HargaReferensiDashboard | duplicate entry | `shouldRegisterNavigation = false` |

### Verification
```bash
php artisan view:clear
php artisan config:clear
php artisan filament:clear-cache
```
Check:
1. All groups have emoji icons
2. No duplicate nav entries for the same module
3. No identical labels in different groups
4. Disabled pages don't appear for any role

**F. Full navbar reorganization (13 grup → 9 grup divisi, 2026-08-01):**

Langkah yang terbukti untuk reorganisasi massal (52 file, 1 sesi):

1. **Petakan dulu** — `grep -rn "navigationGroup\|navigationSort\|navigationLabel" app/Filament/` + cek `getNavigationItems()` override (custom NavigationItem) + sub-pages (`Resources/*/Pages/*.php`). Jangan lupa `Pages/Settings/*.php` dan Resource sub-pages.
2. **Kunci urutan grup** di `AdminPanelProvider`:
```php
->navigationGroups([
    NavigationGroup::make('🏠 Beranda'),
    NavigationGroup::make('🏗️ Proyek & Penawaran'),
    // ... urutan sidebar mengikuti array ini
])
```
3. **Edit massal via execute_code Python** (regex per file, 2 substitusi: group + sort), bukan 52x patch manual:
```python
src = re.sub(r"^(\s*)protected static \?string \$navigationGroup = '[^']*';",
             lambda m: m.group(1) + f"protected static ?string $navigationGroup = '{group}';",
             src, count=1, flags=re.M)
src = re.sub(r"^(\s*)protected static \?int \$navigationSort = \d+;",
             lambda m: m.group(1) + f"protected static ?int $navigationSort = {sort};",
             src2, count=1, flags=re.M)
```
4. **Custom NavigationItem** (`getNavigationItems()`): set `->group('...')->sort(n)` — group string HARUS sama persis dengan grup lain.
5. **Verifikasi konsistensi:** `grep -rn "navigationGroup" app/Filament/ | grep -oP "'[^']*'" | sort -u` → hasil harus TEPAT = 9 grup target. Satu string beda = grup ganda di sidebar.
6. `php artisan optimize:clear` + `php -l` semua file + test browser sidebar.

**JANGAN tertipu angka di samping label menu** (mis. "Dashboard Gudang 2", "Mapping BOM 4"): itu `navigationBadge` (jumlah item butuh aksi — sparepart kritis, item belum di-map), BUKAN label rusak. Cek `browser_console` dengan selector `a[href*="..."]` — textContent label terpisah dari badge. Jangan "perbaiki" label yang sebenarnya badge.

**Konsep grup (alur divisi, bukan fitur):** urutkan sesuai alur kerja — Beranda → Proyek & Penawaran (Klien→Kontrak→RAB→Penawaran→Pekerjaan→Jadwal→Laporan→Aset→Harga Ref) → Gudang & Material → Keuangan → SDM → Dokumen → Monitoring & Analisa → User & Akses → Pengaturan. Halaman `canAccess()` role-based (Workflow Monitoring, Budget Monitor, Audit Trail) tetap disembunyikan per role — jangan dipaksa tampil.

## 19. `CompanySetting::set()` Uses `update()` Not `updateOrCreate()`

**Symptom:** User fills settings form → clicks "Simpan" → no error → page reloads → form fields are empty / settings not saved. No exception logged.

**Root cause:** `CompanySetting::set()` does `self::where('key', $key)->update([...])` which calls Eloquent's `Builder::update()`. If no record with that key exists, `WHERE` matches 0 rows → `update()` is a no-op → 0 rows affected → no error → setting silently discarded.

```php
// ❌ BROKEN — update() on 0 rows = silent no-op
public static function set(string $key, $value): void
{
    self::where('key', $key)->update(['value' => $value]);
    // ^^ if key doesn't exist, does nothing, no error
}
```php
// ✅ FIXED (2026-07-31) — firstOrNew: update cuma value, create isi label+group
public static function set(string $key, $value, ?string $label = null, string $group = 'umum'): void
{
    if (in_array($key, self::SENSITIVE_KEYS, true)) {
        $value = EncryptionService::encrypt($value);
    }
    $setting = static::firstOrNew(['key' => $key]);
    $setting->value = $value;
    if (!$setting->exists) {
        $setting->label = $label ?: ucwords(str_replace('_', ' ', $key));
        $setting->group = $group;
    }
    $setting->save();
}
```

**Pitfall lanjutan:** `updateOrCreate(['key'=>$key], ['value'=>$v])` TANPA label/group = setiap update menimpa group → 'umum' (row settings hilang dari filter group halaman). firstOrNew + set label/group hanya saat create adalah pola aman. SENSITIVE_KEYS = ['gemini_api_key','llm_api_key'] → terenkripsi EncryptionService; `get()` decrypt otomatis.

**Detection:** Search `CompanySetting::set` calls. If the model uses `where()->update()`, fix it. The fix also applies to any key-value store where keys might not pre-exist.

**Related:** The `CompanySetting::get()` method (which does `where('key', $key)->value('value')`) is fine — returning `null` for missing keys is expected. Only `set()` with `update()` has this bug.

## 20. PDF Page Overflow — Excessive CSS Spacing

**Symptom:** PDF with only 1-2 table rows renders as 2+ pages. Large blank space at bottom of page 1, content pushes to page 2.

**Root cause:** Cumulative effect of large margins, padding, and spacing values in CSS:
- `body { margin: 30px }` + `@page { margin: 30px 30px 50px 30px }` = double margin stack
- `.doc-title { margin-bottom: 20px }` under title
- `.signature-section { margin-top: 40px }` before signature block
- `.signature-wrapper .name { margin-top: 60px }` above name
- `line-height: 1.6` = 60% extra per text line
- Base font 12px too large for compact A4 layout

Combined, even 1-table-row content with header uses >70% of usable page.

```css
/* ❌ BEFORE — too much space */
body { margin: 30px; font-size: 12px; line-height: 1.6; }
@page { margin: 30px 30px 50px 30px; }
.doc-title { margin-bottom: 20px; }
.signature-section { margin-top: 40px; }

/* ✅ AFTER — compact, fits content on one page */
body { margin: 0; padding: 5px 15px; font-size: 10px; line-height: 1.35; }
@page { margin: 10mm 10mm 15mm 10mm; }
.doc-title { margin-bottom: 6px; }
.signature-section { margin-top: 6px; }
.signature-wrapper .name { margin-top: 18px; }
```

**Fix checklist for any DomPDF Blade template:**
1. `@page` margins define the outer printable area — keep ≤10mm
2. `body` margin is INSIDE `@page` — use 0 or minimal; don't stack both
3. Font 9-10px is compact and readable for invoices/quotes; 12px+ reserved for titles
4. `line-height` 1.35-1.4 sufficient for A4 readability
5. Signature block: 6-12px margin-top, 18-30px name margin
6. Table cells: 3-5px padding, not 7-8px
7. Grand total should be near table (2-5px gap)

**Rule of thumb:** If a DomPDF template's content fits in 80% of page 1 AND has a fixed footer, any spacing exceeding 20% pushes to page 2. Cut spacing values in half first, then adjust up.

## 21. Terbilang (Number-to-Words) — Five Conversion Bugs

**Symptom:** `Terbilang::convert(100000000)` returns `"Seratus Nol Rupiah Juta Rupiah"` not `"Seratus Juta Rupiah"`.

**Five distinct bugs:**

| # | Bug | Input | Broken Output | Fixed Output |
|---|-----|-------|---------------|--------------|
| 1 | Numbers <12 not guard-raised | 0 | `""` | `"Nol Rupiah"` |
| 2 | 100-199 recursion passed 0 to convert(0) | 100 | `"Seratus "` (trailing) | `"Seratus"` |
| 3 | Unit loop `$segment > 0` on 0 at remainder | — | variant | clean |
| 4 | Atomic `Seribu` built as `"Se" . "Ribu"` → `"SeRibu"` | 1000 | `"SeRibu"` | `"Seribu"` |
| 5 | `$number <= 0` not guarded → empty string | 0 | `""` | `"Nol Rupiah"` |

**Fix pattern:** Top-level `<=0` guard, `<12` early return, atomic `Seribu`, recursive `terbilang()` with clean 100-199 branch.

**Verification:** `Terbilang::convert(1)` → `"Satu Rupiah"`, `(100)` → `"Seratus Rupiah"`, `(1000)` → `"Seribu Rupiah"`, `(100000000)` → `"Seratus Juta Rupiah"`, `(999999999)` → `"Sembilan Ratus..."`.

## 22. Standalone PDF Templates — Duplicated Header/Signature Across Files

**Symptom:** Changing company name, address, or director requires editing 4+ Blade files (penawaran, faktur, rab, bast). Each template independently reads CompanySetting, renders logo, address, signature.

**Root cause:** No shared layout. Each `pdf/*.blade.php` is a self-contained HTML document with identical ~50-line company header + ~30-line signature block + ~10-line footer.

**Fix:** Extract shared layout to `pdf/base.blade.php`. Child templates use `@extends('pdf.base')` and only define content sections (title, info, items table, totals).

```php
// ✅ Child template — ~50-70 lines total
@php
    $docType = 'penawaran';     // prefix for CompanySetting lookups
    $docNumber = $penawaran->nomor_penawaran;
    $grandTotal = (int) $penawaran->total_keseluruhan;
@endphp
@extends('pdf.base', ['docType' => $docType, 'docNumber' => $docNumber])

@section('doc_title', 'SURAT PENAWARAN')
@section('doc_subtitle', 'No. ' . $penawaran->nomor_penawaran)
@section('kepada', $penawaran->nama_klien)
@section('info') <table class="info-table">...</table> @endsection
@section('content')
    <table class="items"><tr>...</tr></table>
    <table class="summary-table"><tr>...</tr></table>
@endsection
@section('terbilang') {{ Terbilang::convert($grandTotal) }} @endsection
```

**Base template (`pdf/base.blade.php`) sections architecture:**

| Section | Content | Child Must Set? |
|---------|---------|-----------------|
| `doc_title` | `<h1>` document title | Yes |
| `doc_subtitle` | Document number subtitle | Optional |
| `kepada` | Recipient name ("Kepada Yth.") | Optional |
| `info` | Metadata table (date, status, dll) | Optional |
| `content` | Items table + totals | Yes |
| `terbilang` | Amount in words | Optional |
| `notes_extra` | Additional notes below default | Optional |

**What base handles automatically:**
- Company header: logo (left) + company name bold centered + address + Telp italic + Email italic (from CompanySetting)
- Horizontal separator (1px solid black)
- Footer (fixed position)
- Notes box (`{docType}_default_notes` from CompanySetting)
- Bank info (except for RAB)
- Signature (name + ttd direktur, from CompanySetting)
- Doc-type toggle: `{docType}_show_company_profile`, `{docType}_show_signature`

**Header format** (dari template surat resmi — lihat `template hader.png`):
```
┌─────────────────┬──────────────────────────────────────────┐
│                 │     PT EXFERIA PUTRA INOVASI              │  bold 14px, center
│    [Logo]       │     Alamat lengkap perusahaan...          │  regular 9px
│                 │     Telp. 0897-8844-184                   │  italic 9px
│                 │     Email: exferialed@gmail.com           │  italic 9px
└─────────────────┴──────────────────────────────────────────┘
──────────────────────────────────────────────────────────────  1px solid black
```
CSS: `.header-table td { vertical-align: middle }`, `.header-logo-cell { width: 150px }`, `.header-info-cell { text-align: center; }`

**Adding a new document type** (e.g. Invoice):
1. Create `resources/views/pdf/invoice.blade.php` — 50-70 lines
2. Create setting keys: `CompanySetting::set('invoice_show_company_profile', 'true')`, etc.
3. Add route: `Route::get('/invoice/{invoice}/pdf', fn(...) => Pdf::loadView('pdf.invoice', ...))`
4. Header, signature, notes, footer auto-inherited

**Prevention:** Any new PDF-based document type MUST extend `pdf.base`. Only create standalone templates (like `bast.blade.php`, `laporan-pekerjaan.blade.php`) when the document format fundamentally differs (dual signature columns, photo grids, GPS data).

## 23. Settings Form With No Data Visibility

**Symptom:** User opens settings page, fills form, clicks "Simpan" — no error. Next visit: fields are empty or different. No way to know what's currently stored.

**Root cause:** Form only shows input fields — no display of existing setting records. Users can't distinguish "setting doesn't exist yet" from "setting failed to save."

**Fix:** Add `$settingRecords` property populated at mount(), plus a summary table in the shared blade view.

```php
// ✅ FIXED — populate $this->data directly (form reads from statePath)
public $settingRecords = [];

public function mount(): void
{
    $this->settingRecords = CompanySetting::where('group', 'profil')->get();
    $values = [];
    foreach ($this->settingRecords as $s) {
        $values[$s->key] = $s->value;
    }
    $this->data = $values;
}
```

**ROOT CAUSE #2 (2026-07-31, terkonfirmasi): missing `implements HasForms`.** `use InteractsWithForms` TANPA `class X extends Page implements HasForms` = bug diam-diam: server state `$this->data` TERISI (log terbukti), tapi DOM render SEMUA field kosong, `getState()` kosong, save() no-op tanpa error. SEMUA settings page (Profil, Keuangan, dll) kena sejak awal. Fix:

```php
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class ProfilPerusahaanPage extends Page implements HasForms
{
    use InteractsWithForms;
```

**Nested form `->fill()` pitfall:** `$this->form->fill(['data' => $values])` + `->statePath('data')` = double-wrap → field baca null. Pakai fill flat: `$this->form->fill($values);`. Di save(), unwrap defensif: `$state = $this->form->getState(); $state = $state['data'] ?? $state;`. (Alternatif tetap valid: set `$this->data = $values` langsung.)

**Save pattern with file fields:**

```php
public function save(): void
{
    // FileUpload returns null when user didn't select a file
    $fileFields = ['company_logo', 'director_signature'];
    foreach ($this->data as $key => $value) {
        // Skip null for file fields — preserve existing stored value
        if (in_array($key, $fileFields) && $value === null) continue;
        CompanySetting::set($key, $value);
    }
    Notification::make()->title('Berhasil disimpan')->success()->send();
}
```

**Why `$this->data` not `$this->form->getState()`:** With `statePath('data')`, the form's Livewire property IS `$this->data`. Filament auto-synchronizes user input to this array. `$this->form->getState()` returns a nested structure `['data' => [...]]` that's harder to iterate. Direct `$this->data` iteration is simpler and guaranteed to reflect latest user input.

**Why file fields need the skip guard:** `FileUpload::make('logo')` → when user does NOT upload a file, the Livewire field value is `null`. If you pass `null` to `CompanySetting::set()`, it overwrites the existing stored file path with null → logo disappears. Only save file fields when they have a non-null value.

**Pattern applies to ALL settings pages:** Profil, Template, Keuangan, Tampilan, DokumenNotifikasi, Operasional.

```blade
{{-- resources/views/filament/pages/settings-form.blade.php --}}
{{-- TABLE ABOVE FORM --}}
@if(count($settingRecords))
<div class="bg-white rounded-xl border ...">
    <h3>Daftar Pengaturan</h3>
    <table>
        <thead><tr><th>Label</th><th>Nilai</th><th>Group</th><th>Terakhir Update</th></tr></thead>
        <tbody>
            @foreach($settingRecords as $s)
            <tr>
                <td>{{ $s->label }}</td>
                <td>{{ Str::limit($s->value ?? '—', 60) }}</td>
                <td><span>{{ $s->group }}</span></td>
                <td>{{ $s->updated_at->format('d M Y H:i') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- FORM BELOW --}}
{{ $this->form }}
<x-filament::button wire:click="save">Simpan Pengaturan</x-filament::button>
```

**Must also fix `CompanySetting::set()` — see antipattern #19.** Without `updateOrCreate()`, settings that don't exist yet are silently discarded when saved.

## 24. Modal Action: `schema()` vs `form()`, formatStateUsing & Toggle di Repeater

**Symptom:** `BadMethodCallException: Method Filament\Actions\Action::schema does not exist`; harga satuan "required" setelah apply programmatic fill; Livewire "property cannot be found" di toggle.

**Root causes:**
1. **`schema()` = method Page action (menu), `form()` = method Action modal.** Header action modal pakai `->form([...])` — `->schema()` selalu salah di `Filament\Actions\Action`.
2. **`formatStateUsing()` (number_format titik ribuan) pada TextInput numeric MERUSAK programmatic fill** → field tampil terisi tapi value dianggap kosong oleh validasi. Jangan pasang formatStateUsing di input numerik yang diisi via code.
3. **`Toggle` di Repeater dalam modal action = Livewire entangle error** ("property cannot be found") — pakai `Checkbox` yang lebih andal untuk state boolean per-row.

**Pattern RAB AI Copilot (CreateRab header action):**
```php
protected function getHeaderActions(): array
{
    return [
        Actions\Action::make('ai_copilot')
            ->label('✨ Buat RAB dengan AI')
            ->modalHeading('Asisten RAB (AI Copilot)')
            ->form([ // BUKAN ->schema()
                Select::make('jenis_pekerjaan')->options([...]),
                TextInput::make('jumlah_titik')->numeric()->default(8),
                Forms\Components\Repeater::make('draft_komponen')
                    ->schema([
                        Checkbox::make('pilih')->default(true), // bukan Toggle
                        TextInput::make('uraian_pekerjaan'),
                        TextInput::make('volume'),
                        TextInput::make('harga_satuan')->numeric(), // tanpa formatStateUsing
                    ])->columns(...),
            ])
            ->action(function (array $data, CreateRab $livewire) {
                // isi form utama:
                $livewire->data['komponen'] = collect($data['draft_komponen'])
                    ->where('pilih', true)->map(fn ($i) => [...])->values()->toArray();
                $livewire->form->fill();
            }),
    ];
}
```

## 25. LLM Multi-Provider OpenAI-Compatible (fase 3 RAB AI)

**Pattern:** jangan hardcode endpoint provider tunggal. Satu payload `chat/completions` (Bearer auth, `messages[]`, `choices.0.message.content`) melayani semua provider OpenAI-compatible:

| Provider | base_url | model default |
|----------|----------|---------------|
| Gemini | `https://generativelanguage.googleapis.com/v1beta/openai/chat/completions` | gemini-1.5-flash |
| DeepSeek | `https://api.deepseek.com/chat/completions` | deepseek-chat |
| OpenRouter | `https://openrouter.ai/api/v1/chat/completions` | deepseek/deepseek-chat |

**Key hierarki:** `CompanySetting llm_api_key` → `env LLM_API_KEY` → legacy `gemini_api_key` → `env GEMINI_API_KEY`. Tanpa key → method return null → caller fallback ke generator laporan lokal (graceful degradation, JANGAN throw).

**CompanySetting:** key LLM wajib masuk `SENSITIVE_KEYS` (enkripsi EncryptionService). UI input: Settings → Profil Perusahaan → section "🤖 AI / LLM". Verifikasi config via ReflectionMethod `getLlmConfig()` di tinker; uji call dengan key dummy → null (auth fail) → fallback OK.

## 26. Modal with Multiple Actions: State Does NOT Cross Livewire Requests

**Symptom:** Modal punya 2 action — tombol `Generate Draft` (di dalam `Forms\Components\Actions`) dan tombol submit modal ("Terapkan ke RAB"). Variabel service / flag yang di-set di generate action tidak ada / null di submit action. Contoh nyata: `$aiService->lastUsedLlm` di-read di submit action → selalu false karena instance service baru per request.

**Root cause:** Setiap action closure jalan di **request Livewire terpisah** dengan instance service FRESH. Closure variable dari action A tidak pernah terlihat di action B.

**Fix:** State harus lewat **form state**, bukan closure. Hidden field + `$set()` di action pertama, baca dari `$data` di action submit:

```php
// ✅ PATTERN
->form([
    ...
    Forms\Components\Hidden::make('ai_digunakan')->default(false), // pembawa state antar action
    Forms\Components\Actions::make([
        Actions\Action::make('generate')
            ->action(function (Get $get, Set $set) {
                $items = $service->generateRabFromPrompt(...);
                $set('draft_komponen', $items);
                $set('ai_digunakan', $service->lastUsedLlm); // flag lewat form state
            }),
    ]),
])
->action(function (array $data) {
    // request BARU — hanya $data yang sampai
    $sumber = !empty($data['ai_digunakan']) ? 'ai' : 'template';
});
```

**Rule:** Apapun yang dihasilkan action A dan dipakai action B → simpan ke field form (Hidden/Repeater), JANGAN andalkan properti PHP.

## 27. "AI Used" Detection: Availability ≠ Success

> **FITUR DIHAPUS 2026-08-01** — RAB AI Copilot dihapus atas keputusan user (konsep AI dipindah ke monitoring deterministik). `RabCopilotService.php` dihapus, metode generate RAB di `AiAnalysisService` dibuang (audit harga + laporan lokal tetap), section "🤖 AI / LLM" di CompanySettingPage hilang, 8 rows `llm_*` dihapus dari DB. Jangan cari `RabCopilotService` di codebase — sudah tidak ada. Pola #24-#28 tetap berlaku untuk fitur AI serupa di masa depan.

**Symptom:** Toast "✅ Generate dari AI Prompt selesai" padahal item dari template lokal. Badge 🤖 AI muncul untuk hasil template.

**Root cause:** `llmAvailable()` cek **API key tidak kosong**, bukan key valid. Key 401 (invalid), timeout, atau JSON response gagal parse → `callLLM()` return null → fallback template diam-diam → UI berbohong.

**Fix — flag hasil aktual, bukan cek ketersediaan:**

```php
// Di service
public bool $lastUsedLlm = false;   // default false

public function generateRabFromPrompt(...): array
{
    $this->lastUsedLlm = false;
    $llmResponse = $this->callLLM($prompt);
    if ($llmResponse) {
        $parsed = $this->parseLlmRabResponse($llmResponse, ...);
        if (!empty($parsed)) {
            $this->lastUsedLlm = true;   // HANYA saat parse sukses
            return $parsed;
        }
    }
    return app(RabCopilotService::class)->generate($jenis, $volume); // fallback
}
```

UI: `if ($service->lastUsedLlm)` → toast sukses; else → toast warning jujur ("LLM gagal/API Key tidak valid — pakai template lokal"). Badge sumber: `'ai'` hanya jika flag true, selainnya `'template'`.

**Related:** `callLLM()` untuk response yang harus di-parse JSON WAJIB kasih `max_tokens` (mis. 3000). Tanpa cap, output panjang terpotong → json_decode gagal → fallback diam-diam (gejala: "AI tidak pernah jalan").

## 28. Chat-Mode LLM Prompt: Adaptive Saat Parameter Tidak Diketahui

**Pattern:** Saat UI mengizinkan jenis_pekerjaan/volume kosong (AI infer dari kalimat user), prompt harus adaptif — jangan paksa nilai placeholder:

```php
$jenisDiketahui = in_array($jenis, ['pemasangan_pju', 'perawatan_pju'], true);
$jenisLabel = $jenisDiketahui ? $jenisLabel : 'TENTUKAN SENDIRI dari instruksi user';
$volumeContext = $volume > 0
    ? "- Volume pekerjaan: {$volume} {$volumeLabel}\n"
    : "- Volume pekerjaan: TENTUKAN dari instruksi user ...\n";
$volumeRule = $volume > 0
    ? "2. Hitung volume tiap item = koefisien × {$volume} ...\n"
    : "2. Tentukan volume tiap item dari instruksi user ...\n";
if (!$jenisDiketahui) {
    $koefisienText = "Tidak ada koefisien standar. Gunakan koefisien wajar industri (AHSP/SNI) sesuai instruksi.\n";
}
```

**Fallback template hanya jika jenis diketahui:** chat-mode (jenis kosong) tanpa LLM → tidak ada template → return `[]` (bukan crash, bukan template salah jenis). Caller guard: hasil kosong → toast warning.

**Form UX chat-mode:** `jenis_pekerjaan` + `volume` jadi `required(fn (Get $get) => empty($get('prompt')))` — required hanya di jalur template lokal; prompt terisi = keduanya opsional. Validasi tambahan di action: prompt kosong + jenis/volume kosong → blokir dengan toast.

## 29. Patch Tool Mangles PHP Backslash Escapes (Windows) — Verifikasi `php -l` Wajib

**Symptom:** Patch diterapkan, tapi `php -l` gagal "unexpected token" — string literal PHP berisi `\\n` (backslash dobel) atau newline beneran di tengah string. Terjadi 2x dalam satu sesi (patch `"KONTEKS:\n"` → `"KONTEKS:\\n"`, dan satu kasus jadi newline fisik).

**Root cause:** escaping round-trip tool patch merusak string literal PHP yang mengandung `\n` / `\"` / `\\`.

**Fix:**
1. Setelah SETIAP patch yang menyentuh string literal PHP → langsung `php -l` file itu.
2. Jika rusak, perbaiki byte-exact via execute_code (Python), bukan patch lagi:

```python
with open(path, encoding="utf-8") as f: src = f.read()
src = src.replace("\\\\n", "\\n")      # backslash dobel -> tunggal
src = src.replace('instruksi user.\n";', 'instruksi user.\\n";')  # newline fisik -> escape
with open(path, "w", encoding="utf-8") as f: f.write(src)
```

3. Untuk edit string PHP yang padat backslash: langsung pakai execute_code `open()/replace()` dari awal, hindari tool patch.

**Rule:** diff tool patch boleh menampilkan `\\` (escaped di JSON), tapi isi file harus `\`. Selalu cek byte file, bukan tampilan diff.

**Mode gagal ke-2 (2026-08-01): patch fuzzy drop koma array.** Patch yang menyentuh baris elemen schema (`->helperText('...')`) MENGHILANGKAN trailing comma → parse error `unexpected namespaced name "Forms\Components\TextInput", expecting "]"`. Gejala: error di baris BERIKUTNYA, bukan baris yang diedit. Juga bisa duplikasi string (ganti baris parsial → sisa baris lama menempel). Diagnosa cepat: `sed -n 'N,Mp' file | cat -A | cut -c1-200` (baris terpotong = string terbuka) + `php -l`. Setelah patch apa pun pada schema array → `php -l` wajib; kalau error, cek dulu koma/penutup string di baris sekitarnya, bukan hanya baris patch.

## 30. Deleting an if/else Branch Leaves Orphaned Braces & Header

**Symptom:** After removing a feature from a form/schema builder, `php -l` fails: "unexpected token ','" (orphaned `match` arms at wrong indent) or "unexpected token 'return', expecting 'function' or 'const'" (stray closing `}`).

**Root cause (nyata di CompanySettingPage 2026-08-01):** menghapus blok `if ($groupKey === 'ai') { ... } else {` dengan regex/Python tapi salah potong. Struktur asli:

```php
foreach ($groupSettings as $setting) {
    if ($setting->type === 'file') continue;
    if ($groupKey === 'ai') {          // <- branch custom yang dihapus
        ... 100 baris field AI ...
    } else {
        $field = match ($setting->type) {   // <- header INI ada di dalam else body
            'text', 'email' => ...,
            ...
        };
    }                                    // <- tutup else
    $fields[] = $field;
}
```

Potongan `src[:ai_start] + src[ai_else + len("} else {\n"):]` menghapus header `$field = match(...)` BERSAMA else-opening, tapi tutup `}` else (baris setelah `};`) tersisa → brace tak berpasangan + match arms yatim.

**Fix checklist saat menghapus branch if/else:**
1. Hapus BUKAN hanya `if (...) {` + `else {`, tapi juga baris penutup `}` milik else — trace brace balance manual.
2. Header statement di dalam else body (`$field = match (...) {`) ikut terhapus → harus dipindah ke level foreach body.
3. Setelah edit, wajib `php -l` — parse error muncul di baris yang TIDAK kamu edit (gejala klasik brace orphan).
4. Cek `}` count: buka vs tutup sama banyak di region yang diedit.

**Bentuk final yang benar:**
```php
foreach ($groupSettings as $setting) {
    if ($setting->type === 'file') continue;

    $field = match ($setting->type) {   // header pindah ke body foreach
        'text', 'email' => ...,
        ...
    };

    $fields[] = $field;
}
```

## 31. Deterministic Workflow Monitor (Lapis 1 — Tanpa LLM) + Notif Dedup

**Pattern (dibangun 2026-08-01, `WorkflowMonitorService`):** monitoring "proses terlupakan" TIDAK butuh AI — SQL murni + `InAppNotification::sendToRole()`. LLM justru jelek untuk deteksi status (halusinasi). Arsitektur 3 lapis yang disepakati: (1) monitor deterministik cron, (2) LLM async read-only, (3) chat assistant — baru setelah 1-2 terbukti.

**7 check (masing-masing metode public, return `['count' => n, 'judul' => ...]`):**
1. Kontrak aktif/draft tanpa RAB → `whereDoesntHave('rab')` → R01+R06
2. RAB orphan: `whereNull('kontrak_id')->where('is_active', true)` → R01
3. RAB draft > 14 hari (final = `is_active false` per konvensi sistem) → R01+R06
4. Pekerjaan `status='submitted'` > 3 hari tanpa review → R02+R01
5. Faktur `status='jatuh_tempo'` & `jatuh_tempo < now()` → R05+R01
6. Kontrak `status='active'` & `tgl_akhir < now()` → R01+R06
7. Penawaran expired: `DATE_ADD(tanggal_penawaran, INTERVAL masa_berlaku DAY) < CURDATE()` & belum disetujui → R01

**Dedup anti-spam (krusial untuk cron tiap jam):** skip kirim jika sudah ada notifikasi UNREAD dengan `link` sama dalam 20 jam terakhir:

```php
private function notify(string $role, string $judul, string $pesan, string $tipe, string $link): void
{
    $already = InAppNotification::where('link', $link)
        ->where('is_read', false)
        ->where('created_at', '>', now()->subHours(20))
        ->exists();
    if ($already) return;
    InAppNotification::sendToRole($role, $judul, $pesan, $tipe, $link);
}
```

**Registrasi:** command `Artisan::command('workflow:monitor', ...)` di `routes/console.php` + `Schedule::command('workflow:monitor')->hourly()`. Console output tiap check: `warn("  ⚠️ {judul}: {count}")`, total 0 → "semua sehat".

**Verifikasi:** run 2x berturut — deteksi tetap tampil di console (deteksi ≠ kirim), tapi count notif DB tidak bertambah (dedup). Cek: `InAppNotification::where('link','like',...)->count()` sebelum/sesudah run kedua.

**Aturan besi arsitektur AI di ERP ini:** AI TIDAK PERNAH nulis DB (read-only + konfirmasi manusia), AI TIDAK di request path halaman (load/save/validasi), hasil AI hormat RBAC (DeptAccessService).

## 32. Hard Delete User Fails — FK 1451 (Soft Delete Fix)

**Symptom:** Hapus user → `SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row` (MySQL). Error muncul hanya untuk user yang punya relasi (pekerjaan, faktur, jadwal_teknisi, dll), user tanpa relasi terhapus diam-diam.

**Root cause:** `User` model TIDAK pakai SoftDeletes (model lain Aset/Faktur/Kontrak/Pekerjaan semua pakai) → `delete()` = hard delete. 30+ tabel punya FK ke `users` (cek: `information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME='users'`). Relasi = FK violation.

**Fix — 3 bagian:**
```php
// 1. Migration: tambah deleted_at
Schema::table('users', function (Blueprint $table) {
    $table->softDeletes();
});

// 2. Model: use SoftDeletes
use Illuminate\Database\Eloquent\SoftDeletes;
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, Notifiable, SoftDeletes;
```

```php
// 3. Resource: guard hapus diri + R00 (DeleteAction & DeleteBulkAction)
Tables\Actions\DeleteAction::make()
    ->before(function (User $record) {
        if ($record->id === auth()->id()) throw new \Exception('Tidak bisa menghapus akun sendiri.');
        if ($record->role === 'R00') throw new \Exception('Tidak bisa menghapus Super Admin.');
    }),
```

**Bonus:** SoftDeletes scope otomatis mengecualikan user terhapus dari login (`AuthController` pakai `User::where(...)`). Tidak perlu guard manual di login.

**Pitfall saat tes:** user tanpa relasi (mis. pm1 R03) terhapus PERMANEN oleh tes hard-delete sebelum fix — tak bisa di-restore. Restore dari `aplikasi_kantor_backup.sql` (INSERT VALUES dengan id eksplisit, bcrypt password123). Selalu tes dengan user ber-relasi.

## 33. Restore DB Lama + Migrate Fresh — 3 Mode Gagal Migration

**Symptom:** DB kosong/di-restore dari backup lama (schema Jun), lalu `php artisan migrate --force` gagal beruntun.

**Mode gagal & fix:**
1. **`Base table already exists: 1050` tapi migrations table tidak mencatatnya** (tabel dibuat manual/backup lama, state inconsistent). Fix bersih: **drop semua tabel → import backup → migrate fresh**:
```sql
-- _drop_all.sql (bash makan backtick — tulis ke file, jangan inline)
USE aplikasi_kantor;
SET FOREIGN_KEY_CHECKS=0;
SET GROUP_CONCAT_MAX_LEN=1000000;
SET @tables = NULL;
SELECT GROUP_CONCAT('`', table_name, '`') INTO @tables FROM information_schema.tables WHERE table_schema='aplikasi_kantor';
SET @sql = IFNULL(CONCAT('DROP TABLE IF EXISTS ', @tables), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET FOREIGN_KEY_CHECKS=1;
```
2. **Migration baru yang menambah kolom harus TIMESTAMP SEBELUM migration yang `change()` kolom itu.** `2026_08_01_170000_add_dokumentasi_steps` gagal karena `2026_07_29_120000_fix_nullable_and_length` mengubah kolom itu lebih dulu. Rename file ke `2026_07_29_110000_...` — urutan nama file = urutan eksekusi, timestamp menentukan.
3. **`->string('x', 50)->unique()->change()` → `Duplicate key name '..._unique'`** — unique key sudah ada dari CREATE TABLE asli; `->unique()` di change() bikin duplikat. Hapus `->unique()`, biarkan key lama.

**Kolom dipakai kode tapi TIDAK pernah dibuat migration mana pun:** `dokumentasi_steps` (grep kode: `app/Models/Pekerjaan.php`, `ExecutePekerjaan.php`, `DokumentasiStatsWidget.php`) — dibuat manual di DB asli, backup Jun 6 tidak punya. Verifikasi sebelum migrate: `git log -S "dokumentasi_steps"` + `grep -rn` di database/migrations/. Solusi: migration baru timestamp tepat sebelum consumer-nya.

**Laragon MySQL CLI:** `mysql` tidak di PATH — pakai `"C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe"`. SQL ber-backtick (`GROUP_CONCAT('`', table_name...)`) di-inline via bash → backtick dimakan shell (error 1064 syntax). Tulis ke file .sql lalu `< file`.

**Verifikasi akhir:** `php artisan migrate:status | grep -c "Ran"` = jumlah total migration; login browser + cek halaman yang tadinya crash (dashboard query kolom baru).

## 34. Model Uses Column Never Created by Any Migration (Manual DB Columns)

**Symptom:** Runtime `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'kode_transaksi' in 'where clause'` — mis. halaman Top Up Petty Cash / Kas Operasional crash: `select * from petty_cash where kode_transaksi LIKE 'TU/2026/08/%' order by ...`.

**Root cause:** Kolom dibuat MANUAL di DB asli (tanpa migration file), ATAU migration-nya DIHAPUS dari repo setelah kolom pernah hidup di DB dev. Backup dump = struktur lama, kode = lebih baru. Sudah 3x di ERP ini:
- `pekerjaan.dokumentasi_steps` — dipakai `Pekerjaan` model, `ExecutePekerjaan.php`, `DokumentasiStatsWidget.php`
- `petty_cash.kode_transaksi` — dipakai `PettyCash::generateKodeTransaksi()` (prefix `TU/Y/m/###`), ada di `$fillable`, dibaca `KasOperasionalDashboard`
- `transaksi_keluar.nomor_transaksi` + `transaksi_masuk.nomor_transaksi` — migration `2026_06_12_070000/080000_add_nomor_transaksi_*` DIHAPUS dari repo di commit d34452b (`git log --all --diff-filter=D -- database/migrations/`); kode `TransaksiKeluar::generateNomorTransaksi()` tetap query kolom → 42S22 di halaman Barang Keluar (list & create). Recovery: migration baru `add_nomor_transaksi_to_transaksi_tables` — `string(30)->nullable()->after('id')` + `index()` untuk KEDUA tabel.

**Deteksi (sebelum migrate/restore):**
```bash
git log --all -S "kode_transaksi" --oneline -- database/migrations/   # KOSONG = kolom manual, bukan migration
grep -rln "kode_transaksi" app/ database/migrations/                  # ada di app/, tak ada di migrations/
php artisan tinker --execute="echo implode(', ', DB::getSchemaBuilder()->getColumnListing('petty_cash'));"
```
Bandingkan `$fillable` model vs kolom live — selisih = kolom manual yang belum dimigrasikan.

**Fix:** migration baru `add_<col>_to_<table>` dengan timestamp SEBELUM migration consumer pertama yang `change()`/query kolom itu (lihat #33 mode 2).

**Pitfall lanjutan:** error 42S22 bisa muncul DI HALAMAN MODUL, bukan saat migrate — restore+migrate sukses tapi modul tertentu crash. Setelah restore+migrate WAJIB smoke-test halaman tiap modul (dashboard, list, form), atau verifikasi log:
```bash
grep -E "^\[<YYYY-MM-DD> <jam>:" storage/logs/laravel.log | grep -iE "error|exception" | tail
```
Bedakan error transient (MissingAppKey sesaat setelah cache clear; `users.deleted_at` 42S22 di antara model-edit dan migration jalan) vs bug nyata (kolom hilang, FK). Yang transient hilang sendiri — jangan "diperbaiki" dengan code change.

**Login gagal untuk semua user setelah restore (2026-08-01):** backup lama = hash bcrypt basi — `password_verify('password123', $u->password)` false untuk SEMUA user non-superadmin (superadmin di-reset manual saat sesi restore). Browser login gagal diam-diam (redirect balik ke /admin/login). Reset loop:
```php
foreach (App\Models\User::all() as $u) {
    if (!password_verify('password123', $u->password)) {
        $u->update(['password' => bcrypt('password123'), 'failed_login_attempts' => 0, 'locked_until' => null]);
    }
}
```
Jangan lupa reset `failed_login_attempts`/`locked_until` — percobaan login gagal sebelum reset bisa mengunci akun.

## 35. Select `->preload()` + Related Row NULL Label → TypeError isOptionDisabled

**Symptom:** Halaman create/edit crash `TypeError: Filament\Forms\Components\Select::isOptionDisabled(): Argument #2 ($label) must be of type string, null given` — stack: `Select.php:191 array_map` → `transformOptionsForJs` → `getOptionsForJs` (select.blade.php:144). Muncul saat mount, bukan saat submit. Nyata di `dms-documents/create` (2026-08-01): Select `pekerjaan_id ->relationship('pekerjaan', 'nama_pekerjaan')->preload()`.

**Root cause:** `->preload()` memuat SEMUA record relasi ke options. Record dengan kolom label NULL (mis. 5 pekerjaan lama `nama_pekerjaan` NULL) → Filament panggil `isOptionDisabled(null)` → TypeError. Select tanpa preload (lazy) tidak kena — hanya record yang terbuka yang diproses.

**Deteksi:**
```bash
php artisan tinker --execute="echo DB::table('pekerjaan')->whereNull('nama_pekerjaan')->count();"
```

**Fix (dua lapis):**
1. Data fix — backfill NULL label (root cause):
```php
DB::table('pekerjaan')->whereNull('nama_pekerjaan')->update([
    'nama_pekerjaan' => DB::raw("CONCAT(COALESCE(jenis_pekerjaan,'pekerjaan'),' - ',COALESCE(lokasi_ruas,'lokasi'))"),
]);
```
2. Defensif — Select jangan pernah terima label null:
```php
Select::make('pekerjaan_id')->relationship('pekerjaan', 'nama_pekerjaan')
    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nama_pekerjaan ?: "(tanpa nama #{$record->id})")
```

**Rule:** Setiap `->relationship(..., 'kolom_label')->preload()` di form — cek dulu `whereNull('kolom_label')->count()` di tabel relasi. Data legacy dari restore backup sering punya label NULL.

## 36. CLI Kernel Page Audit LIES for Livewire Pages — Browser Is Ground Truth

**Symptom:** Script audit E2E (`$kernel->handle(Request::create(...))` loop per user per URL) melaporkan 100+ halaman `[500]` — padahal di browser semua render normal. 0 error nyata di `laravel.log` (hanya `StartSession::addCookieToResponse(): Argument #1 ($response) must be of type ...Response, Livewire\Features\SupportRedirects\Redirector given`).

**Root cause:** Livewire page yang me-redirect (mis. `budget-monitor` → redirect ke `project-health`, atau `workflow-monitoring` → redirect) mengembalikan objek `Redirector`, bukan `Response`. Middleware `StartSession::addCookieToResponse()` menerima `Redirector` → TypeError → status 500 palsu. Di browser, redirect normal — tidak ada error. Juga: session singleton kernel tercemar antar user (semua user share 1 session id) → cek `auth()->id()` salah; fix = `$session->setId(bin2hex(random_bytes(16)))` per user — TAPI tetap false-500 untuk halaman redirect.

**Aturan:** CLI kernel handle TIDAK VALID untuk verifikasi render halaman Filament. Browser = satu-satunya ground truth (`browser_navigate` + snapshot). CLI kernel tetap berguna untuk: status HTTP non-200 (302→login, 403→denied), dan deteksi exception yang LOGGED (grep `laravel.log` setelah run). Pemisahan: `[500]` body ter-render penuh = artefak redirect (abaikan); exception di log tanpa `StartSession`/`Redirector` = bug nyata (fix).

**Ganti user di browser (audit multi-role):**
- `/admin/logout` POST-only — GET = MethodNotAllowed.
- Cookie session HttpOnly — TIDAK bisa di-clear via `document.cookie` JS.
- Logout via fetch dengan XSRF:
```js
const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]);
await fetch('/admin/logout', { method: 'POST', headers: { 'X-XSRF-TOKEN': xsrf }, credentials: 'same-origin' });
```
lalu navigasi ke `/admin/login`, isi user berikutnya.

## References

`references/workflow-kontrak-pembayaran.md` — full workflow trace Kontrak→Pembayaran 100%.
`references/rab-import-technical.md` — technical details of RAB import flow, CSV parsing, and verification.
`references/terbilang-converter.md` — Terbilang.php implementation, test cases, and PHP number-to-words conversion nuances.