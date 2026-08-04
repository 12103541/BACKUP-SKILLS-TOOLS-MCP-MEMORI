# Custom Page with Non-Livewire Routes Pattern
## Verified 2026-07-25

When a Filament custom Page (not EditRecord) needs save/update operations, use regular Laravel routes + `fetch()` instead of ANY Livewire mechanism. This avoids the CSRF/session issues that plague custom Pages.

## Problem
Custom Filament Pages with `$view` pointing to a custom blade template have CSRF issues with ALL Livewire mechanisms:
- `wire:model` / `wire:model.live` → AJAX on change → CSRF fail
- `wire:click` → AJAX on click → CSRF fail
- `{{ $this->form }}` → Livewire form state sync → CSRF fail
- `$wire.call()` via Alpine.js x-on:click → STILL sends Livewire AJAX → CSRF fail
- `@this.call()` in regular `<script>` → not compiled (literal text)
- `$L.dispatch()` from HTML onclick → unreliable

## Solution: Regular fetch() Routes
1. Define routes in `routes/web.php` (outside Filament)
2. Blade uses plain JS `fetch()` with CSRF token from meta tag
3. Route handler validates, saves to DB, returns JSON
4. `location.reload()` to re-render page

## Route Pattern
```php
// routes/web.php
Route::post('/pekerjaans/{pekerjaan}/save-step', function (Request $request, $pekerjaan) {
    $user = auth()->user();
    if (!$user || $user->role !== 'R02') return response()->json(['error' => 'Unauthorized'], 403);
    
    $record = Pekerjaan::findOrFail($pekerjaan);
    $tahap = $request->input('tahap');
    $keterangan = $request->input('keterangan', '');
    $photoPaths = $request->input('photo_paths', []);
    
    $steps = $record->dokumentasi_steps ?? [];
    $existingPhotos = $steps[$tahap]['photos'] ?? [];
    $steps[$tahap] = [
        'photos' => array_merge($existingPhotos, $photoPaths),
        'keterangan' => $keterangan,
        'saved_at' => now()->toIso8601String(),
    ];
    
    $record->update(['dokumentasi_steps' => $steps]);
    if ($record->status === 'assigned') $record->update(['status' => 'in_progress']);
    
    return response()->json(['success' => true, 'steps' => $steps]);
})->name('pekerjaans.save-step');
```

## Blade Pattern
```blade
<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

async function saveStep(percent) {
    const tahap = percent + '%';
    const ket = document.getElementById('input-ket-' + percent)?.value || '';
    const files = document.getElementById('input-foto-' + percent)?.files || [];
    
    // Upload photos first if any
    let photoPaths = [];
    if (files.length > 0) {
        const fd = new FormData();
        for (let f of files) fd.append('photos[]', f);
        const uploadRes = await fetch('/pekerjaans/upload-photos', {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const uploadData = await uploadRes.json();
        if (uploadData.paths) photoPaths = uploadData.paths;
    }
    
    // Save step data
    const saveRes = await fetch('/pekerjaans/' + RECORD_ID + '/save-step', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ tahap, keterangan: ket, photo_paths: photoPaths }),
    });
    const saveData = await saveRes.json();
    if (saveData.success) { alert('✅ Tersimpan!'); location.reload(); }
}
</script>

<button onclick="saveStep(0)">💾 Simpan Tahap 0%</button>
```

## Why fetch() beats all Livewire alternatives
| Mechanism | AJAX Target | CSRF Layer | Result on Custom Page |
|-----------|------------|-----------|---------------------|
| `wire:model` | `/livewire/update` | Livewire + Laravel | ❌ CSRF fail |
| `wire:click` | `/livewire/update` | Livewire + Laravel | ❌ CSRF fail |
| `$wire.call()` | `/livewire/update` | Livewire + Laravel | ❌ CSRF fail |
| `fetch()` to route | `/pekerjaans/save-step` | Laravel only | ✅ Works |

## Key Points
- Routes should be in `routes/web.php` inside the `web` middleware group
- CSRF token from `<meta name="csrf-token">` works for regular `fetch()` because it uses the session cookie directly
- Auth check in route: `auth()->user()->role !== 'R02'`
- Return JSON: `{ success: true }` or `{ error: 'message' }`
- After save: `location.reload()` to re-render the page with fresh data
- GPS: Use browser `navigator.geolocation.getCurrentPosition()` → `fetch()` to save route
- File upload: `fetch()` with `FormData` → return paths → include in save-step call

## $fillable Gotcha
When adding a JSON column to a model, ALWAYS add it to `$fillable`:
```php
// Model.php
protected $fillable = [
    // ... existing fields ...
    'dokumentasi_steps',  // MUST be here or update() silently ignores it
];

protected function casts(): array {
    return [
        'dokumentasi_steps' => 'array',  // Cast JSON → PHP array
    ];
}
```
Without `$fillable`, `$record->update(['dokumentasi_steps' => $data])` silently does nothing — the column stays null.
