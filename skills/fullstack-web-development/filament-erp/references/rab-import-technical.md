# RAB Import — Technical Reference (July 23, 2026)

## Files
- `app/Services/RabImportService.php` — Parsing + import logic
- `app/Filament/Resources/RabResource/Pages/ImportRab.php` — Filament page (2-mode: auto-create or add to existing)
- `resources/views/filament/resources/rab-resource/pages/import-rab.blade.php` — Blade view

## DB Requirement
```sql
ALTER TABLE rab ADD COLUMN is_markup_applied TINYINT(1) NOT NULL DEFAULT 0 AFTER markup_persen;
```

## Import Flow: Auto-Create Parent from File

Default mode: upload file → system auto-creates RAB + imports all komponen. No manual RAB creation needed.

```
Upload File → Mode: "Buat RAB Baru dari File" (default) → Parse → Preview → "Import & Buat RAB Baru"
                                                                          ↓
                                                              Rab::create() + importAllSheets()
                                                                          ↓
                                                              Redirect to list RAB
```

**Mode selector**: Radio with `'new'` (default) and `'existing'` options. Show/hide sections via `->visible(fn () => $this->import_mode === 'new')`.

**Auto-generate nomor**: `RAB-YYYYMM-XXXX` with collision check. Generated on `mount()`, re-generated after each successful import.

**Critical pitfall**: `->relationship('kontrak', 'nomor_kontrak')` on Select does NOT work in custom Filament Pages. Use `->options(fn () => Kontrak::pluck('nomor_kontrak', 'id'))` instead. Error: `Call to a member function isRelation() on null` at Select.php.

## Auto-Detect Header Algorithm

Score-based, not first-match:
```php
// Exact match = 100 pts, Contains = len(keyword) * 5
// Best field per column wins. Header row threshold: score >= 20.
```

### Keyword Priority (specific → generic)
```php
'harga_satuan' => ['harga satuan', 'harga/satuan', 'harga', 'rupiah', 'price'],
'jumlah_harga' => ['total', 'jumlah harga', 'subtotal', 'jumlah', 'amount'],
'volume'       => ['volume', 'qty', 'quantity', 'kuantitas', 'vol'],
```

## Indonesian Number Format
```php
// "1.500.000,50" → 1500000.50
$str = str_replace(['Rp', ' '], '', $str);
$str = str_replace('.', '', $str);
$str = str_replace(',', '.', $str);
return (float) $str;
```

## Skip Patterns
- Empty rows: all cells null/empty
- Total rows: first cell contains "total", "subtotal", "jumlah", "grand total"
- Short uraian (< 3 chars)

## PhpSpreadsheet Notes
- v1.30.5 (via maatwebsite/excel ^1.30.4)
- ext-zip REQUIRED for .xlsx — `C:\laragon\bin\php\php-X.X\ext\php_zip.dll`
- `toArray()` may throw on broken formulas — use cell-by-cell fallback

## Livewire Property Binding
- Public properties MUST match form field names
- Use non-nullable defaults: `string $x = ''`, `array $x = []`, `bool $x = false`
- FileUpload: `public $file = null` (no type hint)
- Missing `InteractsWithForms` trait → Livewire can't bind form state
- `->relationship()` on Select in custom Pages → `isRelation() on null`

## Multi-Sheet Import
- `parseAllSheets()` iterates all sheets, skips empty ones
- `importAllSheets()` imports all parsed sheets into one RAB
- Dashboard/summary sheets (e.g., "RESUME") parse as 0 rows → auto-skipped

## Form UX
- Collapsible sections: `->collapsible()->collapsed()` on metadata sections
- Header actions for cross-page navigation (Import ↔ Create ↔ List)
