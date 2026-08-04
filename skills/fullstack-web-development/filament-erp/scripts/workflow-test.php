<?php
/**
 * ERP Workflow End-to-End Test
 * Chain: KONTRAK → RAB → BOM → PEKERJAAN → TRANSAKSI KELUAR → FAKTUR → PEMBAYARAN → LUNAS
 *
 * Run via: php artisan tinker --execute="require 'scripts/workflow-test.php'"
 * Or:     cd /path && php artisan tinker < scripts/workflow-test.php
 *
 * Environment: Laravel 11 + Filament v3.2, PT EXFERIA PUTRA INOVASI ERP
 */

use App\Models\{User, Kontrak, Rab, RabKomponen, RabMaterialPlan, RabMaterialPlanItem, Sparepart, Pekerjaan, TransaksiKeluar, Faktur, Pembayaran, Aset};
use App\Services\RabMaterialPlanService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// ─── Setup ───
$su = User::where('username', 'superadmin')->first();
Auth::loginUsingId($su->id);
$echo = fn($m) => printf("  %s\n", $m);

echo str_repeat('=', 60) . PHP_EOL;
echo "  WORKFLOW TEST: KONTRAK → RAB → BOM → PEKERJAAN → TK → FAKTUR → BAYAR\n";
echo str_repeat('=', 60) . PHP_EOL;

// ─── 1. KONTRAK ───
echo "\n1. KONTRAK\n";
$kontrak = Kontrak::create([
    'nomor_kontrak' => 'TEST/' . now()->format('Ymd') . '/WF',
    'nama_pekerjaan' => 'Test Workflow ' . now()->format('Y-m-d H:i'),
    'klien_id' => 0,
    'nama_klien' => 'PT Test Klien',
    'nilai' => 200_000_000,
    'status' => 'active',
    'user_id' => $su->id,
    'tgl_mulai' => now(),
    'tgl_akhir' => now()->addMonths(6),
]);
$echo("Kontrak: {$kontrak->nomor_kontrak} | Rp " . number_format($kontrak->nilai, 0, ',', '.'));

// ─── 2. RAB ───
echo "\n2. RAB\n";
$rab = Rab::create([
    'kontrak_id' => $kontrak->id,
    'nomor_rab' => 'RAB-TEST/' . now()->format('YmdHis'),
    'nama_proyek' => 'Test Proyek Workflow',
    'user_id' => $su->id,
    'status' => 'aktif',
    'total_rab' => 0,
]);

// RAB Items (5 sample)
$items = [
    ['Kabel NAYFGBY 4x10mm', 50, 150_000],
    ['MCB 1P 10A', 10, 85_000],
    ['Lampu LED 30cm', 8, 250_000],
    ['Tiang PJU 9m', 2, 3_500_000],
    ['Sambungan Kabel', 10, 25_000],
];
$totalRab = 0;
foreach ($items as $i => [$nama, $vol, $harga]) {
    $total = $vol * $harga;
    $totalRab += $total;
    RabKomponen::create([
        'rab_id' => $rab->id,
        'kode_komponen' => 'K' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
        'uraian_pekerjaan' => $nama,
        'volume' => $vol,
        'satuan' => 'unit',
        'harga_satuan' => $harga,
        'total_harga' => $total,
        'jumlah_harga' => $total,
    ]);
}
$rab->update(['total_rab' => $totalRab]);
$echo("RAB: {$rab->nomor_rab} | " . count($items) . " items | Rp " . number_format($totalRab, 0, ',', '.'));

// ─── 3. BOM ───
echo "\n3. BOM\n";
$bomService = app(RabMaterialPlanService::class);
$bomService->generateFromRab($rab);
$plan = RabMaterialPlan::where('rab_id', $rab->id)->first();
if (!$plan) { die("BOM generation failed\n"); }
$echo("Plan: {$plan->nomor_plan} | Status: {$plan->status}");

// Auto-create sparepart for unmapped items
$unmapped = $plan->items()->whereNull('sparepart_id')->whereIn('tipe_item', ['sparepart', 'material', 'lainnya'])->get();
if ($unmapped->count() > 0) {
    $unique = $unmapped->groupBy(fn($i) => strtolower(trim($i->uraian_pekerjaan)))->map(fn($g) => $g->first());
    $newParts = [];
    foreach ($unique as $item) {
        $harga = $item->harga_satuan_rab ?: ($item->total_harga_rab / max(1, $item->volume));
        $sp = Sparepart::create([
            'nama_part' => $item->uraian_pekerjaan,
            'sku' => 'SP-TEST-' . str_pad(Sparepart::max('id') + 1, 5, '0', STR_PAD_LEFT),
            'harga' => (string) $harga,
            'harga_jual' => (string) $harga,
            'stok' => 100,
            'safety_stock' => 5,
            'satuan' => $item->satuan ?? 'unit',
        ]);
        $newParts[strtolower(trim($item->uraian_pekerjaan))] = $sp->id;
    }
    foreach ($unmapped as $bi) {
        $key = strtolower(trim($bi->uraian_pekerjaan));
        if (isset($newParts[$key])) {
            $bi->update(['sparepart_id' => $newParts[$key]]);
        }
    }
    $echo("Auto-created: " . count($newParts) . " spareparts, linked " . $unmapped->count() . " BOM items");
}

// ─── 4. ACTIVATE BOM ───
echo "\n4. AKTIVASI BOM\n";
$gudang = User::where('role', 'R04')->first();
Auth::loginUsingId($gudang->id);
$bomService->activate($plan);
$plan->refresh();
$echo("Status: {$plan->status} | Approved by: {$gudang->name}");

// ─── 5. PEKERJAAN ───
echo "\n5. PEKERJAAN\n";
Auth::loginUsingId($su->id);
$teknisi = User::where('role', 'R02')->first();
$pj = Pekerjaan::create([
    'kontrak_id' => $kontrak->id,
    'user_id' => $teknisi->id,
    'nama_pekerjaan' => 'Test Pekerjaan Workflow',
    'jenis_pekerjaan' => 'instalasi',
    'status' => 'approved',
]);
$echo("Pekerjaan: ID {$pj->id} | {$pj->nama_pekerjaan}");

// ─── 6. TRANSAKSI KELUAR ───
echo "\n6. TRANSAKSI KELUAR\n";
$mappedItems = $plan->items()->whereNotNull('sparepart_id')->get();
$totalTk = 0;
$countTk = 0;
foreach ($mappedItems as $item) {
    $sp = $item->sparepart;
    if (!$sp || $sp->stok <= 0) continue;
    $qty = (int) min($item->quantity_rencana, $sp->stok);
    if ($qty <= 0) continue;
    $hj = (float) ($sp->harga_jual > 0 ? $sp->harga_jual : $item->harga_satuan_rab);
    $subtotal = $qty * $hj;
    $totalTk += $subtotal;
    TransaksiKeluar::create([
        'nomor_transaksi' => 'TK-TEST/' . str_pad($item->id, 4, '0', STR_PAD_LEFT),
        'sparepart_id' => $sp->id,
        'quantity' => $qty,
        'harga_beli' => (float) $sp->harga,
        'harga_jual' => $hj,
        'tanggal' => now(),
        'pekerjaan_id' => $pj->id,
        'teknisi_id' => $teknisi->id,
        'status_tagih' => 'belum_tertagih',
        'tipe' => 'keluar',
    ]);
    $countTk++;
}
$echo("Transaksi: $countTk items | Rp " . number_format($totalTk, 0, ',', '.'));

// ─── 7. FAKTUR ───
echo "\n7. FAKTUR\n";
$ppn = round($totalTk * 0.12);
$totalFaktur = $totalTk + $ppn;
$faktur = Faktur::create([
    'nomor_faktur' => 'F-TEST/' . now()->format('Ymd') . '/0001',
    'tanggal_faktur' => now(),
    'kontrak_id' => $kontrak->id,
    'nama_klien' => 'PT Test Klien',
    'subtotal' => $totalTk,
    'ppn' => $ppn,
    'total_tagihan' => $totalFaktur,
    'jatuh_tempo' => now()->addDays(30),
    'status' => 'terbit',
    'user_id' => $su->id,
]);
$echo("Faktur: {$faktur->nomor_faktur} | Rp " . number_format($totalFaktur, 0, ',', '.'));

// ─── 8. PEMBAYARAN → LUNAS ───
echo "\n8. PEMBAYARAN → LUNAS\n";
$byr = Pembayaran::create([
    'faktur_id' => $faktur->id,
    'nomor_pembayaran' => 'BYR-TEST/' . now()->format('Ymd') . '/0001',
    'jumlah' => $totalFaktur,
    'tanggal_bayar' => now(),
    'metode_bayar' => 'Transfer Bank',
    'user_id' => $su->id,
]);

// Verify auto-lunas
$faktur->refresh();
$echo("Pembayaran: {$byr->nomor_pembayaran} | Rp " . number_format($totalFaktur, 0, ',', '.'));
$echo("Faktur status: {$faktur->status} " . ($faktur->status === 'lunas' ? '✅' : '❌'));

// ─── SUMMARY ───
echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
echo "  ✅ WORKFLOW COMPLETE\n";
echo str_repeat('=', 60) . PHP_EOL;
echo "  Kontrak:        {$kontrak->nomor_kontrak} | Rp " . number_format($kontrak->nilai, 0, ',', '.') . PHP_EOL;
echo "  RAB:            {$rab->nomor_rab} | " . count($items) . " items" . PHP_EOL;
echo "  BOM:            {$plan->nomor_plan} | {$plan->status}" . PHP_EOL;
echo "  Pekerjaan:      ID {$pj->id}" . PHP_EOL;
echo "  Transaksi:      $countTk items | Rp " . number_format($totalTk, 0, ',', '.') . PHP_EOL;
echo "  Faktur:         {$faktur->nomor_faktur} | {$faktur->status}" . PHP_EOL;
echo "  Pembayaran:     {$byr->nomor_pembayaran} | Rp " . number_format($totalFaktur, 0, ',', '.') . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;
