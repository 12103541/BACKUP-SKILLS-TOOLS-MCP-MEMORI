# Placeholder Reactive UI Pattern (Filament v3.2)

## Problem
Need to show related info (e.g., client name, asset types) **reactively** when a dropdown (Select) changes — without form submission.

## Solution: `Placeholder` + `live()` on Select + `afterStateUpdated`

### Basic Pattern
```php
// In form() method of Resource/Page
Select::make('kontrak_id')
    ->label('Kontrak')
    ->relationship('kontrak', 'nomor_kontrak', fn($q) => $q->where('status', 'active'))
    ->searchable()
    ->preload()
    ->required()
    ->live()  // ← CRITICAL: makes afterStateUpdated fire on change
    ->afterStateUpdated(fn (callable $set, $state) => $set('klien_nama', 
        $state ? Kontrak::with('klien')->find($state)?->klien?->nama : 'Klien tidak ditemukan'
    )),

Placeholder::make('klien_info')
    ->label('Klien')
    ->content(fn (callable $get) => $get('klien_nama') ?? 'Pilih kontrak terlebih dahulu')
    ->columnSpanFull(),
```

### Why This Works
1. `->live()` on Select makes it reactive — triggers `afterStateUpdated` immediately on user selection
2. `afterStateUpdated` uses `$set` to write to a **hidden form field** (`klien_nama`)
3. `Placeholder::content()` uses a closure with `$get` to read that hidden field reactively
4. Placeholder automatically re-renders when `$get('klien_nama')` changes

### Advanced: Multiple Related Fields from One Select
```php
Select::make('kontrak_id')
    ->live()
    ->afterStateUpdated(function (callable $set, callable $get, $state) {
        $kontrak = Kontrak::with(['klien', 'aset'])->find($state);
        if ($kontrak) {
            $set('klien_nama', $kontrak->klien?->nama ?? '-');
            $set('klien_email', $kontrak->klien?->email ?? '-');
            $set('aset_jenis', $kontrak->aset->pluck('jenis')->unique()->implode(', ') ?: 'Tidak ada aset');
        } else {
            $set('klien_nama', 'Klien tidak ditemukan');
            $set('klien_email', '-');
            $set('aset_jenis', '-');
        }
    }),

Placeholder::make('klien_info')
    ->label('Klien')
    ->content(fn (callable $get) => "{$get('klien_nama')} ({$get('klien_email')})")
    ->visible(fn (callable $get) => filled($get('kontrak_id'))),

Placeholder::make('aset_info')
    ->label('Jenis Aset')
    ->content(fn (callable $get) => $get('aset_jenis'))
    ->visible(fn (callable $get) => filled($get('kontrak_id'))),
```

### Key Requirements
| Requirement | Why |
|-------------|-----|
| `->live()` on Select | Without it, `afterStateUpdated` only fires on form submit |
| Hidden TextInput for each derived value | Placeholder reads via `$get('field_name')` |
| `Placeholder::content(fn($get) => ...)` | Closure allows reactive reading of other form fields |
| `->visible(fn($get) => filled($get('kontrak_id')))` | Don't show placeholder until parent field has value |

### Common Pitfalls
| Mistake | Result |
|---------|--------|
| Missing `->live()` | Placeholder never updates |
| Using `$set` without the field in form schema | Silent failure — field doesn't exist in Livewire component |
| `Placeholder::content('static string')` | Not reactive — must use closure with `$get` |
| Using `relationship()` without `with('klien')` | N+1 queries on each select change (use `with` in `afterStateUpdated`) |

### Alternative: Hidden TextInput + Placeholder
```php
// Hidden fields to store derived values
TextInput::make('klien_nama')->visible(false)->dehydrated(),
TextInput::make('klien_email')->visible(false)->dehydrated(),

// Placeholder reads them
Placeholder::make('klien_display')
    ->content(fn ($get) => $get('klien_nama') ?? 'Pilih kontrak...'),
```

### Real-World Example: Pekerjaan Create (2026-07-25)
- `kontrak_id` Select → `live()` → sets `klien_nama`, `aset_jenis` via `afterStateUpdated`
- `klien_info` Placeholder shows client name + email
- `aset_info` Placeholder shows all asset types from kontrak (e.g., "VMS, PJU")
- `jenis_pekerjaan` hardcoded to `'perbaikan'` in `mutateFormDataBeforeCreate()` — NOT in form
- `user_id` (teknisi) optional, label "Teknisi (Assign Nanti)" + helper text

### Performance Note
Each `afterStateUpdated` runs a DB query. For heavy relations, consider:
```php
// Cache the query for the session
$kontrak = Cache::remember("kontrak_{$state}", 300, fn() => 
    Kontrak::with(['klien', 'aset'])->find($state)
);
```

---

## Related Patterns
- `references/form-select-filtering.md` — Filament v3.2 Select relationship filtering (3rd arg closure)
- `references/livewire-filament-page-pitfalls.md` — Livewire property binding for form fields
- `references/pekerjaan-create-deferred-assign-pattern.md` — Full Pekerjaan create implementation