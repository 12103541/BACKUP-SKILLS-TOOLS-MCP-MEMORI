# Role-Based Dashboard Pattern — Filament v3.2 ChartWidget
# Role-Based Dashboard Pattern — Filament v3.2 ChartWidget

## Overview
Custom dashboard showing different ChartWidgets based on user role/division.
Each widget has `canView()` that filters by role, so the default Filament Dashboard
renders only the charts relevant to the logged-in user's division.

## RECOMMENDED: Single Master Widget with Filter Tabs

**Do NOT create 8+ separate ChartWidgets.** Filament's Livewire/Alpine hydration
fails silently when too many ChartWidgets are on one page — only the first 2-3 render.
Instead, consolidate ALL division charts into ONE `DivisionDashboardWidget`.

### Why this works
- Single Alpine chart component to initialize → reliable rendering
- `getFilters()` returns role-specific filter sets → RBAC built-in
- `getType()` switches chart type per filter → one widget, many views
- `getData()` dispatches to per-filter data methods → clean separation

### Verified working implementation (2026-07-24)

```php
<?php
namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DivisionDashboardWidget extends ChartWidget
{
    protected static ?string $heading = '📊 Dashboard Analitik';
    protected static ?string $description = 'Ringkasan lintas departemen';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 0;
    protected static ?string $maxHeight = '400px';

    protected static ?array $options = [
        'plugins' => [
            'legend' => ['display' => true, 'position' => 'bottom'],
            'tooltip' => ['enabled' => true],
        ],
        'scales' => [
            'y' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1]],
        ],
    ];

    // Role-based filter sets
    public function getFilters(): ?array
    {
        $role = auth()->user()->role ?? '';
        $filters = [
            'overview'    => '🏢 Overview',
            'proyek'      => '📋 Pekerjaan',
            'kontrak'     => '📝 Kontrak & Pipeline',
            'faktur'      => '💰 Faktur',
            'pajak'       => '🧾 Pajak',
            'pengeluaran' => '💸 Pengeluaran',
            'gudang'      => '📦 Stok Gudang',
            'pengajuan'   => '📋 Pengajuan Sparepart',
        ];

        // Super Admin / Manajer → all filters
        if (in_array($role, ['R00', 'R06'])) return $filters;

        // Other roles → limited filters
        return match ($role) {
            'R01' => array_filter($filters, fn($k) => in_array($k, ['overview','proyek','kontrak'])),
            'R02','R03' => array_filter($filters, fn($k) => in_array($k, ['overview','proyek','pengajuan'])),
            'R04' => array_filter($filters, fn($k) => in_array($k, ['overview','gudang','pengajuan'])),
            'R05' => array_filter($filters, fn($k) => in_array($k, ['overview','faktur','pajak','pengeluaran'])),
            default => ['overview' => '🏢 Overview'],
        };
    }

    // Chart type per filter
    protected function getType(): string
    {
        return match ($this->filter ?? 'overview') {
            'pengeluaran' => 'line',
            'pengajuan'   => 'bar',
            'gudang'      => 'bar',
            default       => 'bar',
        };
    }

    // Data dispatch per filter
    protected function getData(): array
    {
        return match ($this->filter ?? 'overview') {
            'overview'    => $this->getOverviewData(),
            'proyek'      => $this->getProyekData(),
            'faktur'      => $this->getFakturData(),
            'pengeluaran' => $this->getPengeluaranData(),
            'gudang'      => $this->getGudangData(),
            default       => $this->getOverviewData(),
        };
    }

    private function getOverviewData(): array
    {
        $data = [
            'Pekerjaan'  => Pekerjaan::count(),
            'Kontrak'    => Kontrak::count(),
            'Faktur'     => Faktur::count(),
            // ...
        ];
        return [
            'datasets' => [[
                'label' => 'Total Data',
                'data' => array_values($data),
                'backgroundColor' => ['#f59e0b','#3b82f6','#10b981','#ef4444','#8b5cf6','#06b6d4'],
            ]],
            'labels' => array_keys($data),
        ];
    }

    // ... other getData methods per filter
}
```

### AdminPanelProvider registration
```php
->widgets([
    // ONE chart widget (master)
    \App\Filament\Widgets\DivisionDashboardWidget::class,
    // Stat widgets (per divisi) — these render fine individually
    \App\Filament\Widgets\SdmDashboardWidget::class,
    \App\Filament\Widgets\GudangDashboardWidget::class,
    \App\Filament\Widgets\KeuanganDashboardWidget::class,
    \App\Filament\Widgets\TeknisiDashboardWidget::class,
])
```

## Legacy Pattern (DO NOT USE — rendering issues)

**The multi-ChartWidget approach (8+ separate widgets) has a confirmed rendering bug.**
Only 2-3 of 8+ ChartWidgets actually render in the DOM. The rest exist in Livewire
snapshot data but their view HTML never appears. No JS/PHP errors.

### Verified facts
- `Filament::getPanel('admin')->getWidgets()` returns all widgets ✅
- `canView()` returns true for all expected widgets ✅
- Only 2 `.fi-wi-chart` containers in DOM
- `x-load-src` only appears 5 times (3 stat + 2 chart), not 8+
- Widget class names found in `innerHTML` (snapshot data) but `<h3>` headings NOT in visible DOM
- No JS errors, no PHP errors, no Laravel log errors

### Root cause (likely)
Livewire/Alpine hydration fails silently for ChartWidgets when too many are on one
page. The Alpine `x-load-src` attribute loads the chart component lazily, but only
the first 2 get loaded. Dashboard `getColumns(): 2` may also limit grid rendering.

## Architecture (Legacy — for reference only)
```
AdminPanelProvider
  -> widgets([DivisionDashboardWidget::class, ...stat widgets...])
```

## Widget Class Pattern

### ChartWidget skeleton
```php
<?php
namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class DivisionChartWidget extends ChartWidget
{
    protected static ?string $heading = '📊 Title';
    protected static ?string $description = 'Subtitle';
    protected static ?int $sort = 10;
    protected static string $color = 'primary';

    public static function canView(): bool
    {
        $role = auth()->user()->role ?? '';
        return in_array($role, ['R00', 'R01', 'R06']);
    }

    protected function getFilters(): ?array
    {
        return [
            'bar' => 'Bar View',
            'donut' => 'Donut View',
        ];
    }

    protected function getType(): string
    {
        return match ($this->filter) {
            'donut' => 'doughnut',
            default => 'bar',
        };
    }

    protected function getData(): array
    {
        return [
            'datasets' => [[
                'label' => 'Dataset',
                'data' => [1, 2, 3],
                'backgroundColor' => ['#22c55e', '#f59e0b', '#ef4444'],
                'borderRadius' => 8,
            ]],
            'labels' => ['Label A', 'Label B', 'Label C'],
        ];
    }
}
```

### Line chart (time series)
```php
protected function getData(): array
{
    $months = collect();
    for ($i = 5; $i >= 0; $i--) {
        $date = now()->subMonths($i);
        $months->push([
            'label' => $date->format('M'),
            'total' => Model::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)->sum('nominal'),
        ]);
    }
    return [
        'datasets' => [[
            'label' => 'Pengeluaran',
            'data' => $months->pluck('total')->toArray(),
            'borderColor' => '#ef4444',
            'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
            'fill' => true,
            'tension' => 0.4,
        ]],
        'labels' => $months->pluck('label')->toArray(),
    ];
}
```

## Pitfalls

### CRITICAL: Filament assets not published → ALL charts blank
`php artisan filament:assets` MUST be run after setup or cache clear. Without it, `chart.js` is not at the expected URL, Chart.js never loads, and ALL ChartWidget canvases render as empty white boxes.

**Verify**: `typeof Chart !== 'undefined'` in browser console. If NOT loaded:
```bash
php artisan config:clear && php artisan route:clear && php artisan cache:clear && php artisan filament:assets
```
**Verify asset**: `ls public/js/filament/widgets/components/chart.js`

### discoverWidgets auto-discovers everything
`->discoverWidgets(in: app_path('Filament/Widgets'))` auto-registers ALL PHP classes in the directory. The `->widgets([])` array in AdminPanelProvider is for ADDITIONAL explicit registration, not replacement. Don't list auto-discovered widgets in `->widgets([])`.

### Sort order for dashboard widgets
Use `protected static ?int $sort = N;` on each widget. Lower = higher on page. Recommended ranges:
- 0: Master dashboard widget (DivisionDashboardWidget)
- 1-9: Stat cards (existing widgets)
- 10+: Detail/secondary charts

### Chart colors
Filament maps `static $color` to CSS custom properties (`--fi-color-{name}-500`). Use standard Filament colors: `'primary'`, `'success'`, `'warning'`, `'danger'`, `'info'`. For custom chart colors, set them in `getData()` datasets directly.

## Role Division Mapping
| Role | Code | Dashboard filters |
|------|------|------------------|
| Super Admin | R00 | All 8 filters |
| Admin Proyek | R01 | Overview, Pekerjaan, Kontrak & Pipeline |
| Teknisi | R02 | Overview, Pekerjaan, Pengajuan Sparepart |
| Supervisor | R03 | Overview, Pekerjaan, Pengajuan Sparepart |
| Staff Gudang | R04 | Overview, Stok Gudang, Pengajuan Sparepart |
| Keuangan | R05 | Overview, Faktur, Pajak, Pengeluaran |
| Manajer | R06 | All 8 filters |

### Filter verification via tinker
```php
php artisan tinker --execute="
use App\Filament\Widgets\DivisionDashboardWidget;
\$user = auth()->loginUsingId(1); // R00
\$filters = (new DivisionDashboardWidget)->getFilters();
echo implode(', ', array_values(\$filters));
"
```

## DB Table Name Corrections
Raw DB queries in widget `getData()` methods MUST use actual table names, not Laravel conventions:
- `pengeluaran` NOT `pengeluarans` (columns: `tanggal_pengeluaran`, `jumlah_biaya`)
- `spareparts` NOT `sparepart` (column: `kategori`)
- `pemakaian_sparepart` NOT `pemakaian_spareparts` (uses `created_at`, no `tanggal_pemakaian`)
- `klien` NOT `kliens`
- `kontrak` NOT `kontraks`
- `pengajuan_sparepart` NOT `pengajuan_spareparts`
