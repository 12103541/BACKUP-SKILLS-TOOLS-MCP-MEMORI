# RBAC & Permission Patterns for Filament ERP

## canAccess() —  Use hasPermission(), not role array

### Bener

```php
public static function canAccess(): bool
{
    return auth()->user()?->hasPermission('faktur.view') ?? false;
}
```

### Salah

```php
public static function canAccess(): bool
{
    return in_array(Auth::user()->role, ['R00', 'R01', 'R05', 'R06']);
}
```

**Alasan:**
- Role baru (R07, R08) perlu edit 20+ resource kalau pakai hardcode
- Permission override per-user (grant/revoke) tidak akan bekerja
- `hasPermission()` internal: Super Admin (R00) otomatis bypass → lihat `User::hasPermission()`

### Mapping Permission ke Resource

| Resource | Permission |
|----------|-----------|
| FakturResource | `faktur.view` |
| KontrakResource | `kontrak.view` |
| AsetResource | `aset.view` |
| KlienResource | `klien.view` |
| PenawaranResource | `penawaran.view` |
| PajakResource | `pajak.view` |
| PengeluaranResource | `pengeluaran.view` |
| PettyCashResource | `petty_cash.view` |
| Sparepart/Supplier/StockOpname/Pemakaian | `gudang.view` |
| RabResource | `rab.view` |
| JadwalResource | `calendar.view` |
| DmsFolder/DmsTag | `dokumen.view` |
| UserResource | `admin.users` |
| PermissionResource | `admin.settings` |
| WorkflowProyekResource | `approval.view` |

## Permission N+1 Prevention

Setiap `hasPermission()` query:
1. Ambil `Permission.id` dari `kode` (via cache atau query)
2. Cek `UserPermission` (override)
3. Cek `RolePermission` (role default)

Tanpa caching, 20 resource di sidebar = 20+ query permission.

### Step 1: Request-level cache di User model

```php
private array $permCache = [];

public function hasPermission(string $permissionKode): bool
{
    if ($this->role === 'R00') return true;
    if (array_key_exists($permissionKode, $this->permCache)) {
        return $this->permCache[$permissionKode];
    }

    $permId = Permission::where('kode', $permissionKode)->value('id');
    if (!$permId) {
        $this->permCache[$permissionKode] = false;
        return false;
    }

    $userOverride = UserPermission::where('user_id', $this->id)
        ->where('permission_id', $permId)
        ->first();

    if ($userOverride) {
        $this->permCache[$permissionKode] = $userOverride->granted;
        return $userOverride->granted;
    }

    $result = RolePermission::where('role', $this->role)
        ->where('permission_id', $permId)
        ->exists();

    $this->permCache[$permissionKode] = $result;
    return $result;
}
```

### Step 2: Bulk preload method

```php
public function preloadAllPermissions(): void
{
    if ($this->role === 'R00') return;

    $permissions = Permission::pluck('kode', 'id');
    $overridePermIds = UserPermission::where('user_id', $this->id)
        ->whereIn('permission_id', $permissions->keys())
        ->get()
        ->keyBy('permission_id');
    $rolePermIds = RolePermission::where('role', $this->role)
        ->whereIn('permission_id', $permissions->keys())
        ->pluck('permission_id')
        ->toArray();

    foreach ($permissions as $pid => $kode) {
        if (isset($overridePermIds[$pid])) {
            $this->permCache[$kode] = $overridePermIds[$pid]->granted;
        } else {
            $this->permCache[$kode] = in_array($pid, $rolePermIds);
        }
    }
}
```

### Step 3: Middleware

```php
// app/Http/Middleware/PreloadUserPermissions.php
class PreloadUserPermissions
{
    public function handle($request, $next)
    {
        if ($user = $request->user()) {
            $user->preloadAllPermissions();
        }
        return $next($request);
    }
}
```

### Step 4: Daftarkan di AdminPanelProvider

```php
->authMiddleware([
    Authenticate::class,
    \App\Http\Middleware\PreloadUserPermissions::class,
])
```

## HasDeptAccess Trait — Jangan Dead Code

Jika `use HasDeptAccess;` dipasang di resource, pastikan implementasi beneran:

```php
trait HasDeptAccess
{
    public static function checkDeptAccess(): bool
    {
        $slug = static::resolveSlug(); // dari $navSlug atau class basename
        if (!$slug) return true;
        return DeptAccessService::canAccess($slug);
    }

    protected static function resolveSlug(): ?string
    {
        if (property_exists(static::class, 'navSlug') && static::$navSlug) {
            return static::$navSlug;
        }
        $basename = class_basename(static::class);
        $basename = preg_replace('/Resource$|Page$/', '', $basename);
        return str($basename)->kebab()->toString() ?: null;
    }
}
```

Atau hapus trait dari resource yang tidak menggunakannya.

## PPN Rate — Config, Bukan Hardcode

```php
// ❌ SALAH — hardcode
$ppn = round($termin->nilai * 0.11);

// ✅ BENAR — dari config
$tarifPpn = (float) config('pajak.tarif_ppn_keluaran', 12);
$ppn = round($termin->nilai * $tarifPpn / 100);
```

Config `config/pajak.php`:
```php
'tarif_ppn_keluaran' => (float) env('TARIF_PPN_KELUARAN', 12),
```

Label form juga harus dinamis:
```php
Placeholder::make('ppn_display')
    ->label(fn (): string => 'PPN (' . config('pajak.tarif_ppn_keluaran', 12) . '%)')
```

## ValidationException — Namespace

```php
// ✅ BENAR
throw new \Illuminate\Validation\ValidationException($validator);

// ❌ SALAH — class tidak ada
throw new \ValidationException($validator);
```
