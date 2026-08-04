# RAB Workbench — Unified Edit + Analysis + Pricing Pattern

## Purpose
One page that combines: Excel-like table editing, price analysis (HargaReferensi), markup control, auto-pricing, and per-item recommendations. Replaces separate View/Edit/Analyze pages.

## Files
- `app/Filament/Resources/RabResource/Pages/ViewRab.php` — Full workbench page class
- `resources/views/filament/resources/rab-resource/pages/view-rab.blade.php` — Blade table view with analysis columns

## Table Columns (11)
| # | Uraian | Volume | Satuan | Hrg Satuan | Jumlah | Hrg Pasar | Δ% | Status | Rekomendasi | Aksi |
|---|--------|--------|--------|-----------|--------|-----------|-----|--------|-------------|------|

### Precise Column Sizing with `colgroup`

Use `table-layout: fixed` + `<colgroup>` for pixel-perfect column widths. Without this, columns jump/resize as user scrolls through 200+ rows.

```html
<div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
  <div class="overflow-x-auto overflow-y-auto" style="max-height: calc(100vh - 320px);">
    <table class="border-collapse" style="width: 100%; min-width: 1100px; table-layout: fixed; border-spacing: 0;">
      <colgroup>
        <col style="width: 36px;">    {{-- # --}}
        <col style="width: auto;">    {{-- Uraian — takes remaining space --}}
        <col style="width: 62px;">    {{-- Volume --}}
        <col style="width: 60px;">    {{-- Satuan --}}
        <col style="width: 110px;">   {{-- Hrg Satuan --}}
        <col style="width: 120px;">   {{-- Jumlah --}}
        <col style="width: 100px;">   {{-- Hrg Pasar --}}
        <col style="width: 54px;">    {{-- Δ% --}}
        <col style="width: 68px;">    {{-- Status --}}
        <col style="width: 120px;">   {{-- Rekomendasi --}}
        <col style="width: 76px;">    {{-- Aksi --}}
      </colgroup>
      <thead class="sticky top-0 z-10">...</thead>
      <tbody>...</tbody>
    </table>
  </div>
</div>
```

### Sticky Header for Large Tables
Add `sticky top-0 z-10` on `<thead>` so column labels stay visible during vertical scroll through 200+ rows. Wrap table in container with `max-height: calc(100vh - 320px)` and `overflow-y-auto`. The footer (subtotal/grand total) stays visible below the scrollable area.

### Tooltip for Truncated Input
When Uraian content is truncated in narrow cells, add `title` for full text on hover and `truncate` CSS:
```html
<input type="text" value="{{ $item['uraian_pekerjaan'] }}"
  title="{{ $item['uraian_pekerjaan'] }}" class="… truncate">
```

### Hrg Pasar "—" for No Data
Never show `Rp0` for missing references — user mistakes it for a real price of zero:
```blade
{{ $item['harga_pasar'] > 0 ? 'Rp' . number_format($item['harga_pasar'], 0, ',', '.') : '—' }}
```
Apply same pattern to `selisih_persen` and `rekomendasi` when null/empty.

**Sizing rules:**
- Numeric columns: 54–120px fixed — compact but readable
- Description column: `width: auto` — absorbs remaining space
- Action column: 76px (tight, icon-only buttons)
- `min-width: 1100px` on table → horizontal scroll kicks in cleanly
- `overflow-x-auto overflow-y-auto` on wrapper → both scrollbars appear only when needed

**Cell padding:** `px-1.5 py-1` for body cells, `px-2 py-1.5` for headers. Use `text-xs` (or `text-[11px]`) for compact body text, `font-mono` for all numeric values. Zebra stripe: `bg-white` even rows, `bg-gray-100/60` odd rows.

- **Hrg Pasar**: from HargaReferensi lookup (blue background columns)
- **Δ%**: `(harga_kita - harga_pasar) / harga_pasar × 100` (color-coded)
- **Status**: badge `✅ OK | ⚠️ Mahal | 💎 Murah | ❌ No Data | ⏳ Belum`
- **Rekomendasi**: harga rekomendasi + markup % + source + "Terapkan" button

## Page Class Structure
```php
class ViewRab extends Page implements HasForms
{
    use InteractsWithForms;
    public ?Rab $rab = null;
    public array $komponen = [];  // includes analisa fields
    public float $markupPersen = 0;
    public float $potentialSavings = 0;
    protected SmartPricingService $pricingService;

    // Methods: updateCell(), jalankanAnalisa(), autoPrice(),
    //          terapkanRekomendasi(), addRow(), duplicateRow(),
    //          removeRow(), moveUp(), moveDown(), saveAll()
}
```

## Komponen Array Shape
```php
[
    'id' => 123,
    'uraian_pekerjaan' => 'Kabel NYM 2x2.5mm',
    'volume' => 100,
    'satuan' => 'm',
    'harga_satuan' => 25000,
    'jumlah_harga' => 2500000,
    // Analisa fields (filled by jalankanAnalisa())
    'harga_pasar' => 22000,
    'selisih_persen' => 13.6,
    'status_analisa' => 'referenced', // referenced|overpriced|underpriced|no_ref|pending
    'rekomendasi_harga' => 26500,
    'rekomendasi_markup' => 18,
    'rekomendasi_sumber' => 'Marketplace',
]
```

## Analysis Flow
1. **jalankanAnalisa()**: Loop komponen → HargaReferensi::cariHargaMultiSumber() → fill harga_pasar, selisih, status, rekomendasi
2. **autoPrice()**: Batch update harga_satuan from cheapest reference → recalculates totals
3. **terapkanRekomendasi($index)**: Apply rekomendasi_harga to single item
4. **saveAll()**: Bulk save → RabKomponen update/create/delete → Rab::hitungTotal()

## SmartPricing Rekomendasi Algorithm
- Base markup: 15%
- Competition adjustment: ≤1 ref → +10%, ≤3 → +5%, >3 → -3%
- Volume adjustment: ≥100 → -8%, ≥50 → -5%, ≥10 → -3%
- Price vs market: >10% above → -5%, >10% below → +8%
- Clamped: 3%–50%

## Toolbar
```
[Tambah Baris] [Jalankan Analisa] [Auto Price] | ... [Simpan Semua] | N komponen
Markup: [0] %  | 💡 Potensi hemat: RpX.XXX
```

## Header Actions
Import Excel | Template Excel | PDF | Kembali

## Status Badge Colors
- `referenced` → green "✅ OK" (Δ% within ±20%)
- `overpriced` → red "⚠️ Mahal" (Δ% > +20%)
- `underpriced` → blue "💎 Murah" (Δ% < -20%)
- `no_ref` → gray "❌ No Data"
- `pending` → yellow "⏳ Belum"

## Key Pitfalls
1. **Analysis is session-only** — harga_pasar/rekomendasi are stored in `$komponen` array, not DB. After saveAll() + page reload, analysis is lost unless re-run. Consider caching in a DB column if needed.
2. **Performance with 200+ items** — jalankanAnalisa() does N×HargaReferensi queries. For large RABs, consider chunked analysis or async processing.
3. **autoPrice requires prior analysis** — user must run jalankanAnalisa() first so harga_pasar fields are populated.
4. **Save preserves analysis state** — after saveAll(), the code re-loads komponen from DB and re-applies analysis for display consistency.
5. **wire:change not wire:model** — table inputs use wire:change (fires on blur) not wire:model (fires on every keystroke). Critical for 200+ row performance.
6. **Currency formatting** — Harga Satuan and Jumlah columns MUST use `number_format($val, 0, ',', '.')` in blade. Raw `{{ $item['harga_satuan'] }}` renders "Rp3" instead of "Rp 3.000". Same applies to toolbar subtotal, markup display, and grand total.
