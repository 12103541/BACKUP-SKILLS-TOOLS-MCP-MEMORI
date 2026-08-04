# RAB Sumber Badge — List View Differentiation

## Context
Session 2026-07-31: User requested clear visual differentiation in RAB List view between AI-generated, manual, imported, and template RABs. Previously only icons existed without text labels.

## Implementation Summary
**Files modified:**
1. `database/migrations/2026_07_31_134941_add_sumber_field_to_rab_table.php` — enum column `sumber` ('manual'|'ai'|'import'|'template') default 'manual', indexed
2. `app/Models/Rab.php` — added `sumber` to `$fillable`
3. `app/Filament/Resources/RabResource/Pages/CreateRab.php` — AI Copilot action sets `sumber='ai'` on apply
4. `app/Filament/Resources/RabResource/Pages/ImportRab.php` — new RAB from file sets `sumber='import'`
5. `app/Filament/Resources/RabResource.php` — table column + filter

## Table Column (RabResource.php)
```php
Tables\Columns\TextColumn::make('sumber')
    ->label('Sumber')
    ->badge()
    ->color(fn (string $state): string => match ($state) {
        'ai' => 'warning',
        'import' => 'info',
        'template' => 'success',
        default => 'gray',
    })
    ->icon(fn (string $state): string => match ($state) {
        'ai' => 'heroicon-o-sparkles',
        'import' => 'heroicon-o-arrow-down-tray',
        'template' => 'heroicon-o-document-duplicate',
        default => 'heroicon-o-pencil',
    })
    ->formatStateUsing(fn (string $state): string => match ($state) {
        'ai' => '🤖 AI',
        'import' => '📁 Import',
        'template' => '📋 Template',
        default => '✏️ Manual',
    })
    ->sortable()
    ->toggleable(),
```

## Filter
```php
Tables\Filters\SelectFilter::make('sumber')
    ->options([
        'manual' => '✏️ Manual',
        'ai' => '🤖 AI',
        'import' => '📁 Import',
        'template' => '📋 Template',
    ])
    ->label('Sumber'),
```

## Visual Result (Verified in Browser)
| Sumber Value | Badge Text | Color | Icon |
|--------------|------------|-------|------|
| `ai` | 🤖 AI | warning (orange) | sparkles |
| `import` | 📁 Import | info (blue) | arrow-down-tray |
| `template` | 📋 Template | success (green) | document-duplicate |
| `manual` | ✏️ Manual | gray | pencil |

## Key Pattern: Badge + Icon + Text
Filament's `TextColumn::badge()` combined with:
- `color()` — semantic color per value
- `icon()` — heroicon per value  
- `formatStateUsing()` — **human-readable label with emoji** (not just icon)

This ensures **at-a-glance differentiation** without hovering or clicking.

## Pitfall Avoided
Initially only `icon()` was used — user correctly noted "tidak ada keterangan hanya icon". Added `formatStateUsing()` with explicit emoji+text labels. Badge styling makes it prominent in table row.