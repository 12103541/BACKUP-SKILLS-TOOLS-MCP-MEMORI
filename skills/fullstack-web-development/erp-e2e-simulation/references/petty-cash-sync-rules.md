# Petty Cash Sync Rules (Aturan 1-4) — Verified 2026-08-01

## Masalah Awal
```
petty_cash (ledger: top_up + pengeluaran, saldo berjalan)
  ├─ Top Up          → PettyCash::topUp()          ✅ dipakai
  ├─ Operasional Kantor → PettyCashResource (tipe=pengeluaran) ✅ langsung ke ledger
  └─ Operasional Proyek → tabel pengeluaran         ❌ TIDAK pernah sentuh petty_cash
```
Kolom `sumber_dana` sudah ada di fillable tapi tak terpakai. `PettyCash::catatPengeluaran(Pengeluaran)` ada tapi tidak pernah dipanggil. Akibat: pengeluaran proyek Rp108jt (bulan ini) tidak mengurangi saldo kas kecil → ledger tidak sinkron.

---

## 4 Aturan Sinkronisasi (Ini Syarat, Bukan Halaman)

### Aturan 1: Saldo Hitung Ulang (bukan row terakhir)
```php
// PettyCash::saldoSaatIni() — Σ top_up − Σ pengeluaran
$masuk = (float) static::where('tipe', 'top_up')->sum('jumlah');
$keluar = (float) static::where('tipe', 'pengeluaran')->sum('jumlah');
return $masuk - $keluar;
```
- Kebal korupsi: kalau row tengah diedit/dihapus, saldo tetap benar
- Snapshot `saldo_sebelum/sesudah` per row tetap disimpan sebagai audit trail

### Aturan 2: Kolom `sumber_dana` di `pengeluaran`
Migration: `2026_08_01_140000_add_sumber_dana_to_pengeluaran_table.php`
```php
$table->string('sumber_dana', 20)->default('bank')->after('jumlah_biaya');
```
Nilai: `petty_cash` | `bank`. Default `bank` — proyek dibayar dari bank, tidak sentuh kas kecil.

### Aturan 3: Hook `Pengeluaran` → auto write ledger (satu transaksi, tak bisa desync)
```php
// app/Models/Pengeluaran.php::booted()
static::created(function (self $p) {
    if ($p->sumber_dana === 'petty_cash') {
        PettyCash::catatPengeluaran($p);
    }
});

static::updated(function (self $p) {
    $row = PettyCash::where('pengeluaran_id', $p->id)->first();
    $nowKas = $p->sumber_dana === 'petty_cash';
    if ($nowKas && !$row) PettyCash::catatPengeluaran($p);       // bank→kas
    elseif ($nowKas && $row) $row->update([...]);                 // update jumlah/tgl/ket
    elseif (!$nowKas && $row) $row->delete();                     // kas→bank
});

static::deleted(function (self $p) {
    PettyCash::where('pengeluaran_id', $p->id)->delete();
});
```

### Aturan 4: Filter Kanal (UI — tidak double-count)
| Halaman | Filter | Sumber Data |
|---------|--------|-------------|
| Operasional Kantor (`PettyCashResource`) | `tipe='pengeluaran' AND pengeluaran_id IS NULL` | Manual kas kecil (ATK, listrik, dll) |
| Operasional Proyek (`PengeluaranResource`) | `sumber_dana` filter + tabel `pengeluaran` | Proyek (kategori upah, sewa, transport) |
| Kas Operasional Dashboard | 3 tab dari 1 ledger `petty_cash` | Satu sumber kebenaran |

---

## Form Pengeluaran Proyek (opsional `sumber_dana`)
```php
Forms\Components\Select::make('sumber_dana')
    ->label('Sumber Dana')
    ->options([
        'bank' => '🏦 Bank (Transfer / Rekening)',
        'petty_cash' => '💵 Kas Kecil (Petty Cash)',
    ])
    ->default('bank')
    ->required()
    ->helperText('Kas Kecil: otomatis kurangi saldo petty cash. Bank: tidak menyentuh kas kecil.'),
```

---

## Test Verified (15/15 PASS — `_sync_kas.php`, rollback)
```
✅ create petty_cash → row kas dibuat
✅ row kas jumlah = 500.000
✅ row kas link pengeluaran_id
✅ saldo turun 500.000
✅ update jumlah → row kas 750.000
✅ saldo turun 750.000
✅ pindah bank → row kas hilang
✅ saldo kembali awal
✅ pindah kas → row kas dibuat lagi
✅ delete → row kas hilang
✅ saldo kembali awal (delete)
✅ bank → tidak ada row kas
✅ bank → saldo tetap
✅ kantor manual → pengeluaran_id null
✅ kantor manual → saldo turun 100rb
```

---

## Halaman Gabungan: Kas Operasional Dashboard (`/admin/kas-operasional`)
**File:** `app/Filament/Pages/KasOperasionalDashboard.php` + `resources/views/filament/pages/kas-operasional-dashboard.blade.php`

4 kartu stat (Saldo, Top Up Bulan Ini, Kantor Bulan Ini, Proyek via Kas Bulan Ini) + 3 tab:
- **Top Up** — history top_up dari ledger
- **Operasional Kantor** — tipe=pengeluaran + pengeluaran_id IS NULL
- **Operasional Proyek (Kas)** — pengeluaran sumber_dana=petty_cash (via join)

**Akses:** R00, R05, R06 + `dashboard.view` permission. Livewire tab switching (`wire:click="$set('tab', ...)"`).

---

## Verifikasi Real-Time
```php
// Terminal
Pengeluaran::create(['sumber_dana' => 'petty_cash', 'jumlah_biaya' => 2500000, ...]);
// → hook created → PettyCash::catatPengeluaran() dalam transaksi sama
// → saldo 100jt → 97.5jt
// Browser tab Proyek: langsung muncul record baru
// Kartu "Proyek via Kas Kecil": Rp2.500.000
✅ SEMUA SINKRON
```

---

## Lesson untuk Simulasi Ke Depan
**SETIAP simulasi WAJIB include tahap pengeluaran operasional** — ~12% nilai kontrak, 7 record (upah_borongan 35%, sewa_alat 20%, transport 15%, upah_harian 15%, bahan_habis 8%, makan 5%, akomodasi 2%), tanggal tersebar tgl_mulai..tgl_akhir. Backfill script pattern: `_fill_pengeluaran.php` (lihat erp-e2e-simulation SKILL.md section "SIMULATION MUST COVER MIDDLE STAGES").