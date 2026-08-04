# Perawatan (MC Billing) Contract — Verified E2E Sim (2026-08-01)

Full flow for a PERAWATAN contract (monthly MC billing) — differs from pemasangan (single-faktur). Verified live against DB with PT TRANS MARGA JATENG VMS kontrak KTR-VMS-0801120130, Rp 267.500.000, completed 100%.

## Key difference vs PJU sim: MC Termin billing chain

Perawatan kontrak uses MC (monthly) termin instead of one big faktur:

```
kontrak create (jenis='perawatan')
  → hook Kontrak::boot()::saved auto-generates 12 MC termin (BillingPerawatanService::generateMcTermin)
  → per termin: BillingPerawatanService::generateFakturForDueMc(termin) → Faktur (draft, kontrak_termin_id set)
  → Faktur status 'terbit'
  → Pembayaran::create(jumlah = total_tagihan) → hook Pembayaran::saved → faktur 'lunas'
  → Faktur::boot()::updated → termin status sync + hitungProgresOtomatis()
  → progres_keuangan = sum(faktur lunas total_tagihan) / nilai_efektif, min(100)
  → fisik 100% + keuangan 100% → isCompleted() → complete() → buatAsetDariPekerjaan()
```

## Verified numbers (VMS sim)

- 8 sparepart VMS master: Modul LED, Kontroler, Modem 4G, PSU 12V, Kabel UTP Cat6, Kipas, UPS 1500VA, Sensor — SKU VMS-001..008
- RAB 10 komponen (8 material volume 10 = buffer 2 + 2 jasa ls) → total 267.500.000
- Kontrak: `jumlah_mc` = 12, `masa_garansi` = 12 → hook auto-generate 12 termin (generateMcTermin returns 0 on second call = dedup, NOT error)
- 8 pekerjaan per titik (KM 390-397), 64 TransaksiKeluar (8 part × 8 titik) → auto-sync BOM realisasi 8/10 = 80% → 7 auto-PP (sisa 2/part); Kabel UTP 120/300 = 40% → no PP
- 12 faktur MC via generateFakturForDueMc, PPN `config('pajak.tarif_ppn_keluaran', 11)%` → 12 pembayaran → 12 termin lunas → keuangan 100%
- Auto-complete → 8 aset VMS, PPN Keluaran total Rp 29.424.996 (`pajak.nominal_pajak`)

## Schema gotchas (this flow)

- `pajak` columns: `dpp`, `tarif_pajak`, `nominal_pajak`, `faktur_id` — NOT `jumlah`. PPN Keluaran auto-created by Faktur hook when status → terbit/lunas with ppn > 0.
- `Kontrak::nilai_efektif` = `nilai + adendum()->sum('nilai')` (accessor, no column).
- `KontrakTermin.status` values: `belum_tertagih|tertagih|belum_lunas|lunas` (sync'd from faktur via statusMap in Faktur::boot).
- `generateFakturForDueMc` skips termin status != belum_tertagih/tertagih and returns null if faktur already exists for termin (dedup).
- Workflow badge shows "Lunas 100% Rp 296.924.996 dari 267.500.000" (nilai + PPN) — confusing but `min(100)` keeps keuangan at 100. Not a bug.

## Committed-sim cleanup (perawatan, FK order)

```php
// per faktur: Pajak::where('faktur_id',$f->id)->delete(); DB::table('faktur_items')->where('faktur_id',$f->id)->delete(); $f->forceDelete();
// KontrakTermin: $kontrak->termin()->delete();
// Aset: DB::table('aset_riwayat')->where('aset_id',$a->id)->delete(); $a->forceDelete();
// TransaksiKeluar: DB::table('transaksi_keluar')->where('pekerjaan_id',$p->id)->delete(); (restore stok manual)
// Pekerjaan forceDelete → RabMaterialPlanItem::where('plan_id',$plan->id)->delete() → plan delete → RabKomponen::where('rab_id')->delete() → Rab forceDelete
// Penawaran forceDelete → Kontrak forceDelete → sparepart VMS delete → klien TMJ delete
```

## Script pattern for committed sims

Same self-contained pattern as skill main (require vendor/autoload + bootstrap), but NO DB::beginTransaction — commit so user sees data in UI. Use `updateOrCreate` for master data (klien by nama, sparepart by nama_part) so re-run is idempotent. Generate `$ts = substr(date('YmdHis'), 4)` for 8-digit nomor suffixes (penawaran.nomor_penawaran is varchar(20) — full 14-digit timestamp overflows).
