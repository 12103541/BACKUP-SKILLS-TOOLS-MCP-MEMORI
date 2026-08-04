# Manajemen Proyek — Business Flow Analysis (July 22, 2026)

## 6 Modules In Order

```
HARGAREFERENSI → database harga pasar (standalone, FULLTEXT search)
       ↓ referenced by
RAB            → rencana anggaran biaya (links to kontrak)
       ↓
PENAWARAN      → penawaran ke klien (can be converted to kontrak)
       ↓ convert
KONTRAK        → kontrak kerja (pusat flow)
       ├── TERMIN        → termin pembayaran (syncs with faktur)
       ├── FAKTUR        → tagihan ke klien (creates PPN Keluaran)
       ├── PEKERJAAN     → pekerjaan lapangan (updates progres_fisik)
       ├── PENGELUARAN   → biaya proyek
       └── SELESAI       → auto-create ASET
```

## 8 Issues Identified & Fixed

| # | Module | Issue | Severity | Fix | Status |
|---|--------|-------|----------|-----|--------|
| 1 | Pekerjaan | Approved doesn't update kontrak progres_fisik | KRITIS | Boot observer: wasChanged('status') → hitungProgresOtomatis() | Done |
| 2 | Penawaran | Form uses TextInput for status/kontrak_id/user_id instead of Select | KRITIS | Convert to Select with options, auto-set user_id | Done |
| 2b | Penawaran | No "Konversi ke Kontrak" action | KRITIS | Table action with confirmation modal | Done |
| 3 | RAB | total_rab doesn't auto-calculate from komponen | SEDANG | Rab::saved() observer calls hitungTotal() | Done |
| 4 | RAB | No reference to HargaReferensi for harga_satuan | SEDANG | helperText callback with HargaReferensi::cariHarga() | Done |
| 5 | Penawaran | Expired status not auto-updated, isExpired() logic wrong | SEDANG | CheckPenawaranExpired scheduler + fix isExpired() | Done |
| 6 | Klien | Index table doesn't show kontrak stats | RENDAH | counts + computed columns | Done |

## Key Improvements

### Pekerjaan → Kontrak (child→parent progress)
- When pekerjaan approved: update approved_by + approved_at + recalculate kontrak progres_fisik
- When pekerjaan status reverts from approved: clear approved_by + approved_at + recalculate
- Use `updateQuietly()` to avoid observer recursion

### Penawaran → Kontrak (entity conversion)
- Table action "Buat Kontrak" opens modal with klien, tgl_mulai, tgl_akhir, nilai
- Auto-generates nomor_kontrak from nomor_penawaran
- Auto-sets penawaran status to approved + links kontrak_id
- Hidden when already converted

### RAB Total BOTTOM-UP Calculation
- `hitungTotal()` sums komponen.jumlah_harga, applies markup_persen
- Triggered by `static::saved()` observer
- Uses `updateQuietly()` to prevent recursion

### Cross-Model Reference Prices
- RAB harga_satuan field shows helperText with HargaReferensi::cariHarga()
- Dynamic: recalculates when uraian_pekerjaan changes
- Falls back silently when no match (returns null)

## Data Integrity Patterns

1. **No orphan conversions**: after Konversi ke Kontrak, Penawaran status → approved, kontrak_id → set
2. **No orphan rekalkulasi**: child status change triggers parent recalculate, never skipped
3. **No orphan cleanup**: reopen/delete/restore all cascade correctly
4. **No hardcoded rates**: PPN derived from data, config fallback
5. **No orphan expired**: scheduler catches all time-sensitive statuses
