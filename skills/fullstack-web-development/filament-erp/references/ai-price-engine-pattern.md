# AI Price Engine Pattern — Database-Backed Approach (Updated)

## Purpose
Populate `harga_referensi` database with market prices and run SmartPricingService analysis on RAB items. Two entry points: (1) manual input form in AI Price Dashboard, (2) batch via Artisan CLI.

## Architecture (Working Version)
```
┌─────────────────────────────────────────────────────────┐
│                   AI Price Dashboard                     │
│                  (Filament Page)                         │
├─────────────────────────────────────────────────────────┤
│  ┌────────────────────┐   ┌────────────────────────┐    │
│  │ Manual Input Form  │   │ Analisa RAB            │    │
│  │ Nama | Harga |     │   │ (SmartPricingService)  │    │
│  │ Satuan | Sumber |  │   │                        │    │
│  │ Kategori           │   │ Markup / AutoPrice /    │    │
│  │                    │   │ CostOpt / VolDiscount   │    │
│  └────────┬───────────┘   └────────────┬───────────┘    │
│           ▼                            ▼                │
│  ┌─────────────────────────────────────────────────┐    │
│  │     HargaReferensi DB (12+ records)             │    │
│  │  marketplace | google | supplier | ahsp | manual │    │
│  └──────────────┬──────────────────────────────────┘    │
│                 ▼                                       │
│  ┌─────────────────────────────────────────────────┐    │
│  │     SmartPricingService (4 engines)              │    │
│  └─────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────┘
```

**Key lesson**: Google Shopping scraping is blocked from localhost/Laragon and most VPS providers. Don't depend on it. Build on existing DB data + manual input.

## Files
- `app/Services/SmartPricingService.php` — 4 analysis engines (pre-existing)
- `app/Services/GoogleShoppingScraper.php` — Google scraping (exists but unreliable)
- `app/Services/AiPriceAnalyst.php` — AI keyword analysis (requires Ollama)
- `app/Console/Commands/HargaScrape.php` — CLI batch command
- `app/Filament/Resources/RabResource/Pages/AiPriceDashboard.php` — Filament UI
- `resources/views/.../ai-price-dashboard.blade.php` — Blade view

## SmartPricingService API (4 Engines)

### analyzeOptimalMarkup(Rab $rab) → Markup analysis
```json
{
  "items": [{ "item", "harga_modal", "volume", "harga_jual", "markup_rekomendasi",
              "penyesuaian": ["+10% (kompetisi rendah)"], "kompetisi", "harga_pasar_rata2" }],
  "summary": { "total_current", "total_optimal", "potential_savings", "savings_percent",
               "avg_markup_current", "avg_markup_recommended" }
}
```
Algorithm: base 15%, adjusted for competition (-3% to +10%), volume (-8% to 0%), price volatility (+2% to +5%), market position (-5% to +8%). Clamped 3%–50%.

### autoPriceDiscovery(Rab $rab) → Batch price update
```json
{
  "updated": [{ "item", "harga_lama", "harga_baru", "selisih", "sumber",
                "sumber_tipe", "potensi_hemat" }],
  "no_match": ["RabKomponen"],
  "total_updated", "total_no_match", "total_potential_savings"
}
```
Finds cheapest reference for each item via `cariHargaMultiSumber()`.

### costOptimizationSuggestions(Rab $rab) → Cheaper alternatives
```json
{
  "suggestions": [{ "item", "tipe", "harga_saat_ini", "harga_alternatif",
                    "hemat_per_unit", "hemat_total", "alternatif_nama", "alternatif_merek" }],
  "general_tips": [{ "tipe", "judul", "deskripsi", "aksi" }],
  "total_suggestions", "total_potential_savings"
}
```
Only suggests when total savings > Rp 10,000 per item.

### volumeDiscountCalculator(Rab $rab) → Volume tier pricing
```json
{
  "items": [{ "item", "volume", "harga_satuan", "total_saat_ini",
              "tiers": [{ "label", "discount_persen", "harga_setelah_diskon", "hemat" }],
              "best_tier" }],
  "total_items_with_discount", "total_potential_saving"
}
```
Tier configs: 100+ = 15%, 50-99 = 10%, 20-49 = 7%, 10-19 = 5%, 5-9 = 3%.

## Livewire Property Rules for AiPriceDashboard

```php
// ✅ WORKING — nullable with non-null default
public ?string $manualNama = '';
public ?string $manualHarga = '';
public string $manualSatuan = 'unit';
public ?string $manualSumber = '';
public ?string $manualKategori = '';

// ✅ WORKING — internal state
public string $selectedRabId = '';
public array $rabList = [];
public array $analisaResults = [];
public bool $isAnalisaRunning = false;
```

**Pitfall**: `public string $manualNama = ''` (without `?`) → 500 `Cannot assign null to property ... of type string`. Livewire sets null before user interacts with a field. Add `?` to allow null assignment.

## Notification Pattern (Filament v3)

```php
// ✅ CORRECT
Notification::make()->success('Pesan berhasil')->send();
Notification::make()->warning('Peringatan')->send();
Notification::make()->danger('Error: ' . $e->getMessage())->send();

// ❌ WRONG — causes 500: "Non-static method cannot be called statically"
Notification::success('...');
Notification::warning('...');
Notification::danger('...');
```

Every notification in Filament v3 MUST go through the `make()` factory. `success()`, `warning()`, `danger()` are instance methods, not static.

## Manual Input Workflow
1. Enter: nama_item (required), harga (required numeric), satuan (select), sumber (text), kategori (text)
2. Click "Simpan Harga" → saves to `harga_referensi` with `sumber_tipe = 'manual'`
3. Optional: click "Cari di Database" to search existing references

## RAB Analysis Workflow
1. Select RAB from dropdown
2. Click "Jalankan Analisa" → runs all 4 SmartPricingService engines
3. Review results: Markup analysis + Auto Price Discovery + Cost Optimization + Volume Discount
4. Click "Terapkan Auto Price" → updates RAB item prices from cheapest reference
5. Recalculates `$rab->total_rab` after price update

## CLI Commands
```bash
# Single keyword (Google scrape + save)
php artisan harga:scrape "kabel NYM 2x2.5mm" --save

# Bulk from RAB
php artisan harga:scrape --rab=10 --save

# Search only
php artisan harga:scrape "MCCB 100Amp" --limit=5
```
Note: Google scraping returns empty from local servers. CLI is useful with residential proxy.

## Integration with RAB Workbench
When "Jalankan Analisa" is clicked in Workbench:
1. Each komponen → `HargaReferensi::cariHargaMultiSumber($uraian, 5)`
2. Compute avg market price from references
3. Compute Δ% = (harga_kita - avg_market) / avg_market × 100
4. Classify: overpriced (>20%), underpriced (<-20%), referenced (±20%), no_ref
5. SmartPricingService calculates rekomendasi (markup %, optimal price)
6. User clicks "Terapkan" to accept recommendation per-item

## HargaReferensi Search Methods
```php
HargaReferensi::cariHarga($keyword)            // Single best match (4-tier fallback)
HargaReferensi::cariBeberapaReferensi($kw, 5)   // Multiple matches
HargaReferensi::cariHargaMultiSumber($kw, 2)    // Per sumber_tipe priority
HargaReferensi::bersihkanKeyword($keyword)      // Strip stopwords, numbers, short words
```
Search priority: marketplace → google → supplier → ahsp → historis → manual

## Key Pitfalls
1. **Google blocks local scraping** — without residential proxy, GoogleShoppingScraper returns 0 results. Build on DB data + manual input.
2. **Notification::danger() static call** — 500 error. Always use `Notification::make()->danger()->send()`.
3. **Livewire null assignment** — `string $prop = ''` causes 500 on null. Use `?string $prop = ''`.
4. **Analysis is session-only** — harga_pasar/rekomendasi are PHP array properties, not DB columns. Lost on reload. If persistence needed, add DB columns.
5. **Performance on 200+ items** — N separate DB queries per RAB. Consider chunking for large RABs.
6. **cariHargaMultiSumber priority** — marketplace searched first (best commercial prices), manual last.
7. **Don't use InteractsWithForms + getFormSchema for pure blade pages** — use direct `wire:model` in blade instead. Filament's form system conflicts with Livewire state management on custom Pages.
8. **Existing services before building new** — SmartPricingService already had all 4 analysis engines. Check `app/Services/` before creating new analysis classes.
9. **bersihkanKeyword() over-stripping** — CRITICAL. RAB items follow "Pekerjaan Lain-Lain : Pekerjaan Pemasangan X" pattern. The stopwords list removes "pekerjaan" but "lain-lain" (meaning "other/misc") is also noise. Numbers stripped (100Amp → removed). After cleaning, the query string contains noise words that don't match any reference. Result: ALL items marked "no_ref" even when reference data exists. Fix approach: (a) Add "lain", "lain-lain" to stopwords, (b) Add RAB prefix pattern extraction: detect "Pekerjaan.*:" prefix → extract the meaningful keyword after the last colon, (c) In `cariHargaMultiSumber()`, when full cleaned string fails, split into individual words > 3 chars and try each as separate LIKE query, picking matches with most word overlap. (d) Seed enough data — search quality is bounded by reference data coverage.
10. **N×query for analisa** — Each of 204 RAB items triggers a separate `cariHargaMultiSumber()` call (each with fulltext + LIKE fallback). 204 queries × 3 fallback tiers = up to 600+ DB queries. Fix: collect all uraian keywords, build a single batch query: `HargaReferensi::whereRaw("MATCH(nama_item) AGAINST(? IN BOOLEAN MODE)", [batchKeywords])->get()`, then map results to items in PHP by word overlap scoring.
11. **Kontrak→Rab missing reverse relationship** — Kontrak model has no `hasMany(Rab::class, 'kontrak_id')`. Cannot list RABs from Kontrak detail page. Always add both directions: `Rab::belongsTo(Kontrak)` AND `Kontrak::hasMany(Rab)`.

## Keyword Cleaning Improvements (Recommended)

Current `bersihkanKeyword()` is too aggressive for ERP item names. Recommended fixes:

```php
// Add to stopwords: RAB-specific noise words
'lain', 'lain-lain', 'dan lain',

// Add RAB prefix extraction BEFORE general cleaning
private static function extractProductKeyword(string $keyword): string
{
    // Pattern: "Pekerjaan Lain-Lain : Pekerjaan Pemasangan MCCB 100Amp"
    // Extract after last colon
    $parts = explode(':', $keyword);
    if (count($parts) > 1) {
        $keyword = end($parts); // "Pekerjaan Pemasangan MCCB 100Amp"
    }
    // Remove "Pekerjaan", "Pemasangan", "Pembuatan" prefix words
    $keyword = preg_replace('/\b(pekerjaan|pemasangan|pembuatan|pemasangan)\b/i', '', $keyword);
    return trim($keyword);
}
```

Also add word-level fallback in `cariHargaMultiSumber()`:
```php
// After full-text search fails, try individual significant words
$words = explode(' ', $cleanKeyword);
foreach ($words as $word) {
    if (strlen($word) > 3) {
        $wordResults = static::where('nama_item', 'like', "%{$word}%")->get();
        $results = $results->merge($wordResults);
        if ($results->count() >= $limit) break;
    }
}
```

## Code Review Findings (2026-07-23)

### RAB Total Calculation — Create vs Import vs Workbench

Three code paths calculate `total_rab` differently:
| Path | Method | Includes Markup? | Sets `is_markup_applied`? |
|------|--------|-------------------|---------------------------|
| `RabImportService::import()` | `$rab->hitungTotal()` | ✅ Yes | ✅ Yes |
| `CreateRab::afterCreate()` | Raw SQL `SUM(volume*harga_satuan)` | ❌ No | ❌ No |
| `EditRab::afterSave()` | Raw SQL `SUM(volume*harga_satuan)` | ❌ No | ❌ No |
| `ViewRab::saveAll()` | `$rab->hitungTotal()` | ✅ Yes (if markup) | ✅ Via save |

**Result**: Imported RABs get markup in total_rab. Manually created RABs don't.

**Fix**: Replace raw SQL in CreateRab/EditRab with:
```php
protected function afterCreate(): void
{
    $this->record->is_markup_applied = $this->record->markup_persen > 0;
    $this->record->saveQuietly(); // triggers boot() saved → hitungTotal()
}
```
Let `Rab::hitungTotal()` be the SINGLE calculation path.

### AiPriceDashboard::applyAutoPrice() Missing jumlah_harga

```php
// WRONG — only updates harga_satuan, total_rab will be stale
$item['item']->update(['harga_satuan' => $item['harga_baru']]);

// RIGHT — also recalculate jumlah_harga
$item['item']->update([
    'harga_satuan' => $item['harga_baru'],
    'jumlah_harga' => $item['item']->volume * $item['harga_baru'],
]);
```

### AiPriceAnalyst::recommendPrice() Heredoc Bug

```php
// WRONG — literal text inside heredoc
$prompt = <<<PROMPT
Harga saat ini: Rp" . number_format($currentPrice, 0, ',', '.') . "
PROMPT;

// RIGHT — assign before heredoc
$formattedPrice = 'Rp' . number_format($currentPrice, 0, ',', '.');
$prompt = <<<PROMPT
Harga saat ini: $formattedPrice
PROMPT;
```
