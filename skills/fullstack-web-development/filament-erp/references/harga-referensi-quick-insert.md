# HargaReferensi Quick Insert Pattern

## Purpose
Quick data entry modal on HargaReferensi list page for rapid manual price reference insertion.

## Implementation (ListHargaReferensis)

### Pattern: Filament Action Modal (NOT custom blade)
```php
// In ListHargaReferensis::getHeaderActions()
Actions\Action::make('insertManual')
    ->label('Tambah Manual')
    ->icon('heroicon-o-pencil-square')
    ->color('success')
    ->modalHeading('Tambah Harga Referensi Manual')
    ->modalDescription('Isi data harga dari survei atau pengalaman lapangan')
    ->modalSubmitActionLabel('Simpan Referensi')
    ->form([
        Forms\Components\Section::make('Informasi Item')
            ->schema([
                Forms\Components\TextInput::make('nama_item')
                    ->required()->maxLength(255)
                    ->placeholder('Contoh: VMS Camera Dome IP 4MP')
                    ->columnSpanFull(),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('satuan')
                        ->options(['unit'=>'unit','buah'=>'buah','m'=>'m',...])
                        ->default('unit'),
                    Forms\Components\TextInput::make('kategori')
                        ->placeholder('VMS, PJU, Kabel...'),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('merek')
                        ->placeholder('Hikvision, Huawei...'),
                    Forms\Components\Select::make('sumber_tipe')
                        ->options([...])
                        ->default('manual'),
                ]),
            ]),
        Forms\Components\Section::make('Data Harga')
            ->description('Harga rata-rata akan digunakan sebagai referensi utama.')
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('harga_terendah')
                        ->required()->numeric()->minValue(0)->prefix('Rp')->inputMode('numeric'),
                    Forms\Components\TextInput::make('harga_rata2')
                        ->required()->numeric()->minValue(0)->prefix('Rp')->inputMode('numeric'),
                    Forms\Components\TextInput::make('harga_tertinggi')
                        ->required()->numeric()->minValue(0)->prefix('Rp')->inputMode('numeric'),
                ]),
                Forms\Components\TextInput::make('sumber')
                    ->placeholder('Contoh: Tokopedia, Supplier A'),
                Forms\Components\Textarea::make('spesifikasi')
                    ->rows(2)->columnSpanFull(),
            ])->columns(1),
    ])
    ->action(function (array $data): void {
        $data['tahun'] = (int) date('Y');
        HargaReferensi::create($data);
        Notification::make()->title('Berhasil ditambahkan!')->success()->send();
    }),
```

## Key Principles
1. **Use Filament Action::make() modal** — NOT custom blade modals. Simpler, no Livewire public property issues, validation built-in.
2. **Auto-set tahun** — `date('Y')` in action callback, not in form (avoids stale year).
3. **Section grouping** — "Informasi Item" + "Data Harga" sections keep form organized.
4. **Rp prefix on numeric inputs** — better UX for Indonesian users.
5. **Input mode** — `->inputMode('numeric')` for mobile-friendly number keyboard.

## Alternative: Custom Blade Modal (Use Only When...)
Custom blade modal is MORE complex and only needed when:
- Need real-time calculation during form fill
- Need preview/autocomplete from existing data
- Need complex conditional UI (show/hide fields)

If none of these apply, ALWAYS use the Action::make() modal pattern.

## HargaReferensi Resource Enhancements (Added 2026-07-23)
- Table: added `->limit(50)` on `nama_item` to prevent overflow
- Table: added `prefix('Rp')` on all harga columns
- Table: added delete action alongside edit
- Filters: added `SelectFilter::make('sumber_tipe')` and `SelectFilter::make('kategori')`
- Form: `merek` maxLength=100 (matches DB varchar(100))
- Form: `sumber_tipe` uses `Select` with 6 options, NOT TextInput
