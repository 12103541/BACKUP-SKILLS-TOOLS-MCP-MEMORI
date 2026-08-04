<?php
/**
 * E2E project simulation for PT EXFERIA ERP — full lifecycle:
 * Klien → RAB → Penawaran → Kontrak → BOM → Eksekusi (TransaksiKeluar)
 * → Supervisor approve → Faktur → Pembayaran 100% → Kontrak complete → Aset.
 *
 * Proven 2026-07-31 (PJU Tangerang sim). Adjust $CFG before running.
 * RUN: php e2e-simulate-project.php  (from project root C:\laragon\www\PT.EXFERIA PUTRA INOVASI)
 * NOTE: edit this file with python write + cp, NOT heredoc (single quotes break git-bash heredoc).
 */
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{User, Klien, Kontrak, Rab, RabKomponen, Sparepart, Pekerjaan, TransaksiKeluar, Faktur, FakturItem, Pembayaran, Aset};
use App\Services\RabMaterialPlanService;

// ── CONFIG ──────────────────────────────────────────────
$CFG = [
    'email_r01' => 'admin.proyek@example.com', // Admin Proyek
    'email_r02' => 'teknisi1@example.com',     // Teknisi
    'email_r03' => 'supervisor@example.com',   // Supervisor
    'email_r04' => 'gudang@example.com',       // Gudang
    'email_r05' => 'keuangan@example.com',     // Keuangan
    'nama_klien'   => 'PT Jasa Marga (Persero) Tbk',
    'nama_proyek'  => 'Pemasangan PJU Tol Tangerang KM 12-20',
    'nomor_prefix' => 'PJU-TNG/2026/001',
    'nilai'        => 109040000, // must equal sum of RAB komponen
    'jenis_kontrak'=> 'pengadaan_langsung', // enum: pengadaan_langsung|perawatan|lelang|swakelola|projek
    'lokasi_km'    => 12, // DECIMAL column — number only, no "12-14"
    'masa_garansi' => 12,
    'komponen' => [
        // uraian, volume, satuan, harga_satuan — uraian should match sparepart nama_part for auto-mapping
        ['Tiang PJU Octagonal 9m', 8, 'unit', 6500000],
        ['Lampu LED PJU 120W',     8, 'unit', 1850000],
        ['Kabel NYY 2x6mm',      300, 'm',     28000],
        ['MCB 1P 10A',             8, 'unit',   85000],
        ['Panel PJU 1 Fasa',       8, 'unit',  750000],
        ['Kontaktor 25A',          8, 'unit',  320000],
        ['Timer Astronomis',       8, 'unit',  450000],
        ['Baut & Mur SS (set)',   40, 'set',    25000],
        ['Pekerjaan Instalasi & Setting', 1, 'ls', 15000000], // jasa → auto-skipped in BOM
        ['Mobilisasi & Pengiriman',       1, 'ls',  5000000], // jasa → auto-skipped in BOM
    ],
];
// sparepart master (nama_part, kategori, stok, safety, harga) — upserted by this script
$PARTS = [
    ['Tiang PJU Octagonal 9m','tiang',12,2,6500000],
    ['Lampu LED PJU 120W','lampu',25,5,1850000],
    ['Kabel NYY 2x6mm','kabel',400,100,28000],
    ['MCB 1P 10A','panel',20,5,85000],
    ['Panel PJU 1 Fasa','panel',10,3,750000],
    ['Kontaktor 25A','panel',10,3,320000],
    ['Timer Astronomis','panel',8,3,450000],
    ['Baut & Mur SS (set)','aksesoris',50,10,25000],
];
// ────────────────────────────────────────────────────────

$step = fn($s) => print("\n=== [$s] ===\n");
$u = fn($e) => User::where('email', $e)->first();

$step("0. GUDANG (R04) — MASTER SPAREPART (upsert)");
foreach ($PARTS as $i => [$n, $kat, $stok, $safe, $hrg]) {
    Sparepart::updateOrCreate(['nama_part' => $n], [
        'sku' => 'PJU-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT), 'kategori' => $kat,
        'stok' => $stok, 'safety_stock' => $safe, 'reorder_point' => $safe,
        'harga' => $hrg, 'harga_jual' => $hrg,
    ]);
}
echo "sparepart ready\n";

$step("1. R01 — KLIEN");
$klien = Klien::firstOrCreate(['nama' => $CFG['nama_klien']], [
    'npwp' => '01.234.567.8-901.000', 'alamat' => 'Plaza Tol Taman Mini, Jakarta',
    'telepon' => '021-80888000', 'email' => 'procurement@jasamarga.com',
    'pic' => 'Ir. Andi Wijaya', 'jabatan_pic' => 'Manager Pengadaan', 'status' => 'aktif',
]);
echo "Klien #{$klien->id} {$klien->nama}\n";

$step("2. R01 — RAB + KOMPONEN");
$rab = Rab::create([
    'nomor_rab' => 'RAB/'.$CFG['nomor_prefix'], 'tanggal_pembuatan' => now(),
    'nama_proyek' => $CFG['nama_proyek'], 'total_rab' => $CFG['nilai'],
    'markup_persen' => 0, 'is_markup_applied' => false, 'versi' => 1, 'is_active' => true,
    'user_id' => $u($CFG['email_r01'])->id,
]);
foreach ($CFG['komponen'] as [$ur, $vol, $sat, $hs]) {
    RabKomponen::create(['rab_id' => $rab->id, 'uraian_pekerjaan' => $ur, 'volume' => $vol,
        'satuan' => $sat, 'harga_satuan' => $hs, 'jumlah_harga' => $vol * $hs]);
}
echo "RAB #{$rab->id} {$rab->nomor_rab} — ".count($CFG['komponen'])." komponen, Rp ".number_format($rab->total_rab, 0, ',', '.')."\n";

$step("3. R01 — PENAWARAN (kontrak_id diisi setelah kontrak)");
$pnw = \App\Models\Penawaran::create([
    'nomor_penawaran' => 'PNW/'.$CFG['nomor_prefix'], 'tanggal_penawaran' => now(),
    'nama_klien' => $klien->nama, 'deskripsi_pekerjaan' => $CFG['nama_proyek'],
    'total_keseluruhan' => $CFG['nilai'], 'masa_berlaku' => 30,
    'status' => 'disetujui', 'user_id' => $u($CFG['email_r01'])->id,
]);

$step("4. R01 — KONTRAK");
$kontrak = Kontrak::create([
    'nomor_kontrak' => 'KONTRAK/'.$CFG['nomor_prefix'], 'nama_kontrak' => $CFG['nama_proyek'],
    'klien_id' => $klien->id, 'nama_klien' => $klien->nama, 'npwp_klien' => $klien->npwp,
    'alamat_klien' => $klien->alamat, 'telepon_klien' => $klien->telepon, 'email_klien' => $klien->email,
    'pic_klien' => $klien->pic, 'nilai' => $CFG['nilai'], 'tgl_mulai' => now()->toDateString(),
    'tgl_akhir' => now()->addMonths(3)->toDateString(), 'jenis' => $CFG['jenis_kontrak'],
    'status' => 'active', 'masa_garansi' => $CFG['masa_garansi'], 'created_by' => $u($CFG['email_r01'])->id,
]);
$rab->update(['kontrak_id' => $kontrak->id]);
$pnw->update(['kontrak_id' => $kontrak->id]);
echo "Kontrak #{$kontrak->id} {$kontrak->nomor_kontrak}\n";

$step("5. BOM — R01 generate, R04 activate");
$plan = app(RabMaterialPlanService::class)->generateFromRab($rab);
app(RabMaterialPlanService::class)->activate($plan);
$plan->refresh();
echo "Plan {$plan->nomor_plan} — {$plan->total_item_rencana} item, status {$plan->status}, "
    .$plan->items()->whereNotNull('sparepart_id')->count()." mapped\n";

$step("6. R02 — EKSEKUSI (TransaksiKeluar tipe='keluar' auto-decrement stok)");
$pekerjaan = Pekerjaan::create([
    'kontrak_id' => $kontrak->id, 'user_id' => $u($CFG['email_r02'])->id,
    'nama_pekerjaan' => $CFG['nama_proyek'], 'jenis_pekerjaan' => 'instalasi', 'aset' => 'PJU',
    'lokasi_ruas' => 'Tol Tangerang', 'lokasi_km' => $CFG['lokasi_km'],
    'tenggat_waktu' => now()->addMonth()->toDateString(), 'waktu_mulai' => now(), 'status' => 'submitted',
]);
foreach ($plan->items()->whereNotNull('sparepart_id')->get() as $it) {
    $sp = Sparepart::find($it->sparepart_id);
    TransaksiKeluar::create([
        'sparepart_id' => $sp->id, 'quantity' => $it->volume, 'tanggal' => now(),
        'pekerjaan_id' => $pekerjaan->id, 'teknisi_id' => $u($CFG['email_r02'])->id,
        'harga_beli' => $sp->harga, 'harga_jual' => $sp->harga, 'tipe' => 'keluar', // enum keluar|retur
    ]);
}
echo "Pekerjaan #{$pekerjaan->id} — ".TransaksiKeluar::where('pekerjaan_id', $pekerjaan->id)->count()." item keluar\n";

$step("7. R03 — SUPERVISOR APPROVE");
$pekerjaan->update(['status' => 'approved', 'approved_by' => $u($CFG['email_r03'])->id, 'approved_at' => now()]);
echo "approved\n";

$step("8. R01 — FAKTUR + ITEM");
$ppn = config('pajak.ppn', 12) / 100;
$ppnVal = round($kontrak->nilai * $ppn);
$faktur = Faktur::create([
    'nomor_faktur' => Faktur::generateNomorFaktur('F'), 'tanggal_faktur' => now(),
    'kontrak_id' => $kontrak->id, 'klien_id' => $klien->id, 'nama_klien' => $klien->nama,
    'subtotal' => $kontrak->nilai, 'ppn' => $ppnVal, 'total_tagihan' => $kontrak->nilai + $ppnVal,
    'jatuh_tempo' => now()->addDays(30), 'status' => 'terbit', 'user_id' => $u($CFG['email_r01'])->id,
]);
$i = 1;
foreach ($kontrak->rab()->first()->komponen as $k) {
    FakturItem::create(['faktur_id' => $faktur->id, 'no_urut' => $i++, 'uraian' => $k->uraian_pekerjaan,
        'quantity' => $k->volume, 'harga_satuan' => $k->harga_satuan, 'total' => $k->jumlah_harga]);
}
echo "Faktur #{$faktur->id} {$faktur->nomor_faktur} — total Rp ".number_format($faktur->total_tagihan, 0, ',', '.')."\n";

$step("9. R05 — PEMBAYARAN 100% (auto lunas via Pembayaran::saved)");
Pembayaran::create([
    'faktur_id' => $faktur->id, 'nomor_pembayaran' => 'BYR/'.$CFG['nomor_prefix'],
    'jumlah' => $faktur->total_tagihan, 'tanggal_bayar' => now(), 'metode_bayar' => 'transfer',
    'referensi' => 'TRF-'.date('Ymd'), 'user_id' => $u($CFG['email_r05'])->id,
]);
$faktur->refresh();
echo "Faktur => {$faktur->status}\n";

$step("FINAL — KONTRAK COMPLETE + ASET");
$kontrak->hitungProgresOtomatis(); $kontrak->refresh();
if ($kontrak->isCompleted()) { $kontrak->complete(); $kontrak->buatAsetDariPekerjaan(); }
$kontrak->refresh();
echo "Kontrak => {$kontrak->status} | fisik {$kontrak->progres_fisik}% | keuangan {$kontrak->progres_keuangan}%\n";
foreach (Aset::where('kontrak_id', $kontrak->id)->get() as $a) {
    echo "Aset: {$a->kode_aset} — {$a->nama_aset} | {$a->kondisi} | {$a->status}\n";
}
echo "\nDONE.\n";
