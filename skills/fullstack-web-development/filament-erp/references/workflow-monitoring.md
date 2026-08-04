# Workflow Monitoring Component Reference

## Component: `workflow-indicator.blade.php`

### Purpose
Multi-division pipeline visualization with color-coded stages per division, used in Workflow Monitoring page.

### Props
```php
@props([
    'stages' => [],           // Array of stage data from WorkflowIndicatorService
    'progress' => 0,          // Overall progress percentage (0-100)
    'title' => 'Workflow Progress',
    'compact' => false        // Hide validation panel if true
])
```

### Stage Data Structure
Each stage in `$stages` array:
```php
[
    'label' => 'Kontrak|RAB|Penawaran|Pekerjaan|Approval|Faktur|Lunas 100%',
    'status' => 'completed|active|pending',
    'overdue' => false,
    'route' => '/admin/...',   // Optional link to stage page
    'date' => '30 Jul 2026',   // Optional date display
    'detail' => 'KONTRAK/ME-CIPALI/2026/001',  // Subtitle
    'description' => 'Optional long description',
    'validations' => [         // For validation panel
        ['label' => 'Nomor kontrak sudah diisi', 'status' => 'passed'],
        ['label' => 'Klien sudah dipilih', 'status' => 'passed'],
        // ...
    ],
    'required_action' => 'Next action text',
    'target_days' => 7,
    'target_days_left' => 7,
    'time_usage_percent' => 0,
]
```

### Color Scheme Per Division

| Division | Primary | Light BG | Dark Text | Icon BG | Ring | Connector Dot | Gradient |
|----------|---------|----------|-----------|---------|------|---------------|----------|
| Kontrak | indigo | indigo-50 | indigo-700 | bg-indigo-500 | ring-indigo-200 | bg-indigo-500 | from-indigo-500 to-indigo-600 |
| RAB | violet | violet-50 | violet-700 | bg-violet-500 | ring-violet-200 | bg-violet-500 | from-violet-500 to-violet-600 |
| Penawaran | amber | amber-50 | amber-700 | bg-amber-500 | ring-amber-200 | bg-amber-500 | from-amber-500 to-amber-600 |
| Pekerjaan | orange | orange-50 | orange-700 | bg-orange-500 | ring-orange-200 | bg-orange-500 | from-orange-500 to-orange-600 |
| Approval | emerald | emerald-50 | emerald-700 | bg-emerald-500 | ring-emerald-200 | bg-emerald-500 | from-emerald-500 to-emerald-600 |
| Faktur | blue | blue-50 | blue-700 | bg-blue-500 | ring-blue-200 | bg-blue-500 | from-blue-500 to-blue-600 |
| Lunas 100% | rose | rose-50 | rose-700 | bg-rose-500 | ring-rose-200 | bg-rose-500 | from-rose-500 to-rose-600 |

### Key Visual Features (Updated)

#### Gradient Circles with Division Icons
- **Completed stages**: Gradient background + white checkmark + **division-specific icon** (bottom-right, w-4 h-4)
- **Active stages**: Gradient background + white dot + **division-specific icon** + pulsing outer ring
- **Pending stages**: Dimmed gradient + initial letter
- **Overdue stages**: Red gradient + warning icon + red ping animation

#### Division Icons (SVG inline) - Updated Size w-4 h-4 (16px)
```php
'Kontrak' => '<svg class="w-4 h-4 text-indigo-600" stroke-width="3">document</svg>',
'RAB' => '<svg class="w-4 h-4 text-violet-600" stroke-width="3">calculator</svg>',
'Penawaran' => '<svg class="w-4 h-4 text-amber-600" stroke-width="3">invoice list</svg>',
'Pekerjaan' => '<svg class="w-4 h-4 text-orange-600" stroke-width="3">construction</svg>',
'Approval' => '<svg class="w-4 h-4 text-emerald-600" stroke-width="3">shield check</svg>',
'Faktur' => '<svg class="w-4 h-4 text-blue-600" stroke-width="3">dollar</svg>',
'Lunas 100%' => '<svg class="w-4 h-4 text-rose-600" stroke-width="3">check badge</svg>',
```
Icons placed in `w-6 h-6 bg-white/95 rounded-full shadow-md` badge at `bottom-0 right-0` for visibility against gradient.

#### Connectors
- **Completed**: Full division color (`bg-{color}-500`)
- **Active**: 50% opacity division color (`bg-{color}-500/50`)
- **Pending**: Gray (`bg-gray-200`)

#### Status Badges (Pill-shaped, division-colored)
```php
$statusBadge = match(true) {
    $isOverdue => 'bg-red-100 text-red-700 border-red-200',
    $isDone => $colors['light'] . ' ' . $colors['dark'] . ' border-' . $colors['primary'] . '-200',
    $isActive => $colors['light'] . ' ' . $colors['dark'] . ' border-' . $colors['primary'] . '-200',
    default => 'bg-gray-100 text-gray-500 border-gray-200',
};
```

#### Animations
- Active stage: `animate-pulse` on inner gradient + outer ring `animate-pulse`
- Overdue: `animate-ping` red ring
- Hover: `group-hover:scale-110` on circles
- Connector transitions: `transition-colors duration-300`

### Validation Panel (bottom section)
Shows for active stage when `!compact`:
- **Left**: Validation checklist with ✅/❌/⏳ icons in colored cards
- **Right**: Timeline progress bar + Next Action box + **"Buka halaman" link**

### Usage Example
```blade
<x-workflow-indicator
    :stages="$workflow['stages']"
    :progress="$workflow['progress']"
    :title="'Workflow Progress'"
    :compact="false"
/>
```

### Data Source
Data comes from `WorkflowIndicatorService::getWorkflowStages($kontrak)` which queries:
- Kontrak, RAB, Penawaran, Pekerjaan, Approval, Faktur, Pembayaran models
- Returns structured stages with validation results per division

### Files
- `resources/views/components/workflow-indicator.blade.php` — Main component
- `resources/views/components/workflow-stage-validation.blade.php` — Accordion validation panel (same color scheme)

### Pitfalls & Gotchas (Updated)

1. **Variable scope**: `$label` must be defined BEFORE `$stageColors` array access (line 45 in current version)
2. **Validation panel variables**: Must be pre-computed at template root (before `@foreach`), not inside loop
3. **Compact mode**: Set `compact=true` in list rows to hide validation panel
4. **Gradient classes**: Use `bg-gradient-to-br from-{color}-500 to-{color}-600` not single-color bg
5. **Connector positioning**: Absolute positioned `left-1/2 -translate-x-1/2 top-full h-4 -mt-0.5` for visual continuity

### Route Fixes Applied (2026-07-30)
**Issue**: "Buka halaman RAB" and "Buka halaman Penawaran" links were broken
**Root cause**: Incorrect routes in `WorkflowIndicatorService.php`:
- RAB: `/admin/rabs?kontrak_id=` → **Fixed to**: `/admin/rab?kontrak_id=`
- Penawaran: `/admin/penawarans?kontrak_id=` → **Fixed to**: `/admin/penawaran?kontrak_id=`

Filament resource slugs are singular (`rab`, `penawaran`), not plural. The `?kontrak_id=` query param filters the index page correctly.

### Icon Size Update (2026-07-30)
- **Before**: `w-3 h-3` (12px) icons inside `w-4 h-4` badges
- **After**: `w-4 h-4` (16px) icons inside `w-6 h-6` badges with `shadow-md`
- **Stroke width**: Increased from 2 → 3 for better visibility
- **Position**: `bottom-0 right-0` (was `bottom-1 right-1`) to prevent clipping