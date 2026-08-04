# RAB Module — Route & View Map

## Routes (from web.php)

| Route | Name | Controller | View |
|-------|------|-----------|------|
| `GET /rab` | rab.index | RabController@index | rab.index |
| `GET /rab/create` | rab.create | RabController@create | rab.create |
| `GET /rab/{rab}` | rab.show | RabController@show | rab.show |
| `GET /rab/{rab}/detail` | rab.detail | RabController@detail | rab.detail |
| `GET /rab/import` | rab.import.form | RabController@importForm | rab.import |
| `GET /rab/{rab}/export-pdf` | rab.exportPdf | RabController@exportPdf | pdf.rab-export |

## View Details

### rab.show (read-only view)
- **URL**: `/rab/{id}`
- **Column headers**: No, Uraian Pekerjaan, Satuan, Volume, Harga Satuan, Jumlah
- **Formatting**: ✅ Already uses `number_format($hargaSatuan, 0, ',', '.')`
- **Features**: Finalisasi, Export PDF, markup info display

### rab.detail (editable workbench)
- **URL**: `/rab/{id}/detail`
- **Column headers**: No, Uraian Pekerjaan, Satuan, Volume, Harga Ref AI, Harga Satuan, Jumlah, Markup, Harga Markup, Keterangan
- **Formatting**: ✅ Already uses `number_format()` on display columns
- **Features**: Inline editing, auto-harga, markup, AI analysis, smart pricing panels

### rab.index (list page)
- **URL**: `/rab`
- **Features**: Table listing, filter, search, pagination

### Mystery: /admin/rab/{id}/view
- **Status**: Route NOT in web.php. Page renders a "RAB Workbench" with different columns (#, Uraian Pekerjaan, Vol, Sat, Hrg Sat, Jumlah Hrg, Hrg Pasar, Δ%, Status, Rekomendasi, Aksi)
- **Formatting**: ❌ Shows "Rp3" instead of "Rp 3.000"
- **Source file**: CANNOT be found anywhere in codebase. Possibly created by previous AI session and deleted, or generated dynamically.
- **Action**: If user references this URL, check if they can access `/rab/{id}` or `/rab/{id}/detail` instead. If Workbench is needed, it must be rebuilt.

## Quick Debugging

To verify formatting on any RAB view:
```bash
# Check what view a controller method returns
grep -n "return view\|return.*->view" app/Http/Controllers/Web/RabController.php

# Search compiled views for specific strings
grep -rn "Hrg Sat\|Jumlah Hrg\|harga_satuan" storage/framework/views/*.php

# Verify route exists
php artisan route:list 2>/dev/null | grep "rab"
```
