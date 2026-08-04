# PJU Tol Tangerang — Verified E2E Simulation (2026-07-31)

Full flow executed and verified against live DB. Reuse as template for other projects.

## Data Set

**8 sparepart (Gudang R04 master):**
| nama_part | kategori | stok awal | safety | harga |
|---|---|---|---|---|
| Tiang PJU Octagonal 9m | tiang | 12 | 2 | 6.500.000 |
| Lampu LED PJU 120W | lampu | 25 | 5 | 1.850.000 |
| Kabel NYY 2x6mm | kabel | 400 | 100 | 28.000 |
| MCB 1P 10A | panel | 20 | 5 | 85.000 |
| Panel PJU 1 Fasa | panel | 10 | 3 | 750.000 |
| Kontaktor 25A | panel | 10 | 3 | 320.000 |
| Timer Astronomis | panel | 8 | 3 | 450.000 |
| Baut & Mur SS (set) | aksesoris | 50 | 10 | 25.000 |

SKU pattern: `PJU-001`... `updateOrCreate(['nama_part'=>...])` — idempotent re-run.

**RAB 10 komponen (R01):** 8 part rows (qty 8/300m/40) + 2 jasa rows:
- Pekerjaan Instalasi & Setting, 1 ls, 15.000.000
- Mobilisasi & Pengiriman, 1 ls, 5.000.000
Total Rp 109.040.000. Jasa rows auto-skipped in BOM (`tipe_item=jasa → status=skipped`), so mapped = 8/10.

**Per-titik usage (8 titik KM 12-19):** Tiang 1, Lampu 1, Kabel 37.5 (INT rounds → 38; total 304 vs RAB 300 — known drift), MCB 1, Panel 1, Kontaktor 1, Timer 1, Baut 5.

## Numbers That Must Match

- Kontrak nilai 109.040.000 = RAB total = Penawaran total (back-link all three via `kontrak_id` after kontrak create)
- Faktur: subtotal 109.040.000 + PPN 11% (`CompanySetting::get('ppn_rate') ?? config('pajak.tarif_ppn_keluaran', 11)`) = 11.994.400 → total 121.034.400
- Pembayaran → faktur auto-`lunas` (Pembayaran::saved hook) → kontrak auto-complete path: `hitungProgresOtomatis()` → `isCompleted()` → `complete()` → `buatAsetDariPekerjaan()`
- 8 pekerjaan approved → 8 aset AST-PJU-0001..0008, progres 12.5% → 100% per approve
- Stok akhir: Tiang 4, Lampu 17, Kabel 104, MCB 12, Panel 2, Kontaktor 2, Timer 0, Baut 10 → 4 items below safety (Panel/Kontaktor/Timer/Baut)

## Auto-PP (realisasi ≥80% — NOT activation, verified 2026-08-01)

`syncFromTransaksiKeluar()` → `tambahRealisasi()` → `checkAndCreatePermintaanPembelian()` fires when realisasi crosses 80% of quantity_rencana; dedup on existing "Auto-generated dari BOM:%" PP. At 100% realisasi sisa = ceil(0) = 0 → no PP (fulfilled, correct). PP/202607/0005-0008 for sp 5-8 came from this path during sim. Later `autoDetectLowStock()` returns 0 — expected.

## Cleanup Recipe (run between re-sims)

Order matters — soft-delete FK trap:
```php
Faktur::withTrashed()->where('kontrak_id',$id)->get() → DB::table('faktur_items')->where('faktur_id',$f->id)->delete(); $f->forceDelete();
Aset::withTrashed()->where('kontrak_id',$id)->get() → DB::table('aset_riwayat')->where('aset_id',$a->id)->delete(); $a->forceDelete();
TransaksiKeluar::where('pekerjaan_id',$pid)->delete();  // then manually restore sparepart stok
Pekerjaan::find($pid)?->forceDelete();
RabMaterialPlanItem::where('plan_id',$planId)->delete(); RabMaterialPlan::find($planId)?->delete();
$rab->komponen()->delete(); $rab->forceDelete();
Kontrak::withTrashed()->find($id)?->forceDelete();
Penawaran::withTrashed()->where('nomor_penawaran',...)->forceDelete();  // unique nomor blocks re-create
```

## User Preferences Observed

- Wants opinion + masukan after sim, not just results; expects flow-logic critique (order, segregation of duty, per-titik granularity)
- Accepts English code / Bahasa labels; numbers in `number_format($v,0,',','.')`
- Prefers 1 runnable script per phase split when script fails mid-way (step 6b resume pattern) over re-running from scratch
