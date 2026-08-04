# Settings Page Patterns — Profil & Template Merge

## Merged Settings Page (Profil + Template)

Single page combining:
- Profil Perusahaan (nama, tagline, alamat, telepon, email, website, NPWP)
- Logo Perusahaan (FileUpload)
- Direktur & Tanda Tangan (nama, jabatan, FileUpload)
- Pengaturan Tampilan Dokumen per jenis: Penawaran / Faktur / RAB
  - Toggle profil perusahaan
  - Toggle tanda tangan
  - Catatan bawaan (textarea)

```php
protected static ?string $navigationLabel = 'Profil & Template';
protected static ?string $navigationGroup = '⚙️ Pengaturan';
protected static ?string $slug = 'settings/profil-template';

public static function canAccess(): bool { 
    return auth()->user()?->hasPermission('admin.settings'); 
}
```

Hide old pages:
```php
protected static bool $shouldRegisterNavigation = false; // on ProfilPerusahaanPage & TemplateDokumenPage
```

## Preview PDF Button Pattern

### Backend (Page class)
```php
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Illuminate\Support\Facades\Storage;

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
```

### Blade (settings-form.blade.php)
```blade
<x-filament::button wire:click="preview" color="info" icon="heroicon-o-document-text" class="px-4">
    Preview PDF
</x-filament::button>
```

### JS Listener (in same blade @push('scripts'))
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

### Key Points
- Use `Storage::disk('public')->url()` not `asset()` — works with `php artisan storage:link`
- File saved to `storage/app/public/previews/` — auto-cleanup via scheduled job if needed
- Preview uses **current form state** (unsaved) merged with DB — user sees exactly what they're editing
- Dispatch event from Livewire → Alpine listener opens new tab (no redirect, no full page reload)