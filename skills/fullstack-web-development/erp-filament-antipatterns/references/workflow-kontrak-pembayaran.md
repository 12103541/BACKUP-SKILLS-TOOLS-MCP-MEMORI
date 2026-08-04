# Workflow Trace: Kontrak → Pembayaran 100%

Flow traced 2026-07-29. All bugs found + fixed noted inline.

```
KONTRAK created (active)
  ├── Termin dibuat (belum_tertagih)
  │     Badge fix: colors() map was 'pending' → 'belum_tertagih' [FIXED]
  │
  ├── Pekerjaan (draft → submitted → approved)
  │     ├── approved_by & approved_at set in Controller AND boot()
  │     │   Not a functional bug, but duplicate assignment
  │     └── Progres Fisik = approved pekerjaan / total pekerjaan
  │
  ├── FAKTUR
  │     ├── Create from termin: auto-fill subtotal, ppn, total_tagihan
  │     │   Bug: PPN 11% hardcoded vs config 12% [FIXED]
  │     │   Bug: items empty → total overwritten to 0 [FIXED]
  │     ├── Terbit: termin sync → 'tertagih' (via Faktur::boot event)
  │     │   PPN Keluaran auto-create
  │     ├── Jatuh tempo auto (cron): terbit → jatuh_tempo
  │     │   Bug: batch where()->update() kills event → termin stays tertagih [FIXED]
  │     └── Lunas (via pembayaran): termin sync → 'lunas'
  │         Kontrak::hitungProgresOtomatis() triggered
  │
  ├── PEMBAYARAN
  │     ├── Bug: duplicate hitungProgresOtomatis() 2x
  │     │   RelationManager after() + Model boot::saved() both call it [FIXED]
  │     └── Faktur status → 'lunas' via model event
  │
  └── KONTRAK auto-complete
        ├── Progres fisik ≥ 100% && keuangan ≥ 100%
        ├── Bug: self::STATUS_COMPLETED undefined → FATAL ERROR [FIXED]
        ├── Aset auto-create from approved pekerjaan
        └── Garansi dimulai

## Status Table

| Tahap | Sebelum | Sesudah Fix |
|-------|---------|-------------|
| Kontrak dibuat | ✅ OK | ✅ OK |
| Termin dibuat | ⚠️ Badge no color | ✅ Badge colors match DB |
| Faktur dibuat | ❌ PPN 11%, total 0 | ✅ PPN 12%, total from termin |
| Faktur terbit | ✅ Termin sync via event | ✅ OK |
| Jatuh tempo auto | ❌ Batch kill event | ✅ Loop per record |
| Pembayaran | ⚠️ Double sync | ✅ Single sync (model event) |
| Kontrak auto-complete | ❌ Fatal error | ✅ Constants defined |
| Aset auto-created | ❌ Never reached | ✅ Now reachable |

## Key Models

| Model | File | Status Constants |
|-------|------|-----------------|
| Kontrak | app/Models/Kontrak.php | active, completed, terminated |
| Faktur | app/Models/Faktur.php | draft, terbit, lunas, jatuh_tempo |
| Faktur → Termin map | Faktur::boot() | belum_tertagih→tertagih, lunas→lunas, jatuh_tempo→terlambat |
| Pembayaran | app/Models/Pembayaran.php | boot::saved() handles sync |
