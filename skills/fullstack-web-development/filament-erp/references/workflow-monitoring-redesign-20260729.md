# Workflow Monitoring Redesign — 2026-07-29

## Context
User asked to redesign workflow monitoring page indicators to be professional, readable, and show progress per stage. Existing component was basic (small circles, truncated labels, no overdue indication).

## Problem Analysis
- **Node width**: 80px → labels truncated to single letter (R, P, A, F, L)
- **No status badges**: Couldn't tell SELESAI/BERJALAN/MENUNGGU at a glance
- **No overdue detection**: Contracts past `tgl_akhir` looked same as active
- **Progress bar**: Flat color, no gradient/shadow
- **Missing legend**: No summary of completed/active/overdue/pending counts

## Solution: Professional Pipeline Component

### `resources/views/components/workflow-indicator.blade.php`

Key design decisions:
1. **Node width 140px** (was 80px) — full labels fit with `whitespace-nowrap`
2. **Status badges above nodes**: SELESAI (emerald), BERJALAN (blue), TERLAMBAT (red+pulse), MENUNGGU (gray)
3. **Animated nodes**: 
   - Completed: checkmark + emerald bg
   - Active: white dot + blue pulse ring
   - Overdue: warning icon + red pulse ring
   - Pending: initial letter + gray
4. **Progress bar**: 2px height, gradient (blue→emerald), shadow-inner
5. **Connector lines**: Match stage color (emerald/blue/red/gray)
6. **Legend**: Shows counts per status

### `WorkflowIndicatorService` Enhancements
- **Fix `nilai_efektif`**: Payment progress uses `nilai_efektif` (includes adendum) not `nilai`
- **Overdue detection**: `$isOverdue = $kontrak->tgl_akhir && $kontrak->tgl_akhir < now() && !in_array($kontrak->status, ['completed','dibatalkan'])`
- **Stage overdue flag**: Only first active stage gets `overdue=true` flag passed to component

### Monitoring Page (`workflow-monitoring.blade.php`)
- **6 gradient stat cards**: Total, Berjalan, Selesai, **Terlambat** (red), Belum, Rata-rata
- **Search + filter**: Client-side JS, instant filter by status + overdue
- **Card border**: Overdue contracts get `border-red-200 ring-1 ring-red-100` + "OVERDUE" badge

## Pattern for Future Pipeline Displays

```blade
<!-- Reusable component signature -->
@props(['stages' => [], 'progress' => 0, 'title' => 'Workflow Progress', 'compact' => false])

<!-- Stage array structure -->
$stage = [
    'id' => 'kontrak',           // unique key
    'label' => 'Kontrak',        // display name (full, not truncated)
    'icon' => 'file-contract',   // optional icon name
    'description' => '...',      // optional hover text
    'status' => 'completed|active|pending|overdue',
    'overdue' => false,          // boolean, only true for first active if overdue
    'date' => '29 Jul 2026',     // short date/status text
    'detail' => 'KTR-2026-001',  // secondary info
    'route' => '/admin/kontraks/10'
]
```

## Files Changed
- `app/Services/WorkflowIndicatorService.php` — logic fixes + overdue
- `resources/views/components/workflow-indicator.blade.php` — full redesign
- `resources/views/filament/pages/workflow-monitoring.blade.php` — page redesign

## Cleanup: Duplicate Workflow System Removed
- Deleted `WorkflowProyek`/`WorkflowStep`/`WorkflowTahapan` models (0 data)
- Deleted `WorkflowProyekResource` + pages
- Deleted `WorkflowDetailPage`, `WorkflowOverviewWidget`
- Deleted 2 migration files
- Single source of truth: 7-stage pipeline from `WorkflowIndicatorService`