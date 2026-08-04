# Custom Filament Page with FileUpload — Array/String Gotcha

## Background
`CompanySetting` model stores ALL values (text, select, file path) as plain strings in `value` column. Filament `FileUpload` component expects **array** values (e.g. `['company/logo.png']`) in its state, but the DB returns `'company/logo.png'` (string). This mismatch causes `foreach() argument must be of type array|object, string given` at `BaseFileUpload.php:740`.

## Fix Pattern: Custom Page mount() + save()

```php
class ProfilTemplatePage extends Page
{
    use InteractsWithForms;
    
    public ?array $data = [];
    public $settingRecords = [];

    public function mount(): void
    {
        $this->settingRecords = CompanySetting::whereIn('group', ['profil', 'template'])->get();
        $values = [];
        foreach ($this->settingRecords as $s) { $values[$s->key] = $s->value; }
        $this->data = $values;

        // CRITICAL: FileUpload expects array, DB stores string
        foreach (['company_logo', 'director_signature'] as $f) {
            if (isset($this->data[$f]) && is_string($this->data[$f])) {
                $this->data[$f] = [$this->data[$f]];
            }
        }
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            FileUpload::make('company_logo')
                ->image()
                ->disk('public')          // ← REQUIRED for local storage
                ->directory('company')
                ->maxSize(2048),
            FileUpload::make('director_signature')
                ->image()
                ->disk('public')          // ← REQUIRED
                ->directory('company')
                ->maxSize(1024),
        ])->statePath('data');
    }

    public function save(): void
    {
        // MUST use form->getState() — NOT $this->data — so FileUpload
        // processes temporary file keys into final paths
        $state = $this->form->getState();
        $formData = $state['data'] ?? $state;

        $fileFields = ['company_logo', 'director_signature'];
        foreach ($formData as $key => $value) {
            if (in_array($key, $fileFields) && $value === null) continue;
            if (in_array($key, $fileFields) && is_array($value)) {
                $value = $value[0] ?? null;
                if ($value === null) continue;
            }
            CompanySetting::set($key, $value);
        }
        // Sync back so form re-renders with correct values
        $this->data = $formData;
    }

    // Optional: Preview PDF with current form state
    public function preview(): void
    {
        $state = $this->form->getState();
        $formData = $state['data'] ?? $state;

        // Merge with DB for complete settings
        $settings = [
            'company_name' => $formData['company_name'] ?? CompanySetting::get('company_name'),
            'company_logo' => is_array($formData['company_logo'] ?? null) ? ($formData['company_logo'][0] ?? null) : ($formData['company_logo'] ?? CompanySetting::get('company_logo')),
            // ... other fields
        ];

        $pdf = PdfFacade::loadView('pdf.penawaran', [
            'penawaran' => \App\Models\Penawaran::first(),
            'items' => [],
            'setting' => (object) $settings,
        ]);

        $pdfContent = $pdf->output();
        $path = 'previews/penawaran-preview-' . now()->format('YmdHis') . '.pdf';
        Storage::disk('public')->put($path, $pdfContent);
        
        $url = Storage::disk('public')->url($path);
        $this->dispatch('openPreview', url: $url);
    }
}
```

## Root Cause
- `$this->data = $values` bypasses Filament's form processing pipeline
- FileUpload stores a **Livewire temporary key** (e.g. `livewire-file-abc123`) as the value in `$this->data`
- `$this->form->getState()` triggers the move from temp → permanent storage and returns the final path
- Using `$this->data` directly in `save()` stores the temp key → image never saved

## Always
1. Add `->disk('public')` on every FileUpload (default disk is `local`, not web-accessible)
2. Use `$this->form->getState()` in save(), not `$this->data`
3. Convert string→array in mount for file fields
4. Convert array→string in save for file fields
5. Sync `$this->data = $formData` after save for proper re-render
6. For preview: merge form state with DB settings, generate PDF, dispatch `openPreview` event

## Blade JS Listener (for preview)
```blade
@push('scripts')
<script>
    document.addEventListener('livewire:load', () => {
        Livewire.on('openPreview', (event) => {
            if (event.url) {
                window.open(event.url, '_blank');
            }
        });
    });
</script>
@endpush
```

## Preview PDF Before Save (New Pattern)
Generate PDF from current form state (not yet saved to DB) — useful for "Preview PDF" button on settings pages.

```php
public function preview(): void
{
    $state = $this->form->getState();
    $formData = $state['data'] ?? $state;

    // Merge with DB settings for complete data (form may only have subset)
    $settings = [
        'company_name' => $formData['company_name'] ?? CompanySetting::get('company_name'),
        'company_address' => $formData['company_address'] ?? CompanySetting::get('company_address'),
        // ... other fields
        'company_logo' => is_array($formData['company_logo'] ?? null) ? ($formData['company_logo'][0] ?? null)
                         : ($formData['company_logo'] ?? CompanySetting::get('company_logo')),
        'director_signature' => is_array($formData['director_signature'] ?? null) ? ($formData['director_signature'][0] ?? null)
                             : ($formData['director_signature'] ?? CompanySetting::get('director_signature')),
        // doc-type settings
        'penawaran_show_company_profile' => $formData['penawaran_show_company_profile'] ?? CompanySetting::get('penawaran_show_company_profile', 'true'),
        'penawaran_show_signature' => $formData['penawaran_show_signature'] ?? CompanySetting::get('penawaran_show_signature', 'true'),
        'penawaran_default_notes' => $formData['penawaran_default_notes'] ?? CompanySetting::get('penawaran_default_notes', ''),
    ];

    $pdf = PdfFacade::loadView('pdf.penawaran', [
        'penawaran' => \App\Models\Penawaran::first(), // sample data for table
        'items' => [],
        'setting' => (object) $settings,
    ]);

    $pdfContent = $pdf->output();
    $path = 'previews/penawaran-preview-' . now()->format('YmdHis') . '.pdf';
    Storage::disk('public')->put($path, $pdfContent);

    $url = Storage::disk('public')->url($path);
    $this->dispatch('openPreview', url: $url);
}
```

Route in `routes/web.php`:
```php
Route::get('/admin/settings/profil-template/preview', ...)->name('settings.profil-template.preview');
```

## FileUpload formatStateUsing Alternative (Cleaner than mount conversion)
Instead of manual string↔array conversion in mount/save, use `formatStateUsing` on the component:

```php
FileUpload::make('company_logo')
    ->image()
    ->disk('public')
    ->directory('company')
    ->formatStateUsing(fn ($state) => is_string($state) ? [$state] : ($state ?? [])),
```

This ensures the component always receives an array regardless of what's in the DB or form state.

---

## Complete FileUpload Pattern for Custom Settings Pages (Session: ME-CIPALI workflow)
**Use when:** Building a merged Profil & Template page with logo/signature upload + PDF preview before save.

### 1. Component Definition (always use `disk('public')`)
```php
FileUpload::make('company_logo')
    ->label('Logo Perusahaan')
    ->image()
    ->disk('public')                    // REQUIRED for web-accessible files
    ->directory('company')
    ->maxSize(2048)
    ->formatStateUsing(fn ($v) => is_string($v) ? [$v] : ($v ?? [])),

FileUpload::make('director_signature')
    ->label('TTD Direktur')
    ->image()
    ->disk('public')                    // REQUIRED
    ->directory('company')
    ->maxSize(1024)
    ->formatStateUsing(fn ($v) => is_string($v) ? [$v] : ($v ?? [])),
```

### 2. Save Method (MUST use `$this->form->getState()`)
```php
public function save(): void
{
    $state = $this->form->getState();
    $formData = $state['data'] ?? $state;

    $fileFields = ['company_logo', 'director_signature'];
    foreach ($formData as $key => $value) {
        if (in_array($key, $fileFields) && $value === null) continue;
        if (in_array($key, $fileFields) && is_array($value)) {
            $value = $value[0] ?? null;
            if ($value === null) continue;
        }
        CompanySetting::set($key, $value);
    }
    // Sync back so form re-renders with correct values
    $this->data = $formData;
    Notification::make()->title('Pengaturan disimpan')->success()->send();
}
```

### 3. Preview PDF Before Save (dispatch `openPreview` event)
```php
public function preview(): void
{
    $state = $this->form->getState();
    $formData = $state['data'] ?? $state;

    // Merge with DB for complete settings (form may only have subset)
    $settings = [
        'company_name' => $formData['company_name'] ?? CompanySetting::get('company_name'),
        'company_address' => $formData['company_address'] ?? CompanySetting::get('company_address'),
        // ... all other fields
        'company_logo' => is_array($formData['company_logo'] ?? null) ? ($formData['company_logo'][0] ?? null)
                         : ($formData['company_logo'] ?? CompanySetting::get('company_logo')),
        'director_signature' => is_array($formData['director_signature'] ?? null) ? ($formData['director_signature'][0] ?? null)
                             : ($formData['director_signature'] ?? CompanySetting::get('director_signature')),
        // doc-type settings
        'penawaran_show_company_profile' => $formData['penawaran_show_company_profile'] ?? CompanySetting::get('penawaran_show_company_profile', 'true'),
        'penawaran_show_signature' => $formData['penawaran_show_signature'] ?? CompanySetting::get('penawaran_show_signature', 'true'),
        'penawaran_default_notes' => $formData['penawaran_default_notes'] ?? CompanySetting::get('penawaran_default_notes', ''),
    ];

    $pdf = PdfFacade::loadView('pdf.penawaran', [
        'penawaran' => \App\Models\Penawaran::first(), // sample data for table
        'items' => [],
        'setting' => (object) $settings,
    ]);

    $pdfContent = $pdf->output();
    $path = 'previews/penawaran-preview-' . now()->format('YmdHis') . '.pdf';
    Storage::disk('public')->put($path, $pdfContent);

    $url = Storage::disk('public')->url($path);
    $this->dispatch('openPreview', url: $url);
}
```

### 4. Blade Listener for Preview (in custom page blade view)
```blade
@push('scripts')
<script>
    document.addEventListener('livewire:load', () => {
        Livewire.on('openPreview', (event) => {
            if (event.url) {
                window.open(event.url, '_blank');
            }
        });
    });
</script>
@endpush
```

### 5. Preview Route (in `routes/web.php`)
```php
Route::get('/settings/profil-template/preview', function () {
    $settings = [
        'company_name' => App\Models\CompanySetting::get('company_name', '...'),
        // ... all settings from DB
    ];
    $pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.penawaran', [
        'penawaran' => \App\Models\Penawaran::first(),
        'items' => [],
        'setting' => (object) $settings,
    ]);
    $pdf->setPaper('A4');
    return $pdf->stream('penawaran-preview.pdf');
})->name('settings.profil-template.preview');
```

### Critical Rules
1. **ALWAYS** `->disk('public')` on FileUpload — default `local` is not web-accessible
2. **MUST** use `$this->form->getState()` in save(), NOT `$this->data` — FileUpload processes temp keys to final paths only through form state
3. `formatStateUsing` handles string→array conversion automatically (DB stores string, component needs array)
4. In save(), convert array→string for file fields before `CompanySetting::set()`
5. Sync `$this->data = $formData` after save for proper re-render
6. Preview merges form state with DB settings so user sees complete PDF with unsaved changes