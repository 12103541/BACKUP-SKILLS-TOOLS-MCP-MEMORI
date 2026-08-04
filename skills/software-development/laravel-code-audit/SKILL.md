---
name: laravel-code-audit
description: "Systematic logic audit for Laravel/Filament codebases."
version: 1.0.0
author: Hermes Agent
license: MIT
platforms: [linux, macos, windows]
metadata:
  hermes:
    tags: [laravel, audit, code-review, architecture, php, filament]
    related_skills: [requesting-code-review, systematic-debugging]
---

# Laravel Code Audit — Architecture & Logic Review

Systematic layer-by-layer audit of existing Laravel codebases to find logic
errors, architectural problems, and design inconsistencies.

**When to use:** User asks you to "review", "check", "audit", "evaluate",
"cari bug", "periksa logic" on a Laravel/Filament codebase, especially
ERP or business applications.

**Not for:** Pre-commit verification of staged changes — use
`requesting-code-review` for that. Bug reproduction & root cause — use
`systematic-debugging`.

## Audit Sequence

Always audit in this order. Each layer depends on the layer before it —
a model fix cascades up; a config fix cascades down.

---

### 1. Models — the data contract

| Check | What to look for |
|-------|-----------------|
| `$fillable` / `$guarded` | Missing fields that should be mass-assignable |
| `casts()` | Decimal/date/json casts correct? Nullable casts? |
| `$table` | Custom table names match migration? |
| Relationships | Correct type (belongsTo/hasMany/hasOne), correct foreign/local keys |
| `boot()` events | `saving`/`updated`/`deleted` — infinite loop guards (`wasChanged()`)? |
| Traits used (`use ...`) | Do trait methods actually exist? Correct namespace? |
| Scopes (`scopeX`) | SQL correctness, index usage |
| Accessors (`getXAttribute`) | Null safety on chained relations (`$this->relation?->field`) |

**Common Laravel model bugs found during audit:**
- `\ValidationException` thrown without FQCN (should be `\Illuminate\Validation\ValidationException`)
- `wasChanged()` check missing in `updated` events → infinite save loops
- Cast mismatch: `decimal:2` on fields that store formatted strings
- `$fillable` missing JSON columns → `update()` silently ignores them

### 2. Services — the business logic

| Check | What to look for |
|-------|-----------------|
| Transaction boundaries | Multi-step ops wrapped in `DB::transaction()`? |
| Input validation | Validated before processing, not in the middle |
| State machine | Status transitions enforced? Illegal transitions blocked? |
| Side effects | Notifications/logs/inventory updates atomic with main op? |
| Duplicate logic | Same validation/repetitive code in multiple methods |
| Constants vs literals | Business rules (percentages, limits) in config, not hardcoded |
| Error messages | User-friendly, specific, actionable (not generic "error") |
| N+1 queries | `->load()` or eager-loading in service methods? |

### 3. Filament Resources & Pages — the UI layer

| Check | What to look for |
|-------|-----------------|
| `canAccess()` | Hardcoded `in_array(role, [...])` or proper `hasPermission()`? |
| Form `live()` + `afterStateUpdated` | Data consistency across interdependent fields |
| Relationship selects | `->relationship('x')` (needs model context) vs manual `->options()` |
| Hidden / dehydrated | `user_id`, `created_by` auto-set? Disabled fields that still submit? |
| Table actions | Side effects on status change call the right service/model methods? |
| Tax / PPN calculations | Config-driven or hardcoded percentage? |
| Auth:: vs auth()-> | Mix of facade and helper (facade import may be missing if helper removed) |
| **Login override (username instead of email)** | Filament v3 custom login extends `Filament\\Pages\\Auth\\Login` — must override `getForms()`, provide `getUsernameFormComponent()`, override `getCredentialsFromFormData()` to return `['username' => ..., 'password' => ...]`, and override `throwFailureValidationException()` referencing `data.username`. Register via `->login(\\App\\Filament\\Pages\\Auth\\Login::class)` in PanelProvider. Cache clear: `php artisan optimize:clear && php artisan filament:cache-components`. |
| **Form Section `visible()` + Livewire state** | `Section::make(...)->visible(fn () => $this->something)` — if `something` gets SET after user action (e.g. preview), the section **disappears** along with all its fields. `form->getState()` returns null for those fields → silent failure. Fix: remove the `!$this->something` condition or track separately. |

**Common Filament audit findings:**
- PPN hardcoded as `0.11` instead of reading from `config('pajak.tarif_ppn_keluaran')`
- Horizontal form grid broken because Section wraps everything in columns
- `->relationship()` used on standalone Pages where it doesn't work
- Action `visible()` conditions that leak to unauthorized roles

### 3b. RBAC Coherence — permission system audit

After auditing individual canAccess() calls, check the permission system holistically:

| Check | What to look for |
|-------|-----------------|
| `config/permissions.php role_map` | Does every role have the permissions it needs? Missing permissions cause hidden menus. |
| `DeptAccessService::NAV_ACCESS` | Does every slug mapping match the config role_map? Drift = some users see extra menus or miss expected ones. |
| `hasPermission()` kode arguments | Cross-reference every kode string against `config/permissions.php permissions[].kode`. Typo = dead permission. |
| SDM pages `return false` | If SDM pages are hard-disabled with `return false`, HRD users see no SDM menu even though they have `sdm.*` permissions. |
| Login page `canAccess()` | Login page must NOT restrict access — it's the entry point. If it returns false, no one can log in. |
| Hardcoded check vs permission | Grep `in_array.*role` in `app/Filament/Pages/` and `in_array.*role` in custom `canAccess()` methods. Every hit is a bypass candidate. |

**Audit command:**
```bash
# Compare DeptAccessService against config permissions
grep -oP "'\K[^']+(?='\s*=>\s*\[)" app/Services/DeptAccessService.php | sort > /tmp/dept_slugs.txt
grep -oP "'kode'\s*=>\s*'\K[^']+" config/permissions.php | sort > /tmp/perm_kodes.txt
diff /tmp/dept_slugs.txt /tmp/perm_kodes.txt 2>/dev/null || echo "Compare manually"

# Find hardcoded role checks
grep -rn "in_array.*role\|auth()->user()->role" app/Filament/ 2>/dev/null
```

**Common RBAC audit findings:**
- Permission name mismatch between config and canAccess() call (e.g. `smart_pricing.view` vs `penawaran.smart_pricing.view`)
- DeptAccessService allows roles that config role_map denies, or vice versa
- SDM pages all `return false` — HRD users see 0 SDM menus despite having all `sdm.*` permissions
- Hardcoded `role === 'R00'` locks pages permanently to Super Admin only (e.g. CompanySettingPage, WorkflowDetailPage)
- Config permissions exist for a modul but ALL Filament pages/resources for that modul are disabled or return false

### 4. Traits — shared behavior

| Check | What to look for |
|-------|-----------------|
| All methods actually called? | Grep for each method name across project |
| Dead stubs | `return true;` without logic, empty methods |
| Namespace correctness | Exceptions/classes referenced in traits |
| `bootTraitName()` | Static boot methods in Eloquent traits fire correctly? |

### 5. Config, Exceptions & Commands — sources of truth

| Check | What to look for |
|-------|-----------------|
| Config vs hardcoded | Are business rules (PPN, limits, paths) in config files? |
| `env()` in config only | `env()` called outside `config/*.php` won't cache properly |
| Exception handler | Proper rendering per environment, logging level |
| Environment-specific paths | Hardcoded `C:/laragon/...` or `C:/xampp/...` in Services? |
| Console commands | Schedule correctness, error handling, output formatting |
| **Command model references** | Every `\App\Models\Xxx` referenced in `app/Console/Commands/*.php` must be a real class. Grep `class Xxx` under `app/Models/` to verify |
| **Command mass-assignment match** | Direct `Model::create(...)` or `Model::update(...)` in Commands may pass columns not in `$fillable`. Always read the target model's `$fillable` array and compare. Silent discard = invisible data loss |
| **Serial number generation** | `while(...->exists())` loops with increment must rebuild the query inside the loop, not reference a stale Builder from outside |

### 6. Filament Widgets — dashboard data layer

| Check | What to look for |
|-------|-----------------|
| Column name correctness | `DATE_FORMAT(tanggal, ...)` — verify column exists in the table's migration/Schema |
| Cross-widget consistency | Same metric (e.g. pengeluaran per bulan) computed the same way across all dashboard files |
| N+1 via closures | Widgets that call `Model::count()` inside loops or per-item callbacks |
| Empty-state guard | `array_keys($data) ?: ['Belum Ada Data']` — silent 0/empty chart fallback |

---

## Common Bug Patterns Reference

| Pattern | Likely Cause | Grep / Search |
|---------|-------------|---------------|
| `\ValidationException` fatal | Wrong namespace (no `\Illuminate\Validation\` prefix) | `throw new \\V` in traits |
| PPN hardcoded | Config not consulted | `0.11`, `0.12`, `'PPN (11%)'` |
| `visible()` condition + Livewire state toggle | Section/action hidden after user triggers action that sets state var (`!$this->previewData`) | `visible.*\$this->` in Pages/ (check for negative state checks) |
| N+1 helperText | Per-render DB query in form `helperText()` closure — fires every Livewire re-render | `helperText.*::(find\|where\|cari\|search)` in Resources/Pages |
| Real-time `live()` on inputs | 1 server request per keystroke for formatting/validation | `->live()` in form schema (should be `->live(onBlur: true)`) |
| Batch update bypassing Eloquent events | `Model::where(...)->update(...)` in commands/controllers doesn't fire `updated` event | `->where\(.*\)->update\(` (not on `$model` instance) |
| Duplicate sync logic | Model `boot()` event + RelationManager `after()` both do same status sync → double processing | `boot.*saved` AND `after` in same feature area |
| N+1 matching loop | Service calls DB query per item in loop (e.g. `matchToSparepart()` inside `foreach`) instead of preloading + in-memory match | `::find\|::where\|->first()` inside `foreach` in Services/ |
| `saveAll()` before single-purpose op | Page calls full save (delete+reinsert+recalc) before kirim/export when only 1-2 fields need sync | `saveAll()` called before `kirim\|generate\|export` in Pages/ |
| Role bypass in canAccess | RBAC not centralized | `in_array.*->role` in Resources |
| Dead trait stub | Abandoned refactor | `return true;` in trait methods |
| Windows-only paths | Hardcoded in Services | `C:/` in files under `app/` |
| `Auth::` without import | Copied code, facade removed | `Auth::` without `use ...Facades\Auth` |
| Infinite event loop | Missing `wasChanged()` gate | `static::updated` without `wasChanged` |
| Infinite loop in code number gen | `$existsQuery` built ONCE outside while, `$nomor` incremented inside but query never rebuilt | `while.*->exists()` in Traits |
| Wrong class name in CLI command | Copied from different project/package, model class doesn't exist | `\\App\\Models\\` in `Commands/` |
| Widget column name mismatch | Different widgets query same table with different column names (e.g. `tanggal` vs `tanggal_pengeluaran`) | `DATE_FORMAT\(` in `Filament/Widgets/` |
| Formula logic inversion | Subset treated as independent count (e.g. `approved / (approved + lateApproved)` when late is subset of approved) | `/ \(.* \+ .*\)` in reporting/controllers |
| Invalid status string | Status constant doesn't exist in model | Chain: find `->where('status',` → grep model for constant/valid values |
| Default-allow security | Unknown slugs return `true` in access service instead of `false` | `return true` in DeptAccess/Services |
| **Partial fix syndrome** | Class name corrected but `::create()` still passes wrong column names (ActivityLog `caused_by`/`properties`/`log_name` after `Activity`→`ActivityLog` fix) | `::create\\(\\[` near `ActivityLog` in Commands — compare each key against model `$fillable` |
| **Integer cast missing** | `stok`, `safety_stock`, `reorder_point` not cast to `integer` in model — string comparison breaks `<=` logic | Check `casts()` for `'stok' => 'integer'` in model files with numeric inventory/quantity fields |
| **Null safety on date accessor** | `$this->tanggal_penawaran->copy()->addDays(...)` crashes if date field is null | `->.*copy\\(\\)\|->.*addDays\\(` on date casts in Accessors — check null guard |
| **Hardcoded role check in Pages** | Custom Filament Pages use `in_array(Auth::user()->role, ['R00', ...])` instead of `hasPermission()`, bypassing DB permission system | `in_array\\\\(.*->role` in `app/Filament/Pages/` — every hit is a bypass candidate |
| **Nilai efektif progres** | `hitungProgresOtomatis()` / `WorkflowIndicatorService::calculate()` uses `$this->nilai` (initial) instead of `$this->nilai_efektif` (including adendum) — progres overstates completion | `hitungProgresOtomatis()` in Kontrak model, `WorkflowIndicatorService::calculate()` — check which `nilai` is referenced |
| **Duplicate workflow systems** | Two independent workflow implementations: pipeline (7-stage from contracts) + WorkflowProyek/WorkflowStep/WorkflowTahapan models — causes user confusion & inconsistent progress | Search for both `WorkflowIndicatorService` AND `WorkflowProyek`/`WorkflowStep`/`WorkflowTahapan` models. If both exist, consolidate to one. |
| **Missing overdue detection** | Contracts past `tgl_akhir` with status `active`/`berjalan` not flagged — no visual/automated alert | Check if service computing progress also checks `tgl_akhir < now()` for active contracts |
| **Professional pipeline UI pattern** | Raw circles + labels → needs: status badge, gradient progress bar, animated active/overdue nodes, legend with counts | `resources/views/components/workflow-indicator.blade.php` — reference for future Filament ERP pipeline displays |
| **RBAC dual-system drift** | `DeptAccessService::NAV_ACCESS` hardcoded array and `config/permissions.role_map` define different access for same module — two sources of truth silently disagree | Compare `DeptAccessService.php` `NAV_ACCESS` keys against `config/permissions.php` `role_map` |
| **Permission name mistmatch** | `hasPermission('penawaran.smart_pricing.view')` in code vs `smart_pricing.view` in config — $permissionKode not found in DB → false for all non-R00 users | `hasPermission\\(` — cross-reference each kode against `config/permissions.php` `kode` array |

---

## Patch Tool Pitfall — PHP Backslash Namespaces

When editing PHP files that contain namespace separators like
`\Illuminate\Validation\`, the `patch` tool may **double the backslashes**
(`\\` → `\\\\`), producing syntax errors.

**Fix:** Use `write_file` to rewrite the entire file instead of `patch`.
Or use `patch` on only the non-backslash portions (e.g. variable names,
method bodies) and check syntax with `php -l <file>` after each edit.
If the patch tool mangles backslashes, immediately switch to `write_file`.

---

## Verification After Fixing

Every file you modify MUST pass syntax check:

```bash
cd /path/to/project && php -l app/Path/To/File.php
```

Verify config-driven values are actually read:
```bash
grep -r "config('pajak.tarif" app/
```

## Runtime Recovery — Empty DB / Login Failure

**Symptom:** User can't login ("superadmin tidak bisa masuk"), login rejects valid credentials, or app shows zero data everywhere.

**First check — is the DB empty?** Migrations present but 0 rows = DB wiped (`migrate:fresh` without seed, or manual drop). This is NOT a code bug — do not start auditing auth logic.

```bash
php artisan tinker --execute="dump(DB::table('users')->count()); dump(Schema::hasTable('users'));"
# count=0 + hasTable=true → DB wiped
```

Quick full-DB emptiness probe (all-zero table_rows = fresh DB):
```bash
"C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -h127.0.0.1 -uroot \
  -e "SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='aplikasi_kantor' ORDER BY table_rows DESC LIMIT 12;"
```

**Pitfall: `mysql` is NOT on git-bash PATH.** Use full Laragon bin path — version dir varies (`ls "C:/laragon/bin/mysql/"` to find it).

**Recovery:**
1. Restore full backup from project root:
   `"C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -h127.0.0.1 -uroot aplikasi_kantor < aplikasi_kantor_backup.sql`
   (full dump incl. users table; ~33 INSERTs. Verify: `SELECT id,username,role FROM users;` — superadmin id=1, role R00)
2. Reset superadmin password (user standard = `password123`):
   `php artisan tinker --execute="App\Models\User::where('username','superadmin')->update(['password'=>bcrypt('password123')]);"`
3. Verify login: `php artisan tinker --execute="dump(Auth::attempt(['username'=>'superadmin','password'=>'password123']));"` → `true`

## Workflow Chain Test Order (Imperative — User Preference, ERP-specific)

**NEVER test features in isolation.** The material/financial flow MUST be tested in its complete chain order — skipping stages produces invalid results and hides inter-stage data bugs. This is an explicit user preference from 2026-07-29.

### Primary Chain: Kontrak → Sparepart → Finance

Correct order for any ERP test simulation involving sparepart/project workflow:
```
KONTRAK
  ↓
RAB (Insert items — harga satuan dari file import)
  ↓
BOM (Bill of Materials — auto-generate via RabMaterialPlanService::generateFromRab)
  ↓
Aktivasi BOM oleh Gudang (R04 — via RabMaterialPlanService::activate)
  ↓
Pekerjaan (dibuat oleh Admin, dikerjakan oleh Teknisi R02)
  ↓
Transaksi Keluar (sparepart terpakai, auto-decrement stok, auto-sync BOM realisasi)
  ↓
Faktur (tagih berdasarkan transaksi keluar + PPN config-driven 12%)
  ↓
Pembayaran (auto-update faktur→lunas via Pembayaran::booted()::saved())
```

**Why chain order matters:**
- RAB is the BASIS for BOM — tanpa RAB, gudang tidak punya acuan
- BOM is the BASIS for sparepart mapping — tanpa sparepart_id, TransaksiKeluar tidak bisa diproses
- Transaksi Keluar is the BASIS for invoicing — tanpa transaksi keluar, faktur tidak punya dasar
- User is learning the logic — test urut agar alur kerja divisi bisa dipantau
- Workflow proyek harus BERURUTAN agar role progres teratur

**Pitfall:** Previous sessions skipped RAB→BOM→Mapping→TransaksiKeluar and jumped straight to Faktur→Pembayaran. This was explicitly CORRECTED by the user (2026-07-29). Do NOT repeat — always run the full chain.

**Two complementary sequences exist:** Role-based sequence (who does what) lives in the project's RULES.md / erp-rules skill. This section defines the data flow order (what order data must travel). Both must be followed; they are not alternatives.

### Running Multi-Step Scripts

Write a `.php` file, then run via tinker (not direct `php`):
```bash
php artisan tinker --execute="require '_workflow_test.php'"
```
Script must include proper `use` imports. Auth is needed before calling services that check `Auth::id()`.

Key service calls in order:
```php
// Step 1-2: RAB → BOM
$plan = app(RabMaterialPlanService::class)->generateFromRab($rab);

// Step 3: Aktivasi BOM oleh Gudang
Auth::loginUsingId($gudangUser->id); // must be role R04
app(RabMaterialPlanService::class)->activate($plan);

// Step 4-5: Pekerjaan → TransaksiKeluar (stok auto-decrement via booted)
$pekerjaan = Pekerjaan::create(['kontrak_id' => $id, 'user_id' => $teknisi->id, 'status' => 'approved', ...]);
TransaksiKeluar::create([
    'sparepart_id' => $sparepartId,
    'quantity' => $qty,
    'pekerjaan_id' => $pekerjaan->id,
    'teknisi_id' => $teknisi->id,
    'harga_jual' => $hargaJual,
    'status_tagih' => 'belum_tertagih',
    'tipe' => 'keluar',
]);

// Step 6: Faktur (direct terbit, skip draft)
$faktur = Faktur::create([
    'kontrak_id' => $id,
    'subtotal' => $total, 'ppn' => round($total * 0.12), 'total_tagihan' => $total + $ppn,
    'status' => 'terbit',
]);

// Step 7: Pembayaran → auto-lunas
Pembayaran::create(['faktur_id' => $faktur->id, 'jumlah' => $totalTagihan, ...]);
```

### Auto-Create Sparepart from Unmapped BOM Items

When BOM has items without `sparepart_id` (no matching sparepart in DB), batch-create sparepart from unique item names:

```php
$unmapped = RabMaterialPlanItem::where('plan_id', $planId)
    ->whereNull('sparepart_id')->where('tipe_item', '!=', 'jasa')->get();

$uniqueItems = $unmapped->groupBy('uraian_item'); // dedup by name

foreach ($uniqueItems as $uraian => $items) {
    $existing = Sparepart::whereRaw('LOWER(TRIM(nama_part)) = ?', [trim(mb_strtolower($uraian))])->first();
    if ($existing) {
        foreach ($items as $item) $item->update(['sparepart_id' => $existing->id]);
        continue;
    }
    $sp = Sparepart::create([
        'sku' => 'SP-' . str_pad(++$lastSkuId, 5, '0', STR_PAD_LEFT),
        'nama_part' => $uraian,
        'kategori' => 'PROYEK', // REQUIRED — no DB default!
        'stok' => 1000,
        'harga' => $first->harga_satuan_rab,
        'harga_jual' => $first->harga_satuan_rab,
    ]);
    foreach ($items as $item) $item->update(['sparepart_id' => $sp->id, 'tipe_item' => 'sparepart']);
}
```

**Pitfalls specific to the sparepart chain:**
- **Sparepart `kategori` field has NO default value** — `Sparepart::create()` without `'kategori'` throws SQL error 1364. Always include kategori.
- **TransaksiKeluar auto-decrements stok via booted()** — `TransaksiKeluar::booted()::created()` calls `decrementStok()` automatically. Do NOT manually `$sp->decrement('stok', ...)` after creating record — that double-deducts.
- **RAB must have kontrak_id** — orphan RABs (kontrak_id=NULL) cannot be reached by `syncFromTransaksiKeluar()` which traverses `pekerjaan→kontrak→RAB→BOM`.
- **BOM fuzzy mapping is approximate** — when only 5 sparepart exist, 518-item RAB maps only ~81 items, many wrong. Use auto-create-sparepart approach for production data.

---

## Pitfalls

- **Patch tool + PHP namespaces** — see note above
- **Layer skipping** — don't jump to config before checking models; a model bug
  makes service/resource changes pointless
- **Over-auditing** — focus on CRITICAL/HIGH findings first (namespace errors,
  security bypasses, config mismatches). Present lower-severity items as notes
- **Reading too many files** — sample strategically: 1-2 models, 1-2 services,
  1-2 resources, then the traits and config. Don't read 500 files
- **False urgency** — the user wants quality findings, not speed. Validate
  each finding before reporting
- **Language mismatch** — user may describe bugs in Bahasa Indonesia but code
  is in English; report findings in the language the user initiated
- **Command $fillable mismatch** — when a CLI command calls `Model::create([...])` directly (not through a service/static method), cross-reference every key against the model's `$fillable` array. Silent mass-assignment discard = invisible data loss. The `created`/`updated` event still fires, so bugs hide at the database layer
- **Sparepart numeric fields** — `stok`, `safety_stock`, `reorder_point` etc. must be cast to `integer`. Without cast, PHP `<=` becomes string comparison (`"9" <= "10"` = false). Check `casts()` for `'integer'` on all numeric inventory/quantity fields
- **Date accessor null crash** — `$this->date_field->copy()->addDays(...)` throws FatalError if date_field is nullable and null. Always guard with `$this->date_field ? $this->date_field->copy()... : null`
- **Hardcoded role checks in custom Pages** — all Filament Resources may use `hasPermission()`, but custom Pages (standalone `Filament\\Pages\\Page`) often use `in_array(role, [...])`. After auditing Resources, grep `app/Filament/Pages/` for `in_array.*role`
- **RBAC dual system drift** — when auditing access control, ALWAYS check both `config/permissions.role_map` AND `DeptAccessService::NAV_ACCESS`. They are independent systems. A role can have permission via config but still be denied by DeptAccessService, or vice versa. Grep both. Cross-reference.
- **Permission name prefix mismatch** — `hasPermission('penawaran.smart_pricing.view')` vs config `smart_pricing.view` (no `penawaran.` prefix). Always cross-reference the exact kode string in `config/permissions.php permissions[].kode`.
- **Duplicate workflow systems** — check for both `WorkflowIndicatorService` (pipeline 7-stage from contracts) AND `WorkflowProyek`/`WorkflowStep`/`WorkflowTahapan` models. If both exist, consolidate to one. User confusion & inconsistent progress reporting. See `references/workflow-monitoring-redesign-20260729.md`.
- **Missing overdue detection** — contracts past `tgl_akhir` with status `active`/`berjalan` not flagged — no visual/automated alert. Check if progress service also checks `tgl_akhir < now()` for active contracts.
- **Professional pipeline UI pattern** — raw circles + labels → needs: status badge, gradient progress bar, animated active/overdue nodes, legend with counts. See `resources/views/components/workflow-indicator.blade.php` — reference for future Filament ERP pipeline displays.
- **KontrakResource View page** — embed pipeline indicator using `ViewEntry::make('pipeline')->view('filament.resources.kontrak-resource.partials.pipeline-indicator')` with `getStateUsing(fn() => ...)` for dynamic stage data from `WorkflowIndicatorService::calculate($record)`. Use `Placeholder` components only for static summary cards; for dynamic calculated values use `TextEntry::make('...')->getStateUsing(fn() => ...)`.
- **Cleanup orphan systems** — when removing duplicate models (e.g. WorkflowProyek), also remove: Resource, Pages, Widgets, Migrations, and verify no FK references remain in other models (e.g. WorkflowLog::tahapan()).
- **Permission name prefix mismatch** — `hasPermission('penawaran.smart_pricing.view')` vs config `smart_pricing.view` (no `penawaran.` prefix). Always cross-reference the exact kode string in `config/permissions.php permissions[].kode`.

---

## Real-World References

Session-specific audit logs live under `references/` in this skill directory:

| File | Session | Focus |
|------|---------|-------|
| `references/erp-audit-example.md` | 2026-07-28 | Batch 1: Models, Services, Resources, Traits, Config |
| `references/erp-audit-batch3.md` | 2026-07-29 | Batch 3: Traits, Commands, Widgets, Controllers, Security |
| `references/erp-audit-batch4.md` | 2026-07-29 | Batch 4: Workflow chain audit, RAB import fix, performance, login override |
| `references/erp-audit-batch5.md` | 2026-07-29 | Batch 5: Full re-audit — 81 models, 21 services, 31 resources, bugs found: ActivityLog columns, role bypass, Sparepart casts, date null safety |
| `references/erp-audit-rbac.md` | 2026-07-29 | RBAC audit: menu-per-role analysis, DeptAccessService vs config drift, 4 hardcoded role checks, HargaReferensi permission mismatch |
| `references/erp-cipali-production-setup-20260730.md` | 2026-07-30 | CIPALI production: orphan RAB 518 item → kontrak → BOM → auto-create 162 sparepart → aktivasi gudang. Code patterns + error traces |

Load with `skill_view(name='laravel-code-audit', file_path='references/<file>')`.

## Reusable Test Script

A reusable workflow-chain verification script (`scripts/test-workflow-chain.php`)
verifies Klien → Kontrak → Termin → Pekerjaan → Faktur → Pembayaran → Aset
auto-cascade events. Copied to project `scripts/` dir and run from project root:

```bash
php scripts/test-workflow-chain.php
php scripts/test-workflow-chain.php --with-jatuh-tempo  # includes overdue + partial pay
php scripts/test-workflow-chain.php --with-adendum      # includes contract revision
php scripts/test-workflow-chain.php --keep              # keep test data
```

The script creates test data, runs all workflow steps, auto-cleans on exit.
Returns exit code 0 on all-pass, 1 on any failure.
