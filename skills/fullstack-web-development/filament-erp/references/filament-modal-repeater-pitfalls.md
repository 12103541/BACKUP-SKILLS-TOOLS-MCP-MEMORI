# Filament modal-action + Repeater pitfalls (RAB AI Copilot, 2026-07-31)

Sumber: error nyata saat build "✨ Buat RAB dengan AI" di CreateRab (RabResource).

## 1. Filament\Actions\Action::schema does not exist
Header action di Page/CreateRecord (`getHeaderActions()`): gunakan `->form([...])`, BUKAN `->schema([...])`.
`schema()` hanya ada di page-level menu actions (`Filament\Actions\Action` via `getActions()` di Page).
Error: `BadMethodCallException — Method Filament\Actions\Action::schema does not exist`.
Fix: `->form([ ...Forms\Components... ])`.

## 2. Toggle dalam Repeater dalam modal action → Livewire Entangle Error
Gejala: konsol browser penuh `Livewire Entangle Error: Livewire property ['mountedActionsData.0.draft_komponen.0.pilih'] cannot be found on component`; toggle tampil unchecked padahal state `pilih => true` dikirim service.
Fix: ganti `Toggle::make('pilih')` → `Checkbox::make('pilih')->default(true)` — ter-render checked, tidak ada entangle error.
Diagnosa: `browser_console` (tanpa expression) menampilkan entangle errors — cara cepat deteksi.

## 3. formatStateUsing + number_format merusak programmatic fill
`TextInput::make('harga_satuan')->numeric()->formatStateUsing(fn($s) => number_format(...))` → saat `$set()`/`fill()` menulis state "6500000", value "6.500.000" masuk ke `<input type=number>` → invalid → validasi gagal "The harga satuan field is required" (state terlihat kosong di UI).
Fix: HAPUS formatStateUsing dari field numeric yang diisi programmatic (draft modal → form utama). Simpan formatting hanya utk display non-editable (disabled fields).

## 4. Pattern: modal action → isi Repeater form utama (CreateRecord)
```php
public function getHeaderActions(): array
{
    return [
        Actions\Action::make('ai_copilot')
            ->form([...])                    // modal schema
            ->action(function (array $data) {
                $items = collect($data['draft_komponen'] ?? [])
                    ->filter(fn($i) => !empty($i['pilih']))
                    ->map(fn($i) => [/* uraian, volume, satuan, harga_satuan, jumlah_harga */])
                    ->values()->all();
                $this->data = array_merge($this->data ?? [], ['komponen' => $items]);
                $this->form->fill($this->data);   // Repeater form utama terisi
            }),
    ];
}
```
- Item Repeater draft WAJIB bawa `'pilih' => true` dari service (kalau tidak, checkbox unchecked).
- Sumber harga per item (badge 'sumber') simpan sbg field `disabled()->dehydrated(false)` — tampil tapi tak ikut simpan.

## 5. Tinker --execute: jangan `exit;`
`exit;` di dalam --execute → Psy\Exception\BreakException + dump stack penuh. Pakai if/else biasa:
```php
$r = Model::where(...)->first();
if ($r) { ... } else { echo "TIDAK ADA\n"; }
```

## Verifikasi service (RabCopilotService)
- generate('pemasangan_pju', 8) = Rp 109.040.000 — identik RAB PJU asli.
- generate('perawatan_pju', 12) = Rp 67.278.880 (riil 67.280.000; selisih rounding kabel 8.33×12=99.96 vs 100).
