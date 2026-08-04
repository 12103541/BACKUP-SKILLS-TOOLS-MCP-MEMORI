---
name: erp-e2e-simulation
description: Use when simulating full ERP workflows end-to-end.
tags: [erp, laravel, filament, simulation, e2e, workflow, seeding]
---

# ERP End-to-End Simulation

## Project Path
`C:\laragon\www\PT.EXFERIA PUTRA INOVASI\` (Apache live copy — scripts must run from here)

## Script Pattern (self-contained, no artisan tinker)

```php
<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User,Kontrak,...};
$r01 = User::where('email','admin.proyek@example.com')->first();
// ... create records, print step-by-step progress
echo "\nDONE.\n";
```

Run: `php _script.php` from project root. Delete after. NEVER migrate:fresh — cleanup = delete created rows (see cleanup pattern below).

**Pitfall**: `php artisan tinker --execute='include "scripts/foo.php"'` FAILS on this host (path resolves to `scriptseval()'d code`). Always use the standalone bootstrap pattern above. For `erp-rules/scripts/test-all-roles.php` (user-owned), prepend the bootstrap lines, save as `_test_roles.php`, run `php _test_roles.php`.

**More tinker/script pitfalls (verified 2026-08-01)**:
- `tinker --execute` with any `$var` (e.g. `$ts = substr(...)`) FAILS via bash — bash expands `$ts` before PHP sees it → `ParseError: unexpected ','`. NEVER inline tinker for scripts containing PHP variables; write a file.
- `round()` returns FLOAT — strict `=== 100` / `=== 71` assertions fail silently. Cast `(int)` / `(float)` before comparing.
- `Penawaran::create` REQUIRES `deskripsi_pekerjaan` + `masa_berlaku` (no DB default → SQLSTATE 1364).

**WRITING the script**: write_file tool WORKS directly on the Laragon path (verified 2026-08-01: `_sim_vms.php` written straight to `C:\laragon\www\PT.EXFERIA PUTRA INOVASI\`). bash heredoc (`cat > x << 'EOF'`) can still fail for large PHP (unexpected EOF quoting bug) — fallback if needed: execute_code Python `open(...,"w")`, then `cp` into project root.

## SIMULATION MUST COVER MIDDLE STAGES (LESSON 2026-08-01)

User caught: sims always did early (klien→RAB→kontrak→BOM) + late (pekerjaan→faktur→bayar→aset) but SKIPPED middle → `pengeluaran` table stayed 0 records → Operasional Proyek page empty, contract invisible there, Health "Realisasi" Rp0, margin fake 100%. TransaksiKeluar (sparepart usage) WAS done (64 rec for PJU-TNG) — the skipped piece was Pengeluaran operasional (non-material cost).

**Rule: every committed sim MUST include Pengeluaran operasional** — ~12% nilai kontrak, 7 records (upah_borongan 35%, sewa_alat 20%, transport 15%, upah_harian 15%, bahan_habis 8%, makan 5%, akomodasi 2%), dates spread across tgl_mulai..tgl_akhir, `user_id` = kontrak.created_by. Kategori ONLY from `Pengeluaran::KATEGORI` (upah_borongan, upah_harian, sewa_alat, bahan_habis, perawatan, transport, makan, akomodasi, lainnya) — inline lists elsewhere (KontrakResource relation manager) are stale.

Backfill script pattern (`_fill_pengeluaran.php`, committed data): loop `Kontrak::all()`, skip if `pengeluaran()->count() > 0`, else 12% × nilai split per dist above, `->min($akhir)` on date. Verified: 28 records / 4 kontrak, page shows all 4 with status badges.

## Correct Business Order (verified with user, 2026-07-31)

```
Klien → RAB (biaya) → Penawaran (harga jual = RAB + markup) → Kontrak (dari penawaran disetujui)
      → BOM (R01 generate, R04 activate) → Eksekusi (R02) → Approve (R03) → Tagihan → Bayar (R05)
```

User's original rule said "kontrak→RAB→penawaran" — that is WRONG; penawaran must precede kontrak (realia: RAB dulu untuk hitung harga jual). RAB & Penawaran get `kontrak_id` back-filled after kontrak creation.

## Per-Titik Rule (multi-point projects)

1 pekerjaan = 1 titik = 1 aset. Progres fisik = approved_pekerjaan/total × 100 (12.5% per titik for 8 points). One pekerjaan covering all points → progres jumps to 100% and assets are wrong. Create one Pekerjaan per KM/titik.

## Role Flow (users exist in seeder)

| Step | Role | Email |
|---|---|---|
| Klien/RAB/Penawaran/Kontrak | R01 Admin Proyek | admin.proyek@example.com |
| Eksekusi + TransaksiKeluar | R02 Teknisi | teknisi1@example.com |
| Approve | R03 Supervisor | supervisor@example.com |
| BOM aktivasi / stok | R04 Gudang | gudang@example.com |
| Faktur + Pembayaran | R05 Keuangan | keuangan@example.com |

Faktur dibuat oleh R05 (segregation of duty) — R01 hanya siapkan data. Jangan pindahkan ke R01 tanpa alasan.

## ENUM Gotchas (script must use exact DB values, not labels)

- `kontrak.jenis`: `pengadaan_langsung`|`perawatan`|`lelang`|`swakelola`|`projek` — "Pengadaan Langsung" → SQLSTATE 1265 Data truncated
- `kontrak.status`: `active`|`completed`|`terminated` — BUKAN 'aktif'
- `faktur.status`: `draft`|`terbit`|`jatuh_tempo`|`lunas` — BUKAN 'belum_lunas'; field `jatuh_tempo` WAJIB saat create
- `pekerjaan.status`: varchar — `draft|submitted|approved|rejected|direvisi`; `approved_by`/`approved_at` auto-set oleh hook model saat status→approved (jangan set manual)
- `pengajuan_sparepart.status`: `draft`|`menunggu_approval`|`disetujui`|`ditolak`|`diproses`|`selesai`|`dibatalkan`
- `permintaan_pembelian.status`: `draft`|`diajukan`|`disetujui`|`ditolak`|`dipesan`|`diterima`|`selesai`
- `rab_material_plans.status`: varchar `draft|active`; `tipe_item`: `sparepart`|`jasa`|`lainnya` (BUKAN 'material')
- `transaksi_keluar.tipe`: `keluar`|`retur` — 'pekerjaan' → 1265
- `transaksi_keluar.quantity` is INT — 37.5m kabel rounds to 38; total drifts from RAB
- `pekerjaan.lokasi_km` DECIMAL — no "12-14" ranges
- `nomor_penawaran` varchar(20) — nomor pendek wajib (PEN+timestamp 14 digit = 21 > 20)
- Check: `DB::selectOne("SHOW COLUMNS FROM <t> WHERE Field=?", ["col"])->Type` before bulk insert

## Auto-PP Behavior (don't double-create) — UPDATED 2026-08-01

Auto-PP TIDAK dibuat saat aktivasi BOM. Trigger = `RabMaterialPlanItem::checkAndCreatePermintaanPembelian()` saat realisasi item ≥80% via `syncFromTransaksiKeluar($sparepartId, $pekerjaanId, $qty, 'add')` — hanya jika sisa material > 0 (realisasi 100% → skip, sudah penuh) dan belum ada PP draft/diajukan "Auto-generated dari BOM" utk sparepart itu (dedup).
Chain lookup: pekerjaan→kontrak→RAB→BOM active→item (status planned/partial). `findPlanItemBySparepart` returns null jika chain putus — sync balik false, tidak error.

## PengajuanSparepart — WAJIB lewat service (FIXED 2026-08-01)

Semua transisi status WAJIB lewat `PengajuanSparepartService` (buatPengajuan/ajukan/approve/tolak/proses/selesaikan/batalkan) — validasi stok + items + log + notifikasi di dalamnya. Create manual + update status langsung = gagal validasi.
BUG ditemukan 2026-08-01 & diperbaiki: `ValidatesProgres` rule `items required|array|min:1` membaca `$this->items` = Collection relasi (bukan array) → semua transisi status (ajukan/approve/proses/selesaikan) gagal "items must be an array". Fix: closure rule `fn() => $this->items()->count() < 1`. UI juga tidak punya tombol "Ajukan" → pengajuan dead-end di draft; action `ajukan` ditambahkan di `PengajuanSparepartResource` table (visible: draft + bisaDiajukan).

## Kontrak::complete() Idempotency (FIXED 2026-07-31)

Was NOT idempotent: calling twice duplicated assets (1 pekerjaan → 4 aset). Fixed:
- early `return` if status already `completed`
- `buatAsetDariPekerjaan()` skips when `Aset` with same `kontrak_id`+`nama_aset` exists

Verify after any completion-path change: call complete() twice, assert asset count unchanged.

## Auto-PP Behavior (VERIFIED 2026-08-01 — fires at realisasi ≥80%, NOT at BOM activation)

`RabMaterialPlanService::syncFromTransaksiKeluar($sparepartId, $pekerjaanId, $qty, 'add', $hargaBeli)` → `RabMaterialPlanItem::tambahRealisasi()` → `checkAndCreatePermintaanPembelian()` creates draft PP when realisasi crosses 80% of quantity_rencana. Dedup: skip if draft/diajukan PP with keterangan "Auto-generated dari BOM:%" already exists for that sparepart. At 100% realisasi → sisa = ceil(0) = 0 → NO PP (fulfilled, correct). BOM `activate()` itself creates NO PP. To prove the trigger in a test: link a FRESH sparepart to a plan item (status planned, rencana 100), then sync 85% of rencana → PP appears.

## Verified Schema / Enum Values (2026-08-01)

Before bulk inserts, run `DB::selectOne("SHOW COLUMNS FROM <t> WHERE Field=?", [$col])->Type` — model fillable names ≠ DB reality in places.

- `kontrak.status` enum: `active|completed|terminated` (NOT 'aktif'); `jenis`: `pengadaan_langsung|perawatan|lelang|swakelola|projek`; columns `nama_kontrak`/`nilai`/`tgl_mulai`/`tgl_akhir`/`created_by`
- `faktur.status` enum: `draft|terbit|jatuh_tempo|lunas` (NOT 'belum_lunas'); `jatuh_tempo` REQUIRED; columns `tanggal_faktur`/`total_tagihan`/`ppn`
- `pekerjaan.status` varchar; approved value = `approved` (model hook auto-fills `approved_by`/`approved_at` + calls `kontrak->hitungProgresOtomatis()`); column `user_id` (not teknisi_id)
- `rab_material_plans.status` varchar: `active`; item `tipe_item`: `sparepart|jasa|lainnya` (NOT 'material')
- `pengajuan_sparepart.status` enum: `draft|menunggu_approval|disetujui|ditolak|diproses|selesai|dibatalkan`; MUST create via `PengajuanSparepartService::buatPengajuan($data, $user)` (validates items ≥1 + stok > diminta); direct create with non-draft status throws "Minimal 1 item sparepart harus ditambahkan"
- `penawaran.nomor_penawaran` varchar(20) — keep codes ≤ 20 chars; `pembayaran`: `tanggal_bayar`/`metode_bayar`; `transaksi_keluar`: `nomor_transaksi` required; `rab`: `nomor_rab`/`nama_proyek`/`total_rab`
- `rab_material_plan_items.plan_id` — BUKAN `rab_material_plan_id`; item col `rab_komponen_id`/`quantity_rencana`/`quantity_terealisasi`
- SoftDeletes per model TIDAK seragam: Kontrak/Faktur/Pekerjaan/Aset/TransaksiKeluar/Klien/Penawaran pakai; `Rab`/`RabKomponen`/`RabMaterialPlan*` TIDAK → `Rab::withTrashed()` = BadMethodCallException
- `kontrak_adendum` REQUIRES `adendum_ke` (no DB default → SQLSTATE 1364); `nilai_efektif` = nilai + Σ adendum (accessor, jangan query manual)
- `pajak` columns: `dpp`/`tarif_pajak`/`nominal_pajak` (NOT `jumlah`) — SUM `nominal_pajak`
- `pengeluaran`: `jumlah_biaya` (bukan biaya); kategori valid = `Pengeluaran::KATEGORI` (lihat sync section)

## Non-Destructive E2E Test Pattern (test logic tanpa polusi DB)

Wrap the whole sim in `DB::beginTransaction()` … `DB::rollBack()` with a `check($label, $cond, $detail)` helper + `catch (\Throwable)`. Real services + model hooks run; nothing persists. Ideal for "test ulang logic semua divisi" on a live DB — zero cleanup. Proven 2026-08-01: 24 checks across R01–R07 + billing + aset idempotency in one transaction.

## Cleanup Pattern (soft-delete FK trap)

Deleting a Kontrak with soft-deleted Faktur fails: `Cannot delete parent row (1451)`.
Correct order:
```php
// soft-delete children with trashed Faktur/Aset:
Faktur::withTrashed()->where(...)->get() → DB::table('faktur_items')->where(...)->delete(); $f->forceDelete();
// non-soft-delete children (faktur_items, aset_riwayat) have NO withTrashed() — use DB::table
// then parent: Kontrak::withTrashed()->find($id)?->forceDelete();
```
NOTE (2026-08-01): `TransaksiKeluar::booted()` registers created/updated/deleted hooks → decrement/adjust/restore stok + BOM sync. Model delete (incl. forceDelete) fires `deleted` → stok auto-restore; the old "restore manually" note applied to raw `DB::table` deletes only.

Verified committed-sim cleanup order (2026-08-01, `_cleanup_vms.php`, idempotent, anchor = nomor pattern `LIKE 'KTR-VMS-%'`):
`Pajak → Pembayaran → Faktur → KontrakTermin → Aset → TransaksiKeluar → Pekerjaan → PermintaanPembelian → RabMaterialPlanItem (FK = plan_id) → RabMaterialPlan → RabKomponen → Rab → Penawaran → Kontrak → Sparepart (hanya jika 0 referensi) → Klien (hanya jika 0 kontrak lain)`.

## Full Verified Flow (PJU Tol Tangerang, 2026-07-31)

References `references/pju-tangerang-sim.md` — runnable recipe with exact component sets, quantities, and expected outputs (8 titik, Rp 109.040.000, PPN 11% config-driven — `CompanySetting::get('ppn_rate') ?? config('pajak.tarif_ppn_keluaran', 11)`, 8 aset, 8 auto-PP).

## Perawatan / MC Billing Flow (VMS, 2026-08-01) — COMMITTED sim

References `references/perawatan-mc-billing-sim.md` — perawatan kontrak uses MC termin chain instead of single faktur: kontrak create (jenis='perawatan') → hook auto-generates 12 MC termin → `generateFakturForDueMc(termin)` per termin → faktur terbit → pembayaran → termin lunas → keuangan 100% → auto-complete → aset. Key gotchas: `generateMcTermin` returns 0 when termin exist (dedup, not error); `pajak` table columns are `nominal_pajak`/`dpp`/`tarif_pajak` (NOT `jumlah`); `nilai_efektif` = nilai + adendum accessor. Committed-sim pattern (no rollback — user sees data in UI) + FK-order cleanup included.

## Project Health / Workflow Sync (FIXED 2026-08-01)

`WorkflowIndicatorService::getStages()` = 7 stages (kontrak, rab, penawaran, pekerjaan, approval, faktur, pembayaran), 33 validations total (6+5+5+5+4+5+3). `progress` = completed_stages/7.

- **Sequential lock**: ONLY one stage `active` — later 'active' stages demoted to `pending` (e.g. approval shows `pending` while pekerjaan is active — BY DESIGN, not a bug).
- Stage `pekerjaan` completed = SEMUA pekerjaan keluar draft (diserahkan), BUKAN ≥1 approved. Bug fixed: 1/8 approved → stage hijau padahal fisik 12.5%.
- `'in_progress'` is NOT a pekerjaan status (enum: draft|submitted|approved|rejected|direvisi) — use `['draft','submitted']` for "sedang dikerjakan".
- Stage `faktur` completed = fakturLunas ≥ nilai_efektif; `pembayaran` completed = paymentPercent ≥ 100. Workflow 8/8 approved tanpa faktur = 71%; + faktur lunas = 100%.
- Dashboard aggregates (`ProjectHealthDashboard::getSummary/getKontrakData`) MUST use `nilai_efektif` (nilai+adendum), not raw `nilai` — else margin/sisa-pagu/progress drift from `Kontrak::hitungProgresOtomatis` & workflow (both use nilai_efektif). Fixed both methods.
- `HealthCalculator::fromWorkflows` — healthScore = passed_validations/total_validations (NOT stage progress); margin thresholds 20/10/0 → sehat/warning/kritis/rugi. `fromMargin` is shared by ProjectHealth + BudgetMonitor.

### Operasional Proyek ↔ Project Health ↔ Kontrak sync (FIXED 2026-08-01)

Root cause of "angka beda" antara halaman: **scope mismatch** — `Pengeluaran::sum()` (Operasional Proyek page) hitung SEMUA kontrak (active+completed+terminated), `ProjectHealthDashboard::getSummary()` hanya `status='active'`. Dua halaman jawab pertanyaan beda tapi label sama → terlihat rusak.

**3 konsep terpisah (satu sumber `nilai_efektif`):**
- **Pendapatan**: `nilai_efektif` ↔ Σ faktur lunas → `progres_keuangan` (Kontrak)
- **Biaya**: `nilai_efektif − Σpengeluaran` → Sisa Pagu & Margin (Project Health + ViewKontrak)
- **Operasional**: Σpengeluaran per kontrak, SEMUA status (historis) — halaman Operasional Proyek

Fix pattern: widget Operasional Proyek tambah stat terpisah "Biaya Kontrak Berjalan" (scope active) agar angka bisa dicocokkan silang dgn Project Health; jangan ubah scope total (historis tetap berguna). ViewKontrak kini tampilkan Sisa Pagu + Margin cards (rumus sama persis dgn health: `(nilai_efektif − Σpengeluaran)/nilai_efektif`). Label blade perjelas scope: "Realisasi (Kontrak Aktif)".

Pitfall tambahan: `PengeluaranResource` form pakai `Pengeluaran::KATEGORI` (9: upah_borongan, upah_harian, sewa_alat, bahan_habis, perawatan, transport, makan, akomodasi, lainnya) tapi `KontrakResource` relation manager punya list inline LAMA (listrik/ATK/internet/operasional_kantor) — kategori dari relation manager tidak valid di tabel utama. Sinkronkan ke KATEGORI saat edit.

Probe: `scripts/verify-workflow-sync.php` — copy to project root as `_verify_workflow_sync.php`, run `php _verify_workflow_sync.php`. Asserts 43%→71%→100% progression + stage semantics in a transaction (rolls back, zero pollution). Re-run after any stage-logic change.

## Verified Role Access (2026-08-01)

References `references/role-access-verified-2026-08-01.md` — 86 permissions, per-role counts, module matrix, user overrides, login = username. Use when sanity-checking role coverage before/after adding resources.

**Audit render halaman per role: browser, BUKAN CLI kernel.** Script `$kernel->handle()` loop per user → false 500 massal (Livewire `Redirector` vs `StartSession` TypeError) + session singleton tercemar antar user. Browser login+snapshot = ground truth; ganti user via POST `/admin/logout` + `X-XSRF-TOKEN` fetch (cookie HttpOnly tak bisa di-clear JS). Detail lengkap: `erp-filament-antipatterns` #36.

## Filament v3 Form Pitfalls (2026-08-01, hit while editing PengeluaranResource)

- `Select::modifyQueryUsing()` DOES NOT EXIST in Filament v3 → `BadMethodCallException: Method Filament\Forms\Components\Select::modifyQueryUsing does not exist` on page mount (create form). Sort options via the 3rd arg of `->relationship('kontrak', 'nomor_kontrak', fn ($q) => $q->orderByRaw('status = "active" DESC, nomor_kontrak ASC'))`. For `->options()` selects there is NO query callback either — build the array in the closure.
- `{ucfirst($record->status)}` inside a double-quoted string is INVALID PHP (function calls not allowed in `"{$var}"` interpolation). Use concatenation: `$record->nomor_kontrak . ' — ' . $record->nama_kontrak . ' (' . ucfirst($record->status) . ')'`.
- Adding a chain method to a Filament component in a schema array: missing trailing comma after the last method → `Parse error: unexpected namespaced name "Forms\Components\TextInput", expecting "]"`. Always `php -l` after schema edits; this error is a comma, not a class problem.
- Caution with `cat -A` / truncated `sed` output when diagnosing broken lines — 150-char display truncation can make an intact line look truncated; the real bug was the missing comma. Read the actual line with plain `sed -n 'Np'` before patching.

## Financial Dashboard Pattern (KeuanganDashboard, 2026-08-01)

`app/Filament/Pages/KeuanganDashboard.php` (page + 3 data methods) + `resources/views/filament/pages/keuangan-dashboard.blade.php` (custom cards, same style as manager-dashboard), registered in `AdminPanelProvider::pages()` under navigation group `💰 Keuangan`. `canAccess()` = `hasPermission('dashboard.view') ?: in_array($user->role, ['R00','R05','R06'])`.

Verified data pitfalls:
- Faktur lunas INCLUDES PPN 11% → Σ lunas can exceed `nilai_efektif` (observed Rp133,2jt lunas vs Rp120jt nilai = raw 111%). ALWAYS clamp `min(100, round(lunas/nilai*100))` — identical clamp to `Kontrak::hitungProgresOtomatis()` (line ~192). Unclamped bar shows 111% and looks broken.
- `Pajak` has NO `tanggal_pembayaran` column — "belum setor" = `whereRaw('IFNULL(verified, 0) = 0')`.
- Piutang = faktur status `terbit`+`jatuh_tempo` (exclude `draft`, exclude `lunas`).
- Cross-check: Σ `Pembayaran::sum('jumlah')` must equal Σ faktur lunas — divergence = pembayaran/faktur out of sync.
- `canAccess()` returns false in tinker/CLI (no `Auth::user()`) — verify access via browser, not CLI.
