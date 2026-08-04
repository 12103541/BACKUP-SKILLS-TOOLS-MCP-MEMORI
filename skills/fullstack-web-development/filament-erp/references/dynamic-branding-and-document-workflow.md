# Dynamic Branding & Document Workflow (ERP)

## Dynamic Brand Name from DB

Instead of hardcoding `->brandName('Aplikasi Kantor')` in `AdminPanelProvider.php`, use closures to read from `CompanySetting`:

```php
->brandName(fn () => \App\Models\CompanySetting::get('company_name', 'Aplikasi Kantor'))
->brandLogo(function () {
    $logo = \App\Models\CompanySetting::get('company_logo', '');
    return $logo ? asset('storage/' . $logo) : null;
})
->brandLogoHeight('2.5rem')
->favicon(function () {
    $logo = \App\Models\CompanySetting::get('company_logo', '');
    return $logo ? asset('storage/' . $logo) : null;
})
```

**Affects:** sidebar header, browser tab title, login page, favicon.

### Login Page Title
Update `app/Filament/Pages/Auth/Login.php` — override `getTitle()`:
```php
public function getTitle(): string
{
    $company = \App\Models\CompanySetting::get('company_name', 'Aplikasi Kantor');
    return 'Masuk - ' . $company;
}
```
This changes browser tab on login from hardcoded `'Masuk - Aplikasi Kantor'` to dynamic company name.

**CompanySetting::set()** — saved via Profil Perusahaan page (`⚙️ Pengaturan > Profil Perusahaan`), field `company_name`.

## DMS Document Workflow Integration

### Migration — Link DMS to Pekerjaan + Approval
```php
Schema::table('dms_documents', function (Blueprint $table) {
    $table->string('nomor_dokumen', 100)->nullable();
    $table->foreignId('pekerjaan_id')->nullable()->constrained('pekerjaan')->nullOnDelete();
    $table->timestamp('expired_at')->nullable();
    $table->string('approval_status', 30)->default('draft'); // draft|pending|approved|rejected
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
});
```

### DmsDocument Scopes
```php
public function scopeExpired($q) { $q->whereNotNull('expired_at')->where('expired_at', '<', now()); }
public function scopeExpiringSoon($q, $days = 30) { $q->whereNotNull('expired_at')->whereBetween('expired_at', [now(), now()->addDays($days)]); }
public function scopePendingApproval($q) { $q->where('approval_status', 'pending'); }
```

### Table Actions — Approval Workflow
```php
// Ajukan (draft → pending)
Tables\Actions\Action::make('request_approval')
    ->label('Ajukan')->icon('heroicon-o-arrow-up-circle')->color('warning')
    ->action(fn(DmsDocument $record) => $record->update(['approval_status' => 'pending']))
    ->visible(fn(DmsDocument $r) => $r->approval_status === 'draft');

// Setujui (pending → approved)
Tables\Actions\Action::make('approve')
    ->label('Setujui')->icon('heroicon-o-check')->color('success')
    ->action(fn(DmsDocument $record) => $record->update([
        'approval_status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now()
    ]))
    ->visible(fn(DmsDocument $r) => $r->approval_status === 'pending');

// Tolak (pending → rejected)
Tables\Actions\Action::make('reject')
    ->label('Tolak')->icon('heroicon-o-x-mark')->color('danger')
    ->requiresConfirmation()
    ->form([Forms\Components\Textarea::make('alasan')->label('Alasan Penolakan')->required()])
    ->action(fn(DmsDocument $record, array $data) => $record->update(['approval_status' => 'rejected']))
    ->visible(fn(DmsDocument $r) => $r->approval_status === 'pending');
```

### Pekerjaan → Dokumen Relation Manager
Create `app/Filament/Resources/PekerjaanResource/RelationManagers/DokumenRelationManager.php`:
```php
class DokumenRelationManager extends RelationManager
{
    protected static string $relationship = 'dokumen';
    protected static ?string $title = 'Dokumen Terkait';
    // ... standard Filament RM with create + preview actions
}
```

### Relation Manager — register in Resource
```php
public static function getRelations(): array
{
    return [
        \App\Filament\Resources\PekerjaanResource\RelationManagers\DokumenRelationManager::class,
    ];
}
```

### BAST Auto-generate PDF
Route example:
```php
Route::get('/kontrak/{kontrak}/bast', function (App\Models\Kontrak $kontrak) {
    $kontrak->load('klien');
    $pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.bast', compact('kontrak'));
    $pdf->setPaper('A4');
    return $pdf->download('BAST_' . $kontrak->nomor_kontrak . '.pdf');
})->name('kontrak.bast');
```

Template (`pdf.bast`): formal BAST with company header, kontrak details, signature lines for both parties.

Table action in KontrakResource:
```php
Tables\Actions\Action::make('bast')
    ->label('BAST PDF')
    ->icon('heroicon-o-document-arrow-down')
    ->color('info')
    ->url(fn(Kontrak $record) => route('kontrak.bast', $record))
    ->openUrlInNewTab()
    ->visible(fn(Kontrak $record): bool => in_array($record->status, ['active', 'completed', 'terminated']));
```

### Dashboard Dokumen Page
Create a Filament Page with stat cards:
- Total Dokumen (Final / Draft)
- Menunggu Persetujuan
- Akan Expired (30hr)
- Folder & Tag
- List Dokumen Terbaru + Perlu Persetujuan

## PDF Export for Penawaran

Use existing `Barryvdh\DomPDF` (already installed):
```php
Route::get('/penawaran/{penawaran}/pdf', function (App\Models\Penawaran $penawaran) {
    $penawaran->load('items');
    $pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.penawaran', compact('penawaran'));
    $pdf->setPaper('A4');
    return $pdf->stream('Penawaran_' . $penawaran->nomor_penawaran . '.pdf');
})->name('penawaran.pdf');
```

Template includes: company header, nomor, tanggal, klien, items table with no/qty/harga/jumlah, grand total, terbilang, notes, bank info, signature.

Action in ViewPenawaran:
```php
Actions\Action::make('print')
    ->label('Cetak PDF')
    ->icon('heroicon-o-printer')
    ->color('warning')
    ->url(fn () => route('penawaran.pdf', $this->record))
    ->openUrlInNewTab(),
```
