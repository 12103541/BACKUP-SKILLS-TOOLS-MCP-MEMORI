# HargaReferensi Multi-Source Concept (Item-Centric Grouped View)

## Problem
Current HargaReferensi list is a flat table: one row per price record. If "VMS Camera Dome IP 4MP" has 3 sources (Tokopedia, Supplier A, AHSP), it appears as 3 separate rows. User cannot easily compare prices across sources for the same item.

## Proposed Pattern: Item-Centric Grouping
Keep 1 item name → N source prices. Group by `nama_item` and show all sources for one item in a sub-card.

## Wireframe
```
┌────────────────────────────────────────────────────────────────┐
│  🔍 Search Item...                            [Filter: ▾]     │
├────────────────────────────────────────────────────────────────┤
│  📦 VMS Camera Dome IP 4MP                          unit      │
│  ┌──────────┬──────────┬──────────┬──────────┬──────────┐     │
│  │ Sumber   │ Terendah │ Rata-rata│ Tertinggi│ Status   │     │
│  ├──────────┼──────────┼──────────┼──────────┼──────────┤     │
│  │ 🟢 Toko  │ 1.500.000│ 1.800.000│ 2.200.000│ ⭐ Best  │     │
│  │ 🔵 Supp A│ 1.600.000│ 1.900.000│ 2.300.000│          │     │
│  │ 🟡 AHSP  │ 2.000.000│ 2.400.000│ 2.800.000│ ⚠️ Mahal │     │
│  └──────────┴──────────┴──────────┴──────────┴──────────┘     │
│  [+ Tambah Sumber]  💡 Selisih: 25% antara min/max            │
├────────────────────────────────────────────────────────────────┤
│  📦 Kabel UTP Cat6 Belden                           meter     │
│  ┌──────────┬──────────┬──────────┬──────────┬──────────┐     │
│  │ 🟢 Toko  │ 12.000   │ 15.000   │ 18.000   │ ⭐ Best  │     │
│  └──────────┴──────────┴──────────┴──────────┴──────────┘     │
│  [+ Tambah Sumber]  💡 Selisih: 20%                           │
└────────────────────────────────────────────────────────────────┘
```

## Key Features (No Schema Changes Required)
All done with query grouping — no new tables or columns needed:

1. **GROUP BY query**: `SELECT nama_item, satuan, MIN(harga_terendah) as min_all, MAX(harga_tertinggi) as max_all, COUNT(*) as sumber_count FROM harga_referensi GROUP BY nama_item, satuan ORDER BY nama_item`

2. **Sub-query per item**: `SELECT * FROM harga_referensi WHERE nama_item = ? ORDER BY FIELD(sumber_tipe, 'marketplace','google','supplier','ahsp','historis','manual'), harga_rata2 ASC`

3. **Auto-calculated stats per item group**:
   - Selisih % = `(max_harga - min_harga) / min_harga × 100`
   - Best source = row with lowest harga_rata2
   - Overpriced flag when source is >20% above cheapest
   - Sumber count badge

4. **Visual source type badges**:
   - 🟢 Marketplace → `bg-green-100 text-green-700`
   - 🔵 Google → `bg-blue-100 text-blue-700`
   - 🟣 Supplier → `bg-purple-100 text-purple-700`
   - 🟡 AHSP → `bg-yellow-100 text-yellow-700`
   - ⚪ Historis → `bg-gray-100 text-gray-700`
   - 🟠 Manual → `bg-orange-100 text-orange-700`

## Implementation Approach

### Option A: Custom Filament Page (Recommended)
Create a dedicated page (not using ListRecords) that queries grouped data:

```php
class MultiSumberHarga extends Page
{
    protected static string $view = '...';

    public array $itemGroups = [];
    public string $search = '';
    public ?string $filterSumberTipe = null;

    public function mount(): void
    {
        $this->loadGroups();
    }

    public function loadGroups(): void
    {
        $query = HargaReferensi::query()
            ->select('nama_item', 'satuan')
            ->selectRaw('MIN(harga_terendah) as min_all')
            ->selectRaw('AVG(harga_rata2) as avg_all')
            ->selectRaw('MAX(harga_tertinggi) as max_all')
            ->selectRaw('COUNT(*) as sumber_count')
            ->groupBy('nama_item', 'satuan');

        if ($this->search) {
            $query->where('nama_item', 'like', "%{$this->search}%");
        }
        if ($this->filterSumberTipe) {
            $query->where('sumber_tipe', $this->filterSumberTipe);
        }

        $groups = $query->orderBy('nama_item')->paginate(20);

        $this->itemGroups = $groups->map(fn ($g) => [
            'nama_item' => $g->nama_item,
            'satuan' => $g->satuan,
            'min_all' => $g->min_all,
            'avg_all' => $g->avg_all,
            'max_all' => $g->max_all,
            'sumber_count' => $g->sumber_count,
            'sources' => HargaReferensi::where('nama_item', $g->nama_item)
                ->orderByRaw("FIELD(sumber_tipe, 'marketplace','google','supplier','ahsp','historis','manual')")
                ->orderBy('harga_rata2')
                ->get()
                ->toArray(),
        ])->toArray();
    }
}
```

### Option B: Enhanced ListRecords with Inline Expanded Rows
Use Filament's table with expandable rows — each row is the item, expanded shows sub-table of sources.

```php
Tables\Columns\Layout\Split::make([
    Tables\Columns\TextColumn::make('nama_item'),
    Tables\Columns\TextColumn::make('sumber_count')->label('Jumlah Sumber'),
])->collapsible(),
```

### Option C: Keep current flat table, add better group-by tools
Simpler: add "Group by Item" toggle on existing list page that switches between flat and grouped view.

## Route & Nav
New page registered in `HargaReferensiResource::getPages()`:
```php
'sumber' => Pages\MultiSumberHarga::route('/sumber'),
```

Nav item: `MultiSumberHarga::getNavigationItems()` or add link from existing list page.

## Considerations
1. **Performance** — paginate items, lazy-load sources. Each page of 20 items = 21 queries (1 group list + 20 source fetches). For large data (5000+ records), consider eager-loading all sources in one batch query and grouping in PHP.
2. **Inline edits** — if user needs to edit prices from this view, add inline save buttons per source row, or link to edit page.
3. **Quick "add source"** — from an item group, clicking [+ Tambah Sumber] opens modal pre-filled with that item's nama_item and satuan.

## When NOT To Use
- If user rarely needs price comparison (just needs the best price), flat table + search is simpler.
- If data volume is tiny (<100 records), grouping adds complexity with minimal benefit.
