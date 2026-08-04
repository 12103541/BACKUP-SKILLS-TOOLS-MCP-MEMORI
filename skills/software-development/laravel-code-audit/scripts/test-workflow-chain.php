<?php
/**
 * Workflow Chain Test — Klien → Kontrak → Termin → Pekerjaan → Faktur → Pembayaran → Aset
 *
 * Verifies all auto-cascade events at every step.
 * RUN from project root: php scripts/test-workflow-chain.php
 * Data is auto-cleaned after test.
 *
 * --with-jatuh-tempo  Also simulates overdue faktur + partial payment
 * --with-adendum      Also simulates contract revision (adendum + new termin)
 * --keep              Don't auto-cleanup test data
 */

$withJatuhTempo = in_array('--with-jatuh-tempo', $argv ?? []);
$withAdendum    = in_array('--with-adendum', $argv ?? []);
$keepData       = in_array('--keep', $argv ?? []);

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
if (!$user) die("ERROR: No user found. Run seeder first.\n");

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void {
    global $passed, $failed;
    if ($condition) { $passed++; echo "  ✓ {$label}\n"; }
    else            { $failed++; echo "  ✗ {$label}\n"; }
}

echo "\n=== WORKFLOW CHAIN TEST ===\n\n";

// ─── SETUP ───
$klien = \App\Models\Klien::create(['nama' => 'Test Klien Chain', 'alamat' => 'Test', 'telepon' => '021-000']);
$kontrak = \App\Models\Kontrak::create([
    'nomor_kontrak' => 'TEST/CHAIN/' . now()->format('Ymd'),
    'nama_kontrak' => 'Chain Test ' . now()->format('Y-m-d H:i'),
    'klien_id' => $klien->id, 'nama_klien' => $klien->nama,
    'nilai' => $withAdendum ? 100_000_000 : 100_000_000,
    'tgl_mulai' => now()->toDateString(), 'tgl_akhir' => now()->addMonths(6)->toDateString(),
    'status' => \App\Models\Kontrak::STATUS_ACTIVE, 'masa_garansi' => 12,
]);

$termin = \App\Models\KontrakTermin::create([
    'kontrak_id' => $kontrak->id, 'termin_ke' => 1, 'nama' => 'Termin 1',
    'persentase' => 100, 'nilai' => $kontrak->nilai,
    'tgl_jatuh_tempo' => $withJatuhTempo ? now()->subDays(5)->toDateString() : now()->addMonth()->toDateString(),
    'status' => 'belum_tertagih', 'user_id' => $user->id,
]);

echo "Setup: Kontrak #{$kontrak->id} ({$kontrak->nomor_kontrak}) nilai=" . number_format($kontrak->nilai, 0, ',', '.') . "\n\n";

// ─── STEP 1: PEKERJAAN → APPROVED ───
echo "--- STEP 1: PEKERJAAN APPROVED ---\n";
$pekerjaan = \App\Models\Pekerjaan::create([
    'kontrak_id' => $kontrak->id, 'user_id' => $user->id,
    'nama_pekerjaan' => 'Test Chain', 'jenis_pekerjaan' => 'Test',
    'aset' => 'VMS', 'lokasi_ruas' => 'Test', 'lokasi_km' => 1.0,
    'foto_paths' => [], 'dokumentasi_steps' => [],
    'status' => 'draft',
]);
$pekerjaan->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
$kontrak->refresh();
check('Fisik 100%', (float) $kontrak->progres_fisik === 100.0);

// ─── STEP 2: FAKTUR from TERMIN ───
echo "\n--- STEP 2: FAKTUR DARI TERMIN ---\n";
$tarifPpn = (float) config('pajak.tarif_ppn_keluaran', 12);
$ppn = round($termin->nilai * $tarifPpn / 100, 2);
$faktur = \App\Models\Faktur::create([
    'nomor_faktur' => 'FAK-CHAIN/' . str_pad(\App\Models\Faktur::max('id') + 1, 4, '0', STR_PAD_LEFT),
    'tanggal_faktur' => now()->toDateString(),
    'kontrak_id' => $kontrak->id, 'kontrak_termin_id' => $termin->id,
    'klien_id' => $klien->id, 'nama_klien' => $klien->nama,
    'subtotal' => $termin->nilai, 'ppn' => $ppn,
    'total_tagihan' => $termin->nilai + $ppn,
    'jatuh_tempo' => $termin->tgl_jatuh_tempo, 'status' => 'draft', 'user_id' => $user->id,
]);
check('PPN value correct', $faktur->ppn > 0);

// ─── STEP 3: TERBITKAN FAKTUR ───
echo "\n--- STEP 3: TERBITKAN FAKTUR ---\n";
$faktur->update(['status' => 'terbit']);
$faktur->refresh();
$termin->refresh();
check('Faktur status = terbit', $faktur->status === 'terbit');
check('Termin sync ke tertagih', $termin->status === 'tertagih');
$ppnPajak = \App\Models\Pajak::where('faktur_id', $faktur->id)->where('jenis', 'ppn_keluaran')->first();
check('PPN Keluaran tercatat', $ppnPajak !== null);
if ($ppnPajak) check('PPN nominal sesuai', (float) $ppnPajak->nominal_pajak === $ppn);

// ─── STEP 4: PEMBAYARAN (partial jika adendum) ───
echo "\n--- STEP 4: PEMBAYARAN ---\n";
$jumlahBayar = $withAdendum ? round($faktur->total_tagihan * 0.4) : $faktur->total_tagihan;
$bayar = \App\Models\Pembayaran::create([
    'faktur_id' => $faktur->id,
    'nomor_pembayaran' => 'BYR-CHAIN/001',
    'jumlah' => $jumlahBayar,
    'tanggal_bayar' => now()->toDateString(),
    'metode_bayar' => 'transfer',
    'user_id' => $user->id,
]);
$faktur->refresh();
$termin->refresh();
$kontrak->refresh();

if ($withAdendum) {
    check('Bayar 40% → faktur tetap terbit', $faktur->status === 'terbit');
    // Progres keuangan dihitung terhadap nilai_efektif, bukan nilai awal
    $expectedKeuangan = round(40_000_000 / $kontrak->nilai_efektif * 100, 2);
    check('Keuangan sesuai nilai_efektif (bukan nilai awal)', abs((float) $kontrak->progres_keuangan - $expectedKeuangan) < 0.1);
} else {
    check('Faktur lunas', $faktur->status === 'lunas');
    check('Termin lunas', $termin->status === 'lunas');
    check('Keuangan 100%', (float) $kontrak->progres_keuangan === 100.0);
}

// ─── ADENDUM + TERMIN BARU (if --with-adendum) ───
if ($withAdendum) {
    echo "\n--- ADENDUM: REVISI KONTRAK + TERMIN BARU ---\n";

    $maksAdendum = $kontrak->sisa_adendum_max;
    check('Sisa maks adendum > 0 (20% of nilai)', $maksAdendum > 0);

    $adendum = \App\Models\KontrakAdendum::create([
        'kontrak_id' => $kontrak->id, 'adendum_ke' => 1,
        'nilai' => 20_000_000,
        'keterangan' => 'Penambahan lingkup test',
        'tanggal' => now()->toDateString(),
    ]);
    check('Adendum created', $adendum->id > 0);
    check('Nilai efektif bertambah jadi Rp120jt', (float) $kontrak->fresh()->nilai_efektif === 120_000_000.0);

    $termin2 = \App\Models\KontrakTermin::create([
        'kontrak_id' => $kontrak->id, 'termin_ke' => 2,
        'nama' => 'Termin 2 (Adendum)', 'persentase' => round(20_000_000 / 120_000_000 * 100, 2),
        'nilai' => 20_000_000,
        'tgl_jatuh_tempo' => now()->addMonths(2)->toDateString(),
        'status' => 'belum_tertagih', 'user_id' => $user->id,
    ]);
    check('Termin baru (adendum) created', $termin2->id > 0);
    check('Total termin = nilai efektif (Rp120jt)', $kontrak->fresh()->termin()->sum('nilai') == 120_000_000);

    // Faktur + bayar termin adendum
    $ppn2 = round(20_000_000 * $tarifPpn / 100, 2);
    $faktur2 = \App\Models\Faktur::create([
        'nomor_faktur' => 'FAK-ADM/' . str_pad(\App\Models\Faktur::max('id') + 1, 4, '0', STR_PAD_LEFT),
        'tanggal_faktur' => now()->toDateString(),
        'kontrak_id' => $kontrak->id, 'kontrak_termin_id' => $termin2->id,
        'klien_id' => $klien->id, 'nama_klien' => $klien->nama,
        'subtotal' => 20_000_000, 'ppn' => $ppn2,
        'total_tagihan' => 20_000_000 + $ppn2,
        'jatuh_tempo' => now()->addMonths(2)->toDateString(),
        'status' => 'terbit', 'user_id' => $user->id,
    ]);
    check('Faktur adendum created', $faktur2->id > 0);

    $bayar2 = \App\Models\Pembayaran::create([
        'faktur_id' => $faktur2->id,
        'nomor_pembayaran' => 'BYR-ADM/001',
        'jumlah' => $faktur2->total_tagihan,
        'tanggal_bayar' => now()->toDateString(),
        'metode_bayar' => 'transfer',
        'user_id' => $user->id,
    ]);
    $faktur2->refresh();
    $termin2->refresh();
    $kontrak->refresh();
    check('Faktur adendum lunas', $faktur2->status === 'lunas');
    check('Termin adendum lunas', $termin2->status === 'lunas');

    // Cek progres keuangan: Rp40jt + Rp20jt dari Rp120jt = 50%, bukan 60%
    $expectedKeu = round(60_000_000 / 120_000_000 * 100, 2);
    check("Keuangan {$kontrak->progres_keuangan}% (expected ~{$expectedKeu}% — thd nilai_efektif)", abs((float) $kontrak->progres_keuangan - $expectedKeu) < 0.2);

    // Sisa bayar termin 1 (Rp60jt)
    $sisa1 = $faktur->total_tagihan - $jumlahBayar;
    $bayar1Lunas = \App\Models\Pembayaran::create([
        'faktur_id' => $faktur->id,
        'nomor_pembayaran' => 'BYR-CHAIN/002',
        'jumlah' => $sisa1,
        'tanggal_bayar' => now()->toDateString(),
        'metode_bayar' => 'transfer',
        'user_id' => $user->id,
    ]);
    $faktur->refresh();
    $termin->refresh();
    $kontrak->refresh();
    check('Faktur 1 lunas (setelah adendum)', $faktur->status === 'lunas');
    check('Termin 1 lunas', $termin->status === 'lunas');
    check('Keuangan 100% (Rp120jt/Rp120jt — nilai efektif)', (float) $kontrak->progres_keuangan >= 100.0);

    // Cleanup adendum data
    if (!$keepData) {
        \App\Models\Pembayaran::whereIn('faktur_id', [$faktur2->id])->forceDelete();
        \App\Models\Pajak::where('faktur_id', $faktur2->id)->delete();
        $faktur2->forceDelete();
        \App\Models\KontrakTermin::where('id', $termin2->id)->forceDelete();
        \App\Models\KontrakAdendum::where('kontrak_id', $kontrak->id)->delete();
    }
}

// ─── STEP 5: KONTRAK COMPLETE + ASET ───
echo "\n--- STEP 5: KONTRAK SELESAI + ASET ---\n";
check('Kontrak isCompleted', $kontrak->fresh()->isCompleted());
$kontrak->refresh();
check('Status = completed', $kontrak->status === \App\Models\Kontrak::STATUS_COMPLETED);
$asets = \App\Models\Aset::where('kontrak_id', $kontrak->id)->get();
check('Aset auto-created', $asets->count() >= 1);
if ($asets->count()) {
    $a = $asets->first();
    check('Aset status = aktif', $a->status === 'aktif');
    check('Aset kondisi = baik', $a->kondisi === 'baik');
    check('Aset punya riwayat', $a->riwayat()->count() >= 1);
    check('Aset punya garansi', $a->tanggal_garansi_berakhir !== null);
}

// ─── JATUH TEMPO + PARTIAL (if --with-jatuh-tempo) ───
if ($withJatuhTempo) {
    echo "\n--- STEP 6 (extra): JATUH TEMPO + PARTIAL BAYAR ---\n";

    $klien2 = \App\Models\Klien::create(['nama' => 'Test Overdue', 'alamat' => 'JT', 'telepon' => '021-001']);
    $kontrak2 = \App\Models\Kontrak::create([
        'nomor_kontrak' => 'TEST/OVERDUE/' . now()->format('Ymd'),
        'nama_kontrak' => 'Overdue Test',
        'klien_id' => $klien2->id, 'nama_klien' => $klien2->nama,
        'nilai' => 50_000_000,
        'tgl_mulai' => now()->subMonth()->toDateString(), 'tgl_akhir' => now()->addMonths(5)->toDateString(),
        'status' => \App\Models\Kontrak::STATUS_ACTIVE, 'masa_garansi' => 6,
    ]);
    $termin2 = \App\Models\KontrakTermin::create([
        'kontrak_id' => $kontrak2->id, 'termin_ke' => 1, 'nama' => 'Termin Overdue',
        'persentase' => 100, 'nilai' => $kontrak2->nilai,
        'tgl_jatuh_tempo' => now()->subDays(5)->toDateString(),
        'status' => 'belum_tertagih', 'user_id' => $user->id,
    ]);
    $faktur2 = \App\Models\Faktur::create([
        'nomor_faktur' => 'FAK-OVERDUE/' . str_pad(\App\Models\Faktur::max('id') + 1, 4, '0', STR_PAD_LEFT),
        'tanggal_faktur' => now()->subDays(5)->toDateString(),
        'kontrak_id' => $kontrak2->id, 'kontrak_termin_id' => $termin2->id,
        'klien_id' => $klien2->id, 'nama_klien' => $klien2->nama,
        'subtotal' => $termin2->nilai, 'ppn' => round($termin2->nilai * $tarifPpn / 100, 2),
        'total_tagihan' => $termin2->nilai + round($termin2->nilai * $tarifPpn / 100, 2),
        'jatuh_tempo' => now()->subDays(5)->toDateString(), 'status' => 'terbit', 'user_id' => $user->id,
    ]);

    $faktur2->update(['status' => 'jatuh_tempo']);
    $faktur2->refresh();
    $termin2->refresh();
    check('Faktur jadi jatuh_tempo', $faktur2->status === 'jatuh_tempo');
    check('Termin jadi terlambat', $termin2->status === 'terlambat');

    $bayarSetengah = \App\Models\Pembayaran::create([
        'faktur_id' => $faktur2->id,
        'nomor_pembayaran' => 'BYR-PARTIAL/001',
        'jumlah' => round($faktur2->total_tagihan / 2),
        'tanggal_bayar' => now()->toDateString(),
        'metode_bayar' => 'transfer',
        'user_id' => $user->id,
    ]);
    $faktur2->refresh();
    $totalBayar = \App\Models\Pembayaran::totalPerFaktur($faktur2->id);
    check('Bayar setengah → faktur tetap JT (total < tagihan)', $faktur2->status === 'jatuh_tempo');
    check('Total bayar < tagihan', $totalBayar < $faktur2->total_tagihan);

    $sisa = $faktur2->total_tagihan - $totalBayar;
    $bayarLunas = \App\Models\Pembayaran::create([
        'faktur_id' => $faktur2->id,
        'nomor_pembayaran' => 'BYR-PARTIAL/002',
        'jumlah' => $sisa,
        'tanggal_bayar' => now()->toDateString(),
        'metode_bayar' => 'transfer',
        'user_id' => $user->id,
    ]);
    $faktur2->refresh();
    $termin2->refresh();
    check('Bayar sisa → faktur lunas', $faktur2->status === 'lunas');
    check('Termin jadi lunas', $termin2->status === 'lunas');

    if (!$keepData) {
        \App\Models\Pembayaran::whereIn('faktur_id', [$faktur2->id])->forceDelete();
        \App\Models\Pajak::where('faktur_id', $faktur2->id)->delete();
        $faktur2->forceDelete();
        \App\Models\KontrakTermin::where('kontrak_id', $kontrak2->id)->forceDelete();
        $kontrak2->forceDelete();
        \App\Models\Klien::where('id', $klien2->id)->forceDelete();
    }
}

// ─── RESULTS ───
echo "\n=== RESULTS ===\n";
echo "  Passed: {$passed}\n";
echo "  Failed: {$failed}\n";
echo "  " . ($failed === 0 ? "ALL PASSED" : "SOME FAILED") . "\n";

// ─── CLEANUP ───
if (!$keepData) {
    echo "\nCleanup...\n";
    if ($withAdendum) {
        \App\Models\Pembayaran::where('nomor_pembayaran', 'BYR-CHAIN/002')->forceDelete();
    }
    \App\Models\Pembayaran::where('faktur_id', $faktur->id)->forceDelete();
    \App\Models\Pajak::where('faktur_id', $faktur->id)->delete();
    $faktur->forceDelete();
    $asets = \App\Models\Aset::where('kontrak_id', $kontrak->id)->get();
    foreach ($asets as $a) {
        \App\Models\AsetRiwayat::where('aset_id', $a->id)->delete();
        $a->forceDelete();
    }
    \App\Models\Pekerjaan::where('kontrak_id', $kontrak->id)->forceDelete();
    \App\Models\KontrakTermin::where('kontrak_id', $kontrak->id)->forceDelete();
    \App\Models\KontrakAdendum::where('kontrak_id', $kontrak->id)->delete();
    $kontrak->forceDelete();
    \App\Models\Klien::where('id', $klien->id)->forceDelete();
    echo "  ✓ Data cleaned\n";
}

echo "\n";
exit($failed);
