# Pekerjaan Create — Deferred Assign Pattern

**Context**: Admin Proyek (R01) creates repair schedules; Teknisi (R02) executes later; Supervisor (R03) reviews.

**Pattern**: Split one business entity across multiple pages by role phase.

---

## Workflow

```
CREATE (Admin Projek R01)          EXECUTE (Teknisi R02)           REVIEW (Supervisor R03)
──────────────────────────         ──────────────────────          ────────────────────────
- Pilih Kontrak                    - Foto Dokumentasi              - Approve / Reject
- Isi Lokasi (Ruas, KM)            - Tahap: 0% → 50% → 100%        - Alasan penolakan
- (Optional) Assign Teknisi        - Keterangan                    - Update status
- Status: DRAFT                    - Save → Auto status            
    ↓                              - Submit Review (100%) →        ↓
Execute Page                                           submitted
```

---

## Implementation Files

| File | Purpose |
|------|---------|
| `PekerjaanResource.php` | Form, infolist, table, `getPages()` includes `'execute'` |
| `CreatePekerjaan.php` | `mutateFormDataBeforeCreate()` sets defaults |
| `ListPekerjaans.php` | Table action "Assign Teknisi" |
| `ExecutePekerjaan.php` | Custom Livewire Page for teknisi |
| `execute-pekerjaan.blade.php` | Custom view (or use standard Filament page) |

---

## Key Code Patterns

### 1. Create Form — Minimal Fields Only

```php
// PekerjaanResource::form()
Select::make('kontrak_id')
    ->label('Kontrak')
    ->relationship('kontrak', 'nomor_kontrak', fn($q) => $q->where('status', 'active'))
    ->searchable()
    ->preload()
    ->required()
    ->live()
    ->afterStateUpdated(fn($set, $state) => $set('klien_nama', 
        $state ? Kontrak::with('klien')->find($state)?->klien?->nama : null
    )),

Placeholder::make('klien_info')
    ->label('Klien')
    ->content(fn($get) => $get('klien_nama') ?? 'Pilih kontrak terlebih dahulu'),

Select::make('user_id')
    ->label('Teknisi (Assign Nanti)')
    ->relationship('user', 'name', fn($q) => $q->whereIn('role', ['R02', 'R03']))
    ->searchable()
    ->preload()
    ->required(false)  // ← Optional!
    ->helperText('Opsional — bisa di-assign nanti dari daftar pekerjaan'),

// HAPUS dari form: jenis_pekerjaan, aset, foto_paths, dokumentasi_tahap, dokumentasi_keterangan
```

### 2. Auto-Set Business Defaults in CreateRecord

```php
// CreatePekerjaan::mutateFormDataBeforeCreate()
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['jenis_pekerjaan'] = 'perbaikan';     // Hardcoded business rule
    $data['status'] = 'draft';                   // Workflow start state
    $data['dokumentasi_tahap'] = '0%';           // Default
    
    // Auto-populate aset from kontrak
    if ($kontrak = Kontrak::find($data['kontrak_id'])) {
        $data['aset'] = $kontrak->aset ?? '-';
    }
    
    // Auto-generate nama_pekerjaan
    if (empty($data['nama_pekerjaan']) && $kontrak) {
        $data['nama_pekerjaan'] = sprintf('%s - %s (%s KM %s)',
            $kontrak->nomor_kontrak,
            ucfirst($data['jenis_pekerjaan']),
            $data['aset'],
            $data['lokasi_km']
        );
    }
    return $data;
}
```

### 3. Custom Execute Page (Livewire Page)

```php
// ExecutePekerjaan.php
class ExecutePekerjaan extends Page
{
    protected static string $resource = PekerjaanResource::class;
    protected static string $view = 'filament.resources.pekerjaan-resource.pages.execute-pekerjaan';
    
    public Pekerjaan $record;
    public ?array $data = [];
    
    public function mount(Pekerjaan $record): void
    {
        $this->record = $record;
        $this->data = [
            'foto_paths' => $record->foto_paths ?? [],
            'dokumentasi_tahap' => $record->dokumentasi_tahap ?? '0%',
            'dokumentasi_keterangan' => is_array($record->dokumentasi_keterangan)
                ? implode("\n", $record->dokumentasi_keterangan)
                : ($record->dokumentasi_keterangan ?? ''),
        ];
    }
    
    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Dokumentasi Pekerjaan')->schema([
                FileUpload::make('foto_paths')->multiple()->directory('pekerjaan/foto'),
                Select::make('dokumentasi_tahap')
                    ->options(['0%'=>'0%','50%'=>'50%','100%'=>'100%'])
                    ->required(),
                Textarea::make('dokumentasi_keterangan')
                    ->rows(4)
                    ->afterStateHydrated(fn($c,$s) => is_array($s) && $c->state(implode("\n",$s)))
                    ->afterStateUpdated(fn($set,$s) => $set('dokumentasi_keterangan', array_filter(array_map('trim', explode("\n",$s??''))))),
            ])->columns(2),
            
            Section::make('Info')->schema([
                Placeholder::make('kontrak')->content($this->record->kontrak->nomor_kontrak . ' — ' . ($this->record->kontrak->klien?->nama ?? 'N/A')),
                Placeholder::make('jenis')->content(ucfirst($this->record->jenis_pekerjaan)),
                Placeholder::make('aset')->content($this->record->aset),
                Placeholder::make('teknisi')->content($this->record->user?->name ?? 'Belum di-assign'),
                Placeholder::make('status')->content(match($this->record->status) { 'draft'=>'Draft', 'submitted'=>'Diajukan', 'approved'=>'Disetujui', 'rejected'=>'Ditolak', default=>$this->record->status }),
            ])->columns(4),
        ])->statePath('data')->model($this->record);
    }
    
    public function save(): void
    {
        $data = $this->form->getState();
        $this->record->update([
            'foto_paths' => $data['foto_paths'] ?? [],
            'dokumentasi_tahap' => $data['dokumentasi_tahap'],
            'dokumentasi_keterangan' => $data['dokumentasi_keterangan'],
        ]);
        
        // Auto-status based on tahap
        if ($data['dokumentasi_tahap'] === '100%') {
            $this->record->update(['status' => 'completed']);
        } elseif ($data['dokumentasi_tahap'] === '50%' && $this->record->status === 'draft') {
            $this->record->update(['status' => 'in_progress']);
        }
        Notification::make()->title('Tersimpan')->success()->send();
    }
    
    public function submitForReview(): void
    {
        if ($this->record->dokumentasi_tahap !== '100%') {
            Notification::make()->title('Tahap Harus 100%')->warning()->send();
            return;
        }
        $this->record->update(['status' => 'submitted']);
        $this->redirect(static::getResource()::getUrl('index'));
    }
}
```

### 4. Register Execute Route

```php
// PekerjaanResource::getPages()
public static function getPages(): array
{
    return [
        'index' => Pages\ListPekerjaans::route('/'),
        'create' => Pages\CreatePekerjaan::route('/create'),
        'view' => Pages\ViewPekerjaan::route('/{record}'),
        'edit' => Pages\EditPekerjaan::route('/{record}/edit'),
        'execute' => Pages\ExecutePekerjaan::route('/{record}/execute'),  // ← Custom route
    ];
}
```

### 5. Migration: Make user_id Nullable + Add Fields

```php
// migration
Schema::table('pekerjaan', function (Blueprint $table) {
    $table->foreignId('user_id')->nullable()->change();  // Was NOT NULL
    $table->string('nama_pekerjaan', 255)->nullable()->after('kontrak_id');
    $table->text('dokumentasi_keterangan')->nullable()->after('foto_paths');
    $table->enum('dokumentasi_tahap', ['0%', '50%', '100%'])->default('0%')->after('dokumentasi_keterangan');
});
// Requires doctrine/dbal for ->change()
```

---

## Blade View Pitfall (Filament v3.2)

**Problem**: Custom blade view for Page gets `Undefined variable $getTitle`.

**Cause**: Filament's base `Page` doesn't pass `$getTitle()` to custom views like `EditRecord`/`CreateRecord` do.

**Fix Options**:

1. **Use standard Filament page** (no custom blade) — simplest, works out of box
2. **Extend base page and override render** — complex
3. **Pass data explicitly in `getData()`** and use in blade:

```php
// In ExecutePekerjaan.php
public function getData(): array
{
    return [
        'title' => $this->getTitle(),
        'subheading' => $this->getSubheading(),
        'record' => $this->record,
    ];
}

// In blade:
<h1>{{ $title }}</h1>
<p>{{ $subheading }}</p>
```

**Recommendation**: Avoid custom blade for simple Pages. Use standard Filament layout unless you need Chart.js or complex custom CSS.

---

## Assign Teknisi Table Action (ListPekerjaans)

```php
// ListPekerjaans.php
use App\Models\User;

Tables\Actions\Action::make('assignTeknisi')
    ->label('Assign Teknisi')
    ->icon('heroicon-o-user-plus')
    ->form([
        Select::make('user_id')
            ->label('Teknisi')
            ->options(fn() => User::whereIn('role', ['R02','R03'])->pluck('name','id'))
            ->required(),
    ])
    ->action(fn(array $data, Pekerjaan $record) => $record->update([
        'user_id' => $data['user_id'],
        'status' => 'assigned',
    ]))
    ->visible(fn(Pekerjaan $record) => $record->status === 'draft' && $record->user_id === null)
    ->color('success'),
```

---

## Business Rules Summary

| Rule | Where Enforced |
|------|----------------|
| Jenis pekerjaan = 'perbaikan' only | `CreatePekerjaan::mutateFormDataBeforeCreate()` |
| Aset auto from kontrak | Same method |
| Teknisi optional at create | `Select::required(false)` |
| Tahap docs only in execute | Not in create form |
| Status: draft → assigned → in_progress → completed → submitted → approved/rejected | Auto in save(), explicit in submitForReview() |
| Only R02/R03 in teknisi dropdown | `relationship(..., fn($q) => $q->whereIn('role', ['R02','R03']))` |
| Only active kontrak in dropdown | `relationship(..., fn($q) => $q->where('status', 'active'))` |

---

## Related References

- `references/placeholder-reactive-ui-pattern.md` — Client name auto-show on kontrak select
- `references/livewire-filament-page-pitfalls.md` — Page property binding errors
- `references/session-2026-07-25-dashboard-and-pekerjaan-fixes.md` — This session's full context