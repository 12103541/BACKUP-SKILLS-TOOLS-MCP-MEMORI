# DMS-Workflow Integration (July 2026)

## Architecture
Linking Document Management System (DMS) to the project workflow pipeline:
```
Penawaran → Kontrak → Pekerjaan → Dokumentasi → BAST
                                              ↘ Dokumen Final
```

## Key Patterns

### 1. Document Approval Workflow
Status flow: `draft` → `pending` → `approved` | `rejected`

**Migration fields** (add to `dms_documents`):
```php
$table->string('nomor_dokumen', 100)->nullable();
$table->foreignId('pekerjaan_id')->nullable()->constrained('pekerjaan')->nullOnDelete();
$table->timestamp('expired_at')->nullable();
$table->string('approval_status', 30)->default('draft');
$table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('approved_at')->nullable();
```

**DmsDocument scopes**:
```php
public function scopeExpired($q) { return $q->whereNotNull('expired_at')->where('expired_at', '<', now()); }
public function scopeExpiringSoon($q, $days = 30) { return $q->whereNotNull('expired_at')->whereBetween('expired_at', [now(), now()->addDays($days)]); }
public function scopePendingApproval($q) { return $q->where('approval_status', 'pending'); }
```

### 2. Pekerjaan → Dokumen Relation Manager
Create at `app/Filament/Resources/PekerjaanResource/RelationManagers/DokumenRelationManager.php`:
- Title: 'Dokumen Terkait'
- Columns: No. Dokumen, Nama, Tipe (badge), Approval (badge), Upload date
- Actions: Create (auto-set uploaded_by), Preview (if previewable), Delete
- Register in PekerjaanResource::getRelations()

### 3. Filament Approval Actions Pattern
Add these to DmsDocumentResource table actions:
```php
// Ajukan (draft → pending)
Action::make('request_approval')
    ->visible(fn(DmsDocument $r) => $r->approval_status === 'draft')
    
// Setujui (pending → approved)
Action::make('approve')
    ->visible(fn(DmsDocument $r) => $r->approval_status === 'pending')
    
// Tolak (pending → rejected)  
Action::make('reject')
    ->visible(fn(DmsDocument $r) => $r->approval_status === 'pending')
    ->requiresConfirmation()
    ->form([Textarea::make('alasan')->required()])
```

### 4. Auto-generate PDF from Model Data
Use Barryvdh\DomPDF. Route pattern:
```php
Route::get('/{model}/{record}/pdf', function (Model $record) {
    $record->load('relations');
    $pdf = Pdf::loadView('pdf.template', compact('record'));
    $pdf->setPaper('A4');
    return $pdf->stream('DocName_' . $record->nomor . '.pdf');
})->name('model.pdf');
```

### 5. BAST Auto-generation from Kontrak
Template: `resources/views/pdf/bast.blade.php`
- Loads company profile + klien data
- Generates formal BAST document with clauses + signature space
- Access via `route('kontrak.bast', $kontrak)` from KontrakResource table action

### 6. Dokumen Dashboard
Create `app/Filament/Pages/DokumenDashboard.php` with blade view.
Stat cards using the pattern:
```blade
<div class="rounded-xl bg-white p-5 border shadow-sm">
    <div class="flex items-center gap-3">
        <div class="p-3 rounded-lg bg-primary-100 text-primary-600">
            <x-filament::icon icon="heroicon-o-document-text" class="w-6 h-6" />
        </div>
        <div>
            <p class="text-2xl font-bold">{{ number_format($total) }}</p>
            <p class="text-xs text-gray-500">Label</p>
        </div>
    </div>
</div>
```

### 7. Penawaran → PDF Export
Template: `resources/views/pdf/penawaran.blade.php`
- Full company header, title "SURAT PENAWARAN"
- Table items with No, Uraian, Qty, Harga Satuan, Jumlah
- Grand total + terbilang
- Signature box for director
- Bank account info from CompanySetting

**Auto-calculate totals in afterCreate/afterSave:**
```php
protected function afterCreate(): void
{
    $grandTotal = 0;
    foreach ($this->record->items as $item) {
        $item->total = ($item->quantity ?? 0) * ($item->harga_satuan ?? 0);
        $item->save();
        $grandTotal += $item->total;
    }
    $this->record->update(['total_keseluruhan' => $grandTotal]);
}
```

### 8. Terbilang Helper
`app/Helpers/Terbilang.php` — converts numbers to Indonesian words.
Include in composer autoload or create helper dir manually.

## Workflow Sync Matrix
| Workflow Stage | Related Documents | Feature |
|---|---|---|
| Penawaran → Approved | Penawaran PDF | Auto-generate from form |
| Kontrak → Active | SPK, Kontrak PDF | Upload via Kontrak Dokumen RM |
| Pekerjaan → In Progress | Foto dokumentasi | Steps 0%→50%→100% |
| Pekerjaan → Approved | BAST, BASTP | Auto-generate from Kontrak |
| Pekerjaan → Final | Dokumen Final | Approval workflow DMS |

## Pitfalls
- DmsDocument::isPreviewable() checks tipe_file — only pdf/jpg/jpeg/png work
- DomPDF can't render Tailwind/TailwindCSS — use raw CSS in blade templates
- When adding migration in production, use `--path=` to run only specific file
- FileUpload `visible(fn($record) => $record !== null)` for create-only form fields
- Don't forget `HasDeptAccess` trait for permission checks on all DMS resources
