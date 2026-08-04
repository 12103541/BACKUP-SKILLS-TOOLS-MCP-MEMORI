# CIPALI Production Workflow (2026-07-30)

Two sequential runs. First run (production data with existing SQL restore), second run (factory reset → clean → re-run).

## Full Sequential Workflow

```
KLIEN: PT Jasa Marga Tbk (ID 1)
  └─ KONTRAK: KTR-CIPALI-20260730 (ID 18, Rp 1.713.883.998)
      ├─ RAB ME-CIPALI (ID 19, 518 komponen) ← imported from SQL backup
      ├─ BOM Generate → BOM/202607/0004 (ID 6)
      │   ├─ 509 sparepart mapped (auto-match by keyword)
      │   └─ 9 jasa items (auto-skip)
      ├─ AKTIVASI BOM → Eka Gudang (R04) → status: active
      ├─ PEKERJAAN → Candra Teknisi (R02) → approved
      ├─ TRANSAKSI KELUAR → 509 items, Rp 1.540.324.490
      │   (auto-decrement stok via TransaksiKeluar::booted)
      ├─ FAKTUR → F/CIPALI/20260730/0001
      │   Subtotal Rp 1.540.324.490 + PPN 12% Rp 184.838.939 = Rp 1.725.163.429
      └─ PEMBAYARAN → BYR/CIPALI/20260730/0001 (Transfer Bank) → LUNAS ✅
          └─ ASET → AST-CIPALI-0001 (auto-create)
```

## Division-to-Step Mapping

| Step | Role | User | Aksi |
|------|------|------|------|
| 1. Klien | — | PT Jasa Marga Tbk | Data existing |
| 2. Kontrak | R00/R01 | Super Admin | Update status → active, set klien_id |
| 3. RAB | R01 | Admin Proyek | Import data existing |
| 4. BOM Generate | R04 | Eka Gudang | `RabMaterialPlanService::generateFromRab()` |
| 5. BOM Aktivasi | R04 | Eka Gudang | `RabMaterialPlanService::activate($plan)` |
| 6. Pekerjaan | R02 | Candra Teknisi | `Pekerjaan::create(...)` |
| 7. Transaksi Keluar | R04 | Eka Gudang | `TransaksiKeluar::create(...)` per item |
| 8. Faktur | R05 | Staff Keuangan | `Faktur::create(...)` with PPN |
| 9. Pembayaran | — | Klien | `Pembayaran::create(...)` → auto-lunas |
| 10. Aset | Auto | Sistem | Auto-create dari kontrak selesai |

## Factory Reset → Clean Demo Pattern

```php
// 1. Backup first (always!)
$bs = app(BackupService::class);
$bs->backupDatabase();
Backup::create(['nama' => 'Pre-Reset - '.now(), 'tipe' => 'database', ...]);

// 2. Factory reset — keeps users, roles, permissions, migrations, company_settings
$rs = app(ResetService::class);
$rs->factoryReset();

// 3. Restore reference data from SQL backup
// mysql.exe -u root aplikasi_kantor < path/to/backup.sql
// Or artisan tinker with DB::unprepared() per INSERT statement

// 4. Top-up depleted stok
Sparepart::where('stok', '<', 100)->update(['stok' => 1000]);
```

## Schema Quirks (Required Fields Blocking Creation)

### pekerjaan table
```php
Pekerjaan::create([
    'kontrak_id'     => 18,        // ★ REQUIRED
    'user_id'        => $teknisi->id,
    'nama_pekerjaan' => '...',
    'jenis_pekerjaan'=> 'instalasi', // ★ REQUIRED, no default
    'aset'           => 'PJU',       // ★ REQUIRED, ENUM('VMS','PJU','videotron')
    'lokasi_ruas'    => 'Cipali...', // ★ REQUIRED, no default
    'lokasi_km'      => 100.0,       // ★ REQUIRED, decimal(5,1)
    'status'         => 'approved',
]);
```

### faktur table
```php
Faktur::create([
    'nomor_faktur'  => '...', // ★ REQUIRED unique
    'tanggal_faktur'=> now(),
    'kontrak_id'    => 18,    // ★ REQUIRED
    'nama_klien'    => '...', // ★ REQUIRED (not auto-filled from kontrak)
    'subtotal'      => $val,
    'ppn'           => $val,
    'total_tagihan' => $val,
    'jatuh_tempo'   => now()->addDays(30), // ★ REQUIRED
    'status'        => 'terbit',
    'user_id'       => auth()->id(), // ★ REQUIRED
]);
```

### pembayaran table
```php
Pembayaran::create([
    'faktur_id'        => $faktur->id,    // ★ REQUIRED
    'nomor_pembayaran' => 'BYR/...',      // ★ REQUIRED unique
    'jumlah'           => $total,         // ★ REQUIRED
    'tanggal_bayar'    => now(),          // ★ REQUIRED
    'metode_bayar'     => 'Transfer Bank',
    'referensi'        => 'TRF/...',
    'user_id'          => auth()->id(),   // ★ REQUIRED
]);
```

### kontrak table
Uses `tgl_mulai`/`tgl_akhir` NOT `tanggal_mulai`/`tanggal_selesai`.

## Auto-Create Sparepart from Unmapped BOM Items

```php
// Group unmapped items by unique name (162 unique names for 412 items)
$unmapped = $plan->items()->whereNull('sparepart_id')->whereNotIn('tipe_item', ['jasa'])->get();
$uniqueNames = $unmapped->pluck('uraian_pekerjaan')->unique();

foreach ($uniqueNames as $name) {
    $sp = Sparepart::create([
        'nama_part' => $name,
        'sku' => 'SP-'.str_pad(Sparepart::max('id')+1, 5, '0', STR_PAD_LEFT),
        'harga' => $unmapped->where('uraian_pekerjaan', $name)->first()->harga_satuan_rab,
        'stok' => 1000,
        'kategori' => 'CIPALI',
    ]);
    // Link all BOM items with this name to the new sparepart
    $plan->items()->whereNull('sparepart_id')->where('uraian_pekerjaan', $name)->update(['sparepart_id' => $sp->id]);
}
```

## Known Bug: `toArray()` → Blade `format()` Fails

**Error**: `Call to a member function format() on string` in `gudang-dashboard.blade.php:536`
**Cause**: `getTransaksiHariIni()` returns `->toArray()` → dates become strings. Blade `$tk['created_at']->format('H:i')` fails.
**Fix**: `{{ \Carbon\Carbon::parse($tk['created_at'])->format('H:i') }}`

**Detection regex**: Search Blade for `$var\['\w+'\]->format\(` — these break when source is `->toArray()`.
