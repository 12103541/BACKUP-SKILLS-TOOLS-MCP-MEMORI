# Scheduler & Status Automation (July 22, 2026)

## Cron Commands

| Command | Schedule | Purpose |
|---------|----------|---------|
| `faktur:check-overdue` | 07:00 daily | Faktur terbit + overdue → jatuh_tempo + termin → terlambat |
| `termin:update-overdue` | 07:30 daily | Termin tertagih + overdue → terlambat |
| `faktur:reminder-jatuh-tempo` | 08:00 daily | H-7 and H-1 reminders for faktur + termin (logs to Activity) |
| `aset:check-garansi` | 09:00 daily | Log warranty expiration for active assets |
| `kontrak:check-notifications` | 08:00 daily | Existing notification checker |
| `backup:run-scheduled` | 01:00 daily | Automated DB backup |

## Status State Machine

### Faktur ↔ KontrakTermin (bidirectional)
```
Faktur → Termin:          Termin → Faktur:
  draft → belum_tertagih    lunas → lunas + tgl_bayar
  terbit → tertagih         belum_tertagih → draft
  jatuh_tempo → terlambat
  lunas → lunas
```

### Faktur → PPN Keluaran Auto-Create
Triggered: `static::updated` when `wasChanged('status') && status === 'terbit' && ppn > 0`.
Creates Pajak with `jenis = 'ppn_keluaran'`, dedup-checked by `faktur_id + jenis`.
tarif_pajak = ppn/subtotal × 100 (not hardcoded).

### Faktur Lunas → Progres Kontrak
Triggered: `static::updated` when `wasChanged('status') && status === 'lunas'`.
`hitungProgresOtomatis()` recalculates:
- progres_fisik = pekerjaan approved / total pekerjaan
- progres_keuangan = faktur lunas / nilai kontrak (NOT cost + revenue)
- If both ≥ 100%: kontrak auto-complete → aset auto-create

### Faktur Deleted → Cleanup
Triggered: `static::deleted`.
- Deletes related Pajak records
- Recalculates kontrak progres

### Faktur Restored → Recreate
Triggered: `static::restored`.
- If ppn > 0 && status = terbit: recreate PPN Keluaran
- Recalculates kontrak progres

### Kontrak Reopen → Aset Cleanup
Triggered: status changes from 'completed' → 'active'.
Deletes auto-created aset records (matched by catatan LIKE '%dibuat otomatis%').
