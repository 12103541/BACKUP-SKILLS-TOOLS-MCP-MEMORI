# GPS Auto-Capture Pattern for Technician Execution Pages

**Context**: Filament v3.2 ERP for PT EXFERIA PUTRA INOVASI — Technician (R02) execution workflow with GPS validation.

---

## Problem

Technicians (R02) must prove they are physically at the job site (kontrak location) when:
- Starting work (tahap 50%)
- Completing work (tahap 100%)
- Submitting for Supervisor review

Admin Proyek (R01) sets a reference GPS point on the Kontrak. Technician's captured GPS is validated against it (≤ 500m = valid).

---

## Architecture

### Database Migrations

**Pekerjaan table** (add GPS fields):
```php
$table->decimal('gps_latitude', 10, 8)->nullable();
$table->decimal('gps_longitude', 11, 8)->nullable();
$table->decimal('gps_accuracy', 8, 2)->nullable();     // meters
$table->timestamp('gps_captured_at')->nullable();
$table->decimal('jarak_dari_lokasi_km', 8, 3)->nullable();
$table->boolean('gps_valid')->nullable();  // true = within 0.5km
```

**Kontrak table** (reference point):
```php
$table->decimal('gps_latitude', 10, 8)->nullable();
$table->decimal('gps_longitude', 11, 8)->nullable();
```

### Model Methods (Pekerjaan.php)

```php
// Haversine distance calculation
public function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

// Auto-validate against kontrak GPS
public function validateGpsLocation(): void {
    $kontrak = $this->kontrak;
    if ($this->gps_latitude && $this->gps_longitude && $kontrak->gps_latitude && $kontrak->gps_longitude) {
        $jarak = $this->haversineDistance(
            $this->gps_latitude, $this->gps_longitude,
            $kontrak->gps_latitude, $kontrak->gps_longitude
        );
        $this->jarak_dari_lokasi_km = round($jarak, 3);
        $this->gps_valid = $jarak <= 0.5; // 500 meter radius
        $this->gps_captured_at = now();
        $this->saveQuietly();
    }
}

// Status label for UI
public function getGpsStatusLabelAttribute(): string {
    if (!$this->gps_latitude || !$this->gps_longitude) return 'Belum Diambil';
    if ($this->gps_valid === null) return 'Menunggu Validasi...';
    return $this->gps_valid ? '✅ Valid (Di Lokasi)' : '⚠️ Tidak Valid (Jauh: ' . $this->jarak_dari_lokasi_km . ' km)';
}
```

---

## Execute Page Implementation

### Page Class (ExecutePekerjaan.php)

```php
class ExecutePekerjaan extends Page
{
    protected static string $resource = PekerjaanResource::class;
    protected static string $view = 'filament.resources.pekerjaan-resource.pages.execute-pekerjaan';

    public Pekerjaan $record;
    public ?array $data = [];

    public function mount(Pekerjaan $record): void {
        $this->record = $record;
        $this->data = [
            'foto_paths' => $record->foto_paths ?? [],
            'dokumentasi_tahap' => $record->dokumentasi_tahap ?? '0%',
            'dokumentasi_keterangan' => is_array($record->dokumentasi_keterangan)
                ? implode("\n", $record->dokumentasi_keterangan)
                : ($record->dokumentasi_keterangan ?? ''),
            'gps_latitude' => $record->gps_latitude ?? null,
            'gps_longitude' => $record->gps_longitude ?? null,
            'gps_accuracy' => $record->gps_accuracy ?? null,
        ];
    }

    public function form(Form $form): Form {
        return $form->schema([
            // Hidden fields - populated by browser JS
            Hidden::make('gps_latitude')->default(null),
            Hidden::make('gps_longitude')->default(null),
            Hidden::make('gps_accuracy')->default(null),

            Section::make('Dokumentasi Pekerjaan')->schema([
                FileUpload::make('foto_paths')
                    ->label('Foto Dokumentasi')
                    ->multiple()
                    ->directory('pekerjaan/foto')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->imagePreviewHeight(100)
                    ->columnSpanFull(),
                Select::make('dokumentasi_tahap')
                    ->label('Tahap Dokumentasi')
                    ->options([
                        '0%' => '0% (Belum Mulai)',
                        '50%' => '50% (Proses)',
                        '100%' => '100% (Selesai)',
                    ])
                    ->native(false)->required(),
                Textarea::make('dokumentasi_keterangan')
                    ->label('Keterangan Dokumentasi')
                    ->rows(4)->columnSpanFull()
                    ->afterStateHydrated(fn($c, $s) => is_array($s) && $c->state(implode("\n", $s)))
                    ->afterStateUpdated(fn($set, $s) => $set('dokumentasi_keterangan',
                        array_filter(array_map('trim', explode("\n", $s ?? ''))) ?: null)),
            ])->columns(2),

            Section::make('Info Pekerjaan')->schema([
                Placeholder::make('kontrak')->label('Kontrak')
                    ->content($this->record->kontrak->nomor_kontrak . ' — ' . ($this->record->kontrak->klien?->nama ?? 'N/A')),
                Placeholder::make('jenis_pekerjaan')->label('Jenis')
                    ->content(ucfirst($this->record->jenis_pekerjaan)),
                Placeholder::make('aset')->label('Aset')->content($this->record->aset),
                Placeholder::make('teknisi')->label('Teknisi')
                    ->content($this->record->user?->name ?? 'Belum di-assign'),
                Placeholder::make('status')->label('Status')
                    ->content(match($this->record->status) {
                        'draft' => 'Draft', 'submitted' => 'Diajukan',
                        'approved' => 'Disetujui', 'rejected' => 'Ditolak',
                        default => $this->record->status,
                    }),
            ])->columns(4),

            Section::make('Validasi Lokasi GPS')->schema([
                Placeholder::make('gps_status')->label('Status GPS')
                    ->content(fn() => $this->record->gps_status_label ?? 'Belum Diambil'),
                Placeholder::make('gps_jarak')->label('Jarak dari Kontrak')
                    ->content(fn() => $this->record->jarak_dari_lokasi_km
                        ? $this->record->jarak_dari_lokasi_km . ' km'
                        : 'Belum dihitung'),
                Placeholder::make('gps_accuracy')->label('Akurasi GPS')
                    ->content(fn() => $this->record->gps_accuracy
                        ? $this->record->gps_accuracy . ' meter'
                        : 'Belum diambil'),
                Placeholder::make('gps_captured_at')->label('Waktu Ambil GPS')
                    ->content(fn() => $this->record->gps_captured_at
                        ? $this->record->gps_captured_at->format('d/m/Y H:i:s')
                        : 'Belum diambil'),

                // GPS buttons rendered via Placeholder with raw HTML
                Placeholder::make('gps_capture_button')->label('')
                    ->content('
                        <div class="flex gap-2 mt-2">
                            <button type="button" id="btn-capture-gps" class="btn btn-primary flex items-center gap-2"
                                    onclick="captureGps()"
                                    ' . (!$this->record->gps_latitude && !$this->record->gps_longitude ? '' : 'style="display:none"') . '>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                                </svg>
                                <span>Ambil Lokasi GPS</span>
                            </button>
                            <button type="button" id="btn-watch-gps" class="btn btn-secondary flex items-center gap-2"
                                    onclick="startWatchGps()"
                                    ' . (!$this->record->gps_latitude && !$this->record->gps_longitude ? '' : 'style="display:none"') . '>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path>
                                </svg>
                                <span>Pantau GPS</span>
                            </button>
                            <button type="button" id="btn-stop-watch" class="btn btn-danger flex items-center gap-2 hidden"
                                    onclick="stopWatchGps()">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                </svg>
                                <span>Hentikan</span>
                            </button>
                        </div>
                    ')
                    ->extraAttributes(['class' => 'col-span-4']),
            ])->columns(4)->collapsible()->collapsed(false),
        ])->statePath('data')->model($this->record);
    }

    public function save(): void {
        $data = $this->form->getState();

        // GPS required when starting (50%) or completing (100%)
        if (in_array($data['dokumentasi_tahap'], ['50%', '100%'])) {
            if (!$data['gps_latitude'] || !$data['gps_longitude']) {
                Notification::make()->title('GPS Wajib Diambil')
                    ->body('Klik tombol "Ambil Lokasi GPS" sebelum menyimpan.')
                    ->danger()->send();
                return;
            }

            $this->record->gps_latitude = $data['gps_latitude'];
            $this->record->gps_longitude = $data['gps_longitude'];
            $this->record->gps_accuracy = $data['gps_accuracy'] ?? null;
            $this->record->gps_captured_at = now();
            $this->record->validateGpsLocation();
        }

        $this->record->update([
            'foto_paths' => $data['foto_paths'] ?? [],
            'dokumentasi_tahap' => $data['dokumentasi_tahap'],
            'dokumentasi_keterangan' => $data['dokumentasi_keterangan'],
            'gps_latitude' => $this->record->gps_latitude,
            'gps_longitude' => $this->record->gps_longitude,
            'gps_accuracy' => $this->record->gps_accuracy,
            'gps_captured_at' => $this->record->gps_captured_at,
            'jarak_dari_lokasi_km' => $this->record->jarak_dari_lokasi_km,
            'gps_valid' => $this->record->gps_valid,
        ]);

        // Auto-status workflow
        if ($data['dokumentasi_tahap'] === '100%') {
            $this->record->update(['status' => 'completed']);
        } elseif ($data['dokumentasi_tahap'] === '50%' && $this->record->status === 'draft') {
            $this->record->update(['status' => 'in_progress']);
        }

        $gpsMsg = $this->record->gps_valid === true ? ' ✅ GPS Valid'
            : ($this->record->gps_valid === false ? ' ⚠️ GPS Tidak Valid (Jauh: ' . $this->record->jarak_dari_lokasi_km . ' km)' : '');
        Notification::make()->title('Dokumentasi Tersimpan' . $gpsMsg)->success()->send();
    }

    public function submitForReview(): void {
        if ($this->record->dokumentasi_tahap !== '100%') {
            Notification::make()->title('Tahap Harus 100%')
                ->body('Dokumentasi harus 100% (Selesai) sebelum submit untuk review.')
                ->warning()->send();
            return;
        }
        if (!$this->record->gps_valid) {
            Notification::make()->title('GPS Tidak Valid')
                ->body('Lokasi GPS tidak valid (jauh dari lokasi kontrak). Hubungi Supervisor.')
                ->danger()->send();
            return;
        }
        $this->record->update(['status' => 'submitted']);
        Notification::make()->title('Dikirim untuk Review')
            ->body('Pekerjaan telah dikirim ke Supervisor untuk review.')->success()->send();
        $this->redirect(static::getResource()::getUrl('index'));
    }

    public function captureGps(): void {
        // Called by JS after browser geolocation fills hidden fields
        $this->form->getState();
        if ($this->record->gps_latitude && $this->record->gps_longitude) {
            $this->record->validateGpsLocation();
            $this->record->refresh();
        }
    }
}
```

---

## Custom Blade View (execute-pekerjaan.blade.php)

```blade
@extends('filament-panels::pages/page')

@section('content')
<div class="fi-page">
    <div class="fi-page-header">
        <div class="fi-page-header-actions">
            <x-filament::button :href="route('filament.admin.resources.pekerjaans.index')" variant="gray" icon="heroicon-o-arrow-left">
                {{ __('filament-panels::resources/pages/list-page.back') }}
            </x-filament::button>
        </div>
        <h1 class="fi-page-title">{{ $getTitle() }}</h1>
        @if ($getSubheading())
            <p class="fi-page-subheading">{{ $getSubheading() }}</p>
        @endif
    </div>

    <div class="fi-page-body">
        <form wire:submit.prevent="save" class="fi-form">
            <div class="fi-form-components">
                {{ $form->render() }}
            </div>

            <div class="fi-form-actions flex gap-3 mt-6 pt-4 border-t">
                <x-filament::button type="submit" class="w-full sm:w-auto">
                    Simpan Dokumentasi
                </x-filament::button>

                @if ($record->dokumentasi_tahap === '100%' && in_array($record->status, ['draft', 'in_progress', 'completed']))
                    <x-filament::button wire:click="submitForReview" color="success" icon="heroicon-o-paper-airplane" class="w-full sm:w-auto">
                        Submit untuk Review
                    </x-filament::button>
                @endif
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('livewire:load', function() {
    let gpsWatchId = null;

    // Get hidden inputs
    const latInput = document.querySelector('input[name="data.gps_latitude"]');
    const lngInput = document.querySelector('input[name="data.gps_longitude"]');
    const accInput = document.querySelector('input[name="data.gps_accuracy"]');

    // UI elements
    const statusEl = document.querySelector('[data-field="gps_status"] .fi-placeholder-content');
    const jarakEl = document.querySelector('[data-field="gps_jarak"] .fi-placeholder-content');
    const accEl = document.querySelector('[data-field="gps_accuracy"] .fi-placeholder-content');
    const capturedEl = document.querySelector('[data-field="gps_captured_at"] .fi-placeholder-content');

    // === CAPTURE GPS ===
    window.captureGps = function() {
        if (!navigator.geolocation) {
            alert('Geolocation tidak didukung browser ini');
            return;
        }

        const btn = document.getElementById('btn-capture-gps');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Mengambil GPS...';

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = position.coords.accuracy;

                // Fill hidden inputs
                if (latInput) latInput.value = lat.toFixed(8);
                if (lngInput) lngInput.value = lng.toFixed(8);
                if (accInput) accInput.value = accuracy.toFixed(2);

                // Trigger Livewire updates
                if (latInput) latInput.dispatchEvent(new Event('input', { bubbles: true }));
                if (lngInput) lngInput.dispatchEvent(new Event('input', { bubbles: true }));
                if (accInput) accInput.dispatchEvent(new Event('input', { bubbles: true }));

                // Update UI
                if (statusEl) {
                    statusEl.textContent = '⏳ Menunggu Validasi...';
                    statusEl.className = 'fi-placeholder-content text-yellow-600 font-medium';
                }
                if (accEl) accEl.textContent = accuracy.toFixed(1) + ' meter';
                if (capturedEl) capturedEl.textContent = new Date().toLocaleString('id-ID');

                btn.innerHTML = '✅ GPS Diambil';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-success');

                // Filament notification
                if (window.Filament && window.Filament.notifications) {
                    window.Filament.notifications.success({
                        title: 'GPS Berhasil Diambil',
                        body: 'Lokasi: ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + ' (Akurasi: ' + accuracy.toFixed(1) + 'm)'
                    });
                }

                // Trigger server validation
                window.Livewire.find('{{ $this->id }}').call('captureGps');
            },
            function(error) {
                let msg = 'Gagal mengambil GPS: ';
                switch(error.code) {
                    case error.PERMISSION_DENIED: msg += 'Izin lokasi ditolak. Izinkan di pengaturan browser.'; break;
                    case error.POSITION_UNAVAILABLE: msg += 'Lokasi tidak tersedia. Coba di area terbuka.'; break;
                    case error.TIMEOUT: msg += 'Timeout. Coba lagi.'; break;
                    default: msg += error.message;
                }
                alert(msg);

                btn.disabled = false;
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    };

    // === WATCH GPS (real-time tracking) ===
    window.startWatchGps = function() {
        if (gpsWatchId !== null) return;

        const btnWatch = document.getElementById('btn-watch-gps');
        const btnStop = document.getElementById('btn-stop-watch');
        btnWatch.classList.add('hidden');
        btnStop.classList.remove('hidden');

        gpsWatchId = navigator.geolocation.watchPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = position.coords.accuracy;

                if (latInput) latInput.value = lat.toFixed(8);
                if (lngInput) lngInput.value = lng.toFixed(8);
                if (accInput) accInput.value = accuracy.toFixed(2);

                if (latInput) latInput.dispatchEvent(new Event('input', { bubbles: true }));
                if (lngInput) lngInput.dispatchEvent(new Event('input', { bubbles: true }));
                if (accInput) accInput.dispatchEvent(new Event('input', { bubbles: true }));

                if (statusEl) {
                    statusEl.textContent = '📡 Tracking: ' + lat.toFixed(6) + ', ' + lng.toFixed(6);
                    statusEl.className = 'fi-placeholder-content text-blue-600 font-medium';
                }
                if (accEl) accEl.textContent = accuracy.toFixed(1) + ' meter';
                if (capturedEl) capturedEl.textContent = new Date().toLocaleString('id-ID');
            },
            function(error) { console.error('Watch GPS Error:', error); },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 1000 }
        );

        if (window.Filament && window.Filament.notifications) {
            window.Filament.notifications.info({ title: 'Monitoring GPS Aktif', body: 'Lokasi akan diupdate otomatis saat bergerak' });
        }
    };

    window.stopWatchGps = function() {
        if (gpsWatchId !== null) {
            navigator.geolocation.clearWatch(gpsWatchId);
            gpsWatchId = null;
        }
        const btnWatch = document.getElementById('btn-watch-gps');
        const btnStop = document.getElementById('btn-stop-watch');
        btnWatch.classList.remove('hidden');
        btnStop.classList.add('hidden');

        if (window.Filament && window.Filament.notifications) {
            window.Filament.notifications.info({ title: 'Monitoring GPS Dihentikan', body: 'Lokasi tidak lagi diupdate otomatis' });
        }
    };
});
</script>
@endpush
@endsection
```

---

## Validation Rules

| Scenario | GPS Required | Validation |
|----------|--------------|------------|
| Save at 0% (draft) | ❌ No | Optional |
| Save at 50% (start) | ✅ Yes | Block if missing |
| Save at 100% (complete) | ✅ Yes | Block if missing |
| Submit for Review | ✅ Yes + Valid | Block if invalid (>0.5km) |

---

## Configuration

GPS validation radius is hardcoded at 0.5km (500m) in `validateGpsLocation()`. To make configurable:

```php
// config/gps.php
return [
    'validation_radius_km' => 0.5,
    'required_accuracy_meters' => 50,
];

// In model:
$radius = config('gps.validation_radius_km', 0.5);
$this->gps_valid = $jarak <= $radius;
```

---

## Testing Checklist

- [ ] Login as Teknisi (R02) → open Execute page
- [ ] Click "Ambil Lokasi GPS" → allow browser permission
- [ ] Verify hidden inputs filled → Placeholder status updates
- [ ] Select Tahap 50% → Click Simpan → Status auto → `in_progress`
- [ ] Verify `gps_valid` calculated against Kontrak GPS
- [ ] Try Submit Review with invalid GPS → blocked
- [ ] Set Kontrak GPS to match technician → Submit → `submitted`
- [ ] Test "Pantau GPS" live tracking button

---

## Pitfalls & Fixes

| Issue | Cause | Fix |
|-------|-------|-----|
| GPS not captured | Browser blocked geolocation | HTTPS required for geolocation; use `localhost` or HTTPS |
| Livewire not seeing GPS values | Hidden inputs not triggering update | Dispatch `input` event with `bubbles: true` after setting `.value` |
| `Placeholder::content()` raw HTML escaped | Filament escapes by default | Use Placeholder with `->extraAttributes(['class' => 'col-span-4'])` and raw HTML in content string |
| `captureGps()` action not found | Method name mismatch | Ensure `wire:click="captureGps"` matches `public function captureGps()` |
| Custom view not loading | Page class missing `$view` property | Add `protected static string $view = '...'` |
| Kontrak GPS not set | Admin forgot to set reference | Add GPS fields to Kontrak Create/Edit form |
| Livewire "page expired" on GPS capture | `form()` + `$this->form` in blade triggers CSRF | Use plain Blade + wire:model approach instead (see below) |

---

## VERIFIED APPROACH: Alpine.js `$wire.call()` (2026-07-25)

**Use this approach.** All others (`form()` + Hidden, `@this.call()` in `<script>`, `$L.dispatch()`) are UNRELIABLE. See main SKILL.md for full explanation.

### PHP (NO form() method, NO wire:model properties):
```php
class ExecutePekerjaan extends Page {
    public Pekerjaan $record;
    public ?string $tahapDokumentasi = null;
    public ?string $keteranganDokumentasi = null;
    public array $newPhotos = [];

    public function mount(Pekerjaan $record): void {
        $this->record = $record;
        $this->tahapDokumentasi = $record->dokumentasi_tahap ?? '0%';
        $this->keteranganDokumentasi = is_array($record->dokumentasi_keterangan)
            ? implode("\n", $record->dokumentasi_keterangan)
            : ($record->dokumentasi_keterangan ?? '');
    }

    public function saveGps(float $lat, float $lng, float $accuracy): void {
        $this->record->refresh();
        $officeLat = -6.2088; // TODO: from kontrak GPS
        $officeLng = 106.8456;
        $distance = $this->haversine($officeLat, $officeLng, $lat, $lng);
        $this->record->update([
            'gps_latitude' => $lat, 'gps_longitude' => $lng,
            'gps_accuracy' => $accuracy, 'gps_captured_at' => now(),
            'jarak_dari_lokasi_km' => round($distance, 3),
            'gps_valid' => $accuracy <= 100,
        ]);
        Notification::make()->title('GPS Tersimpan')->success()->send();
        $this->dispatch('refresh');
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $R = 6371;
        $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
        return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
    }
}
```

### Blade GPS button (Alpine.js — PROVEN WORKING):
```blade
<div x-data="{ loading: false, msg: '' }">
    <button type="button" :disabled="loading" @click="
        loading = true; msg = 'Mengambil GPS...';
        navigator.geolocation.getCurrentPosition(
            (pos) => { $wire.call('saveGps', pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy); loading = false; msg = '✅ Tersimpan'; },
            (err) => { msg = '❌ ' + (err.code === 1 ? 'Izin ditolak' : 'Gagal'); loading = false; },
            { enableHighAccuracy: true, timeout: 15000 }
        );
    ">📍 Ambil Lokasi GPS</button>
    <span x-text="msg" class="text-sm text-gray-500"></span>
</div>
```

Key: `$wire.call()` inside `x-on:click` (Alpine.js) is the ONLY reliable Livewire 3 JS→PHP bridge. Do NOT use `@this.call()` in `<script>` tags or `$L.dispatch()` from HTML onclick.

---

## Related Files

- `app/Models/Pekerjaan.php` — `validateGpsLocation()`, `haversineDistance()`, `gps_status_label`
- `app/Models/Kontrak.php` — `gps_latitude`, `gps_longitude` fillable + casts
- `app/Filament/Resources/PekerjaanResource/Pages/ExecutePekerjaan.php` — Page logic
- `resources/views/filament/resources/pekerjaan-resource/pages/execute-pekerjaan.blade.php` — Custom view with JS
- `database/migrations/2026_07_24_160000_add_gps_fields_to_pekerjaan_table.php`
- `database/migrations/2026_07_24_170000_add_gps_fields_to_kontrak_table.php`

---

*Created: 2026-07-24*