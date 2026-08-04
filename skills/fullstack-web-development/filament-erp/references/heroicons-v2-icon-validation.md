# Heroicons v2 Icon Naming Convention — Filament Mapping

## Context
blade-heroicons v2.7+ changed the SVG file naming convention from v1. Filament's `heroicon-o-X` syntax maps to specific SVG filenames. Using an invalid name crashes the ENTIRE admin panel (not just the page using it) because Filament renders all navigation items on every page load.

## File Naming Map (blade-heroicons v2)

| Filament syntax | SVG filename prefix | Size | Example |
|---|---|---|---|
| `heroicon-o-X` | `c-X.svg` | 24×24 outline | `heroicon-o-user-group` → `c-user-group.svg` |
| `heroicon-m-X` | `m-X.svg` | 16×16 mini | `heroicon-m-user-group` → `m-user-group.svg` |
| `heroicon-s-X` | `s-X.svg` | 24×24 solid | `heroicon-s-user-group` → `s-user-group.svg` |

**Note:** The `o-` prefix exists in v2 SVG files too (16×16 mini), but Filament uses it for outline (24×24), which maps to `c-` in the SVG files. This is a common source of confusion.

## Known Removed/Renamed Icons (v1 → v2)

| Old name (v1) | New name (v2) | Notes |
|---|---|---|
| `heroicon-o-arrow-right-on-rectangle` | `heroicon-o-arrow-right-start-on-rectangle` | Sign-out icon |
| `heroicon-o-sitemap` | **REMOVED** — no replacement | Use `heroicon-o-user-group` or `heroicon-o-building-office` |
| `heroicon-o-arrow-left-on-rectangle` | `heroicon-o-arrow-left-start-on-rectangle` | Sign-in icon |

## Validation Steps

### 1. Extract all icon references from code
```bash
grep -rn "navigationIcon" app/Filament/ | grep "heroicon"
```

### 2. Get all available SVG filenames
```bash
ls vendor/blade-ui-kit/blade-heroicons/resources/svg/ | sed 's/.svg//' | sort
```

### 3. Cross-reference
Map each `heroicon-o-X` to `c-X` and check existence. One missing icon = entire admin panel down.

### 4. Quick reset after fix
```bash
php artisan icons:clear && php artisan view:clear && php artisan icons:cache
```

## Why One Bad Icon Crashes Everything
Filament's sidebar navigation is rendered on every admin page. The nav builder iterates ALL registered Resources/Pages and calls `<x-icon name="..." />` for each. If ANY icon name throws `SvgNotFound`, the entire page render fails — not just the sidebar, the ENTIRE page (including the content area).

The error message in `storage/logs/laravel.log` will show:
```
Svg by name "o-XXXX" from set "heroicons" not found
```
followed by a stack trace through `icon.blade.php` repeated 5+ times (once per view layer).

## Validation Script
See `scripts/validate-icons.sh` — run from project root to check all icons at once.
