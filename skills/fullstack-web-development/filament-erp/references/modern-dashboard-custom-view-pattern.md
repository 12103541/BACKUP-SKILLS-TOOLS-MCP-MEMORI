# Modern Dashboard Custom View Pattern (Chart.js CDN in Blade)

## Problem
Filament v3.2 `ChartWidget` has severe limitations:
- Multiple ChartWidgets (8+) fail to render — Livewire/Alpine hydration silently drops 6 of 8
- No control over styling (gradient cards, donut center text, line chart gradient fills)
- `->dashboard()` method doesn't exist on Panel
- `getWidgets()` override doesn't suppress panel-level widgets

## Solution: Chart.js CDN in Custom Blade View

### 1. Create Dashboard Page Class
```php
<?php
namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Illuminate\Support\Facades\DB;

class ModernDashboard extends Dashboard
{
    protected static string $view = 'filament.pages.modern-dashboard';
    protected static ?int $navigationSort = -2;

    public function getWidgets(): array { return []; }

    public function getData(): array
    {
        $pekerjaanStatus = DB::table('pekerjaan')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
        // ... more queries
        return compact('pekerjaanStatus', ...);
    }
}
```

### 2. Create Blade View
Key pattern — use `<x-filament-panels::page>` wrapper + Chart.js CDN:
```html
<x-filament-panels::page>
@php $data = $this->getData(); @endphp

<style>
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .kpi-card { border-radius: 16px; padding: 1.25rem; color: #fff; position: relative; overflow: hidden; }
    .chart-card { background: #fff; border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,.06); }
    .donut-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none; }
</style>

<div class="dash-root">
    <div class="kpi-grid">
        <div class="kpi-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="kpi-value">{{ $data['total'] }}</div>
            <div class="kpi-label">Label</div>
        </div>
    </div>
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-canvas-wrap" style="height:280px; position:relative;">
                <canvas id="chart1"></canvas>
                <div class="donut-center">
                    <div class="big">{{ $data['total'] }}</div>
                    <div class="small">Total</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chart1'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode(array_keys($data['statusData'])) !!},
        datasets: [{
            data: {!! json_encode(array_values($data['statusData'])) !!},
            backgroundColor: ['#667eea','#764ba2','#f093fb','#f5576c'],
            borderWidth: 0, hoverOffset: 8
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '65%',
        plugins: { legend: { position: 'bottom' } } }
});
</script>
</x-filament-panels::page>
```

### 3. Register in AdminPanelProvider
```php
->pages([
    \App\Filament\Pages\ModernDashboard::class,
    \App\Filament\Resources\PettyCashResource\Pages\TopUpOperasional::class,
])
```

### 4. Verify Route
```bash
php artisan route:list --name=admin | grep dashboard
# Should show: GET|HEAD admin → filament.admin.pages.modern-dashboard
```

## Why This Works
- `<x-filament-panels::page>` provides Filament layout wrapper (sidebar, header, content area)
- Custom Blade view COMPLETELY replaces Filament's default dashboard view
- No Filament widgets rendered — just raw HTML + Chart.js
- Full CSS control for gradients, donut center text, responsive grid

## Role-Based Section Filtering (not just dropdown filters)

The dashboard should show DIFFERENT sections per role — not just different filter options. Implement via `getSectionsForRole()` + `@if(in_array('section', $sections))` in Blade.

### PHP — Only query data the role needs
```php
protected function getSectionsForRole(string $role): array
{
    if (in_array($role, ['R00', 'R06'])) {
        return ['overview', 'proyek', 'keuangan', 'gudang', 'sdm'];
    }
    return match ($role) {
        'R01' => ['proyek'],
        'R02', 'R03' => ['proyek', 'gudang'],
        'R04' => ['gudang'],
        'R05' => ['keuangan'],
        default => ['overview'],
    };
}

public function getData(): array
{
    $role = auth()->user()->role ?? 'R00';
    $sections = $this->getSectionsForRole($role);
    // Only query data for visible sections
    $kpi = [];
    if (in_array('proyek', $sections)) {
        $kpi['total_pekerjaan'] = DB::table('pekerjaan')->count();
        // ...
    }
    // ... similar for keuangan, gudang sections
}
```

### Blade — Conditional section rendering
```html
{{-- Role badge --}}
<div class="role-badge">👤 {{ $roleName }} · {{ auth()->user()->name }}</div>

{{-- KPI cards — only show if data exists --}}
@if(isset($kpi['total_pekerjaan']))
    <div class="kpi-card kpi-pekerjaan">...</div>
@endif

{{-- Section dividers + charts --}}
@if(in_array('proyek', $sections))
    <div class="section-divider">
        <span class="section-icon">📋</span>
        <div class="section-title">Manajemen Proyek</div>
    </div>
    <div class="chart-grid">
        @if(!empty($data['pekerjaanStatus']))
            <div class="chart-card">...pekerjaan donut...</div>
        @endif
    </div>
@endif
```

### Role → Section mapping (PT EXFERIA)
| Role | Sections visible |
|------|-----------------|
| R00 Super Admin | overview, proyek, keuangan, gudang, sdm |
| R01 Admin Proyek | proyek |
| R02 Teknisi | proyek, gudang |
| R03 Supervisor | proyek, gudang |
| R04 Staff Gudang | gudang |
| R05 Staff Keuangan | keuangan |
| R06 Manajer | overview, proyek, keuangan, gudang, sdm |

## Verified 2026-07-24
- KPI cards with gradient backgrounds ✅
- Donut charts with center text ✅
- Line charts with gradient fill ✅
- Bar charts with rounded corners ✅
- Role-based data filtering (sections) ✅
- Role-based section visibility in Blade ✅
- CDN Chart.js v4.4.7 ✅

## DB Table Name Corrections (caused 500 errors)
Always `SHOW TABLES LIKE '%keyword%'` before writing raw DB queries. This project's actual table names:
- `pengeluaran` NOT `pengeluarans`
- `spareparts` NOT `sparepart`
- `pemakaian_sparepart` NOT `pemakaian_spareparts`
- `klien` NOT `kliens`
- `kontrak` NOT `kontraks`
- `pengajuan_sparepart` NOT `pengajuan_spareparts`

Column name traps:
- `pengeluaran.tanggal_pengeluaran` NOT `tanggal`; `pengeluaran.jumlah_biaya` NOT `nominal`
- `pemakaian_sparepart.created_at` NOT `tanggal_pemakaian` (no date column exists)

## Complete CSS Pattern (proven working)
```css
/* KPI Grid — auto-fit responsive */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kpi-card { border-radius: 16px; padding: 1.25rem 1.5rem; color: #fff; position: relative; overflow: hidden; transition: transform .2s, box-shadow .2s; }
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,.15); }
.kpi-card .kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; background: rgba(255,255,255,.2); margin-bottom: .75rem; }
.kpi-card .kpi-value { font-size: 2rem; font-weight: 700; line-height: 1.1; }
.kpi-card .kpi-label { font-size: .85rem; opacity: .85; margin-top: .25rem; }
.kpi-card::after { content: ''; position: absolute; top: -30px; right: -30px; width: 100px; height: 100px; border-radius: 50%; background: rgba(255,255,255,.1); }

/* Gradient palettes */
.kpi-pekerjaan { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.kpi-kontrak { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.kpi-faktur { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.kpi-pengeluaran { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

/* Chart cards — white with subtle shadow */
.chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
.chart-card { background: #fff; border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,.06); border: 1px solid #f0f0f0; }
.chart-card-full { grid-column: 1 / -1; } /* span full width */

/* Donut center text overlay */
.donut-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none; }
.donut-center .big { font-size: 1.5rem; font-weight: 700; color: #1a1a2e; }
.donut-center .small { font-size: .75rem; color: #6b7280; }

/* Line chart gradient fill (JS context gradient) */
const gradient = ctx.createLinearGradient(0, 0, 0, 280);
gradient.addColorStop(0, 'rgba(102,126,234,.3)');
gradient.addColorStop(1, 'rgba(102,126,234,.01)');
// Use as: borderColor: '#667eea', backgroundColor: gradient, fill: true
```

## Responsive breakpoint
```css
@media (max-width: 768px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .chart-grid { grid-template-columns: 1fr; }
}
```

---

## Session 2026-07-25: Pekerjaan Create/View Logic & ViewRecord Eager-Loading Fix

### Problem: ViewRecord infolist() Shows Empty Data
When `infolist()` uses `TextEntry::make('relationship.attribute')` (e.g., `kontrak.nomor_kontrak`, `user.name`), the ViewRecord page must eager-load those relationships, otherwise the data shows empty even though the record exists.

### Fix: Add getRecordQuery() to Resource OR Override getRecord() in View Page

**Option 1: Resource-level (affects all pages)**
```php
// In PekerjaanResource.php
public static function getRecordQuery(): Builder
{
    return parent::getRecordQuery()->with(['kontrak', 'user', 'approver']);
}
```

**Option 2: View page only (more targeted)**
```php
// In ViewPekerjaan.php
use App\Models\Pekerjaan;

class ViewPekerjaan extends ViewRecord
{
    public function getRecord($recordKey): Model
    {
        return Pekerjaan::with(['kontrak', 'user', 'approver'])->findOrFail($recordKey);
    }
}
```

### Session Fixes Applied
1. **Pekerjaan Create Form Logic** — Filament v3.2 `Select::relationship()` with 3rd-arg closure for filtering (not `->query()` or `->modifyQueryUsing()`)
2. **Status Workflow** — Dynamic options by current state (`draft`→`submitted`→`approved/rejected`)
3. **Approval Role Gate** — `disabled(fn () => !in_array(auth()->user()->role, ['R00','R01','R03','R06']))`
4. **Alasan Penolakan** — Visible+required only when status=rejected
5. **Auto-generate nama_pekerjaan** — In `CreatePekerjaan::mutateFormDataBeforeCreate()`
6. **Duplicate Prevention** — Check kontrak+user+lokasi_ruas, `Notification::danger()` + `$this->halt()`
7. **JSON ↔ Textarea** — `afterStateHydrated`/`afterStateUpdated` for `dokumentasi_keterangan`
8. **Infolist Section Import Fix** — Use `Filament\Infolists\Components\Section as InfolistSection` not `Filament\Forms\Components\Section`
9. **ViewRecord Eager-Loading** — Added `getRecordQuery()->with(['kontrak','user','approver'])` to Resource

### Files Modified
- `app/Filament/Resources/PekerjaanResource.php` — form(), infolist(), getRecordQuery()
- `app/Filament/Resources/PekerjaanResource/Pages/CreatePekerjaan.php` — mutateFormDataBeforeCreate()
- `app/Filament/Resources/PekerjaanResource/Pages/ViewPekerjaan.php` — minimal, relies on Resource getRecordQuery()