# Workflow Chain Testing — Full ERP Chain

## Golden Rule

Test features in **business flow order**, never in isolation. Skipping intermediate steps produces unrealistic test data and masks integration bugs. The complete chain:

```
KONTRAK → RAB → BOM (Mapping) → PEKERJAAN → TRANSAKSI KELUAR → FAKTUR → PEMBAYARAN → ASET
```

---

## Upstream: RAB → BOM → TransaksiKeluar (Procurement & Inventory)

### 1. Kontrak → RAB
- Admin Proyek (R01) creates Kontrak (jenis=projek)
- Creates RAB linked to `kontrak_id`
- RAB items (RabKomponen) define scope + budget
- Total = sum of `jumlah_harga` + optional markup

### 2. RAB → BOM (RabMaterialPlan)
- Trigger: `RabMaterialPlanService::generateFromRab(Rab $rab)`
- Creates RabMaterialPlan (master) + RabMaterialPlanItem per komponen
- **Auto-matching**: matches `uraian_pekerjaan` against Sparepart DB by keyword (in-memory, 0 extra queries)
- Unmatched items → `sparepart_id=null`, `tipe_item=lainnya`
- Jasa items → auto `status=skipped`
- Notifies Gudang (R04) + Admin Proyek (R01)

### 3. BOM Aktivasi (oleh Gudang)
- Staff Gudang (R04) verifies mapping → `$service->activate($plan)`
- Status → `active`, records `approved_by` + `approved_at`
- Auto-populates `reorder_point` on spareparts

### 4. BOM → Pekerjaan → TransaksiKeluar
- Teknisi (R02) creates Pekerjaan linked to kontrak
- TransaksiKeluar records sparepart usage per pekerjaan
- **Auto-stok**: `TransaksiKeluar::booted()::decrementStok()` reduces `spareparts.stok`
- **Auto-BOM sync**: `syncBomOnCreated()` → updates BOM item `quantity_terealisasi` + `harga_realisasi`
- **Auto-PP**: at 80% realisasi, auto-creates draft PermintaanPembelian

### Pitfalls (Upstream)

| Pitfall | Symptom | Fix |
|---------|---------|-----|
| Double stok decrement | Stok goes negative | `TransaksiKeluar::create()` already decrements via booted. Never call `$sp->decrement()` separately |
| Stale stok reference | `min(qty_rencana, stok)` wrong | Call `$sp->refresh()` after each TransaksiKeluar create |
| Unmapped RAB items | TransaksiKeluar silently skips them | Check BOM items for `sparepart_id=null` after generation |
| Freebuff daily limit | Thread stalls mid-workflow | Switch model or use Hermes CLI |
| `float` passed to Sparepart `harga` | brick/math deprecation warning | Cast float to string: `'harga' => (string) $floatValue` |

### Opsi A: Auto-Create Sparepart from Unmapped BOM Items

When BOM has 400+ unmapped items but only a few existing spareparts, use Opsi A:

```php
// 1. Get unique unmapped item names
$unmapped = RabMaterialPlanItem::where('plan_id', $planId)
    ->whereNull('sparepart_id')
    ->whereIn('tipe_item', ['sparepart', 'material', 'lainnya'])
    ->get();

$unique = $unmapped->groupBy(fn($i) => strtolower(trim($i->uraian_pekerjaan)))
    ->map(fn($g) => $g->first());

// 2. Batch create spareparts (harga as string!)
$newParts = [];
foreach ($unique as $item) {
    $sp = Sparepart::create([
        'nama_part' => $item->uraian_pekerjaan,
        'sku' => 'SP-' . str_pad(Sparepart::max('id') + 1, 5, '0', STR_PAD_LEFT),
        'harga' => (string) ($item->harga_satuan_rab ?: $item->total_harga_rab / max(1, $item->volume)),
        'harga_jual' => (string) ($item->harga_satuan_rab ?: $item->total_harga_rab / max(1, $item->volume)),
        'stok' => 1000,
        'safety_stock' => 10,
        'satuan' => $item->satuan ?? 'unit',
        'kategori' => 'CIPALI', // or project name
    ]);
    $newParts[strtolower(trim($item->uraian_pekerjaan))] = $sp->id;
}

// 3. Link BOM items to new spareparts
foreach ($unmapped as $bi) {
    $key = strtolower(trim($bi->uraian_pekerjaan));
    if (isset($newParts[$key])) {
        $bi->update(['sparepart_id' => $newParts[$key]]);
    }
}
```

**Caveats:**
- `harga` field must be cast to string to avoid brick/math deprecation
- Stok defaults to 1000 — adjust per business rules
- SKU auto-generated; may need prefix customization
- Only 1:1 mapping (1 RAB item → 1 sparepart); no merging

---

## Downstream: Kontrak → Pembayaran → Aset (Finance & Completion)

### Pola Test Script

Buat file PHP standalone `test_<skenario>.php` di root project:

```php
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
$kernel->bootstrap();

// [...] logic di sini
```

**Script siap-pakai**: `scripts/workflow-test.php` di skill filament-erp — full chain KONTRAK→RAB→BOM→PEKERJAAN→TK→FAKTUR→BAYAR, auto-create sparepart, siap dijalankan via `php artisan tinker --execute="require 'scripts/workflow-test.php'"` (copy ke root project dulu).

### Template

1. **Setup header** — show title + user info
2. **Create minimal data** — Klien, Kontrak, Termin, Pekerjaan
3. **Step-by-step** — satu section per action, echo ALL state after each
4. **Assertions** — bandingkan expected vs actual, tandai ✓/✗
5. **Summary diagram** — visual flow di akhir
6. **Auto-cleanup** — `forceDelete()` semua data test di akhir

### Cascade Event Checklist

| Action | Expected Cascade |
|--------|-----------------|
| Pekerjaan → `approved` | kontrak→`hitungProgresOtomatis()` → fisik = approved/total × 100 |
| Faktur → `terbit` | termin `tertagih`, PPN record di tabel pajak |
| Pembayaran → `saved()` | faktur `lunas` (jika totalBayar ≥ tagihan), termin `lunas` |
| Pembayaran → partial | faktur tetap status sebelumnya (jika totalBayar < tagihan) |
| Kontrak fisik=100% & keuangan=100% | `complete()` → status `completed`, Aset auto-create |
| Kontrak re-open (`completed`→`active`) | Aset dihapus (where catatan `%dibuat otomatis%`), reset progres |

### Bug yang Pernah Ditemukan via Test

1. **Progres keuangan abaikan adendum** — `hitungProgresOtomatis()` pakai `$this->nilai` bukan `$this->nilai_efektif`. Fix: ganti ke `nilai_efektif`.
2. **foto_paths no default** — kolom JSON NOT NULL, pekerjaan create tanpa `foto_paths` → error 1364. Fix: `$attributes` default `'[]'` di model + DB nullable.
3. **nomor_faktur VARCHAR(20) terlalu pendek** — format `FAK-TEST/20260729/0001` overflow. Fix: VARCHAR(50).

### Alur Jatuh Tempo

```
cron: faktur:check-jatuh-tempo
  → faktur (terbit & jatuh_tempo < today)
  → status jadi 'jatuh_tempo'
  → termin jadi 'terlambat'
  → notifikasi ke role Keuangan (R05)
```

### Aturan Adendum

- Max 20% dari nilai kontrak awal (`ADENDUM_MAX_PERSEN = 0.2`)
- `nilai_efektif` = `nilai` + sum(`adendum.nilai`)
- Termin baru bisa dibuat setelah adendum
- Progres keuangan dihitung terhadap `nilai_efektif`

---

## Demo Preparation: Factory Reset

Before running a demo with clean data, use **Factory Reset** (keeps users/permissions/settings, clears all transactional data):

### Procedure

```
1. Backup DB (always first!)
   → app(BackupService::class)->backupDatabase()
   → simpan record di backups table

2. Factory Reset (via ResetService)
   → app(ResetService::class)->factoryReset()
   → truncates ALL tables except:
      users, roles, role_permissions, user_permissions,
      permissions, company_settings, migrations

3. Verify clean state
   → count users (12 expected)
   → confirm kontraks=0, spareparts=0, fakturs=0, dll
```

### Script (run via `php artisan tinker`)

```php
// Backup
$bs = app(\App\Services\BackupService::class);
$result = $bs->backupDatabase();

// Reset
$rs = app(\App\Services\ResetService::class);
$rs->factoryReset();

// Verify
echo 'Users: ' . \App\Models\User::count(); // 12
echo 'Kontrak: ' . \App\Models\Kontrak::count(); // 0
```

### Reset Modes Available (via BackupPage UI)

| Mode | Cleaned | Preserved |
|------|---------|-----------|
| **Per Modul** | Selected module tables | Everything else |
| **Factory Reset** | All transactional data | Users, roles, perms, settings |
| **Total Reset** | Everything except migrations | — (runs seeder after) |
