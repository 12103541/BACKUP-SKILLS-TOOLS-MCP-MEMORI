# ViewRab — Read-Only Excel-Like Table Pattern

## Purpose
Clean read-only table view for analyzing RAB komponen data. No editing, no save buttons — pure display with subtotals.

## Files
- `app/Filament/Resources/RabResource/Pages/ViewRab.php` — Read-only page class
- `resources/views/filament/resources/rab-resource/pages/view-rab.blade.php` — Blade table view

## Page Class (Simplified)
```php
class ViewRab extends Page implements HasForms
{
    use InteractsWithForms;
    protected static string $resource = RabResource::class;
    protected static string $view = 'filament.resources.rab-resource.pages.view-rab';

    public ?Rab $rab = null;
    public array $komponen = [];
    // ... public properties for display only

    public function mount(int|string $record): void
    {
        $this->rab = Rab::with('komponen', 'kontrak')->findOrFail($record);
        // Map to simple array for Blade rendering
        $this->komponen = $this->rab->komponen
            ->map(fn ($k) => [
                'uraian_pekerjaan' => $k->uraian_pekerjaan,
                'volume' => (float) $k->volume,
                'satuan' => $k->satuan,
                'harga_satuan' => (float) $k->harga_satuan,
                'jumlah_harga' => (float) $k->jumlah_harga,
            ])->toArray();
        // Calculate totals
        $this->totalRab = collect($this->komponen)->sum('jumlah_harga');
        $this->grandTotal = $this->markupPersen > 0
            ? $this->totalRab * (1 + $this->markupPersen / 100)
            : $this->totalRab;
    }
}
```

## Blade Table Structure
- Header: RAB info (nomor, proyek, tanggal, kontrak) + komponen count
- Table: `# | Uraian Pekerjaan | Volume | Satuan | Harga Satuan | Jumlah Harga`
- Footer: Subtotal + Markup (if > 0) + Grand Total
- All cells are plain text (no inputs)
- Zebra stripe: `$index % 2 === 0 ? 'bg-white' : 'bg-gray-50'`
- Sticky header: `<thead class="sticky top-0 z-10">`

## Header Actions (Navigation Only)
```php
protected function getHeaderActions(): array
{
    return [
        Action::make('import')->label('Import Excel')
            ->url(fn () => RabResource::getUrl('import'))
            ->icon('heroicon-o-arrow-up-tray')->color('warning'),
        Action::make('template')->label('Template Excel')
            ->url(route('rab.template-download'))
            ->icon('heroicon-o-document-text')->openUrlInNewTab(),
        Action::make('kembali')->label('Kembali')
            ->url(fn () => RabResource::getUrl('index'))
            ->icon('heroicon-o-arrow-left'),
    ];
}
```

## Registration
```php
// In RabResource::getPages()
'view' => Pages\ViewRab::route('/{record}/view'),

// In table actions
Tables\Actions\Action::make('view')
    ->icon('heroicon-o-table-cells')
    ->url(fn ($record) => RabResource::getUrl('view', ['record' => $record]))
    ->tooltip('Lihat Tabel'),
```

## Currency Formatting (CRITICAL — Common Bug)

All price columns MUST use `number_format()` — raw floats render as "Rp3" instead of "Rp 3.000":

```blade
{{-- Harga Satuan --}}
Rp {{ number_format($item['harga_satuan'], 0, ',', '.') }}

{{-- Jumlah Harga --}}
Rp {{ number_format($item['jumlah_harga'], 0, ',', '.') }}

{{-- Grand Total --}}
Rp {{ number_format($this->totalRab, 0, ',', '.') }}
```

**Pitfall:** Blade's `{{ $val }}` on a float like `3000` renders as `3000`. When prefixed with `Rp`, it looks like `Rp3000` — but if the value is actually `3` (small number), it renders as `Rp3`. Always wrap in `number_format($val, 0, ',', '.')`.

Same rule applies to Hrg Pasar column — but use the "—" fallback for zero:
```blade
{{ $item['harga_pasar'] > 0 ? 'Rp ' . number_format($item['harga_pasar'], 0, ',', '.') : '—' }}
```

## Key Differences from Editable Version
- NO `wire:change` on cells (read-only display)
- NO "Tambah Baris" / "Simpan Semua" buttons
- NO markup input field
- NO row action buttons (move/duplicate/delete)
- NO `updateCell()`, `addRow()`, `saveAll()` methods
- Header actions: only navigation (Import, Template, Kembali)
