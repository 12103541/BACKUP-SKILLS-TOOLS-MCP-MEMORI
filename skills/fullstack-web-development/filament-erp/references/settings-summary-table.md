# Settings Page Summary Table Pattern

## Problem
6 Settings pages (Profil, Keuangan, Tampilan, Template Dokumen, Template & Notifikasi, Operasional) use forms to edit `CompanySetting` values. Users couldn't see a list of what's already saved — had to guess from the form fields.

## Solution
Shared Blade view `resources/views/filament/pages/settings-form.blade.php` renders a **summary table** of all setting records above the edit form.

### Page class pattern
Each Settings page exposes `$settingRecords` loaded in `mount()`:

```php
class ProfilPerusahaanPage extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    public ?array $data = [];
    public $settingRecords = [];

    public function mount(): void
    {
        $this->settingRecords = CompanySetting::where('group', 'profil')->get();
        $values = [];
        foreach ($this->settingRecords as $s) {
            $values[$s->key] = $s->value;
        }
        $this->form->fill(['data' => $values]);
    }
    // ...
}
```

All 6 pages follow identical pattern — only `where('group', '...')` differs.

### Shared Blade (`settings-form.blade.php`)
```blade
<x-filament-panels::page>
    <div class="max-w-5xl mx-auto space-y-6">
        {{-- SUMMARY TABLE --}}
        @if(count($settingRecords))
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-list-bullet class="w-4 h-4 text-gray-500" />
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Daftar Pengaturan</h3>
                </div>
                <span class="text-xs text-gray-400">{{ count($settingRecords) }} item</span>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100">
                        <th>Label</th>
                        <th>Nilai</th>
                        <th class="hidden md:table-cell">Group</th>
                        <th class="hidden md:table-cell">Terakhir Update</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($settingRecords as $s)
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition">
                        <td class="px-4 py-2.5 text-gray-800 font-medium">{{ $s->label }}</td>
                        <td class="px-4 py-2.5 text-gray-600 max-w-xs truncate">
                            @if($s->type === 'boolean')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $s->value === 'true' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $s->value === 'true' ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            @elseif($s->type === 'file')
                                <span class="text-xs text-gray-400 italic">{{ $s->value ? '✓ File tersimpan' : '—' }}</span>
                            @else
                                {{ Str::limit($s->value ?? '—', 60) }}
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-gray-400 text-xs hidden md:table-cell">
                            <span class="inline-block px-2 py-0.5 bg-gray-100 rounded text-xs font-medium">{{ $s->group }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-400 text-xs text-right hidden md:table-cell">
                            {{ $s->updated_at ? $s->updated_at->format('d M Y H:i') : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- SETTINGS FORM --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            ... {{ $this->form }} ...
            <x-filament::button wire:click="save" color="primary">Simpan Pengaturan</x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
```

### Settings pages that use this (2026-07-30)
| Page | Slug | Group |
|------|------|-------|
| ProfilPerusahaanPage | settings/profil | profil |
| KeuanganPage | settings/keuangan | keuangan |
| TampilanPage | settings/tampilan | tampilan |
| TemplateDokumenPage | settings/template | template |
| DokumenNotifikasiPage | settings/dokumen | dokumen, notifikasi |
| OperasionalPage | settings/operasional | operasional |
