# Workflow Monitoring Redesign — 2026-07-29

## Problem

Two parallel workflow systems existed:
1. **Pipeline 7-step per Kontrak** (auto-computed via `WorkflowIndicatorService`) — accurate, data-driven
2. **WorkflowProyek/WorkflowStep/WorkflowTahapan models** (manual CRUD) — empty (0 records), table `workflow_tahapan` missing

User confused by two "Workflow" menus: `Workflow Monitoring` (Proyek & Operasional) and `Workflow Proyek` (Proyek). Both showed different data.

## Solution

**Deleted System 2 entirely** — models, resource, pages, widget, migrations (15+ files). Kept only System 1.

### Files Deleted
```
app/Models/WorkflowProyek.php
app/Models/WorkflowStep.php
app/Models/WorkflowTahapan.php
app/Models/WorkflowLog.php
app/Filament/Resources/WorkflowProyekResource.php (+ 4 Pages)
app/Filament/Pages/WorkflowDetailPage.php
app/Filament/Widgets/WorkflowOverviewWidget.php
app/Services/KaryawanWorkflowService.php
app/Notifications/KaryawanWorkflowNotification.php
database/migrations/2024_01_02_000004_create_workflow_proyek_tables.php
database/migrations/2026_07_20_181806_create_workflow_proyeks_table.php
```

### Files Redesigned (System 1)

#### 1. `WorkflowIndicatorService.php` — Logic Fixes
- **nilai_efektif fix**: `$totalNilai = $kontrak->nilai_efektif ?: $kontrak->nilai ?: 1;` — progress payment now includes adendum (matches `Kontrak::hitungProgresOtomatis()` fix from prior session)
- **Overdue detection**: `$isOverdue = $kontrak->tgl_akhir && $kontrak->tgl_akhir < now() && !in_array($kontrak->status, ['completed', 'dibatalkan']);`
- **Stage overdue flag**: Passed as `'overdue' => $isOverdue` on the active stage only

#### 2. `workflow-indicator.blade.php` — Professional Pipeline UI
| Aspect | Before | After |
|--------|--------|-------|
| Node width | 80px | 140px |
| Label | truncated 10px | full label, `whitespace-nowrap` |
| Status badge | none | **SELESAI / BERJALAN / TERLAMBAT / MENUNGGU** above node |
| Colors | flat | gradient + shadow + ring |
| Active animation | `animate-ping` dot | pulse ring + scale hover |
| Overdue | none | **red node + exclamation icon + ping ring** |
| Connector | 20px thin | 24px thick, color-matched |
| Legend | 3 items | 4 items (adds Terlambat) + counts |
| Progress bar | 1.5px flat | 2px gradient + shadow-inner |

#### 3. `workflow-monitoring.blade.php` — Dashboard Cards + Filter
- **6 gradient stat cards**: Total (gray), Berjalan (blue), Selesai (emerald), **Terlambat (red)**, Belum (gray), Rata-rata (violet)
- **Search + filter**: Input (nomor/nama klien) + Select (Semua/Aktif/Selesai/Draft/Terlambat) — client-side JS
- **Contract cards**: Overdue = red border + ring + "OVERDUE" badge + current stage label
- **Pipeline embedded**: Uses `<x-workflow-indicator compact />`

## Key Technical Patterns

### Overdue Logic
```php
// In WorkflowIndicatorService::getStages()
$isOverdue = $kontrak->tgl_akhir && $kontrak->tgl_akhir < now() 
    && !in_array($kontrak->status, ['completed', 'dibatalkan']);

// Only the FIRST active stage gets overdue flag
$foundActive = false;
foreach ($stages as &$stage) {
    if ($stage['status'] === 'active' && !$foundActive) {
        $stage['overdue'] = $isOverdue;
        $foundActive = true;
    } elseif ($stage['status'] === 'active') {
        $stage['status'] = 'pending';
        $stage['overdue'] = false;
    }
}
```

### Blade Color Token Pattern
```php
$colors = $isOverdue
    ? ['bg' => 'bg-red-500', 'text' => 'text-red-700', 'line' => 'bg-red-300', ...]
    : ($isDone ? [...] : ($isActive ? [...] : [...]));

// Usage in HTML:
<div class="w-11 h-11 rounded-full {{ $colors['bg'] }} {{ $colors['ring'] }} ...">
<span class="{{ $colors['text'] }}">{{ $stage['label'] }}</span>
<div class="w-6 h-1 rounded-full {{ $colors['line'] }}"></div>
```

### Compact Prop for Embedding
```blade
@props(['compact' => false])
@if(!$compact)
    {{-- Legend with counts --}}
@endif
```

## Result

Single source of truth for workflow progress. Professional UI showing:
- ✅ Completed stages (green check)
- 🔵 Active stage (blue pulse)
- 🔴 **Overdue active stage** (red exclamation + ping ring)
- ⚪ Pending stages (gray initial)
- Live progress bar (gradient)
- Search/filter across 7 contracts
- 6 KPI cards with overdue highlighted

## Files for Reference

| File | Purpose |
|------|---------|
| `app/Services/WorkflowIndicatorService.php` | Pipeline calculation + overdue |
| `resources/views/components/workflow-indicator.blade.php` | Pipeline component (reusable) |
| `resources/views/filament/pages/workflow-monitoring.blade.php` | Dashboard page |
| `app/Filament/Pages/WorkflowMonitoringPage.php` | Page class (minimal) |