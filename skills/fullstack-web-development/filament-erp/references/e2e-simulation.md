# E2E Project Simulation (standalone PHP script)

Proven 2026-07-31 — PJU Tangerang full lifecycle sim ran end-to-end:
Klien → RAB → Penawaran → Kontrak → BOM → Eksekusi → Approve → Faktur → Bayar 100% → Kontrak complete → Aset auto-create.

## Reusable script

`scripts/e2e-simulate-project.php` — edit `$CFG` (emails, klien, proyek, nilai, komponen), run:

```
php e2e-simulate-project.php
```

from project root `C:\laragon\www\PT.EXFERIA PUTRA INOVASI`.

## Bootstrap Laravel without HTTP

```php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// then use models/services directly
```

## Writing the script — file delivery pitfalls

- Heredoc `cat > f << 'EOF'` breaks in git-bash when content contains single quotes (unexpected EOF). Write the file via python (execute_code) then `cp` into `/c/laragon/www/...`.
- `write_file` tool does NOT reach the Laragon filesystem — files appear written but don't exist there (same rule as all project edits).
- When a step fails mid-script, rows from earlier steps persist (no transaction). Clean before re-run: delete kontrak/rab/penawaran/plan/pekerjaan rows manually in tinker, or wrap in DB::transaction.

## DB enum/type constraints that reject natural input (SQLSTATE 1265/1366)

| Column | Constraint | Insert value |
|--------|-----------|--------------|
| `kontrak.jenis` | enum('pengadaan_langsung','perawatan','lelang','swakelola','projek') | snake_case e.g. `pengadaan_langsung` — label "Pengadaan Langsung" gets truncated |
| `transaksi_keluar.tipe` | enum('keluar','retur') | `keluar` — `pekerjaan` fails |
| `pekerjaan.lokasi_km` | DECIMAL | number only (e.g. `12`) — "12-14" range string fails |

Check enum values anytime: `DB::selectOne("SHOW COLUMNS FROM <table> WHERE Field = ?", ["<col>"])->Type`.

## Model relation gotchas in scripts

- `Pekerjaan` has NO `transaksiKeluar()` relation → query `TransaksiKeluar::where('pekerjaan_id', $id)` directly.
- `$kontrak->rab` is a Collection → use `$kontrak->rab()->first()->komponen`.
- Sparepart name column is `nama_part`, NOT `nama` (grep filters on `nama` return SQLSTATE 42S22).
- RAB family: `Rab`, `RabKomponen`, `RabMaterialPlan` (BOM master), `RabMaterialPlanItem` (BOM items). Service: `RabMaterialPlanService::generateFromRab($rab)` then `activate($plan)` (Gudang role action). Jasa items auto-`skipped`; unmatched items `planned` with `sparepart_id=null` — filter `whereNotNull('sparepart_id')`.
- `Penawaran.kontrak_id` is filled AFTER kontrak exists (penawaran created pre-contract).

## Proven E2E order (business sequence)

1. R04: upsert sparepart master (nama_part must match RAB uraian for auto-mapping)
2. R01: Klien
3. R01: RAB + RabKomponen (total = sum of komponen)
4. R01: Penawaran (status disetujui)
5. R01: Kontrak → link `rab.kontrak_id` + `penawaran.kontrak_id`
6. R01 generate BOM → R04 activate
7. R02: Pekerjaan submitted + TransaksiKeluar per BOM item (auto stock decrement + BOM realisasi sync)
8. R03: approve (status approved + approved_by/approved_at)
9. R01: Faktur + FakturItem (PPN from `config('pajak.ppn', 12)`)
10. R05: Pembayaran → `Pembayaran::saved` event auto-sets faktur `lunas`
11. `$kontrak->hitungProgresOtomatis()` → if `isCompleted()`: `complete()` + `buatAsetDariPekerjaan()`

## Business rules discovered (process advice for user)

- Urutan bisnis: RAB dulu (hitung biaya) → Penawaran (harga jual = RAB + markup) → klien setuju → Kontrak. User's stated order (kontrak → RAB → penawaran) is backwards; system flows RAB→Penawaran→Kontrak with penawaran.kontrak_id backfilled.
- Tagihan oleh R01 (Admin Proyek) vs faktur oleh R05 (Keuangan): system today = R05 creates faktur. If R01 must bill, add permission `faktur.create` to R01 — no code change needed.
- Multi-titik project (e.g. 8 PJU points): create 1 pekerjaan PER titik. One pekerjaan covering all points → progres fisik jumps 100%, aset count wrong (buatAsetDariPekerjaan iterates pekerjaan, not titik).
- Retensi: kontrak has nilai_retensi/retensi_dibayarkan but faktur lunas → kontrak completed immediately. If retensi (5%) used, faktur 95% → lunas → retensi cair → baru completed. Undecided — ask user.
- Low stock after phase 1 (Timer 0 < safety 3, Panel/Kontaktor 2 < 3): reorder_point auto-set at BOM activation → Gudang must restock before next phase; hook into Permintaan Pembelian flow.
