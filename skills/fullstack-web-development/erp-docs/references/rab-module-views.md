# RAB Module — Views, Routes, and Navigation

## Route Map (verified July 2026)

### Filament Routes (Apache path: `C:\laragon\www\...`)
| URL | Resource/Page | View |
|-----|---------------|------|
| `GET /admin/rab` | `RabResource → ListRabs` | Filament table |
| `GET /admin/rab/create` | `RabResource → CreateRab` | Filament form |
| `GET /admin/rab/{id}` | `RabResource → EditRab` | Filament form |
| `GET /admin/rab/{id}/view` | `RabResource → ViewRab` | **RAB Workbench** (custom Blade) |
| `GET /admin/rab/import` | `RabResource → ImportRab` | Filament page |

### Web Routes (vanilla Laravel)
| URL | Route Name | Controller | Blade View |
|-----|------------|------------|------------|
| `GET /rab` | `rab.index` | `RabController@index` | `rab/index.blade.php` |
| `GET /rab/create` | `rab.create` | `RabController@create` | `rab/create.blade.php` |
| `GET /rab/{rab}` | `rab.show` | `RabController@show` | `rab/show.blade.php` |
| `GET /rab/{rab}/detail` | `rab.detail` | `RabController@detail` | `rab/detail.blade.php` |

## RAB Workbench (Filament Custom Page)

**URL**: `/admin/rab/{id}/view`
**PHP**: `app/Filament/Resources/RabResource/Pages/ViewRab.php` (Apache path only)
**Blade**: `resources/views/filament/resources/rab-resource/pages/view-rab.blade.php`

### Columns
`#`, Uraian Pekerjaan, Vol, Sat, **Hrg Sat** (editable input), **Jumlah Hrg** (calculated), Hrg Pasar, Δ%, Status, Rekomendasi, Aksi

### Price Formatting Rules
- Hrg Sat input: `type="text" inputmode="numeric"` with "Rp" prefix label, displays `$hargaSatDisplay` (uses `number_format()`)
- Jumlah Hrg: `<span>Rp {{ number_format($item['jumlah_harga'], 0, ',', '.') }}</span>`
- All rupiah display: `Rp ` (with space) + `number_format($val, 0, ',', '.')`
- wire:change strips non-numeric: `$event.target.value.replace(/[^0-9]/g,'')`

### Key Filament Files
```
app/Filament/Resources/RabResource.php
app/Filament/Resources/RabResource/Pages/ViewRab.php
app/Filament/Resources/RabResource/Pages/ListRabs.php
app/Filament/Resources/RabResource/Pages/EditRab.php
app/Filament/Resources/RabResource/Pages/CreateRab.php
app/Filament/Resources/RabResource/Pages/ImportRab.php
app/Filament/Resources/RabResource/RelationManagers/RabKomponenRelationManager.php
```

## Notes
- Path (B) OneDrive copy does NOT have Filament files — always search/edit at path (A) Apache root
- After editing Blade templates, run `php artisan view:clear` to invalidate compiled cache
