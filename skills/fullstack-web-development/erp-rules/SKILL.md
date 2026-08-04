---
name: erp-rules
description: Coding rules and conventions for ERP system. Anti-patterns, security rules, Blade/Laravel conventions for PT EXFERIA PUTRA INOVASI.
tags: [erp, rules, conventions, security, filament, laravel]
---

# ERP Coding Rules

## Project Location
```
C:\laragon\www\PT.EXFERIA PUTRA INOVASI\
```

## Full Rules
Read `RULES.md` in the project root for complete conventions.

## Laravel 11 Scheduler (NOT Console/Kernel.php)

Laravel 11 removed `Console/Kernel.php`. Schedules register in `bootstrap/app.php`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withSchedule(function () {
        \Illuminate\Support\Facades\Schedule::command('backup:scheduled:run')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/backup-scheduler.log'));
    })
    ->create();
```

Verify: `php artisan schedule:list`

## Critical Rules (ALWAYS follow)

### Database
1. ❌ NEVER `migrate:fresh` / `migrate:reset` / `db:wipe`
2. ✅ ALWAYS `php artisan migrate` for new features
3. ✅ ALWAYS backup before schema changes
4. ✅ ALWAYS new migration (never edit old ones)

### Filament Resources
1. ❌ NEVER create Resource without `canAccess()`
2. ✅ ALWAYS use `hasPermission('kode')` in `canAccess()`. The `admin.settings` permission is used by all 6 Settings pages (verified 2026-07-27). Reference `references/role-canaccess-map.md` for other resources. Never hardcode role checks like `role === 'R00'` — use `hasPermission()` which already handles R00 bypass.
3. ⚠️ CUSTOM PAGES (extends Page) have SEPARATE `canAccess()` from Resources — always check both when testing role access. Resources register via PanelProvider -> `resources()`, pages via `->pages()`.
4. ✅ ALWAYS match property names to form field names
5. ✅ Use `->options(fn()` on custom Pages (not `->relationship()`)
6. ✅ Use `->url()->openUrlInNewTab()` for file downloads

### Security
1. ✅ Hash passwords: `Hash::make()`
2. ✅ Always use `auth()->user()` not raw session
3. ✅ Validate uploads server-side (MIME + size)
4. ❌ NEVER commit .env or hardcode secrets

### Permission Flow (mandatory)
```
hasPermission('kode') →
  R00? return true (bypass)
  → user_permissions found? return granted/revoke
  → role_permissions found? return true
  → default: return false
```

### Currency Input
```php
TextInput::make('harga')
    ->prefix('Rp')
    ->inputMode('numeric')
```

### Currency Display (Blade)
All price columns MUST use `number_format()` — never render raw floats with `Rp` prefix.
```blade
{{-- ✅ CORRECT --}}
Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}

{{-- ❌ WRONG — renders as "Rp3" or "Rp3000" without thousand separators --}}
Rp{{ $item->harga_satuan }}
Rp {{ $item->harga_satuan }}
```
Pattern: `number_format($val, 0, ',', '.')` — zero decimals, dot separator, comma decimal point (Indonesian Rupiah).

### Currency Input (Blade Table Cell)
For editable price cells in Blade tables (e.g. RAB Workbench):
```blade
<div class="relative">
    <span class="absolute left-1.5 ... pointer-events-none">Rp</span>
    <input type="text" inputmode="numeric"
        wire:change="updateCell({{ $index }}, 'harga_satuan', $event.target.value.replace(/[^0-9]/g,''))"
        value="{{ $hargaSatDisplay }}">
</div>
```
- Prefix "Rp" as separate `<span>` (not inside input value)
- Use computed `$hargaSatDisplay` (from `number_format()`), NOT raw `$item['harga_satuan']`
- Strip non-numeric chars in `wire:change` before sending to PHP

### PPN/Tax
- Rate: 11% tarif umum UU HPP (bukan 12%). Dua sumber: `CompanySetting::get('ppn_rate')` (dari UI Preferensi Keuangan, yang dipakai Faktur model) → fallback `config('pajak.tarif_ppn_keluaran', 11)` (env TARIF_PPN_KELUARAN). Key `config('pajak.ppn')` = alias 11 (added 2026-07-31).
- ⚠️ JANGAN pakai `config('pajak.ppn', 12)` atau `config('pajak.tarif_ppn_keluaran', 12)` di kode baru — angka fallback 12 itu bug tersembunyi (sim 2026-07-31 sempat bikin faktur PPN 12%). Selalu `CompanySetting::get('ppn_rate') ?? config('pajak.tarif_ppn_keluaran', 11)`.
- BillingPerawatanService MC billing: subtotal = nilai termin, PPN dari `config('pajak.tarif_ppn_keluaran')`.

### API Response Format
```json
{"success": true, "data": {}, "message": "Description"}
```

### Git Commits
```
feat(modul): description
fix(modul): description
refactor(modul): description
```

## Role Testing & Verification (Systematic)

### Testing Approach (4 Layers)
1. **Panel Login** — `canAccessPanel()` for every user
2. **Permission Check** — `hasPermission('kode')` for every role
3. **Module Access** — `hasModuleAccess('modul')` per role
4. **Resource Access** — canViewAny() / canCreate() per resource

### Quick Permission Matrix Test
```php
// Run in artisan tinker --execute=''
$roles = ["R00","R01","R02","R03","R04","R05","R06"];
$modules = Permission::distinct()->pluck("modul")->filter()->toArray();
asort($modules);
foreach ($modules as $m) {
    echo str_pad($m,20);
    foreach ($roles as $r) {
        $user = User::where("role",$r)->first();
        echo str_pad($user->hasModuleAccess($m) ? "✓" : "✗",14);
    }
    echo "\n";
}
```

### Verified Permission Counts (as of July 2026)
| Role | Count | Key Modules |
|------|-------|-------------|
| R00 (Super Admin) | 81/81 | ALL — bypass |
| R01 (Admin Proyek) | 58/81 | kontrak, penawaran, rab, pekerjaan, faktur, klien, aset, kalender |
| R02 (Teknisi) | 14/81 | pekerjaan(view+create), gudang(view), dokumen, calendar |
| R03 (Supervisor) | 24/81 | pekerjaan(approve), approval, dashboard, calendar(assign) |
| R04 (Gudang) | 19/81 | gudang(CRUD+stock), aset, permintaan_pembelian |
| R05 (Keuangan) | 32/81 | pengeluaran(CRUD), pajak, faktur(CRUD), petty_cash, report |
| R06 (Manajer) | 44+/81 | dashboard(export), audit_trail, admin, read-only all modules |
| R07 (HRD) | 10/81 | sdm.karyawan, approval(view), sdm.departemen |

### Workflow E2E Test Sequence
1. R01 creates Penawaran → Kontrak → RAB
2. R01 creates Jadwal → assigns Teknisi
3. R02 creates Pekerjaan → upload foto/sparepart → SUBMIT
4. R03 reviews → APPROVE (or REJECT with reason)
5. R04 manages sparepart stock → transaksi masuk/keluar
6. R05 records pengeluaran → petty cash → creates Faktur → input Pajak
7. R06 views dashboard → exports report → audit trail

### Page Navigation Pitfalls
- **`/admin/rab/{id}/view`** is the Filament custom page `ViewRab.php` (RAB Workbench). Route registered by Filament panel, not in `web.php`. Source: `app/Filament/Resources/RabResource/Pages/ViewRab.php` + Blade: `resources/views/filament/resources/rab-resource/pages/view-rab.blade.php`. See `references/rab-module-views.md` for full route map.
- **Multiple views per module** — RAB has index, show (read-only), detail (workbench), create, import. Always confirm which URL/view the user is on before debugging formatting issues. The `rab.show` view already uses `number_format()` correctly.
- **Filament IS installed** (v3.3.54) but may not appear in `composer.json` or `vendor/`. The login page loads Filament CSS/JS. Filament resources are at `app/Filament/Resources/`. Blade views for Filament pages are at `resources/views/filament/`. Use `php artisan route:list | grep admin` to see Filament routes.

### RAB AI Copilot (fase 1-2, 2026-07-31)
- `app/Services/RabCopilotService.php` — generator draft RAB: template (pemasangan_pju, perawatan_pju) + volume per titik/bulan, harga berjenjang sparepart→HargaReferensi→riwayat→estimasi.
- Tombol header "✨ Buat RAB dengan AI" di CreateRab via `getHeaderActions()` → Action modal `->form([...])` (BUKAN schema — schema hanya utk Page actions, form utk Action modal). Repeater draft + checkbox Pilih → action Terapkan mengisi `$this->data['komponen']` + `$this->form->fill()`.
- Pitfall: `formatStateUsing` (number_format titik ribuan) pada TextInput numeric BREAKS programmatic fill → "field required" validasi. Jangan pakai formatStateUsing di input numerik yang diisi via code.
- Pitfall: Toggle di Repeater modal = Livewire entangle error "property cannot be found"; Checkbox aman.
- Pitfall: setelah action modal Terapkan → Livewire re-render → ref browser berubah; type ulang field form utama.
- HargaReferensi: seed via script (historis dari RabKomponen + supplier dari Sparepart.harga_jual). 36 rows. cariHarga() pakai bersihkanKeyword (stopwords m/mm/cm/w/v...).
- AiAnalysisService::analyzeRab — 9/10 matched utk PJU. AI Price dashboard = /admin/rab/ai-price, RAB list dropdown.

### Custom Filament page styling (Project Health etc.)
- public/css/filament/filament/app.css = Filament default prebuilt — TIDAK punya Tailwind utility custom (text-[9px], grid-cols-4, from-slate-900 dsb TIDAK ADA). Tidak ada pipeline npm/vite di proyek.
- Custom page style = scoped `<style>` block di blade (prefix ph-*), JANGAN andalkan Tailwind utility baru. Inline style utk gradient/warna. view:clear setelah edit blade.
- **Long PHP simulation scripts**: heredoc (`cat > x.php << 'EOF'`) fails on Windows bash with quotes/parens inside. Write the file with `execute_code` (Python) to `~/` then `cp` into the Laragon project, run with `php _script.php`. Verified 2026-07-31 PJU Tangerang sim.
- **`Kontrak::complete()` was NOT idempotent** — calling twice created duplicate assets (1 pekerjaan → 4 aset). Fixed 2026-07-31: early-return if already completed + skip aset whose `nama_aset` already exists for the kontrak. `complete()` also auto-calls `buatAsetDariPekerjaan()`.
- **RAB "final" = `is_active=false`** (WorkflowIndicatorService). RAB with `is_active=true` shows "RAB Belum final" badge even if BOM was generated. Finalize RAB before generating BOM to keep workflow green. Workflow stage order: kontrak → rab → penawaran → pekerjaan → approval → faktur → pembayaran (7 stages).
- **`transaksi_keluar.quantity` and `pekerjaan_spareparts.quantity` are INT** while `rab_komponen.volume`/`rab_material_plan_items.*` are DECIMAL — metered materials (kabel, m) get truncated (37.5 → 38). If meter-accurate quantities matter, migrate to DECIMAL(12,2).
- **Migration templates** — Always write actual column definitions in migrations. A `$table->id(); $table->timestamps();` template without the real columns will run but create empty tables. Verify schema after migration: `Schema::getColumnListing('table_name')`.
- **Filament `navigationGroup` with Unicode escape strings** (`\u2699\ufe0f`) creates a SEPARATE sidebar group instead of merging with the real emoji group. Always use literal emoji characters: `'⚙️ Pengaturan'` not `'\\u2699\\ufe0f Pengaturan'`. Duplicate groups = this exact bug.
- **Login uses email** (not username); password may differ from seeder. Verify: `password_verify('pw', $user->password)`. Superadmin: `superadmin@example.com`. Password changes between sessions — reset via: `php artisan tinker --execute="DB::table('users')->where('email','superadmin@example.com')->update(['password'=>Hash::make('newpass')]);"`
- **Finding source files when route doesn't match**: If `php artisan route:list` doesn't show a URL the browser renders, search for the page's distinctive strings (column headers like "Hrg Sat", button labels like "Simpan Semua") across all PHP/blade files. Fallback: search compiled views `storage/framework/views/*.php` for those strings. If still not found, the file may have been created by a previous AI session and deleted.
- **`canAccessPanel(null)` throws TypeError** in tinker. Use: `canAccessPanel(app('filament')->getPanel('admin'))`
- **Resource static methods need auth context** — `canViewAny($user)` won't work via raw tinker; test permissions via `hasPermission('kode')` instead
- **Custom Pages have separate canAccess()** from Filament Resources — always check both
- **DB permissions table** uses `kode`/`nama` columns, NOT `name`/`guard_name` — schema changed from Spatie
- **CompanySetting save() must use `CompanySetting::set($key, $value)`**, NOT `CompanySetting::where('key', $key)->update(['value' => $value])`. The `set()` method handles encryption for sensitive keys (e.g., `gemini_api_key`). All 6 Settings pages were fixed 2026-07-27.

### references/role-permission-matrix.md
Full per-role permission kode listing + module access matrix. Consult when adding new resources to verify existing coverage.

### references/rab-module-views.md
RAB module route/view map, column headers per view, formatting status, and debugging commands. Use when user references a specific RAB URL to determine which view file to edit.

### scripts/test-all-roles.php
Reusable script for 4-layer role testing. Run in artisan tinker to verify panel access, permission counts, module matrix, and user overrides.
