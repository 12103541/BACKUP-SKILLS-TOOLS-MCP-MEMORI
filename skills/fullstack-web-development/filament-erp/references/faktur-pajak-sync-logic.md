# Faktur-Pajak-Kontrak Sync Logic (July 23, 2026)

Business flow: KLIEN → KONTRAK (termin) → PEKERJAAN → PROGRES → FAKTUR → PAJAK → PEMBAYARAN → ASET

## Key Sync Points

### 1. Faktur ↔ KontrakTermin (bidirectional)
Faktur form picks `kontrak_termin_id` from `belum_tertagih` termins.
Auto-fills: subtotal from termin.nilai, jatuh_tempo from termin.tgl_jatuh_tempo.

### 2. Status State Machine (bidirectional)
| Faktur → Termin         | Termin → Faktur              |
|------------------------|------------------------------|
| draft → belum_tertagih | lunas → lunas + tgl_bayar    |
| terbit → tertagih      | belum_tertagih → draft       |
| jatuh_tempo → terlambat|                              |
| lunas → lunas          |                              |

### 3. Faktur Terbit → PPN Keluaran Auto-Create
- Dedup check by `faktur_id + jenis`
- tarif_pajak = ppn/subtotal × 100 (derived, not hardcoded)
- Fallback from `config('pajak.tarif_ppn_keluaran', 12)`

### 4. Faktur Lunas → Progres Kontrak
- `progres_keuangan = total_faktur_lunas / nilai_kontrak × 100`
- If fisik ≥ 100 AND keuangan ≥ 100 → auto-complete + aset auto-create

## 6 Sync Issues Fixed (July 22, 2026)

| # | Issue | Fix |
|---|-------|-----|
| 1 | KontrakTermin→Faktur reverse sync missing | KontrakTermin boot observer: termin lunas → faktur lunas |
| 2 | Faktur delete doesn't cleanup Pajak/recalculate | Faktur::deleted() deletes Pajak + recalculates progres |
| 3 | Tarif PPN hardcoded 11% | Derive from ppn/subtotal, config fallback, default 12% |
| 4 | No H-7/H-1 jatuh tempo reminder | SendJatuhTempoReminder command at 08:00 daily |
| 5 | Aset not cleaned on kontrak reopen | Delete auto-created aset when status → 'active' from 'completed' |
| 6 | Garansi expired no auto-check | CheckGaransiExpired command at 09:00 daily |

## Tarif Config Source of Truth (July 23, 2026)

**Config file**: `config/pajak.php` — all tax rates defined here.
```php
'tarif_ppn_keluaran' => (float) env('TARIF_PPN_KELUARAN', 12),
'tarif_ppn_masukan'  => (float) env('TARIF_PPN_MASUKAN', 12),
'tarif_pph23'        => (float) env('TARIF_PPH23', 2),
```

**Models using config**: `Faktur::hitungTotal()`, `Faktur::createPpnKeluaran()`
**Resources using config**: `PajakResource::getJenisTarif()` (lazy-load getter)

**Lesson**: NEVER hardcode tax rates/percentages in model methods. Always read from config(). When a Resource has a static array of options with configurable values, use a lazy-load getter that reads config on first call.

## FK Backfill Pattern
When adding FK columns, existing records get NULL. Boot observers only fire on NEW changes.
```sql
-- Backfill by business key (not auto-increment ID)
UPDATE pajak p JOIN faktur f ON p.nomor_faktur_pajak = f.nomor_faktur
SET p.faktur_id = f.id WHERE p.faktur_id IS NULL;

-- Backfill by composite key when nomor doesn't match exactly
UPDATE pajak p JOIN faktur f ON p.dpp = f.subtotal AND p.kontrak_id = f.kontrak_id
SET p.faktur_id = f.id WHERE p.faktur_id IS NULL;
```

## E2E Testing Pattern
Observers only fire via Eloquent. Use `artisan tinker --execute` not raw SQL.
Common failure: `isDirty('status')` in `updated` callback → use `wasChanged('status')`.

## DB Schema
```sql
ALTER TABLE faktur ADD COLUMN kontrak_termin_id BIGINT UNSIGNED NULL AFTER kontrak_id;
ALTER TABLE pajak ADD COLUMN faktur_id BIGINT UNSIGNED NULL AFTER kontrak_id;
ALTER TABLE petty_cash ADD COLUMN kode_transaksi VARCHAR(50) NULL AFTER id;
```
