# Capacity settings trio + simulator reset — implementation notes

From REST AREA MONITORING (FastAPI + SQLite WAL). Applies to any VMS/display app
with per-location occupancy boards.

## 1. Simulator data reset (POST /api/deteksi {"reset": true})

Why: simulator counters accumulate since first boot; once `masuk - keluar`
exceeds `kapasitas_maks`, `tersedia` pins at 0 and the whole board shows PENUH —
user reports "display tidak jalan" though the engine ticks fine.

Backend (admin-guarded endpoint — `_admin_saja(request)`):

```python
if body.get("reset") is True:
    db.execute("UPDATE kapasitas SET masuk = 0, keluar = 0, diperbarui_pada = datetime('now','localtime')")
    for t in ("riwayat_deteksi", "riwayat_koreksi", "riwayat_kapasitas", "riwayat_alert"):
        db.execute(f"DELETE FROM {t}")
    engine.deteksi_total = 0
await _broadcast_langsung()   # boards update live via WS
return engine.status()
```

Frontend: red button, `hidden` toggled from `userInfo.peran === "admin"` inside
tampilkanDasbor; `confirm("...SEMUA riwayat dihapus...")` before calling.
Keep the existing "Jeda" guard (simulator always runs) so the reset is the only
way to zero counters.

Verify (read-only, no DELETE in the same command to dodge approval gates):
- Before: snapshot counters via `GET /api/state?lokasi=1` (key on `jenis`).
- Reset via API (login first — token dies on server restart).
- After: counters 0 / tersedia = maks; sleep 10s, re-poll — climbing from 0.

## 2. kapasitas_total (total slots per location)

- Column on `pengaturan_umum`, `INTEGER NOT NULL DEFAULT 0`.
- 0 = auto = sum of `kapasitas_maks` of ACTIVE types. >0 = fixed total.
- Allowed in PUT /api/pengaturan (validate `>= 0`; `allowed` set must include it).
- Public board (public.js): `totalSlot = Number(pengaturan.kapasitas_total) > 0
  ? Number(...) : totalMaks` — used for footer "Total terpakai: a dari b" AND the
  "cadangan" card sub-label. Declare `totalSlot` BEFORE first use (TDZ bug if
  defined after the card block).

## 3. Per-type active switch (`aktif` column)

- `ALTER TABLE kapasitas ADD COLUMN aktif INTEGER NOT NULL DEFAULT 1` via
  `_kolom_ada` guard. Rows stay in DB when off — counters preserved.
- Endpoint: `PUT /api/kapasitas/{jenis}/aktif` `{"aktif": bool}` → logic.set_jenis_aktif.
- IMPORTANT: `db.execute()` returns **lastrowid, not rowcount** — don't branch on
  it to detect "0 rows updated"; SELECT the row first and raise 400/404 if absent.
- state_kapasitas() emits `"aktif": bool(r["aktif"])`.
- Public board filters: `const kapasitas = (data.kapasitas || []).filter(k => k.aktif !== false);`
  (keep `!== false` so legacy rows without the field still show).
- CSS toggle: `.switch` + `.slider` (~40x22px, checked = green, translateX(18px)).

## 4. Custom vehicle icons (UploadFile)

- Endpoint `POST /api/pengaturan/ikon/{jenis}` — `_admin_saja`, validate `jenis`
  in JENIS_LABEL, ext in {png,jpg,jpeg,webp,gif,svg}, `len(isi) <= 1_000_000`.
- Write `static/ikon/ikon_{jenis}.{ext}` (mkdir static/ikon), store relative path
  `ikon/{nama}` in key-value table `pengaturan_app` (`INSERT OR REPLACE`).
- state_kapasitas() joins: `SELECT nilai FROM pengaturan_app WHERE kunci='ikon_<jenis>'`.
- Board renders `<img src="/static/${k.ikon}">` when set, else inline SVG symbol.
- **fetch pitfall**: the shared api() helper must NOT set `Content-Type:
  application/json` for FormData (FastAPI 422 — browser must set multipart
  boundary): `if (!(opsi.body instanceof FormData)) headers["Content-Type"] = "application/json";`

## Verification checklist (all curl, admin token)

1. PUT /api/pengaturan {"kapasitas_total":220} → returns kapasitas_total 220.
2. PUT /api/kapasitas/motor/aktif {"aktif":false} → aktif False; /api/state still
   lists motor (row kept) but public board hides it (frontend filter).
3. Upload tiny PNG (generate with zlib/struct in python) → `ikon/ikon_mobil.png`;
   GET /static/ikon/ikon_mobil.png → 200.
4. py_compile app/*.py + node --check static/*.js after every change.
