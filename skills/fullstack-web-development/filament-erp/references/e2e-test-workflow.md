# End-to-End Test Workflow: 1 Contract Full Cycle (Client → 100% Payment)

Use this pattern to seed/verify a complete project lifecycle for testing.

## Scenario: ME-CIPALI Contract
- **Client:** PT Jasa Marga (Persero) Tbk
- **Contract:** `KONTRAK/ME-CIPALI/2026/001` — 850M IDR
- **Scope:** LED Display & Kontrol Pintu Tol Cikampek
- **Termins:** 5 (1 DP 10% + 4 progress 22.5% each)
- **RAB:** 8 components, 10% markup → 940.5M total
- **Pekerjaan:** 5 work orders (survey → install → test → UAT)
- **Faktur:** 5 invoices (all LUNAS)
- **Pembayaran:** 5 payments (total 850M = 100% contract value)

## Seeder Script

```php
<?php
// test_me_cipali_full.php — run via: php test_me_cipali_full.php
// Cleans existing data, creates fresh complete workflow, asserts 100% progress

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Klien;
use App\Models\Kontrak;
use App\Models\KontrakTermin;
use App\Models\Rab;
use App\Models\RabKomponen;
use App\Models\Pekerjaan;
use App\Models\Faktur;
use App\Models\FakturItem;
use App\Models\Pembayaran;
use App\Models\User;

echo "=== TEST ME-CIPALI FULL WORKFLOW ===\n\n";
$user = User::first(); // superadmin

// CLEANUP (use raw DB for cascade-safe deletion)
$existingKontrak = Kontrak::where('nomor_kontrak', 'KONTRAK/ME-CIPALI/2026/001')->first();
if ($existingKontrak) {
    $kontrakId = $existingKontrak->id;
    \DB::table('pembayaran')
        ->whereIn('faktur_id', Faktur::where('kontrak_id', $kontrakId)->pluck('id'))
        ->delete();
    \DB::table('faktur_items')
        ->whereIn('faktur_id', Faktur::where('kontrak_id', $kontrakId)->pluck('id'))
        ->delete();
    Faktur::where('kontrak_id', $kontrakId)->delete();
    Pekerjaan::where('kontrak_id', $kontrakId)->delete();
    RabKomponen::whereHas('rab', fn($q) => $q->where('kontrak_id', $kontrakId))->delete();
    Rab::where('kontrak_id', $kontrakId)->delete();
    KontrakTermin::where('kontrak_id', $kontrakId)->delete();
    $existingKontrak->delete();
}
$existingKlien = Klien::where('nama', 'PT Jasa Marga (Persero) Tbk')->first();
if ($existingKlien) { $existingKlien->delete(); }

echo "Cleaned existing test data\n\n";

// 1. KLIEN
$klien = Klien::create([
    "nama" => "PT Jasa Marga (Persero) Tbk",
    "npwp" => "01.234.567.8-901.000",
    "alamat" => "Jl. Tol Jakarta - Cikampek KM 58, Cikampek, Karawang, Jawa Barat",
    "telepon" => "0267-123456",
    "email" => "procurement@jasamarga.co.id",
    "pic" => "Bpk. Andi Santoso",
    "jabatan_pic" => "Kepala Divisi Procurement",
    "jenis_usaha" => "BUMN - Pengelola Jalan Tol",
    "status" => "aktif",
]);
echo "1. Klien created: {$klien->id} - {$klien->nama}\n";

// 2. KONTRAK
$kontrak = Kontrak::create([
    "nomor_kontrak" => "KONTRAK/ME-CIPALI/2026/001",
    "nama_kontrak" => "Pemasangan Sistem LED Display & Kontrol Pintu Tol Cikampek",
    "klien_id" => $klien->id,
    "nama_klien" => $klien->nama,
    "npwp_klien" => $klien->npwp,
    "alamat_klien" => $klien->alamat,
    "telepon_klien" => $klien->telepon,
    "email_klien" => $klien->email,
    "pic_klien" => $klien->pic,
    "nilai" => 850_000_000,
    "tgl_mulai" => "2026-07-01",
    "tgl_akhir" => "2026-12-31",
    "progres_fisik" => 0,
    "progres_keuangan" => 0,
    "status" => "active",
    "masa_garansi" => 12,
    "jenis" => "projek",
    "nilai_retensi" => 42_500_000,
    "gps_latitude" => -6.3975,
    "gps_longitude" => 107.4058,
    "jumlah_mc" => 6,
    "nilai_per_mc" => 141_666_667,
    "has_dp" => true,
    "dp_persentase" => 10,
    "adendum_max_persen" => 20,
    "cut_off_date" => 25,
]);
echo "2. Kontrak created: {$kontrak->id} - {$kontrak->nomor_kontrak}\n";

// 3. KONTRAK TERMIN (5 termin: 1 DP + 4 progress)
$terminData = [
    [1, 'Down Payment (DP) 10%', 10, 85_000_000, '2026-07-15', true],
    [2, 'Termin 1 - Mobilisasi & Survey Lapangan', 22.5, 191_250_000, '2026-08-25', false],
    [3, 'Termin 2 - Pemasangan LED Display', 22.5, 191_250_000, '2026-10-25', false],
    [4, 'Termin 3 - Sistem Kontrol & Integrasi', 22.5, 191_250_000, '2026-11-25', false],
    [5, 'Termin 4 - UAT, Training & Serah Terima', 22.5, 191_250_000, '2026-12-25', false],
];
foreach ($terminData as $t) {
    KontrakTermin::create([
        "kontrak_id" => $kontrak->id, "termin_ke" => $t[0], "nama" => $t[1],
        "persentase" => $t[2], "nilai" => $t[3], "tgl_jatuh_tempo" => $t[4],
        "is_dp" => $t[5], "status" => "belum_tertagih",
    ]);
}
echo "3. 5 Termin created\n";

// 4. RAB (referensi harga + markup)
$rab = Rab::create([
    "nomor_rab" => "RAB/ME-CIPALI/2026/001",
    "tanggal_pembuatan" => "2026-06-15",
    "nama_proyek" => $kontrak->nama_kontrak,
    "kontrak_id" => $kontrak->id,
    "markup_persen" => 10,
    "is_markup_applied" => true,
    "versi" => 1,
    "is_active" => true,
    "user_id" => $user->id,
]);
$komponen = [
    ['Survey & Mobilisasi', 1, 'paket', 25_000_000],
    ['LED Display P10 2x3m', 4, 'unit', 75_000_000],
    ['Sistem Kontrol Pintu Tol', 1, 'paket', 180_000_000],
    ['Instalasi Mekanikal', 1, 'paket', 95_000_000],
    ['Instalasi Elektrikal', 1, 'paket', 85_000_000],
    ['Software & Integrasi', 1, 'paket', 120_000_000],
    ['UAT & Training', 1, 'paket', 35_000_000],
    ['Dokumentasi & Serah Terima', 1, 'paket', 15_000_000],
];
foreach ($komponen as $i => $k) {
    RabKomponen::create([
        "rab_id" => $rab->id, "no_urut" => $i+1,
        "uraian_pekerjaan" => $k[0], "volume" => $k[1],
        "satuan" => $k[2], "harga_satuan" => $k[3],
        "jumlah_harga" => $k[1] * $k[3],
    ]);
}
$rab->hitungTotal();
echo "4. RAB created: {$rab->nomor_rab}, Total: Rp " . number_format($rab->total_rab, 0, ",", ".") . "\n";

// 5. PEKERJAAN (field work orders)
$pekerjaanData = [
    ['Mobilisasi & Survey Lapangan', 'survey', 'in_progress'],
    ['Pemasangan LED Display P10', 'instalasi', 'assigned'],
    ['Instalasi Sistem Kontrol Pintu Tol', 'instalasi', 'assigned'],
    ['Pengujian Sistem & Integrasi', 'testing', 'assigned'],
    ['UAT, Training & Serah Terima', 'commissioning', 'assigned'],
];
foreach ($pekerjaanData as $i => $p) {
    Pekerjaan::create([
        "kontrak_id" => $kontrak->id, "user_id" => $user->id,
        "nama_pekerjaan" => $p[0], "jenis_pekerjaan" => $p[1],
        "aset" => "VMS", "status" => $p[2],
        "tenggat_waktu" => \Carbon\Carbon::parse($terminData[$i+1][4])->addDays(5),
        "gps_latitude" => -6.3975, "gps_longitude" => 107.4058,
    ]);
}
echo "5. 5 Pekerjaan created\n";

// 6. FAKTUR per termin (all LUNAS)
foreach ($kontrak->termin as $t) {
    $f = Faktur::create([
        "nomor_faktur" => 'F/' . ($t->is_dp ? 'DP' : 'ME-CIPALI') . '/' . date('Ym', strtotime($t->tgl_jatuh_tempo)) . '/' . str_pad($t->termin_ke, 3, '0', STR_PAD_LEFT),
        "tanggal_faktur" => \Carbon\Carbon::parse($t->tgl_jatuh_tempo)->subDays(5),
        "kontrak_id" => $kontrak->id, "kontrak_termin_id" => $t->id,
        "klien_id" => $klien->id, "nama_klien" => $klien->nama,
        "subtotal" => $t->nilai / 1.11, "ppn" => $t->nilai * 0.11 / 1.11,
        "total_tagihan" => $t->nilai, "jatuh_tempo" => $t->tgl_jatuh_tempo,
        "status" => "lunas", "tanggal_pembayaran" => \Carbon\Carbon::parse($t->tgl_jatuh_tempo)->subDay(),
        "user_id" => $user->id,
    ]);
    $t->update(["status" => "lunas", "tgl_bayar" => $f->tanggal_pembayaran, "faktur_id" => $f->id]);
    FakturItem::create(["faktur_id" => $f->id, "no_urut" => 1, "uraian" => $t->nama, "quantity" => 1, "satuan" => "LS", "harga_satuan" => $f->subtotal, "total" => $f->subtotal]);
}
echo "6. 5 Faktur created (all lunas)\n";

// 7. PEMBAYARAN
foreach ($kontrak->faktur as $f) {
    Pembayaran::create([
        "faktur_id" => $f->id, "tanggal_bayar" => $f->tanggal_pembayaran,
        "jumlah" => $f->total_tagihan, "metode_bayar" => "Transfer Bank BCA",
        "nomor_pembayaran" => Pembayaran::generateNomorPembayaran(),
        "catatan" => 'Pembayaran ' . $f->kontrakTermin->nama . ' - ' . $f->nomor_faktur,
        "user_id" => $user->id, "status" => "verified", "verified_by" => $user->id, "verified_at" => now(),
    ]);
}
echo "7. 5 Pembayaran created\n";

// 8. VERIFY 100% PROGRESS
$kontrak->pekerjaan()->update(["status" => "approved", "approved_by" => $user->id, "approved_at" => now()]);
$kontrak->hitungProgresOtomatis();
$kontrak->refresh();

echo "\n=== FINAL STATUS ===\n";
echo "Kontrak: {$kontrak->nomor_kontrak}\n";
echo "Nilai Kontrak: Rp " . number_format($kontrak->nilai, 0, ",", ".") . "\n";
echo "Progres Fisik: {$kontrak->progres_fisik}%\n";
echo "Progres Keuangan: {$kontrak->progres_keuangan}%\n";
echo "Status: {$kontrak->status}\n";
echo "Tgl Selesai: " . ($kontrak->tgl_selesai ?? "Belum") . "\n";

echo "\nTermin Status:\n";
foreach ($kontrak->termin as $t) {
    echo "  T{$t->termin_ke} {$t->nama}: {$t->status} (Rp " . number_format($t->nilai, 0, ",", ".") . ") - Bayar: " . ($t->tgl_bayar ?? "-") . "\n";
}

echo "\nFaktur Status:\n";
foreach ($kontrak->faktur as $f) {
    echo "  {$f->nomor_faktur}: {$f->status} (Rp " . number_format($f->total_tagihan, 0, ",", ".") . ")\n";
}

$totalBayar = Pembayaran::whereHas('faktur', fn($q) => $q->where('kontrak_id', $kontrak->id))->sum("jumlah");
echo "\nPembayaran Total: Rp " . number_format($totalBayar, 0, ",", ".") . "\n";
echo "RAB Total: Rp " . number_format($rab->total_rab, 0, ",", ".") . "\n";

assert($kontrak->progres_fisik == 100, "Progres fisik should be 100%");
assert($kontrak->progres_keuangan == 100, "Progres keuangan should be 100%");
assert($kontrak->status === 'completed', "Status should be completed");
echo "\n✅ WORKFLOW COMPLETE: 100% Progres Fisik & Keuangan\n";
```

## Key Assertions for Validation
- `progres_fisik == 100` (all pekerjaan approved)
- `progres_keuangan == 100` (all termin lunas)
- `status === 'completed'` (auto-set by model)
- `RAB total >= Kontrak nilai` (markup applied correctly)
- All 5 termin status = `lunas`
- All 5 faktur status = `lunas`
- Total pembayaran = kontrak nilai

## Usage
```bash
cd /c/laragon/www/PT.EXFERIA\ PUTRA\ INOVASI
php test_me_cipali_full.php
```

Expected output:
```
=== TEST ME-CIPALI FULL WORKFLOW ===

Cleaned existing test data

1. Klien created: 10 - PT Jasa Marga (Persero) Tbk
2. Kontrak created: 9 - KONTRAK/ME-CIPALI/2026/001
3. 5 Termin created
4. RAB created: RAB/ME-CIPALI/2026/001, Total: Rp 940.500.000
5. 5 Pekerjaan created
6. 5 Faktur created (all lunas)
7. 5 Pembayaran created

=== FINAL STATUS ===
Kontrak: KONTRAK/ME-CIPALI/2026/001
Nilai Kontrak: Rp 850.000.000
Progres Fisik: 100.00%
Progres Keuangan: 100.00%
Status: completed
Tgl Selesai: 2026-07-30 00:00:00

Termin Status:
  T1 Down Payment (DP) 10%: lunas (Rp 85.000.000) - Bayar: 2026-07-14 00:00:00
  T2 Termin 1 - Mobilisasi & Survey Lapangan: lunas (Rp 191.250.000) - Bayar: 2026-08-24 00:00:00
  ...

✅ WORKFLOW COMPLETE: 100% Progres Fisik & Keuangan
```

## Common Pitfalls
| Issue | Cause | Fix |
|-------|-------|-----|
| `no_urut` column missing on faktur_items | Migration missing | Run `add_no_urut_to_faktur_items` migration |
| `aset` enum value invalid | 'led_display' not in enum | Use `VMS` or `PJU` or `videotron` |
| Duplicate kontrak nomor | Not cleaned up | Use raw DB cleanup before create |
| `faktur_item` vs `faktur_items` table | Model uses `faktur_items` | Check `$table` property on model |
| Progress not 100% | `hitungProgresOtomatis()` not called | Call after updating pekerjaan/termin status |

---

**Reference:** This test was created during the ME-CIPALI end-to-end workflow session (2026-07-30). The script `test_me_cipali_full.php` is available in the project root and can be re-run to regenerate fresh test data.