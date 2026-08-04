# Harga Referensi Multi-Source Pattern — IMPLEMENTED

## Pattern: Custom Livewire Page with Grouped Data Display

When Filament's standard ListRecords can't handle the view (grouped data, nested sub-tables), use a custom Filament Page with Livewire + blade.

## Route Registration (HargaReferensiResource.php)
```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListHargaReferensis::route('/'),
        'dashboard' => Pages\HargaReferensiDashboard::route('/dashboard'),
        'create' => Pages\CreateHargaReferensi::route('/create'),
        'edit' => Pages\EditHargaReferensi::route('/{record}/edit'),
    ];
}
```

## Page Class Structure (HargaReferensiDashboard.php)
Key requirements for custom Filament Pages:
1. `$resource` MUST be `string` not `?string` (parent enforces non-nullable)
2. `$view` points to the blade template
3. Public properties with concrete defaults (non-nullable) for Livewire
4. Methods for modal open/close/save — use `$this->showXxxModal = true/false`
5. Blade views call `$this->methodName()` NOT Model statics

```php
class HargaReferensiDashboard extends Page
{
    protected static string $resource = HargaReferensiResource::class;
    protected static string $view = 'filament.resources.xxx.xxx-dashboard';
    // ...
    public function getSumberBadge(?string $tipe): string {
        return HargaReferensi::getSumberTipeBadge($tipe); // delegate to model
    }
}
```

## Query Pattern: GROUP BY + Sub-query
```php
$grouped = HargaReferensi::query()
    ->selectRaw('nama_item, satuan, kategori, merek')
    ->selectRaw('MIN(harga_terendah) as min_harga')
    ->selectRaw('AVG(harga_rata2) as avg_harga')
    ->selectRaw('MAX(harga_tertinggi) as max_harga')
    ->selectRaw('COUNT(*) as jumlah_sumber')
    ->groupBy('nama_item', 'satuan', 'kategori', 'merek')
    ->get();

// Then for each group, fetch individual sources:
$sources = HargaReferensi::where('nama_item', $g->nama_item)
    ->orderBy('harga_rata2')
    ->get();
```

## Blade View Pitfalls
- **Cannot use Model::staticMethod()** in blade subdirectories → use `$this->method()` on Page class
- **Livewire `wire:key`** needed on dynamic modals: `wire:key="add-item-modal"`
- **Modal close**: `wire:click="$set('showModal', false)"` works for Livewire toggle

## Dedup Logic for Add Source
```php
$existing = HargaReferensi::where('nama_item', $name)
    ->where('sumber_tipe', $tipe)
    ->where('sumber', $sumberName)
    ->first();
if ($existing) {
    $existing->update([...]); // UPDATE existing
} else {
    HargaReferensi::create([...]); // CREATE new
}
```

## Stats per Item
- Selisih % = `(max - min) / min * 100`
- Best price = min harga_rata2 across sources
- Overpriced = source.harga_rata2 > avg * 1.2
- Badge: ⭐ Termurah / ⚠️ Termahal / ▲Mahal / ▼Murah / ✓Sesuai
