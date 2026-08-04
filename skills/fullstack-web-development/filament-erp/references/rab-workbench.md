# RAB Workbench — Livewire Component

## Route
`/admin/rab/{id}/view` — renders "RAB Workbench — {nomor}" with 500+ items in an editable table.

## Key Columns
#, Uraian Pekerjaan, Vol, Sat, Hrg Sat, Jumlah Hrg, Hrg Pasar, Δ%, Status, Rekomendasi, Aksi

## Architecture
This page uses **Livewire** (`wire:snapshot` in HTML). However:
- There is NO `app/Livewire/` directory in the project
- No blade template contains "Hrg Sat" or "RAB Workbench" text
- The component is likely embedded inline or registered via a non-standard mechanism
- Standard `grep`/`find` searches across `resources/views/` and `app/` will NOT find it

## How to Locate the Source
1. Check `wire:snapshot` in the rendered HTML — parse `memo` for the component class name
2. Run `php artisan route:list --path="rab"` to find the route handler
3. Search `storage/framework/views/` for compiled views containing "Workbench"
4. Check if the component is registered in a Filament panel provider or service provider

## Known Price Formatting Issue (2026-07-28)
The "Hrg Sat" column renders raw floats (e.g., "3", "3.2") without `number_format()`.
The "Jumlah Hrg" column shows truncated values like "Rp3" instead of "Rp 3.000".

**Fix**: Find the Livewire component's render method and apply:
```php
'Rp ' . number_format($item->harga_satuan, 0, ',', '.')
```

For blade templates, the correct pattern is:
```blade
Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}
```

## Related Routes (rab module)
- `rab.show` → `GET /rab/{rab}` → `rab.show` blade (read-only)
- `rab.detail` → `GET /rab/{rab}/detail` → `rab.detail` blade (editable, form-based)
- Workbench → `/admin/rab/{id}/view` → Livewire component (full-featured editor)
