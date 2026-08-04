<?php
// ─────────────────────────────────────────────────────────────────────────────
// VERIFY WorkflowIndicatorService stages sync with Kontrak::hitungProgresOtomatis
// Re-run after ANY change to WorkflowIndicatorService / stage logic / progres hooks.
//
// Usage: copy to project root as _verify_workflow_sync.php, then:
//        php _verify_workflow_sync.php   (project root = C:\laragon\www\PT.EXFERIA PUTRA INOVASI)
// Wraps everything in DB::transaction + rollback — zero DB pollution.
// Expected pass values (verified 2026-08-01):
//   1/8 approved → Stage Pekerjaan 'active',  workflow 43%
//   8/8 approved → Pekerjaan+Approval 'completed', workflow 71% (faktur belum ada)
//   + faktur lunas → Faktur+Pembayaran 'completed', workflow 100%, keuangan 100%
// ─────────────────────────────────────────────────────────────────────────────
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Kontrak;
use App\Models\Pekerjaan;
use App\Models\Rab;
use App\Models\Penawaran;
use App\Models\Faktur;
use App\Models\Klien;
use App\Services\WorkflowIndicatorService;

DB::beginTransaction();
$fail = 0;
function ck($label, $cond, $detail = '') {
    global $fail;
    echo ($cond ? '  ✅ ' : '  ❌ ').$label.($cond ? '' : '  ['.$detail.']')."\n";
    if (!$cond) $fail++;
}

$ts = substr(date('YmdHis'), 8);
$k = Kontrak::create(['nomor_kontrak' => 'SYNC-'.$ts, 'nama_kontrak' => 'Sync Test',
    'klien_id' => Klien::first()?->id ?? 1, 'nama_klien' => 'X', 'nilai' => 1000000,
    'tgl_mulai' => now(), 'tgl_akhir' => now()->addMonths(6), 'status' => 'active',
    'jenis' => 'perawatan', 'jumlah_mc' => 6, 'created_by' => 1]);
$rab = Rab::create(['nomor_rab' => 'R-SYNC-'.$ts, 'tanggal_pembuatan' => now(), 'nama_proyek' => 'Sync',
    'total_rab' => 1000000, 'is_active' => false, 'user_id' => 1]);
$rab->update(['kontrak_id' => $k->id]);
$pen = Penawaran::create(['nomor_penawaran' => 'P-SYNC-'.$ts, 'tanggal_penawaran' => now(),
    'nama_klien' => 'X', 'deskripsi_pekerjaan' => 'Perawatan berkala VMS', 'total_keseluruhan' => 1000000,
    'masa_berlaku' => 30, 'status' => 'disetujui', 'user_id' => 1]);
$pen->update(['kontrak_id' => $k->id]);

$ps = [];
for ($i = 0; $i < 8; $i++) {
    $ps[] = Pekerjaan::create(['kontrak_id' => $k->id, 'user_id' => 1, 'nama_pekerjaan' => 'P'.$i,
        'jenis_pekerjaan' => 'Perawatan berkala', 'aset' => 'VMS', 'lokasi_ruas' => 'X',
        'lokasi_km' => $i, 'status' => 'draft']);
}
$ps[0]->update(['status' => 'submitted']);
$ps[0]->update(['status' => 'approved']);

$wf1 = WorkflowIndicatorService::calculate($k);
$st1 = collect($wf1['stages']);
$pekerjaan1 = $st1->firstWhere('id', 'pekerjaan')['status'];
$approval1 = $st1->firstWhere('id', 'approval')['status'];
ck('1/8: Stage Pekerjaan active (bukan prematur completed)', $pekerjaan1 === 'active', $pekerjaan1);
ck('1/8: Stage Approval pending (sequential lock)', $approval1 === 'pending', $approval1);
ck('1/8: workflow 43%', (int) $wf1['progress'] === 43, (string) $wf1['progress']);

foreach ($ps as $p) { $p->update(['status' => 'submitted']); $p->update(['status' => 'approved']); }
$k->refresh();
$k->hitungProgresOtomatis();
$wf2 = WorkflowIndicatorService::calculate($k);
$st2 = collect($wf2['stages']);
ck('8/8: fisik 100%', (float) $k->progres_fisik === 100.0, (string) $k->progres_fisik);
ck('8/8: Stage Pekerjaan completed', $st2->firstWhere('id', 'pekerjaan')['status'] === 'completed', $st2->firstWhere('id', 'pekerjaan')['status']);
ck('8/8: Stage Approval completed', $st2->firstWhere('id', 'approval')['status'] === 'completed', $st2->firstWhere('id', 'approval')['status']);
ck('8/8 tanpa faktur: workflow 71% (keuangan 0)', (int) $wf2['progress'] === 71, (string) $wf2['progress']);

$sub = 1000000;
$ppn = round($sub * (float) config('pajak.tarif_ppn_keluaran', 11) / 100);
Faktur::create(['nomor_faktur' => 'F-SYNC-'.$ts, 'tanggal_faktur' => now(), 'kontrak_id' => $k->id,
    'klien_id' => $k->klien_id, 'nama_klien' => 'X', 'subtotal' => $sub, 'ppn' => $ppn,
    'total_tagihan' => $sub + $ppn, 'jatuh_tempo' => now()->addDays(14), 'status' => 'lunas', 'user_id' => 1]);
$k->refresh();
$k->hitungProgresOtomatis();
$wf3 = WorkflowIndicatorService::calculate($k);
$st3 = collect($wf3['stages']);
ck('lunas: keuangan 100%', (float) $k->progres_keuangan === 100.0, (string) $k->progres_keuangan);
ck('lunas: Stage Faktur completed', $st3->firstWhere('id', 'faktur')['status'] === 'completed', $st3->firstWhere('id', 'faktur')['status']);
ck('lunas: Stage Pembayaran completed', $st3->firstWhere('id', 'pembayaran')['status'] === 'completed', $st3->firstWhere('id', 'pembayaran')['status']);
ck('lunas: workflow 100%', (int) $wf3['progress'] === 100, (string) $wf3['progress']);

echo $fail === 0 ? "✅ WORKFLOW SYNC OK\n" : "❌ $fail FAIL\n";
DB::rollBack();
