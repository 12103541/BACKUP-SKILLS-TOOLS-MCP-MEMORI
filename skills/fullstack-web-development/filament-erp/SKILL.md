---
name: filament-erp
category: fullstack-web-development
description: Patterns, pitfalls, and workflow for building Laravel + Filament v3.2 ERP applications — layout, forms, models, widgets, and business logic.
triggers:
  - AI-powered price scraping from Google for HargaReferensi
  - Pricing analysis, markup optimization, and auto-pricing
  - Data integration with HargaReferensi lookups in forms and tables
  - Building or modifying Filament admin panel features
  - Laravel Filament form/table/page/widget issues
  - ERP module development with Filament resources
  - Filament layout, CSS, or rendering issues
  - Syncing business logic across Filament models (faktur, pajak, kontrak, etc.)
  - Auditing or analyzing ERP module logic flow
  - Entity conversion between modules (e.g., penawaran → kontrak)
  - RAB/RP budget calculation with auto-sum and markup
  - Quick data entry modals (e.g., HargaReferensi manual insert)
  - RAB Workbench table layout, column sizing, sticky header
  - Klien/Model relationship audit (parent↔child, hasMany/belongsTo)
  - Pajak (tax) module verification, form, status, and Faktur auto-create PPN
  - AdminPanelProvider login/route issues
  - Custom Filament Page → ref:custom-page-fileuploadrms, workbench)
  - Blade template edits not reflecting in browser (cache, dual-path)
  - Price formatting (Rp prefix, number_format) in Filament tablesrms, wire:click)
  - Backup & restore system (mysqldump, backup schedules, restore points)
  - NavigationGroup sidebar deduplication
  - write_file vs filesystem (virtual workspace vs real Laragon filesystem)
  - Pajak tarif default mismatch between config and hardcoded values
  - FK backfill for Pajak→Faktur linking after column addition
  - Building role-based dashboards with ChartWidget per division
  - Filament ChartWidget chart not rendering / empty canvas
  - Dashboard widgets auto-discovery via discoverWidgets
  - Custom Dashboard page with styled/modern charts (Chart.js CDN in Blade)
  - Filament Panel::login() method doesn't exist error
  - User can't login (password mismatch / lock / DB issue)
  - Filament admin login failure debugging workflow
  - Role has zero permissions / empty sidebar after login
  - Syncing role_permissions for a role
  - Permission matrix for Teknisi/Supervisor/Gudang/Keuangan
  - Sharing Laravel app on LAN / network access setup
  - Apache VirtualHost for Laragon project on port 80
  - Windows Firewall port opening for web server
  - Custom login redirects
  - getWidgets() override not suppressing panel-level widgets
  - Livewire "page expired" CSRF on custom Page with $view + form()
  - APP_URL mismatch causing session cookie domain error
  - Filament Select query() and modifyQueryUsing() don't exist in v3.2
  - Role-based form field filtering (technician-only Select)
  - Status workflow enforcement (dynamic Select options by state)
  - JSON column ↔ Textarea conversion in Filament forms
  - Duplicate prevention in CreateRecord with $this->halt()
  - Dashboard section-level RBAC (getSectionsForRole + @if in Blade)
---

# Filament v3.2 ERP Development

Patterns and pitfalls for building production ERP with Laravel + Filament v3.2 on Laragon (Windows dev).

## Reference Files
- `references/faktur-pajak-sync-logic.md` — Faktur/Pajak/Kontrak sync state machine
- `references/role-permission-matrix.md` — Full permission set per role (R00-R07), SQL sync queries, verification patterns
- `references/lan-sharing-setup.md` — Laravel/Laragon LAN sharing: VirtualHost, Firewall, Apache restart, login redirect alignment
- `references/scheduler-status-automation.md` — Cron commands and status automation
- `references/manajemen-proyek-flow.md` — Manajemen Proyek module analysis and fixes
- `references/rab-import-technical.md` — RAB Excel/CSV import technical details
- `references/view-rab-read-only-pattern.md` — Read-only Excel-like table view pattern (ViewRab)
- `references/role-based-dashboard-pattern.md` — ChartWidget role-based dashboard: canView(), filters, chart types, empty canvas pitfall
- `references/session-2026-07-25-dashboard-and-pekerjaan-fixes.md` — Modern Dashboard role-based (section-level RBAC, custom Blade with Chart.js CDN) + Pekerjaan create logic (workflow, role filtering, JSON textarea, auto-generate, duplicate prevention)
- `references/rab-workbench-pattern.md` — Unified edit+analyze+pricing workbench (ViewRab evolution)
- `references/ai-price-engine-pattern.md` — AI Price Engine: local DB approach + SmartPricingService
- `references/livewire-filament-page-pitfalls.md` — Livewire property type errors: exact error→fix mappings
- `references/harga-referensi-multi-source-concept.md` — Item-centric grouped view for multi-source price comparison
- `references/harga-referensi-multi-source-pattern.md` — IMPLEMENTED: code structure, query patterns, blade pitfalls, dedup
- `references/pekerjaan-role-based-pattern.md` — Role-based form/table/action visibility per role for Pekerjaan module
- `references/vms-testing-patterns.md` — Comprehensive VMS testing patterns: server setup, auth, API endpoints (NovaStar/TB2/Player), CRUD via tinker, RBAC, import/export, maintenance, git cleanup, end-to-end workflow verification
  - `references/cipali-production-workflow.md` — Production run CIPALI 518 RAB items → TransaksiKeluar 493 → Faktur → Lunas. Includes auto-create sparepart from unmapped BOM items pattern and Dashboard Gudang `->format() on string` bug fix.
- `references/custom-page-non-livewire-routes-pattern.md` — Custom Page save via regular fetch() routes (bypasses Livewire CSRF entirely). Covers route pattern, blade pattern, why fetch beats $wire.call, $fillable + JSON column gotcha.
- `references/heroicons-v2-icon-validation.md` — blade-heroicons v2 naming convention (c-/m-/s- prefix), removed icons, validation steps. One invalid icon crashes entire admin panel.
- `scripts/validate-icons.sh`
- `references/e2e-simulation.md` + `scripts/e2e-simulate-project.php` — full-lifecycle E2E sim: standalone PHP bootstrap, DB enum constraints (kontrak.jenis, transaksi_keluar.tipe, pekerjaan.lokasi_km), relation gotchas (Pekerjaan has no transaksiKeluar(), kontrak->rab is Collection, sparepart col nama_part), multi-titik = 1 pekerjaan per titik.

## Environment
- Laragon at `C:\laragon\` — Apache + MySQL 8.0 + PHP 8.2
- Project root: `C:\laragon\www\<project-name>\`
- MySQL CLI: `"/c/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root <db> -e "<SQL>"`
- Clear cache: `php artisan optimize:clear && php artisan filament:cache-components`

## User Preferences (PT EXFERIA PUTRA INOVASI)
- Bahasa Indonesia for explanations, English code
- Prefers direct action, short feedback, minimal forms
- Each department sees only relevant menus (RBAC)
- When user says "pelajari", "konsep", "analisa" → present analysis FIRST, wait for approval before coding
- When user says "lihat" → build read-only; when "edit"/"ubah" → build editable
- Never add fields user didn't explicitly ask for
- User wants MODERN dashboard design (gradient cards, donut center text, line chart fills) — NOT basic/default Filament widgets
- Role-based dashboard = separate SECTIONS per divisi, not just filter dropdown options
- Laravel + Filament v3 on Laragon; login: superadmin / [credentials in env]

## Layout Pitfalls

### Filament 3.2 Custom Tab Navigation (Alpine.js)

Filament 3.2 does NOT have `x-filament-tabs` or `x-filament::tabs.tab` components — use Alpine.js:

```html
<div x-data="{ tab: 'manual' }">
    <nav class="flex gap-6 border-b border-gray-200 mb-6">
        <button @click="tab='manual'"
            :class="tab==='manual' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500'"
            class="pb-3 px-1 border-b-2 font-medium text-sm">Tab 1</button>
    </nav>
    <div x-show="tab==='manual'" x-cloak>Content</div>
</div>
```

Full code samples + Page `$view` + migration re-run patterns: see `references/filament-3.2-tab-migration-patterns.md`

**PROBLEM**: Filament v3.2 does NOT have `x-filament::tabs.tab` or `x-filament-tabs` blade components for custom Pages. Using either causes `InvalidArgumentException: Unable to locate a class or view for component`.

**SOLUTION**: Use Alpine.js for custom page tabs (Alpine.js is already bundled with Filament).
See `references/filament-32-custom-tabs.md` for full pattern with icons, badges, and gradient header cards.

**Also see**: `references/windows-laragon-file-write.md` for writing large PHP/blade files on Windows.

### Filament Page Wrapper Grid
Filament's `<x-filament-panels::page>` wraps content in a grid. Child `grid-cols-N` gets treated as grid item, not nested grid.

**Fix**: Use `flex` with `flex-1` on children, NOT `grid grid-cols-N`:
```html
<!-- RIGHT — stays horizontal -->
<div class="flex gap-2 mb-6">
  <div class="flex-1 px-3 py-2 border ...">Card 1</div>
</div>
```

### SPA Mode Conflicts
Remove `->spa()` from AdminPanelProvider when Main App has its own `/login` route. SPA mode causes redirect loops or wrong-login-page issues.

### Hardcoded Business Values in Model Methods
`hitungTotal()` or similar calc methods often hardcode tax rates, percentages, or fees (e.g., `round($subtotal * 0.11)`). When config files define these values, always use `config()` as source of truth.

**Fix**: Replace hardcoded values with config lookup:
```php
// WRONG — hardcoded 11%
$ppn = round($subtotal * 0.11);

// RIGHT — reads from config/pajak.php
$tarifPpn = (float) config('pajak.tarif_ppn_keluaran', 12);
$ppn = round($subtotal * $tarifPpn / 100);
```

**Pattern for static arrays**: When a Resource has `private static array $options = [...]` with configurable values, use lazy-loading:
```php
private static array $jenisTarif = [];
private static function getJenisTarif(): array
{
    if (empty(self::$jenisTarif)) {
        self::$jenisTarif = [
            'ppn_masukan'  => (float) config('pajak.tarif_ppn_masukan', 12),
            'ppn_keluaran' => (float) config('pajak.tarif_ppn_keluaran', 12),
        ];
    }
    return self::$jenisTarif;
}
```

### FK Backfill After Adding New Column
When adding a new FK column (e.g., `faktur_id` to `pajak`), existing records get NULL. Boot observers only fire on NEW changes — they don't retroactively link old data.

**Fix**: Manual SQL backfill after migration:
```sql
UPDATE pajak p JOIN faktur f ON p.nomor_faktur_pajak = f.nomor_faktur
SET p.faktur_id = f.id WHERE p.faktur_id IS NULL;
```
Match by business key (nomor, DPP amount, kontrak_id), not auto-increment ID.

### Admin Panel Login 404 — Missing `->login()`
After adding/removing panel config, `/admin/login` can return 404 even though `admin/logout` route exists. The login route is only registered when `->login()` is explicitly on the panel chain.

**Symptoms**: `GET /admin/login` → 404 Not Found; route list shows `admin/logout` but no `admin/login`.

**Fix**: Add `->login()` to AdminPanelProvider panel chain:
```php
->authGuard('web')
->login()  // <-- Required for /admin/login route
->authMiddleware([...])
```

**Recovery sequence** after any AdminPanelProvider change:
```bash
php artisan config:clear && php artisan route:clear && php artisan cache:clear && php artisan filament:cache-components
```

### IconColumn Boolean Cut Off on Wide Tables
`IconColumn::make('verified')->boolean()` renders correctly (SVG icons with fi-color-success/danger classes), but on wide tables the Status column gets visually cut off. The icons ARE in the DOM — just outside viewport.

**Fix — Compact Table Pattern** (no `->scrollable()` in Filament v3.2!):
1. Hide non-essential columns by default: `->toggleable(isToggledHiddenByDefault: true)`
2. Truncate long text: `->limit(12)` on No. Faktur, `->limit(15)` on Lawan Transaksi
3. Target: max 8-9 visible columns for a 1280px viewport

```php
// Hidden by default, user can toggle via "Pilih kolom" button
TextColumn::make('npwp')->toggleable(isToggledHiddenByDefault: true),
TextColumn::make('tarif')->toggleable(isToggledHiddenByDefault: true),
// Truncated for compact display
TextColumn::make('nomor_faktur_pajak')->limit(12),
TextColumn::make('nama_lawan_transaksi')->limit(15),
```

**Pitfall**: `Table::scrollable()` throws `BadMethodCallException` in Filament v3.2 — it does not exist on `Filament\Tables\Table`. Do NOT use it.

### Form Select Filtering — Filament v3.2

### `Select::query()` and `modifyQueryUsing()` Don't Exist
In Filament v3.2, `Select::make()->query()` and `Select::make()->modifyQueryUsing()` throw `BadMethodCallException`. The correct way to filter relationship options is to pass a closure as the **3rd argument** to `->relationship()`:

```php
// WRONG — throws BadMethodCallException
Select::make('kontrak_id')
    ->relationship('kontrak', 'nomor_kontrak')
    ->query(fn ($query) => $query->where('status', 'active'));  // ❌

Select::make('kontrak_id')
    ->relationship('kontrak', 'nomor_kontrak')
    ->modifyQueryUsing(fn ($query) => $query->where('status', 'active'));  // ❌

// RIGHT — closure as 3rd argument to relationship()
Select::make('kontrak_id')
    ->relationship('kontrak', 'nomor_kontrak', function ($query) {
        $query->where('status', 'active');  // ✅
    })
    ->searchable()
    ->preload()
    ->required()
    ->live();
```

### Role-Based Select Filtering
Filter dropdown options by user role (e.g., only technicians for a Teknisi field):
```php
Select::make('user_id')
    ->label('Teknisi')
    ->relationship('user', 'name', function ($query) {
        $query->whereIn('role', ['R02', 'R03']); // Teknisi + Supervisor
    })
    ->searchable()
    ->preload()
    ->required();
```

### Show Related Info (e.g., Client Name) When Dropdown Selected
Use a `Placeholder` that updates via `live()` on the Select field. In the Select's `afterStateUpdated`, fetch related data and set a hidden field that the Placeholder reads:

```php
// In the form() method:
Select::make('kontrak_id')
    ->label('Kontrak')
    ->relationship('kontrak', 'nomor_kontrak', fn($q) => $q->where('status', 'active'))
    ->searchable()
    ->preload()
    ->required()
    ->live() // Triggers afterStateUpdated on change
    ->afterStateUpdated(fn (callable $set, $state) => $set('klien_nama', 
        $state ? Kontrak::with('klien')->find($state)?->klien?->nama : 'Klien tidak ditemukan'
    )),

Placeholder::make('klien_info')
    ->label('Klien')
    ->content(fn (callable $get) => $get('klien_nama') ?? 'Pilih kontrak terlebih dahulu')
    ->columnSpanFull(),
```
Note: The Placeholder's `content()` can use a closure with `$get` to read other form state reactively.

### Status Workflow Enforcement
Dynamic Select options that change based on current record state. Only valid transitions are available:
```php
Select::make('status')
    ->options(function (?string $state) {
        return match ($state) {
            'draft'     => ['draft' => 'Draft', 'submitted' => 'Diajukan'],
            'submitted' => ['submitted' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'],
            'approved'  => ['approved' => 'Disetujui'],
            'rejected'  => ['rejected' => 'Ditolak', 'draft' => 'Kembali ke Draft'],
            default     => ['draft' => 'Draft', 'submitted' => 'Diajukan'],
        };
    })
    ->default('draft')
    ->native(false)
    ->required()
    // Only admin/manajer can approve — disable for others
    ->disabled(fn () => !in_array(auth()->user()->role, ['R00', 'R01', 'R03', 'R06']));
```

### JSON Column ↔ Textarea Conversion
When a model column is JSON-cast but the form uses `Textarea`, use `afterStateHydrated` to convert array→string for display and `afterStateUpdated` to convert string→array for storage:
```php
Textarea::make('dokumentasi_keterangan')
    ->label('Keterangan')
    ->rows(3)
    ->columnSpanFull()
    ->afterStateHydrated(function ($component, $state) {
        if (is_array($state)) {
            $component->state(implode("\n", $state));
        }
    })
    ->afterStateUpdated(function (callable $set, ?string $state) {
        $lines = array_filter(array_map('trim', explode("\n", $state ?? '')));
        $set('dokumentasi_keterangan', $lines ?: null);
    }),
```

### Duplicate Prevention in CreateRecord
Prevent duplicate records by checking unique combination in `mutateFormDataBeforeCreate()`:
```php
class CreatePekerjaan extends CreateRecord
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $exists = Pekerjaan::where('kontrak_id', $data['kontrak_id'])
            ->where('user_id', $data['user_id'])
            ->where('lokasi_ruas', $data['lokasi_ruas'])
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Duplikat Ditemukan!')
                ->body('Pekerjaan dengan kombinasi ini sudah ada.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt(); // Stops form submission, stays on page
        }

        return $data;
    }
}
```

### Show Related Info (e.g., Client Name) When Dropdown Selected
Use a `Placeholder` that updates via `live()` on the Select field. In the Select's `afterStateUpdated`, fetch related data and use a helper to update the Placeholder (or use a dynamic Placeholder with `getStateUsing`):
```php
// In the form() method:
Select::make('kontrak_id')
    ->label('Kontrak')
    ->relationship('kontrak', 'nomor_kontrak', fn($q) => $q->where('status', 'active'))
    ->searchable()
    ->preload()
    ->required()
    ->live() // Triggers afterStateUpdated on change
    ->afterStateUpdated(fn (callable $set, $state) => $set('klien_nama', 
        $state ? Kontrak::with('klien')->find($state)?->klien?->nama : 'Klien tidak ditemukan'
    )),

Placeholder::make('klien_info')
    ->label('Klien')
    ->content(fn (callable $get) => $get('klien_nama') ?? 'Pilih kontrak terlebih dahulu')
    ->columnSpanFull(),
```
Note: The Placeholder's `content()` can use a closure with `$get` to read other form state reactively.

### Auto-Generate Field Values in CreateRecord
Generate computed fields (like `nama_pekerjaan`) in `mutateFormDataBeforeCreate()`:
```php
protected function mutateFormDataBeforeCreate(array $data): array
```

### In-memory matching vs N×DB LIKE queries
Matching RAB komponen to sparepart via `LIKE '%keyword%'` per komponen = massive full table scans × N. Preload `Sparepart::all()` once, run keyword scoring in PHP array iteration. Exact same logic, 0 DB queries.

See also `references/erp-performance-pitfalls.md` for session-specific performance fixes.
    if (empty($data['nama_pekerjaan'])) {
        $kontrak = Kontrak::find($data['kontrak_id']);
        $data['nama_pekerjaan'] = sprintf('%s - %s (%s KM %s)',
            $kontrak?->nomor_kontrak ?? '-',
            ucfirst($data['jenis_pekerjaan']),
            $data['aset'],
            $data['lokasi_km']
        );
    }
    return $data;
}
```

### Hardcode Business Defaults in CreateRecord
When a field has a fixed business value (e.g., Admin Proyek only creates "perbaikan" jobs), set it in `mutateFormDataBeforeCreate()` — NOT in the form (don't show to user):
```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['jenis_pekerjaan'] = 'perbaikan'; // Hardcoded business rule
    $data['status'] = 'draft';              // Default workflow start state
    return $data;
}
```

## Form Input Patterns

### Currency Inputs
Use `type="text" inputmode="numeric"` with `number_format($val, 0, ',', '.')` instead of `type="number"` (which shows spin buttons and decimals).

### Repeater with Auto-Calculated Totals
Use `live()` + `afterStateUpdated()` for real-time calculations. Persist via `Hidden` fields.

### Custom Filament Page Property Binding
Every `SomeField::make('x')` needs `public $x` on the Page class. ALL public properties must use concrete defaults: `string $x = ''`, `array $x = []`, `bool $x = false`. Never use nullable type hints on Livewire-bound properties.

## Model Patterns

### Boot Observer for Cross-Model Sync
Use `wasChanged()` not `isDirty()` in `updated` callbacks. `isDirty()` returns false because model is already saved.

### Bidirectional Status Sync
Always add BOTH directions: Faktur→Termin AND Termin→Faktur. Single-direction sync is incomplete.

### Cleanup on Delete/Restore
Add `static::deleted()` to clean up auto-created records (Pajak, etc.) and recalculate progres.

**Also see**: `references/backup-system-module.md` — complete 5-tab backup system module (models, services, scheduler, migrations, pitfalls).

## Multi-Step Forms
Kontrak model MUST have `hasMany(Rab::class, 'kontrak_id')` even though Rab has `belongsTo(Kontrak)`. Always add BOTH directions.

## Navigation & Routing

### AdminPanelProvider Backslash
Use `discoverResources(for: 'App\\\\Filament\\\\Resources')` (double backslash), NOT quadruple.

### Sidebar Navigation Group Consolidation
When resources/pages are spread across multiple navigation groups (e.g., 'Gudang', '📦 Gudang', '🏠 Beranda') and need merging:

**Steps:**
1. **Enumerate** — `grep -rn "navigationGroup" app/Filament/ | sort` to see all groups and their items
2. **Identify disjoint groups** — a group with only 1-2 items may belong under another umbrella
3. **Patch group names** — batch-patch via Python to change to the canonical name
4. **Reorder sort** — Dashboard=1, Master data=2-3, Transactions=4-6, Reports=7+
5. **Skip hidden resources** — `shouldRegisterNavigation() { return false; }` resources don't matter
6. **Verify** — `php artisan view:clear && php artisan icons:clear && php artisan icons:cache && php artisan cache:clear`, then login as each role

**Pitfall — mixed group names:** Filament treats 'Gudang' and '📦 Gudang' as SEPARATE groups. Choose one canonical name per group and patch all files.

**Pitfall — sort becomes legacy after patch:** Old navigationSort values (1-100) may overlap within the new merged group. Re-sort all items in the target group to have contiguous sort values (1, 2, 3...) so order is predictable.

### Select::make()->relationship() on Custom Pages
`Select::make()->relationship()` ONLY works inside Resource forms. On custom `Page implements HasForms`, use `->options(fn () => Model::pluck())` instead. Causes `Call to a member function isRelation() on null`.

### FK Column Changes — Migration Patterns

#### Make Existing FK Nullable
When a required FK (e.g., `user_id` in `pekerjaan`) needs to become optional (technician assigned later):
```php
Schema::table('pekerjaan', function (Blueprint $table) {
    $table->foreignId('user_id')->nullable()->change();  // Was NOT NULL
    // Add new computed fields in same migration
    $table->string('nama_pekerjaan', 255)->nullable()->after('kontrak_id');
    $table->text('dokumentasi_keterangan')->nullable()->after('foto_paths');
    $table->enum('dokumentasi_tahap', ['0%', '50%', '100%'])->default('0%')->after('dokumentasi_keterangan');
});
```
**Note**: `->change()` requires `doctrine/dbal` package.

#### Add New FK Column + Backfill
When adding a new FK column, existing records get NULL. Boot observers only fire on NEW changes — they don't retroactively link old data. After migration, run manual SQL backfill:
```sql
UPDATE pekerjaan p JOIN kontrak k ON p.kontrak_id = k.id
SET p.nama_pekerjaan = CONCAT(k.nomor_kontrak, ' - Perbaikan (', p.aset, ' KM ', p.lokasi_km, ')')
WHERE p.nama_pekerjaan IS NULL;
```

### ViewRecord infolist() Requires Eager-Loaded Relationships
When `infolist()` uses `TextEntry::make('relationship.attribute')`, the ViewRecord page must eager-load those relationships, otherwise data shows empty. Add to the Resource:
```php
public static function getRecordQuery(): Builder
{
    return parent::getRecordQuery()->with(['kontrak', 'user', 'approver']);
}
```
Or override in the View page:
```php
class ViewPekerjaan extends ViewRecord
{
    public function getRecord($recordKey): Model
    {
        return Pekerjaan::with(['kontrak', 'user', 'approver'])->findOrFail($recordKey);
    }
}
```
Without this, `kontrak.nomor_kontrak` and `user.name` render blank even though the record exists.

## Dashboard Widgets — Role-Based Charts

### CRITICAL: Use ONE master widget, not many separate ChartWidgets
**DO NOT** create 8+ separate ChartWidget classes. Filament's Livewire/Alpine hydration silently fails — only 2-3 of 8+ ChartWidgets actually render. No errors in JS/PHP/log.

**CORRECT approach**: ONE `DivisionDashboardWidget` with role-based `getFilters()` and filter-driven `getData()` dispatch. Verified working 2026-07-24.

```php
class DivisionDashboardWidget extends ChartWidget
{
    public function getFilters(): ?array
    {
        $role = auth()->user()->role ?? '';
        $filters = ['overview'=>'🏢 Overview','proyek'=>'📋 Pekerjaan','faktur'=>'💰 Faktur',...];
        if (in_array($role, ['R00','R06'])) return $filters;
        return match ($role) {
            'R01' => array_filter($filters, fn($k) => in_array($k, ['overview','proyek','kontrak'])),
            'R04' => array_filter($filters, fn($k) => in_array($k, ['overview','gudang','pengajuan'])),
            'R05' => array_filter($filters, fn($k) => in_array($k, ['overview','faktur','pajak','pengeluaran'])),
            default => ['overview' => '🏢 Overview'],
        };
    }
    protected function getData(): array {
        return match ($this->filter ?? 'overview') {
            'proyek' => $this->getProyekData(),
            'faktur' => $this->getFakturData(),
            default  => $this->getOverviewData(),
        };
    }
}
```

Register in AdminPanelProvider: `->widgets([DivisionDashboardWidget::class, ...stat widgets...])`

### `canView()` for StatWidgets
StatsOverviewWidget subclasses render fine individually. Keep `canView()` for RBAC filtering:
```php
class SdmDashboardWidget extends StatsOverviewWidget {
    public static function canView(): bool {
        return in_array(auth()->user()->role ?? '', ['R00','R06','R01']);
    }
}
```

**ChartWidget key points:**
- Chart types: `'bar'`, `'doughnut'`, `'line'`, `'pie'`, `'radar'`, `'polarArea'`
- Filter-based type switching: `match ($this->filter) { ... }` in `getType()`
- `getFilters(): ?array` → `['key' => 'Label']` → renders dropdown with wire:model.live
- `protected static ?array $options` for Chart.js options (legend position, scales, etc.)

### Role → Division mapping (PT EXFERIA)
| Role | Code | Dashboard filters |
|------|------|------------------|
| Super Admin | R00 | All 8 filters |
| Admin Proyek | R01 | Overview, Pekerjaan, Kontrak & Pipeline |
| Teknisi | R02 | Overview, Pekerjaan, Pengajuan Sparepart |
| Supervisor | R03 | Overview, Pekerjaan, Pengajuan Sparepart |
| Staff Gudang | R04 | Overview, Stok Gudang, Pengajuan Sparepart |
| Keuangan | R05 | Overview, Faktur, Pajak, Pengeluaran |
| Manajer | R06 | All 8 filters |

### Reference files
- `references/role-based-dashboard-pattern.md` — Dashboard widget code & pitfalls
- `references/pdf-base-layout.md` `settings-summary-table.md` `companysetting-updateorcreate.md` — PDF layout, settings table, CompanySetting fix
- `references/livewire-filament-page-pitfalls.md` — Livewire property type errors

## RBAC & Dept Access
Use `HasDeptAccess` trait + permission-based `canAccess()` on every resource.

### Permission Flow — How hasPermission() Works
`User::hasPermission($kode)` follows this exact priority chain:
1. **R00 (Super Admin)** → always returns true, no DB check
2. **`user_permissions` table** → check for per-user override (grant=0 or grant=1); if found, return that value
3. **`role_permissions` table** → check if user's role has this permission; return exists()

This means a per-user `granted=0` override in `user_permissions` will BLOCK a permission even if the role has it. Conversely, `granted=1` will GRANT a permission even if the role doesn't have it.

### Every Resource/Page MUST Have canAccess()
If a Filament Resource or Page has NO `canAccess()` method, it is VISIBLE TO ALL USERS regardless of role. This is a security hole — even users who shouldn't see the resource can access it via direct URL or sidebar.

**Audit command** — find resources WITHOUT canAccess():
```bash
# List all Resource files
find app/Filament/Resources -name '*Resource.php' | sort

# Find which ones lack canAccess
for f in $(find app/Filament/Resources -name '*Resource.php'); do
  grep -qL 'canAccess' "$f" && echo "MISSING: $f"
done
```

**Fix pattern** — add to every Resource:
```php
use Illuminate\Support\Facades\Auth;

class XyzResource extends Resource
{
    public static function canAccess(): bool
    {
        return Auth::user()->hasPermission('module.view');
    }
}
```

### Never Hardcode Role Checks — Always Use hasPermission()
Hardcoded `in_array($user->role, ['R00', 'R05'])` in `canAccess()` is wrong because:
- Bypasses the permission system (role_permissions + user_permissions)
- Doesn't respect per-user overrides
- Breaks when new roles are added that should have access

**WRONG:**
```php
public static function canAccess(): bool
{
    $user = Auth::user();
    return in_array($user->role, ['R00', 'R05']);
}
```

**RIGHT:**
```php
public static function canAccess(): bool
{
    return Auth::user()->hasPermission('petty_cash.view');
}
```

### HasDeptAccess Trait — Verify Implementation
If a `HasDeptAccess` trait is used on pages but `checkDeptAccess()` always returns `true`, it provides zero access control. Either implement the logic or remove the trait to avoid false sense of security.

### Detect Orphaned Permissions
Permissions in the `permissions` table that no Resource, Page, or middleware references are dead data. Detect with:
```bash
# Find permission codes used in code
grep -roh "hasPermission('[^']*')" app/Filament/ | sort -u

# Compare with DB permissions
mysql -u root db -e "SELECT kode FROM permissions ORDER BY kode;"
```
Orphaned permissions (like `kalender.*` and `calendar.*` duplicates) are harmless but clutter the matrix. Clean up when safe.

### Permission Audit + Fix Workflow (Step-by-Step)
When user says "fix role permissions", "cek permission", or "perbaiki permission", follow this exact sequence:

**Step 1: Map the DB state** — dump roles, permissions, and role_permissions:
```bash
# All permissions
mysql -u root db -e "SELECT id, kode, nama, modul FROM permissions ORDER BY id;"

# Role→Permission mapping
mysql -u root db -e "SELECT rp.role, p.kode FROM role_permissions rp JOIN permissions p ON rp.permission_id=p.id ORDER BY rp.role, p.modul;"

# Users and their roles
mysql -u root db -e "SELECT id, name, email, role FROM users;"
```

**Step 2: Find resources WITHOUT canAccess():**
```bash
grep -rL 'canAccess' app/Filament/Resources/*.php  # Missing canAccess
grep -rL 'canAccess' app/Filament/Pages/*.php       # Missing canAccess on pages
```

**Step 3: Check each canAccess() uses hasPermission()** (not hardcoded role check):
```bash
grep -B1 -A5 'function canAccess' app/Filament/Resources/*.php app/Filament/Pages/*.php
```
Look for `in_array($user->role, ...)` — these bypass the permission system.

**Step 4: Fix code issues** (add canAccess, replace hardcoded checks):
```php
// Add to every Resource/Page without canAccess:
use Illuminate\Support\Facades\Auth;

public static function canAccess(): bool
{
    return Auth::user()->hasPermission('module.view');
}
```

**Step 5: Fix DB issues** (add missing role_permissions):
```sql
-- Check which roles have zero permissions
SELECT role, COUNT(*) FROM role_permissions GROUP BY role;

-- Add missing permissions for a role
INSERT INTO role_permissions (role, permission_id, created_at, updated_at)
SELECT 'R07', id, NOW(), NOW() FROM permissions WHERE kode IN (...);
```

**Step 6: Clear cache and test:**
```bash
php artisan config:cache && php artisan route:cache
```

**Step 7: Verify programmatically** (matrix test — see role-permission-matrix.md)

**Step 8: Verify via browser** — login as each role, check sidebar matches expected menus

### CRITICAL: Roles Must Have Permissions in role_permissions Table
A role with ZERO entries in `role_permissions` means the user can't see ANY Filament resource — even though `canAccessPanel()` returns true and login succeeds. The user can login but the sidebar is completely empty. This looks like "login doesn't work" but it's actually an empty permission set.

**Diagnostic query** — check if a role has any permissions:
```sql
SELECT role, COUNT(*) as permission_count 
FROM role_permissions 
GROUP BY role 
ORDER BY role;
```

**Per-role permission check:**
```sql
SELECT p.kode, p.nama, p.modul
FROM permissions p
LEFT JOIN role_permissions rp ON rp.permission_id = p.id AND rp.role = 'R02'
WHERE rp.id IS NULL
ORDER BY p.modul;
```

**Sync permissions for a role** — delete all then insert needed:
```sql
-- Clear first
DELETE FROM role_permissions WHERE role = 'R02';

-- Insert needed permissions
INSERT INTO role_permissions (role, permission_id, created_at, updated_at)
SELECT 'R02', id, NOW(), NOW() FROM permissions WHERE kode IN (
    'filament.access', 'dashboard.view',
    'pekerjaan.view', 'pekerjaan.create',
    'kontrak.view', 'kontrak.progress',
    'calendar.view', 'calendar.create',
    'approval.view',
    'dokumen.view', 'dokumen.upload',
    'gudang.view',
    'pengajuan-sparepart.view', 'pengajuan-sparepart.create',
    'pemakaian_sparepart.view', 'pemakaian_sparepart.create',
    'workflow_proyek.view'
);
```

**Verify via tinker:**
```bash
php artisan tinker --execute="
\$u = App\Models\User::where('email','teknisi1@example.com')->first();
echo 'filament.access: '.(\$u->hasPermission('filament.access')?'YA':'TIDAK').PHP_EOL;
echo 'pekerjaan.view: '.(\$u->hasPermission('pekerjaan.view')?'YA':'TIDAK').PHP_EOL;
"
```

**Minimum permissions per role** — see `references/role-permission-matrix.md` for full assignment table.

### Permission Assignment Patterns by Role

| Role | Code | Minimum Permissions |
|------|------|-------------------|
| Super Admin | R00 | All (auto-granted via `isSuperAdmin()`) |
| Admin Proyek | R01 | filament.access, dashboard.view, kontrak.*, pekerjaan.*, rab.view, klien.view, penawaran.* |
| Teknisi Lapangan | R02 | filament.access, dashboard.view, pekerjaan.view/create, kontrak.view/progress, calendar.view/create, dokumen.view/upload, gudang.view, pengajuan-sparepart.view/create, pemakaian_sparepart.view/create, workflow_proyek.view |
| Supervisor Teknik | R03 | R02 permissions + pekerjaan.approve, pengajuan-sparepart.approve, permintaan_pembelian.*, calendar.assign, sdm.karyawan |
| Staff Gudang | R04 | filament.access, dashboard.view, gudang.*, sparepart.*, stock_opname, pengajuan-sparepart.view/approve |
| Keuangan | R05 | filament.access, dashboard.view, faktur.*, pajak.*, pengeluaran.*, petty_cash.view |
| Manajer | R06 | All (like R00 but via permissions) |

**Key rule**: `filament.access` is ABSOLUTELY REQUIRED for any Filament role. Without it, the user can never enter the admin panel regardless of other permissions.

## RAB Total Auto-Calculate
Let `Rab::hitungTotal()` be the SINGLE calculation path. Do NOT use raw SQL in afterCreate/afterSave — it bypasses markup logic.

## Custom Dashboard Page (Modern/Styled Charts)

### Section-Level RBAC (Not Just Filter Dropdown)
The filter dropdown alone isn't enough — KPI cards and chart sections must also be role-restricted. Implement `getSectionsForRole()` in the dashboard page class and use `@if(in_array('section', $sections))` in Blade:

```php
// In ModernDashboard.php
protected function getSectionsForRole(string $role): array
{
    if (in_array($role, ['R00', 'R06'])) {
        return ['overview', 'proyek', 'keuangan', 'gudang', 'sdm'];
    }
    return match ($role) {
        'R01' => ['proyek'],                    // Admin Proyek
        'R02', 'R03' => ['proyek', 'gudang'],   // Teknisi + Supervisor
        'R04' => ['gudang'],                     // Staff Gudang
        'R05' => ['keuangan'],                   // Staff Keuangan
        default => ['overview'],
    };
}

// In getData() — only query data for visible sections
if (in_array('proyek', $sections)) {
    $pekerjaanStatus = DB::table('pekerjaan')->...;
    $kontrakStatus = DB::table('kontrak')->...;
    $kpi['total_pekerjaan'] = DB::table('pekerjaan')->count();
    $kpi['total_kontrak'] = DB::table('kontrak')->count();
}
// KPI cards only show for keys that exist in $kpi array
```

```html
{{-- In Blade view --}}
<div class="role-badge">👤 {{ $roleName }} · {{ auth()->user()->name }}</div>

{{-- KPI cards — only for sections user can see --}}
@if(isset($kpi['total_pekerjaan']))
    <div class="kpi-card kpi-pekerjaan">...</div>
@endif

{{-- Charts — conditional per section --}}
@if(in_array('proyek', $sections))
    <div class="section-divider">📋 Manajemen Proyek</div>
    <div class="chart-grid">
        @if(!empty($data['pekerjaanStatus']))
            <div class="chart-card"><canvas id="chartPekerjaan"></canvas></div>
        @endif
    </div>
@endif
```

**Key insight**: Both the data queries AND the Blade rendering must be gated by sections. Querying unused data wastes resources and leaks information.

### `->dashboard()` Does NOT Exist in Filament v3.2
Calling `$panel->dashboard(SomePage::class)` throws `BadMethodCallException: Method Filament\Panel::dashboard does not exist.` The dashboard page is determined by which registered page extends `Filament\Pages\Dashboard` (which has `$routePath = '/'`).

### Registering a Custom Dashboard
Register the custom dashboard page in `->pages([...])`. It MUST extend `Filament\Pages\Dashboard`:
```php
class ModernDashboard extends Dashboard  // extends Filament\Pages\Dashboard
{
    protected static string $view = 'filament.pages.modern-dashboard';
    protected static ?int $navigationSort = -2;
}
```
In AdminPanelProvider:
```php
->pages([
    \App\Filament\Pages\ModernDashboard::class,
    // ... other pages
])
```

### PITFALL: `getWidgets()` Override Doesn't Suppress Panel-Level Widgets
Overriding `getWidgets()` to return `[]` in a Dashboard subclass does NOT prevent widgets registered in `AdminPanelProvider->widgets([...])` from rendering. The `filament-panels::pages.dashboard` view renders widgets via `getVisibleWidgets()` which calls the panel's widget registry.

**This is the single biggest pitfall for custom dashboards** — verified 2026-07-24:
- Route correctly points to `ModernDashboard` (verified via `php artisan route:list`)
- `getWidgets()` returns `[]`
- But DivisionDashboardWidget + stat widgets STILL render on the page
- Root cause: Filament's dashboard Blade view calls panel-level widget registry, bypassing page-level override

**WORKAROUND — Use raw Chart.js in Blade view (bypass Filament widgets entirely):**
```php
class ModernDashboard extends Dashboard
{
    protected static string $view = 'filament.pages.modern-dashboard';
    
    // Pass data to view instead of using widgets
    public function getData(): array
    {
        return [
            'kpi' => [...],
            'pekerjaanStatus' => DB::table('pekerjaan')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status')->toArray(),
            // ... more data
        ];
    }
}
```
Blade view uses Chart.js CDN directly:
```html
<x-filament-panels::page>
@php $data = $this->getData(); @endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chart1'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode(array_keys($data['pekerjaanStatus'])) !!},
        datasets: [{ data: {!! json_encode(array_values($data['pekerjaanStatus'])) !!}, ... }]
    }
});
</script>
</x-filament-panels::page>
```

**Why this works:** The custom Blade view completely replaces Filament's dashboard view. No widget rendering at all — just raw Chart.js with full CSS control. Enables gradient cards, donut center text, line charts with gradient fills, etc.

### Styling Pattern: Modern Dashboard Cards
```css
.kpi-card { border-radius: 16px; padding: 1.25rem 1.5rem; color: #fff; 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.donut-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); }
```

### Filament `discoverPages` vs Explicit Pages
- `->discoverPages(in: app_path('Filament/Pages'), for: '...')` auto-registers ALL pages in that directory
- `->pages([...])` ADDS additional pages (doesn't replace discovered ones)
- If `discoverPages` finds a class that extends `Filament\Pages\Dashboard`, it will conflict with your custom dashboard
- `ModernDashboard` extending `Dashboard` inherits `$routePath = '/'` which maps to the panel root

## Filament Asset Publishing (CRITICAL)
After any cache clear or fresh setup, Filament chart/stats assets MUST be published:
```bash
php artisan config:clear && php artisan route:clear && php artisan cache:clear && php artisan filament:assets
```
Without `filament:assets`, Chart.js is NOT loaded → all ChartWidget canvases render as empty white boxes. Verify: `typeof Chart !== 'undefined'` in browser console. Asset path: `public/js/filament/widgets/components/chart.js`.

## Filament Custom Page with Forms — Blade Template (Verified 2026-07-25)

When a custom `Page` (not EditRecord/CreateRecord) needs forms, the Blade template approach differs from standard Filament pages:

### WRONG patterns (all cause errors):
```blade
@extends('filament-panels::pages/page')  ← View [pages.page] not found
{{ $form->render() }}                     ← Undefined variable $form
{{ $getTitle() }}                          ← Undefined variable $getTitle
```

### RIGHT pattern — PHP class:
```php
class ExecutePekerjaan extends Page
{
    protected static string $resource = PekerjaanResource::class;
    protected static string $view = 'filament.resources.xxx.pages.execute-xxx';
    public Pekerjaan $record;
    public ?array $data = [];

    public function form(Form $form): Form {
        return $form->schema([...])->statePath('data')->model($this->record);
    }
    protected function getFormActions(): array { return []; } // No default Save/Cancel
}
```

### RIGHT pattern — Blade:
```blade
<x-filament-panels::page>
    <form wire:submit.prevent="save" class="fi-form">
        <div class="fi-form-components">{{ $this->form }}</div>
        <div class="fi-form-actions flex gap-3 mt-6 pt-4 border-t">
            <x-filament::button type="submit">Simpan</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
```

### URL Generation Pitfalls:
- `static::getResource()` → **method not found** on Resource classes — use `static::getUrl()` directly
- `getUrl('index')` → **TypeError** (expects array) — use `getUrl(['index'])`
- Action URL pattern: `->url(fn ($record): string => PekerjaanResource::getUrl('execute', ['record' => $record]))`
- Redirect pattern: `$this->redirect(static::getUrl(['index']))`

## PHP Files with Namespaces — Avoid Patch Tool
The `patch` tool escapes `\` in PHP namespace separators → `Tables\Columns` becomes `Tables\\Columns` → PHP parse error. For any file containing `\\` namespace paths, use `write_file` (full overwrite) instead of `patch`/`replace`.

## Laravel/Laragon LAN Sharing

Making the ERP accessible from other computers on the same network.

### Step 1: Find LAN IP
```bash
ipconfig | grep "IPv4" | grep -v "169.254"
```

### Step 2: Create VirtualHost (override 00-default.conf)
Laragon's default vhost (`00-default.conf`) serves `C:/laragon/www` as document root — the app shows at a long path with spaces. Create a dedicated vhost:

```bash
# Disable default
mv /c/laragon/etc/apache2/sites-enabled/00-default.conf /c/laragon/etc/apache2/sites-enabled/00-default.conf.bak

# Create dedicated vhost
cat > /c/laragon/etc/apache2/sites-enabled/erp.conf << 'VEOF'
<VirtualHost *:80>
    DocumentRoot "C:/laragon/www/PT.EXFERIA PUTRA INOVASI/public"
    <Directory "C:/laragon/www/PT.EXFERIA PUTRA INOVASI/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
VEOF
```

### Step 3: Update APP_URL in .env
```bash
sed -i 's|APP_URL=http://localhost:5500|APP_URL=http://<LAN_IP>|' .env
php artisan config:cache && php artisan route:cache
```

### Step 4: Open Windows Firewall (port 80)
Requires admin elevation:
```bash
powershell.exe -Command "Start-Process -FilePath 'netsh' -ArgumentList 'advfirewall firewall add rule name=\"ERP - HTTP\" dir=in action=allow protocol=tcp localport=80' -Verb RunAs -Wait"
```

### Step 5: Restart Apache
Apache on Windows doesn't auto-restart. Kill httpd.exe and let Laragon respawn it, or start manually:
```bash
powershell.exe -Command "Stop-Process -Name httpd -Force"
# Laragon auto-restarts, or start manually:
"/c/laragon/bin/apache/httpd-2.4.54-win64-VS16/bin/httpd.exe" &
```

### Step 6: Clear all caches
```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Verify
```bash
curl -s -o /dev/null -w "%{http_code}" "http://<LAN_IP>/admin/login"
# Should return 200
```

### PITFALL: Symlinks from Git Bash
`mklink /D` and `mklink /J` do NOT work from Git Bash. Use PowerShell or cmd.exe:
```bash
cmd.exe //C "mklink /J C:\\laragon\\www\\erp \"C:\\laragon\\www\\PT.EXFERIA PUTRA INOVASI\\public\""
```

### PITFALL: `00-default.conf` Can Reappear After Apache/Laragon Restart (Verified 2026-07-25)
Laragon may recreate `00-default.conf` in `sites-enabled/` after Apache restart, Laragon restart, or config cache operations. When this happens, the `<VirtualHost _default_:80>` block takes precedence over `<VirtualHost *:80>` — all requests go to `C:/laragon/www` (generic root) instead of your Laravel `public/` directory.

**Symptoms**: `curl http://localhost/admin/login` returns `404 Not Found`, but `curl http://localhost/` returns 200. Error is from Apache (not Laravel).

**Detection**:
```bash
ls /c/laragon/etc/apache2/sites-enabled/
# If 00-default.conf exists alongside erp-exferia.conf → problem
grep VirtualHost /c/laragon/etc/apache2/sites-enabled/00-default.conf
# _default_:80 takes precedence over *:80
```

**Fix**:
```bash
mv /c/laragon/etc/apache2/sites-enabled/00-default.conf /c/laragon/etc/apache2/sites-enabled/00-default.conf.disabled
# Then restart Apache
taskkill //F //IM httpd.exe
/c/laragon/bin/apache/httpd-2.4.54-win64-VS16/bin/httpd.exe
```

**Permanent monitoring**: After ANY Apache/Laragon restart, verify:
```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost/admin/login
# Must return 200, not 404
```

### PITFALL: IP changes with DHCP
LAN IP can change between reboots. After reboot, re-check with `ipconfig` and update `APP_URL` in `.env` + re-cache. A static IP or hostname entry is more reliable.

## Aligning Custom + Filament Login Redirects

When the ERP has both a custom `/login` and Filament `/admin/login`, ALL unauthenticated routes must redirect to the SAME login page. Otherwise users get inconsistent behavior depending on which URL they hit.

### Routes to update (routes/web.php):
```php
// Root — redirect to Filament login
Route::get('/', function () {
    return redirect('/admin/login');
})->name('home');

// Custom login GET — also redirect to Filament
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');
```

### AuthController — showLogin & logout:
```php
public function showLogin()
{
    return redirect('/admin/login');
}

public function logout()
{
    // ... log activity ...
    Auth::logout();
    return redirect('/admin/login'); // NOT route('login')
}
```

**Why**: Users accessing from LAN type `http://<IP>/` which hits root route. If root redirects to `/login` (custom), they see the wrong login page. All paths must converge on `/admin/login`.

### After any route changes:
```bash
php artisan config:cache && php artisan route:cache
```

## Role-Based Form & Table Filtering (per-user visible fields)

### Different Roles See Different Forms — NOT the Same Form
When Admin Proyek creates a schedule and Teknisi executes it, they MUST see different form sections. Do NOT build one form with disabled fields — use visibility:

```php
public static function form(Form $form): Form
{
    $user = auth()->user();
    $isAdmin = in_array($user->role, ['R00', 'R01', 'R06']);
    $isTeknisi = $user->role === 'R02';

    return $form->schema([
        FormSection::make('Data Pekerjaan')
            ->schema([
                Select::make('kontrak_id')
                    ->disabled(!$isAdmin),  // Teknisi sees value but can't change
                Select::make('user_id')
                    ->visible($isAdmin),    // HIDDEN from teknisi entirely
                TextInput::make('lokasi_ruas')
                    ->disabled(!$isAdmin),
            ]),
        FormSection::make('Dokumentasi')
            ->schema([
                FileUpload::make('foto_paths')
                    ->disabled(!$isTeknisi),  // Admin can't upload fotos
                Select::make('dokumentasi_tahap')
                    ->disabled(!$isTeknisi),
            ])
            ->visible($isTeknisi || $isAdmin), // Only relevant roles see section
    ]);
}
```

### Table Query Filtering — `modifyQueryUsing()` on Table (CRITICAL)
Filament v3 `getEloquentQuery()` on Resource DOES work but `modifyQueryUsing()` on the Table is MORE RELIABLE for role-based row filtering. In testing (2026-07-25), `getEloquentQuery()` alone did NOT filter the table — rows showed for all users. Only adding `modifyQueryUsing()` made it work.

**Use BOTH for belt-and-suspenders:**

```php
public static function table(Table $table): Table
{
    $user = auth()->user();
    $isTeknisi = $user->role === 'R02';
    $isSupervisor = $user->role === 'R03';

    return $table
        ->modifyQueryUsing(function ($query) use ($user, $isTeknisi, $isSupervisor) {
            if ($isTeknisi) {
                $query->where('pekerjaan.user_id', $user->id);
            }
            if ($isSupervisor) {
                $query->where(function ($q) use ($user) {
                    $q->where('pekerjaan.user_id', $user->id)
                      ->orWhere('pekerjaan.status', 'submitted');
                });
            }
        })
        ->columns([...])
```

**Pitfall**: `getEloquentQuery()` alone may not filter the table in all Filament v3.2 scenarios. Use BOTH `getEloquentQuery()` AND `modifyQueryUsing()` for belt-and-suspenders.

### Table Column Visibility by Role
```php
TextColumn::make('kontrak.klien.nama')
    ->visible(fn () => $isAdmin || $isSupervisor),  // Teknisi doesn't need to see client
```

### Status-Based Workflow — 6 Values
Use a 6-value ENUM for job workflow. Code references MUST match DB ENUM exactly:

```sql
-- Database ENUM
enum('draft','assigned','in_progress','submitted','approved','rejected')
```

```php
// Status transitions in ListRecords actions
match ($record->status) {
    'draft'        => /* Admin can Assign or Submit */,
    'assigned'     => /* Teknisi can Execute */,
    'in_progress'  => /* Teknisi can Execute */,
    'submitted'    => /* Supervisor can Approve or Reject */,
    'approved'     => /* Terminal state */,
    'rejected'     => /* Can revise back to draft */,
}
```

**Pitfall**: If code uses `status='assigned'` but ENUM only has `draft/submitted/approved/rejected`, MySQL silently ignores the update or returns error. ALWAYS verify ENUM values match code.

### Filament Page `canAccess()` vs `authorizeAccess()`

`canAccess()` is **STATIC** — cannot access `$this->record`. Use it only for simple role/permission checks:

```php
// Resource canAccess — static, no instance data
public static function canAccess(): bool
{
    return Auth::user()->hasPermission('pekerjaan.view');
}
```

For Page classes that need instance data (e.g., "is this my pekerjaan?"), use `authorizeAccess()` which runs AFTER `mount()`:

```php
class ExecutePekerjaan extends Page
{
    public Pekerjaan $record; // set by mount()

    public function authorizeAccess(): void
    {
        parent::authorizeAccess();

        $user = Auth::user();
        $isAdmin = in_array($user->role, ['R00', 'R01', 'R06']);

        if ($isAdmin) return; // Admin can view all

        // Teknisi: only their own
        if ($user->role === 'R02' && $this->record->user_id === $user->id) return;

        abort(403, 'Anda tidak memiliki akses.');
    }
}
```

### Filament v3 `canAccess()` Signature
MUST include `array $parameters = []` to match parent class:

```php
// WRONG — FatalError
public static function canAccess(): bool { ... }

// RIGHT
public static function canAccess(array $parameters = []): bool { ... }
```

### Actions by Role on ListRecords Page — CRITICAL: Place in Resource table(), NOT ListRecords

**WARNING (Verified 2026-07-25):** `ListXxx::getTableActions()` does NOT work in Filament v3.2. Actions defined there are silently ignored. ALL table row actions MUST be in the Resource's `table()` method via `->actions([...])`.

Use `visible(fn)` on each action to control who sees what:

```php
protected function getTableActions(): array
{
    $user = auth()->user();
    $isAdmin = in_array($user->role, ['R00', 'R01', 'R06']);
    $isTeknisi = $user->role === 'R02';
    $isSupervisor = $user->role === 'R03';

    $actions = [];

    if ($isAdmin) {
        $actions[] = Action::make('assign_teknisi')
            ->visible(fn (Pekerjaan $record) => $record->status === 'draft' && !$record->user_id)
            ->form([...])
            ->action(...);
    }

    if ($isTeknisi) {
        $actions[] = Action::make('execute')
            ->visible(fn (Pekerjaan $record) =>
                in_array($record->status, ['assigned', 'in_progress'])
                && $record->user_id === $user->id)
            ->url(...);
    }

    if ($isSupervisor || $isAdmin) {
        $actions[] = Action::make('approve')
            ->visible(fn (Pekerjaan $record) => $record->status === 'submitted');
    }

    $actions[] = Actions\ViewAction::make(); // Everyone can view

    return $actions;
}
```

### Blade View Status Checks Must Match ENUM
After changing ENUM values, update ALL Blade views that check status:

```blade
{{-- BEFORE (broken) --}}
@if (in_array($record->status, ['draft', 'in_progress', 'completed']))

{{-- AFTER (correct) --}}
@if (in_array($record->status, ['assigned', 'in_progress']))
```

## CSRF / Livewire "Page Has Expired" — Custom Page + $view (Verified 2026-07-25)

When a custom `Page` (not EditRecord/CreateRecord) has `$view` pointing to a custom blade AND a `form()` method, Livewire may trigger a post-mount AJAX re-render that fails CSRF validation. Symptoms: page title renders OK but Livewire immediately shows "This page has expired" confirm dialog.

### Root causes:
1. **APP_URL mismatch** — `APP_URL=http://192.168.0.6` in .env but browser accesses via `http://localhost`. Cookie domain mismatch → session not sent with AJAX requests.
2. **Custom blade + form() lifecycle** — `BasePage` has `InteractsWithForms` trait. When custom blade renders `{{ $this->form }}`, Livewire may trigger a form state sync request. If session cookie doesn't match, CSRF fails.

### Fix sequence:
1. Ensure APP_URL matches access URL: `APP_URL=http://localhost` for localhost access
2. Clear all caches: `php artisan config:cache && php artisan route:cache && php artisan view:cache`
3. If still failing: switch to plain Blade + `wire:model` approach (see below)
4. Last resort: use `EditRecord` base class

### BEST FIX: Regular `fetch()` Routes — Bypass Livewire Entirely (Verified 2026-07-25)
**CRITICAL (2026-07-25, verified multiple times):** ALL Livewire mechanisms fail CSRF on custom Pages with `$view`. This includes `wire:model`, `wire:click`, `$wire.call()` via Alpine.js, `@this.call()`, and `$L.dispatch()`. The ONLY reliable approach is regular `fetch()` to Laravel routes.

**See `references/custom-page-non-livewire-routes-pattern.md` for complete implementation.**

**What triggers CSRF (ALL unsafe — verified failing):**
- `wire:model="prop"` / `wire:model.live="prop"` — AJAX on every change ❌
- `wire:click="method"` — AJAX on click ❌
- `{{ $this->form }}` — Livewire form state sync ❌
- `$wire.call('method')` via Alpine.js x-on:click — STILL sends Livewire AJAX ❌
- `@this.call()` in regular `<script>` — not compiled (literal text output) ❌
- `$L.dispatch()` from HTML `onclick` — unreliable, doesn't fire ❌

**What works (safe):**
- `fetch('/route/path', ...)` with CSRF token from `<meta name="csrf-token">` — regular Laravel route, NOT Livewire ❌→✅
- Plain HTML `<select>`, `<input>`, `<textarea>` with `id` — no Livewire binding
- `<button onclick="jsFunction()">` — pure JS, no Livewire

**The pattern — Alpine.js `$wire.call()` (PROVEN WORKING):**
1. Render all form fields as plain HTML with `id` attributes (NO wire: anything)
2. Each save button: `<div x-data><button x-on:click="$wire.call('save', ...)">Save</button></div>`
3. JS reads form values inline or via helper function, passes as `$wire.call()` params
4. PHP method receives values as parameters, saves to DB
5. `$this->dispatch('refresh')` to re-render page with updated data

### CRITICAL: `@this.call()` Does NOT Work in Regular `<script>` Tags (Livewire 3)

In Livewire 3, `@this` is ONLY compiled inside `@script` / `@endScript` blocks. In a regular `<script>` tag, `@this.call(...)` is rendered as literal text (NOT valid JavaScript) — the button appears to do nothing, no error visible to user.

**WRONG — `@this.call()` in regular `<script>` (silently fails):**
```blade
<script>
function doSave() {
    const val = document.getElementById('myInput').value;
    @this.call('save', val);  // ❌ Literal text in output
}
</script>
<button onclick="doSave()">Save</button>
```

**WRONG — `$L.dispatch()` from HTML onclick (also unreliable):**
```blade
<button onclick="$L.dispatch('doSave')">Save</button>
@script
<script>
$L.on('doSave', () => {
    $wire.call('save', val);  // ❌ Unreliable dispatch from HTML onclick
});
</script>
@endScript
```

**RIGHT — Alpine.js `$wire.call()` via x-on:click (PROVEN WORKING):**
```blade
**The pattern — Regular `fetch()` to Laravel Routes (PROVEN WORKING):**
1. Define POST routes in `routes/web.php` (inside `web` middleware group)
2. Render all form fields as plain HTML with `id` attributes (NO wire: anything)
3. Each save button: `<button onclick="saveStep(N)">` calls a JS function
4. JS function reads form values via `document.getElementById()`, sends via `fetch()` with CSRF token
5. Route handler saves to DB, returns JSON
6. `location.reload()` to re-render page with updated data

**For multi-step forms (3 tahap mandiri):**
```blade
<button onclick="saveStep(0)">💾 Simpan Tahap 0%</button>
<button onclick="saveStep(50)">💾 Simpan Tahap 50%</button>
<button onclick="saveStep(100)">💾 Simpan Tahap 100%</button>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const RECORD_ID = {{ $recordId }};

async function saveStep(percent) {
    const tahap = percent + '%';
    const ket = document.getElementById('input-ket-' + percent)?.value || '';
    const files = document.getElementById('input-foto-' + percent)?.files || [];
    
    let photoPaths = [];
    if (files.length > 0) {
        const fd = new FormData();
        for (let f of files) fd.append('photos[]', f);
        const res = await fetch('/pekerjaans/upload-photos', {
            method: 'POST', body: fd,
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.paths) photoPaths = data.paths;
    }
    
    const res = await fetch('/pekerjaans/' + RECORD_ID + '/save-step', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ tahap, keterangan: ket, photo_paths: photoPaths }),
    });
    const data = await res.json();
    if (data.success) { alert('✅ Tahap ' + tahap + ' tersimpan!'); location.reload(); }
}
</script>
```

**For GPS (plain JS + fetch):**
```blade
<div x-data="{ loading: false, msg: '' }">
    <button @click="
        loading = true; msg = 'Mengambil GPS...';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                fetch('/pekerjaans/' + RECORD_ID + '/save-gps', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy })
                }).then(r => r.json()).then(d => { loading = false; msg = d.success ? '✅ GPS tersimpan' : '❌ ' + d.error; setTimeout(() => location.reload(), 1000); });
            },
            (err) => { msg = '❌ ' + err.message; loading = false; },
            { enableHighAccuracy: true, timeout: 15000 }
        );
    " :disabled="loading">📍 Ambil GPS</button>
    <span x-text="msg"></span>
</div>
```

This approach COMPLETELY avoids Livewire AJAX on form interactions → NO CSRF trigger.
// PHP — NO form() method, NO wire:model properties
// Methods are called by regular routes, NOT Livewire
class ExecutePekerjaan extends Page {
    public Pekerjaan $record;

    public function mount(Pekerjaan $record): void {
        $this->record = $record;
    }
    // Note: save/saveStep methods NOT needed on Page class
    // Route closures in web.php handle the actual save logic
}
```

```blade
{{-- Blade — plain HTML, ZERO wire: attributes on form elements --}}
<select id="input-tahap">
    <option value="0%">0% (Belum Mulai)</option>
    <option value="50%">50% (Proses)</option>
</select>
<textarea id="input-keterangan" rows="4"></textarea>

{{-- Button calls JS function — NO wire:click, NO $wire.call --}}
<button type="button" onclick="saveStep(0)">Simpan</button>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const RECORD_ID = {{ $recordId }};

async function saveStep(percent) {
    const tahap = percent + '%';
    const ket = document.getElementById('input-ket-' + percent)?.value || '';
    const res = await fetch('/pekerjaans/' + RECORD_ID + '/save-step', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ tahap, keterangan: ket, photo_paths: [] }),
    });
    const data = await res.json();
    if (data.success) { alert('✅ Tersimpan!'); location.reload(); }
}
</script>
```

**For file uploads via fetch() (two-step: upload then save):**
```blade
<script>
async function saveStep(percent) {
    const tahap = percent + '%';
    const ket = document.getElementById('input-ket-' + percent)?.value || '';
    const files = document.getElementById('input-foto-' + percent)?.files || [];
    
    let photoPaths = [];
    if (files.length > 0) {
        const fd = new FormData();
        for (let f of files) fd.append('photos[]', f);
        const res = await fetch('/pekerjaans/upload-photos', {
            method: 'POST', body: fd,
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.paths) photoPaths = data.paths;
    }
    
    const res = await fetch('/pekerjaans/' + RECORD_ID + '/save-step', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ tahap, keterangan: ket, photo_paths: photoPaths }),
    });
    const data = await res.json();
    if (data.success) { alert('✅ Tersimpan!'); location.reload(); }
}
</script>
```

This approach COMPLETELY avoids Livewire AJAX on form interactions → NO CSRF trigger.

### When to use EditRecord instead of custom Page:
- If you need Filament's built-in form lifecycle (validation, statePath, model binding) → use EditRecord
- If you want simpler, more control, no CSRF risk → use plain Blade + wire:model

### CRITICAL: Custom Page + form() — CSRF Only on Cloud Browsers (Verified 2026-07-25)
Testing revealed that the "page expired" CSRF error on custom `Page + $view + form()` is **browser-specific**:
- **Browserbase cloud browser**: ALWAYS shows "page expired" — even with minimal blade (no form, no wire: attributes). Cloud browser cannot persist session cookies properly for localhost.
- **Local browser (Chrome/Edge on same machine)**: Works FINE after APP_URL fix. User's screenshot confirmed the test page rendered correctly.

**Root cause**: Cloud browsers access `localhost` from a different network context → session cookie domain mismatch → Livewire CSRF fails.

**For local dev**: APP_URL fix is sufficient. Don't waste time debugging blade templates if the page works in your local browser.
**For cloud browser testing**: This is a known limitation. Test critical pages in local browser instead.

### APP_URL pitfall (Laragon dev):
```env
# WRONG — causes cookie domain mismatch when accessing via localhost
APP_URL=http://192.168.0.6

# RIGHT — matches the access URL
APP_URL=http://localhost
# Or add SESSION_DOMAIN= to prevent domain issues
```

## Multi-Step Documentation Pattern (3 Tahap Mandiri)

When a process has multiple stages (e.g., 0%/50%/100% documentation), EACH stage should have its OWN independent form — not a single dropdown that changes the form. User corrected this: "tiap step persentase mempunyai form masing-masing, bisa memasukkan dokumen bersamaan, tidak satu proses berulang."

### DB Pattern — JSON column for multi-step data
```sql
ALTER TABLE pekerjaan ADD COLUMN dokumentasi_steps JSON AFTER dokumentasi_keterangan;
```

**Data shape:**
```json
{
  "0%": {
    "photos": ["pekerjaan/foto/abc.jpg"],
    "keterangan": "Dokumentasi awal...",
    "saved_at": "2026-07-25T10:00:00+07:00"
  },
  "50%": {
    "photos": ["pekerjaan/foto/def.jpg"],
    "keterangan": "Proses pengerjaan...",
    "saved_at": "2026-07-25T14:00:00+07:00"
  },
  "100%": null
}
```

### Blade Pattern — 3 independent sections
```blade
@php $steps = $record->dokumentasi_steps ?? []; @endphp

@foreach(['0%' => ['Awal', 'gray'], '50%' => ['Proses', 'yellow'], '100%' => ['Selesai', 'green']] as $tahap => [$label, $color])
    @php $step = $steps[$tahap] ?? null; @endphp
    <div class="fi-section ...">
        <div class="fi-section-header">
            <span class="badge badge-{{ $color }}">{{ $tahap }}</span>
            <h2>Dokumentasi {{ $label }}</h2>
            @if($step && !empty($step['photos']))
                <span class="ml-auto text-green-600">✅ Sudah diisi</span>
            @endif
        </div>
        {{-- Show existing photos --}}
        {{-- Plain HTML form: input-foto-{tahap}, input-ket-{tahap} --}}
        {{-- Button: onclick="doSaveStep('{$tahap}')" --}}
    </div>
@endforeach
```

### PHP Pattern — Route handler (in routes/web.php, NOT on Page class)
```php
Route::post('/pekerjaans/{pekerjaan}/save-step', function (Request $request, $pekerjaan) {
    $record = Pekerjaan::findOrFail($pekerjaan);
    $tahap = $request->input('tahap');
    $keterangan = $request->input('keterangan', '');
    $photoPaths = $request->input('photo_paths', []);
    
    $steps = $record->dokumentasi_steps ?? [];
    $existingPhotos = $steps[$tahap]['photos'] ?? [];
    $steps[$tahap] = [
        'photos' => array_merge($existingPhotos, $photoPaths),
        'keterangan' => $keterangan,
        'saved_at' => now()->toIso8601String(),
    ];
    
    $record->update(['dokumentasi_steps' => $steps]);
    return response()->json(['success' => true, 'steps' => $steps]);
})->name('pekerjaans.save-step');
```

### Key rules:
- Each tahap has its own file input, textarea, and save button
- All tahap can be filled simultaneously (no sequential requirement)
- Photo upload via `fetch()` → return paths → include in save-step `fetch()` call
- Submit for review requires 100% step to be filled
- Visual indicator: ✅ "Sudah diisi" badge when step has photos
- Save logic lives in `routes/web.php` route closures, NOT in Page class methods

### Common Pitfalls
See `references/common-pitfalls.md` for the full pitfalls table (filesystem mismatch, migration templates, navigation emoji groups, model mismatch, password defaults, canAccess, blade tabs, Alpine.js patterns).

### Settings Pages Pattern
See `references/settings-pages-pattern.md` for the canonical Settings page pattern (ProfilPerusahaanPage, KeuanganPage, etc.) — canAccess with hasPermission('admin.settings'), save with CompanySetting::set(), FileUpload in custom Pages, shared Blade template.
| Form shows same fields for all roles | Single form design with disabled() only | Use visible() to hide irrelevant sections AND disabled() on read-only fields |
| ExecutePekerjaan authorizeAccess needs record | Access control depends on which record is loaded | Use authorizeAccess() (post-mount) not canAccess() (static) |
| Blade status check broken after ENUM change | `in_array($status, ['draft','completed'])` references old values | Update Blade to match new ENUM: `['assigned','in_progress']` |
| Resources disappear from sidebar | Quadruple backslash in discoverResources | Use double backslash |
| Stat cards stack vertically | Filament grid wrapper conflicts | Use flex + flex-1 |
| Currency shows decimals | type="number" input | Use type="text" inputmode="numeric" |
| Observer fires with old status | static::updating | Use static::updated |
| Observer doesn't trigger | isDirty() in updated callback | Use wasChanged() |
| Eloquent observer not firing | Testing via raw SQL | Observers only fire via Eloquent |
| Auto-create duplicates | No dedup check | Always check exists() before create |
| TextInput for status/enum fields | Free-text input for enum fields | Always use Select::make() with options |
| Child status doesn't update parent | No observer on child model | Add boot observer → parent.hitungProgresOtomatis() |
| RAB total stays 0 after save | No calculation logic | Add Rab::saved() observer → hitungTotal() |
| Notification::danger() static call | Filament v3 Notification is instance-only | Use Notification::make()->danger()->send() |
| Custom Page 500 "Property not found" | Form field has no matching public property | Add `public $fieldName = null` for each make() field |
| Livewire "Property type not supported" | `?string $field = null` nullable type hint | Use `public string $field = ''` (non-nullable) |
| FileUpload "No synthesizer found" | FileUpload stores object, not string | Use `public $file = null` (NO type hint) |
| PDF opens empty modal | Action::action(fn() response()->streamDownload()) | Use ->url(route(...))->openUrlInNewTab() |
| Jumping to code before user ready | User says "pelajari/konsep/analisa" | Present analysis first, wait for approval |
| Creating duplicate features | Agent creates overlapping pages | Check existing pages first; ask user if unsure |
| Building new services when existing work | Agent ignores app/Services/ | Check existing code before building new classes |
| Livewire null on string props | `string $x = ''` without nullable | Use `?string $x = ''` (nullable type, non-null default) |
| Layout breaks ALL pages | Shared layout references missing route | Guard: `@if(Route::has('x.index'))` |
| Fuzzy patch breaks surrounding code | patch() matches too aggressively | Verify with `php -l` after every patch |
| Status enum inconsistent across models | Form Select uses different values than boot sync | Define in ONE place: model constants, reference everywhere |
| Heredoc string is literal text | `<<<TXT` concatenation not evaluated | Assign to var before heredoc: `$x = 'Rp' . number_format($y);` |
| PHP patch double-backslash | Patch tool escapes `\` in PHP namespace paths → parse error | After patching PHP files with namespaces, run `php -l file.php` to catch. If broken, reconstruct via Python: read_file() → fix escaping → write_file() |
| ListRecords getTableActions() doesn't render | Defining actions in `ListXxx::getTableActions()` has NO effect on Filament v3.2 table | Table actions MUST be in Resource `table()` method via `->actions([...])`. ListRecords getTableActions() is NOT called by the table component |
| Sidebar overlay blocks table action clicks | Filament sticky sidebar covers row action buttons on narrow viewports | Collapse sidebar first, or use JS `document.querySelector()` to bypass overlay |
| `->scrollable()` doesn't exist
| Create/Edit raw SQL ignores markup | `DB::raw('SUM(volume*harga_satuan)')` skips markup | Set `is_markup_applied`, let hitungTotal() be single path |
| Eloquent in Livewire array breaks | `$this->arr[] = ['item' => $model]` serialized to stub | Store scalar IDs, reload from DB: `Model::find($id)` |
| Hrg Pasar shows Rp0 for no data | number_format(0) = "Rp0" | Use `—` em dash when value <= 0 |
| TextInput for sumber_tipe enum | User types garbage into enum field | Use Select::make() with options |
| Modal via custom blade + Livewire | Complex onclick/wire:click conflicts | Use Actions\Action::make()->modalHeading()->form() |
| Table columns jump on scroll | No fixed widths | Use table-layout: fixed + colgroup |
| Header scrolls out of view | 200+ rows without sticky header | sticky top-0 z-10 on thead |
| No tooltip for truncated text | Uraian truncated, can't see full text | Add title="{{ }}" + truncate class |
| Large table hard to scan | No alternating row colors | Zebra: bg-white even, bg-gray-100/60 odd |
| N×query in batch analysis | 204 separate DB queries per RAB | Collect keywords, batch query with OR, map in PHP |
| bersihkanKeyword() over-stripping | RAB prefix "Pekerjaan X:" stripped, product keywords lost | Extract after colon, add "lain-lain" to stopwords |
| Kontrak→Rab reverse missing | Kontrak has no hasMany(Rab) | Add `public function rab() { return $this->hasMany(Rab::class); }` |\n| Form field not on model | `createOptionForm()` has `kode` but Klien model uses `kode` only as DB column, not accessible in Select context | Check `Model::$fillable` and actual columns before adding form fields; `Select::make('kode')` only works if model has the attribute |\n| Livewire model serialization in arrays | `$this->arr[] = ['item' => $model]` → model becomes stub after Livewire round-trip, `->update()` fails | Store only the ID: `$this->arr[] = ['item_id' => $model->id, ...]`, then reload: `Model::find($item['item_id'])` before use |\n| Page::$resource must be `string` not `?string` | `protected static ?string $resource = ...` → `FatalError: Type must be string (as in class Page)` | Use `protected static string $resource = ...` (non-nullable) — parent Filament\\Resources\\Pages\\Page declares it as `string`, not `?string` |\n| Blade model statics not found in subdirectory | `HargaReferensi::getSumberTipeBadge()` in `resources/views/filament/resources/.../page.blade.php` → `Class not found` | Blade files in subdirectories don't auto-import models. Use `$this->methodName()` on the Livewire Page class, or `use App\\Models\\HargaReferensi;` in the blade |\n| Multi-module parallel audit | User asks \"check logic across modules\" → need structured review of many files | Dispatch 3 parallel subagents (one per module group), each produces a structured bug report. Consolidate findings, fix critical first, verify with `php -l` and browser |\n| Create/Edit RAB raw SQL ignores markup | `DB::raw('SUM(volume*harga_satuan)')` in afterCreate/afterSave skips markup | Set `is_markup_applied` boolean, let `hitungTotal()` be single calculation path via model observer |\n| AiPriceAnalyst heredoc broken | `Rp\" . number_format(...) . \"` inside `<<<TXT` is literal text, not PHP | Assign formatted value to variable before heredoc: `$fmt = 'Rp' . number_format($val); ... $fmt` |
| Admin panel login 404 | `->login()` missing from AdminPanelProvider | Add `->login()` to panel chain + clear all caches |
| Laravel 11 scheduler not in Console/Kernel.php | Laravel 11 removed Console/Kernel.php; schedules go in bootstrap/app.php `->withSchedule()` | Register in bootstrap/app.php, verify: `php artisan schedule:list` |
| write_file resolves /c/laragon/www/ to wrong Windows path | `write_file(path='/c/laragon/www/...')` resolves to `C:\c\laragon\...` (outside workspace) | Use `terminal()` with `cat > file << 'EOF'` or PHP `file_put_contents()` to write files at `/c/laragon/...` paths |
| Bash heredoc blocks on PHP `&` characters | `cat > file.php << 'EOF'` + PHP code containing `&` causes `unexpected backgrounding` | Use PHP heredoc: `php << 'ENDPHP'` wrapping PHP code, or write via Python base64 |
| PHP heredoc breaks on single quotes inside heredoc-quoted strings | `<<<'PHPEOF'` inside bash heredoc with PHP `use` statements containing `'` breaks bash | Split into multiple small PHP commands, each writing one file |
| Pajak Status column empty in viewport | IconColumn renders but table too wide → icon outside viewport | Use `->toggleable()` on less important columns, or use `->icon()` with text fallback |
| Faktur→Pajak auto-create doesn't link faktur_id | Boot observer creates Pajak but doesn't set faktur_id | Check observer passes faktur_id: `$faktur->pajak()->create(['faktur_id' => $faktur->id, ...])` |\n| MySQL server down → app hangs | `artisan optimize:clear` fails if MySQL not started yet | Check `netstat -an | grep 3306` or `curl -s -o /dev/null -w %{http_code}` before debug. App shows 500/timeout when DB unreachable |
| Hardcoded tax rate in model | `round($subtotal * 0.11)` in hitungTotal() ignores config/pajak.php | Use `config('pajak.tarif_ppn_keluaran', 12)` — config is source of truth |
| New FK column leaves old records NULL | Boot observer only fires on NEW changes, not retroactive | Manual SQL backfill: `UPDATE table JOIN ... SET fk_id = ... WHERE fk_id IS NULL` |
| Static array in Resource hardcodes config | `private static array $tarif = [12]` won't update if config changes | Use lazy-load getter: `getJenisTarif()` reads config() on first call |
| Faktur hitungTotal() hardcodes PPN rate | `round($subtotal * 0.11)` ignores config/pajak.php | Replace with `config('pajak.tarif_ppn_keluaran', 12)` — also fix fallback defaults in observers |
| Pajak tarif mismatch (config vs data) | Config says 12% but old DB records have 11% | After changing config, backfill DB: `UPDATE pajak SET tarif_pajak=12, nominal_pajak=ROUND(dpp*12/100,2) WHERE tarif_pajak=11` |\n| Grouped data view pattern | User needs "one item, many prices" comparison — flat Filament table doesn't group | Custom Livewire Page (not ListRecords) with blade view. Group by `GROUP BY nama_item`, sub-query per item. Register in `getPages()` as custom route |\n| Modal on ListRecords page | Need inline insert modal without leaving list | `Actions\\Action::make('x')->form([...])->action(fn() ...)` on `getHeaderActions()`. Works as Livewire modal without custom blade |
| Form field not on model | `createOptionForm()` has `kode` but Klien model uses `kode` only as DB column, not accessible in Select context | Check `Model::$fillable` and actual columns before adding form fields; `Select::make('kode')` only works if model has the attribute |
| ListRecords custom blade breaks Filament table | Setting `protected static string $view = 'custom.blade'` overrides entire Filament table rendering | Use `Actions\Action::make()->form()` for modals instead of custom blade; custom blade only for complete page overrides |
| Livewire model serialization in arrays | `$this->arr[] = ['item' => $model]` → model becomes stub after Livewire round-trip, `->update()` fails | Store only the ID: `$this->arr[] = ['item_id' => $model->id, ...]`, then reload: `Model::find($item['item_id'])` before use |
| `remember_token` column missing | Filament login "Ingat saya" checkbox triggers `update remember_token` → SQL error if column absent | `ALTER TABLE users ADD COLUMN remember_token VARCHAR(100) NULL AFTER locked_until;` — standard Laravel auth column, always ensure it exists |
| `remember_token` column missing | Login "Ingat saya" checkbox triggers SQL error | `ALTER TABLE users ADD COLUMN remember_token VARCHAR(100) NULL;` — standard Laravel auth column |
| ChartWidget canvas empty white boxes | Filament assets not published | `php artisan filament:assets` — verify with `typeof Chart !== 'undefined'` in console |
| Only 2-3 of 8+ ChartWidgets render | Livewire/Alpine hydration fails silently for many chart widgets | Consolidate into ONE DivisionDashboardWidget with role-based filter tabs |
| `->dashboard()` method doesn't exist | Filament v3.2 Panel has no `dashboard()` method | Register custom dashboard in `->pages([...])` — it must extend `Filament\Pages\Dashboard` which auto-maps to `/` route |
| Custom Dashboard getWidgets() doesn't suppress widgets | Override returns `[]` but panel-level widgets still render | Use raw Chart.js in custom Blade view instead of Filament widget system — complete view replacement |
| Custom Dashboard not used despite correct route | `discoverPages` also finds pages that extend Dashboard, causing conflict | Register custom dashboard explicitly in `->pages([])`; verify with `php artisan route:list --name=admin | grep dashboard` |
| Dashboard shows ALL sections to ALL roles | Only filter dropdown is role-based, but charts/KPI render for everyone | Implement `getSectionsForRole()` + `@if(in_array('section', $sections))` in Blade; only query data for visible sections |
| `Select::query()` throws BadMethodCallException | Filament v3.2 has no `query()` or `modifyQueryUsing()` on Select | Pass closure as 3rd argument to `->relationship()`: `Select::make('x')->relationship('x', 'attr', fn($q) => $q->where(...))` |
| Form Select shows all users for technician field | No role filter on relationship query | `->relationship('user', 'name', fn($q) => $q->whereIn('role', ['R02','R03']))` |
| JSON column textarea shows array brackets | `dokumentasi_keterangan` is JSON cast but Textarea shows `["a","b"]` | Use `afterStateHydrated` to implode array→newline string, `afterStateUpdated` to explode→array |
| Duplicate records created on submit | No unique check before insert | `Pekerjaan::where(...)->exists()` in `mutateFormDataBeforeCreate()` + `$this->halt()` |
| `Placeholder::make()->content()` throws syntax error | Filament v3.2 `Placeholder::content()` doesn't accept closures in all contexts | Move validation logic to `mutateFormDataBeforeCreate()` instead of rendering in form |
| Dashboard user says "terlalu baku" | User wants modern design (gradient cards, donut center text, line fills) not default Filament ChartWidget | Use Chart.js CDN in custom Blade view — full CSS control, see `references/modern-dashboard-custom-view-pattern.md` |
| Table name mismatch in raw DB queries | `DB::table('pengeluarans')` fails — actual table is `pengeluaran` (no trailing 's') | Always `SHOW TABLES LIKE '%keyword%'` or `DESCRIBE tablename` before writing raw queries. Common mismatches in this project: `pengeluaran` (not `pengeluarans`), `spareparts` (not `sparepart`), `pemakaian_sparepart` (not `pemakaian_spareparts`), `klien` (not `kliens`), `kontrak` (not `kontraks`) |
| Column name mismatch in raw DB queries | `tanggal_pemakaian` doesn't exist on `pemakaian_sparepart` — uses `created_at` instead; `pengeluaran` uses `tanggal_pengeluaran` (not `tanggal`) and `jumlah_biaya` (not `nominal`) | Always `DESCRIBE tablename` before writing column references. Don't assume Laravel model naming = DB column naming |
| User exists but can't login (password hash mismatch) | Account not locked, role valid, panel OK — but `Hash::check()` returns false (wrong password in DB or hash corrupted) | Verify: `php artisan tinker --execute="echo Hash::check('pass123', \App\Models\User::where('email','x')->first()->password) ? 'MATCH' : 'MISMATCH';"` then reset: `php artisan tinker --execute="\App\Models\User::where('email','x')->update(['password' => \Illuminate\Support\Facades\Hash::make('newpass')]);"` |
| MySQL CLI not in PATH on Laragon | `mysql` / `mysqladmin` commands not found in git-bash | Use full path: `/c/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe -u root db -e "SQL"` |
| artisan tinker --execute for one-liners | Need quick PHP snippet without interactive REPL | `php artisan tinker --execute="echo Hash::check(...);"` — runs single expression, prints result, exits |
| Custom login rejects email input | Custom `/login` only accepts username, users from LAN try email → "Kredensial tidak valid" | AuthController login(): `User::where('username', $input)->orWhere('email', $input)->first()`. Update blade label to "Username atau Email" |
| Login from network shows wrong page | Root `/` redirects to `/login` (custom) instead of `/admin/login` (Filament) | Update root route + AuthController showLogin + logout to all redirect to `/admin/login`. Clear route cache after. |
| User can login but sidebar is EMPTY | Role has ZERO entries in `role_permissions` table | Check: `SELECT COUNT(*) FROM role_permissions WHERE role='RXX'`. If 0, sync permissions for the role. See "Role Permission Syncing" section above. `filament.access` is absolutely required. |
| Teknisi/Supervisor can't see Pekerjaan menu | Role lacks `pekerjaan.view` and/or `filament.access` permission | Sync role_permissions: `DELETE FROM role_permissions WHERE role='R02'; INSERT ...` with appropriate permission set. Minimum for teknisi: filament.access + dashboard.view + pekerjaan.view/create + kontrak.view/progress + calendar.view + dokumen.view/upload + gudang.view + pengajuan-sparepart.* + pemakaian_sparepart.* |
| LAN access blocked by Windows Firewall | Port 80 not open for inbound connections | `netsh advfirewall firewall add rule name="ERP - HTTP" dir=in action=allow protocol=tcp localport=80` (requires admin elevation via PowerShell Start-Process -Verb RunAs) |
| Apache not serving app on LAN IP | APP_URL still set to localhost:5500 or default vhost serves wrong root | Create dedicated vhost in `sites-enabled/` + update APP_URL to `<LAN_IP>` + `php artisan config:cache && php artisan route:cache` |
| Apache won't restart on Windows | `taskkill` + wait doesn't auto-respawn; `net stop/start` may not match service name | Use `powershell.exe -Command "Stop-Process -Name httpd -Force"` then let Laragon respawn, or start manually with full httpd.exe path |
| Main app sidebar hides menus despite correct permissions | Blade sidebar uses hardcoded role checks (e.g., `role !== 'R02'`) instead of permission-based checks → menus hidden for users who HAVE the permission via `role_permissions` table | Replace `@if(role !== 'R02')` with `@if(hasPermission('module.view'))` in `layouts/app.blade.php`. Both main app sidebar AND Filament `canAccess()` must use the SAME permission-based system. Audit: `grep -n "role !==\|role ==" resources/views/layouts/app.blade.php` |
| Dual permission system conflict | Main app sidebar uses hardcoded role checks while Filament uses `hasPermission()` → same user sees different menus depending on which UI they access | ONE permission source of truth: `role_permissions` table. All UI checks (main app sidebar, Filament `canAccess()`, middleware) must read from `hasPermission()`. Never hardcode role strings in view visibility checks. |
| Invalid heroicon crashes entire Filament admin | One invalid `$navigationIcon` in ANY Resource/Page (e.g. `heroicon-o-sitemap`) causes "Svg not found" error on ALL admin pages — Filament renders all nav items on every page load, so one bad icon breaks everything | Validate ALL icons against installed blade-heroicons SVGs. Run `scripts/validate-icons.sh` from project root. See `references/heroicons-v2-icon-validation.md` for the naming convention map |
| heroicon-o-X renamed in blade-heroicons v2 | `heroicon-o-arrow-right-on-rectangle` doesn't exist in v2 — renamed to `heroicon-o-arrow-right-start-on-rectangle`. `heroicon-o-sitemap` doesn't exist at all in v2.7+ | Map: Filament `heroicon-o-X` → SVG file `c-X.svg`. Check `vendor/blade-ui-kit/blade-heroicons/resources/svg/` for available icons. Run `scripts/validate-icons.sh` after any icon change |
| JSON column not saving (stays null) | New JSON column added via migration but NOT in model `$fillable` array → `$record->update(['col' => $data])` silently does nothing, column stays null | Add column to `$fillable` AND add `'col' => 'array'` to `casts()`. Test: `$record->update(['col' => $data]); $record->refresh(); dd($record->col);` — must NOT be null |
| Filament user menu dropdown won't open in browser automation | Alpine.js x-show dropdowns render in DOM but browser_click on ref doesn't trigger Alpine | Use JS: document.querySelector('button[type="submit"]').click() to find and submit the logout form directly. |
| Resource visible to ALL users (missing canAccess) | Filament Resource has no canAccess() method, shown in sidebar for every logged-in user regardless of role | Add public static function canAccess(): bool { return Auth::user()->hasPermission('module.view'); } to every Resource. Audit: grep -rL 'canAccess' app/Filament/Resources/ |
| Page visible to ALL users (missing canAccess) | Filament Page has no canAccess(), visible in sidebar for all roles | Add canAccess() with permission check. Even settings/company pages need gatekeeping. |
| Hardcoded role check in canAccess bypasses permission system | in_array($user->role, ['R00', 'R05']) in canAccess() ignores user_permissions overrides | Use Auth::user()->hasPermission('module.view') instead. Role-based access is in role_permissions table, not PHP code. |
| HasDeptAccess trait returns true always | checkDeptAccess() hardcodes return true, false sense of security | Either implement actual dept-based logic or remove the trait and add proper canAccess() |
| Apache 404 for project with spaces in path | DocumentRoot with spaces in path, default vhost still active | Ensure 00-default.conf is renamed to .bak. Only ONE VirtualHost should handle *:80 |
| Custom Page blade `@extends` broken | `@extends('filament-panels::pages/page')` not a valid standalone view | Use `<x-filament-panels::page>` wrapper instead of Blade inheritance |
| `$form` undefined in custom blade | Filament form not auto-shared to Blade views | Use `{{ $this->form }}` (Livewire accessible via HasForms trait) |
| `$getTitle()` undefined in Blade | Filament page methods not available as Blade variables | Render from `$record` directly or use `$this->getTitle()` |
| `static::getResource()` method not found | Resource class doesn't have getResource() method | Use `static::getUrl(['page_name', 'record' => $record])` |
| `getUrl()` expects array not string | `getUrl('index')` throws TypeError | Use `getUrl(['index'])` |
| Livewire "page expired" on custom Page with $view + form() | Cloud browser can't persist session cookies for localhost — APP_URL mismatch | Fix APP_URL to match access URL. Works fine on local browser after fix. Don't waste time debugging blade if it works locally. |
| Livewire "page expired" immediately after page load | APP_URL set to LAN IP but accessed via localhost (or vice versa) | Set APP_URL to match the access URL. Add SESSION_DOMAIN= in .env for belt-and-suspenders. |
| `wire:model` / `wire:click` cause "page expired" | ANY `wire:` binding on form elements triggers Livewire AJAX → CSRF fail on custom Page | Use regular `fetch()` to Laravel routes instead. ALL form elements must be plain HTML with `id` attributes, NO wire: bindings |
| `wire:model.live` on `<select>` causes CSRF | Selecting any option triggers AJAX update that fails CSRF | Replace with plain `<select id="input-x">` and read value in JS via `document.getElementById()` |
| `$wire.call()` via Alpine.js still causes CSRF | `$wire.call()` sends Livewire AJAX to `/livewire/update` → CSRF fail on custom Page | Use `fetch()` to regular Laravel routes instead. See `references/custom-page-non-livewire-routes-pattern.md` |
| `@this.call()` in regular `<script>` silently fails | Livewire 3 only compiles `@this` inside `@script`/`@endScript` blocks. In regular `<script>`, it renders as literal text → button does nothing, no error | Use `fetch()` to regular Laravel routes instead of any Livewire mechanism |
| Multi-step form uses single dropdown | One `<select>` to choose stage, re-renders form each time — confusing UX | Use 3 independent sections (one per stage), each with own save button. Store in JSON column |
| Session cookie domain mismatch | APP_URL set to LAN IP but accessed via localhost (or vice versa) | Set APP_URL to match the access URL, or add SESSION_DOMAIN= in .env. Verify: list page Livewire search works after fix |
| Sidebar has duplicate groups | Same functional group split across 'Gudang', '📦 Gudang', '🏠 Beranda' | Pick one canonical name, patch all `navigationGroup` properties, re-sort `navigationSort` values for contiguous order |
| Navigation sort order unpredictable after merge | Old sort values (1-99) from different source groups overlap | Reassign sort as 1=Dashboard, 2=Verifikasi/Action, 3-5=Master, 6-8=Transaksi, 9+=Reports |
| `shouldRegisterNavigation()` hides item | Resource has `return false` — not visible regardless of group | This is intentional (e.g., RelationManagers accessed inline). Don't patch their group. |

## Browser Testing Filament Admin

### Dropdown / User Menu
Filament v3 user menu (avatar → logout) uses Alpine.js `x-show` + `x-transition`. The accessibility tree shows the dropdown items (button "Keluar"), but clicking the avatar ref doesn't reliably toggle the Alpine state.

**Workaround** — bypass the dropdown, submit logout form directly via JS:
```js
document.querySelector('button[type="submit"]').click()
```
This finds the hidden logout form's submit button and triggers it. The form POSTs to `/admin/logout` with CSRF token.

### Session / Login Flow
- Admin login: `GET /admin/login` (Filament built-in, uses **email**)
- Main app login: `GET /login` (custom Web\AuthController, uses **username OR email**)
- Both share `web` guard session — login from either side grants access to both
- Admin logout: `POST /admin/logout` (Filament LogoutController) → redirects to `/admin/login`
- Main app logout: `POST /logout` (Web\AuthController) → redirects to `route('login')`

### Dual Login UX — Accept Both Username and Email
When the ERP has BOTH a custom login (`/login`) and Filament login (`/admin/login`), the custom AuthController MUST accept both username AND email. Users accessing from LAN will naturally try their email — if the custom login only accepts username, they get "Kredensial tidak valid" and think login is broken.

## Dual Permission System — Main App + Filament

When a Laravel ERP has BOTH a custom main app (Blade views with sidebar) AND a Filament admin panel, there are TWO places that check permissions:

1. **Main app sidebar** (`resources/views/layouts/app.blade.php`) — Blade `@if` checks
2. **Filament resources** — `canAccess()` methods checking `hasPermission()`

### CRITICAL: Both must use the SAME permission-based system

**WRONG — hardcoded role checks in sidebar:**
```blade
@if(auth()->user()->role !== 'R02')  ← EXCLUDES teknisi by role string
<a href="{{ route('dms.index') }}">Dokumen</a>
@endif
```
This hides the menu even though R02 has `dokumen.view` permission in `role_permissions` table.

**RIGHT — permission-based checks:**
```blade
@if(auth()->user()->hasPermission('dokumen.view'))  ← Checks actual permission
<a href="{{ route('dms.index') }}">Dokumen</a>
@endif
```

### Audit command — find hardcoded role checks in sidebar:
```bash
grep -n "role !==\|role ==\|role !=" resources/views/layouts/app.blade.php
```

### Pattern: every sidebar menu item should use permission check:
```blade
{{-- Dashboard — permission check --}}
@if(auth()->user()->hasPermission('dashboard.view'))
<a href="{{ route('dashboard') }}">Dashboard</a>
@endif

{{-- Module access — module-level check --}}
@if(auth()->user()->hasModuleAccess('keuangan'))
<a href="{{ route('faktur.index') }}">Keuangan</a>
@endif

{{-- Panel Admin — role check is OK here (gatekeeping entire admin) --}}
@if(in_array(auth()->user()->role, ['R00','R01','R02','R03','R04','R05','R06']))
<a href="/admin">⚡ Panel Admin</a>
@endif
```

### Bottom nav (mobile) also needs permission checks:
```blade
<nav id="bottom-nav">
    <a href="/admin">Admin</a>
    @if(auth()->user()->hasPermission('dokumen.view'))
    <a href="{{ route('dms.index') }}">Dokumen</a>
    @endif
</nav>
```

### Three places that must stay consistent:
| Location | Check method | Common mistake |
|----------|-------------|----------------|
| Main app sidebar (`layouts/app.blade.php`) | `hasPermission()` in Blade `@if` | Hardcoded `role !== 'R02'` |
| Filament Resource `canAccess()` | `hasPermission()` in PHP | Missing `filament.access` permission |
| Route middleware | `CheckPermission` middleware | Using `CheckRole` middleware instead |

**Fix in AuthController login():**
```php
// Accept username OR email
$input = $request->username;
$user = User::where('username', $input)
    ->orWhere('email', $input)
    ->first();
```

**Update blade label**: Change from "Username" to "Username atau Email" and placeholder to "Masukkan username atau email". This tells users both methods work.

### Login Route Registration
The `/admin/login` route ONLY exists when `->login()` is in AdminPanelProvider. Without it, only `/admin/logout` appears in route list. Always verify both routes exist after PanelProvider changes.

## Filament Admin Login Failure — Debugging Workflow

When a user can't login ("tidak bisa login"), follow this checklist in order. Most issues resolve by step 4.

### Step 1: Check user exists in DB
```bash
/c/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe -u root app_db \
  -e "SELECT id, username, email, role, failed_login_attempts, locked_until FROM users WHERE email='x@example.com'\G"
```
**If not found**: check for typos, check correct DB name (`SHOW DATABASES`).

### Step 2: Check account lockout
Look at `failed_login_attempts` and `locked_until` columns.
- `locked_until` is NOT NULL and in the future → account locked
- **Reset**: `UPDATE users SET failed_login_attempts=0, locked_until=NULL WHERE email='x@example.com';`

### Step 3: Check Model and Panel config
- Does `User::canAccessPanel()` allow this role?
- Does AdminPanelProvider have `->login()`? (404 if missing)
- Is `->authGuard('web')` set?

### Step 4: Verify password hash (most common cause)
```bash
php artisan tinker --execute="echo \Illuminate\Support\Facades\Hash::check('password123', \App\Models\User::where('email','teknisi1@example.com')->first()->password) ? 'MATCH' : 'MISMATCH';"
```
- **MISMATCH** → password in DB doesn't match. Reset it:
```bash
php artisan tinker --execute="\App\Models\User::where('email','teknisi1@example.com')->update(['password' => \Illuminate\Support\Facades\Hash::make('password123')]); echo 'Done';"
```
- **MATCH** → password is correct, check other causes (session, CSRF, browser cookies)

### Step 5: Quick reset script (all-in-one)
For known working password, reset in one shot:
```bash
cd "/c/laragon/www/<project>" && \
php artisan tinker --execute="
\$user = \App\Models\User::where('email','x@example.com')->first();
echo 'User: ' . (\$user ? \$user->name . ' (id:' . \$user->id . ')' : 'NOT FOUND') . PHP_EOL;
echo 'Locked: ' . (\$user && \$user->locked_until && \$user->locked_until->isFuture() ? 'YES' : 'NO') . PHP_EOL;
\$user->update(['password' => \Illuminate\Support\Facades\Hash::make('newpass'), 'failed_login_attempts' => 0, 'locked_until' => null]);
echo 'Password reset to: newpass';
"
```

---

*Last updated: 2026-07-27 (heroicons v2 icon validation: naming map, missing icons crash entire admin panel, validate-icons.sh script added. Sidebar navigation group consolidation: mixed group name pitfalls, sort reorder after merge.)*
