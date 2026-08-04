# Settings Pages Pattern — Filament Custom Pages
## Verified 2026-07-27

All Settings pages (`app/Filament/Pages/Settings/`) follow an identical pattern using `CompanySetting` model. This reference documents the canonical pattern and common pitfalls.

## Canonical Pattern (ProfilPerusahaanPage as template)

```php
<?php
namespace App\Filament\Pages\Settings;

use App\Models\CompanySetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ProfilPerusahaanPage extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'Profil Perusahaan';
    protected static ?string $slug = 'settings/profil';
    protected static ?int $navigationSort = 11;
    protected static string $view = 'filament.pages.settings-form';
    protected static bool $shouldRegisterNavigation = true;

    public ?array $data = [];

    // CORRECT: use hasPermission(), NOT hardcoded role check
    public static function canAccess(): bool {
        return auth()->user()?->hasPermission('admin.settings');
    }

    public function mount(): void
    {
        $settings = CompanySetting::where('group', 'profil')->get();
        $values = [];
        foreach ($settings as $s) { $values[$s->key] = $s->value; }
        $this->form->fill(['data' => $values]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            // form fields...
        ])->statePath('data');
    }

    public function save(): void
    {
        // CORRECT: use CompanySetting::set() NOT where()->update()
        foreach (($this->form->getState()['data'] ?? []) as $key => $value) {
            CompanySetting::set($key, $value);
        }
        Notification::make()->title('Pengaturan berhasil disimpan')->success()->send();
    }
}
```

## All 6 Settings Pages (fixed 2026-07-27)

| Page | Slug | Group | Sort |
|------|------|-------|------|
| ProfilPerusahaanPage | settings/profil | profil | 11 |
| KeuanganPage | settings/keuangan | keuangan | 12 |
| OperasionalPage | settings/operasional | operasional | 13 |
| DokumenNotifikasiPage | settings/dokumen | dokumen,notifikasi | 14 |
| TemplateDokumenPage | settings/template | template | 15 |
| TampilanPage | settings/tampilan | tampilan | 16 |

## Pitfalls

### 1. canAccess() MUST use hasPermission(), NOT hardcoded role
```php
// ❌ WRONG — R06 (Manajer) has admin.settings permission but can't access
public static function canAccess(): bool { return auth()->user()?->role === 'R00'; }

// ✅ CORRECT — R00 bypasses via hasPermission(), R06 has the permission
public static function canAccess(): bool { return auth()->user()?->hasPermission('admin.settings'); }
```

The permission `admin.settings` is assigned to roles R00 and R06 via role_permissions table.

### 2. save() MUST use CompanySetting::set(), NOT where()->update()
```php
// ❌ WRONG — only updates, doesn't handle encryption for sensitive keys
CompanySetting::where('key', $key)->update(['value' => $value]);

// ✅ CORRECT — handles sensitive key encryption via model accessor
CompanySetting::set($key, $value);
```

`CompanySetting::set()` uses the model's static method which:
- Encrypts values for keys in `SENSITIVE_KEYS` array (e.g., `gemini_api_key`)
- Consistent with how other parts of the app save settings

### 3. FileUpload in custom Pages (statePath pattern)
When using `statePath('data')` on a custom Page, FileUpload works but the
value stored is the relative file path (e.g., `company/logo.png`), not the
full URL. To display the logo later, prefix with `/storage/`:
```php
Forms\Components\FileUpload::make('company_logo')
    ->image()
    ->directory('company')
    ->maxSize(2048)
    ->imagePreviewHeight('120')
    ->columnSpanFull(),
```

### 4. mount() loads all values as strings
`CompanySetting::get()` returns strings. Form fields like Toggle, ColorPicker,
and Select with boolean values need `->reactive()` or explicit casting if
the stored value is a string like `'true'`/`'false'` instead of actual booleans.

## Shared Blade View
All Settings pages use `filament.pages.settings-form` Blade view:
```blade
<x-filament-panels::page>
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h2 class="text-base font-bold text-gray-900">{{ static::$navigationLabel }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">Atur dan sesuaikan pengaturan {{ lcfirst(static::$navigationLabel) }}</p>
            </div>
            <div class="p-6">
                {{ $this->form }}
                <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end">
                    <x-filament::button wire:click="save" color="primary" icon="heroicon-o-check" class="px-6">
                        Simpan Pengaturan
                    </x-filament::button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
```
