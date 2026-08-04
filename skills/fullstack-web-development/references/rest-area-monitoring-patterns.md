# REST Area Monitoring System Patterns (August 2026)

Python FastAPI + SQLite + Vanilla JS stack for parking availability display with VMS integration.

## Stack Overview
- **Backend**: FastAPI (`app/main.py`), SQLite with custom migrations (`app/database.py`), business logic (`app/logic.py`), simulator (`app/simulator.py`)
- **Frontend**: Jinja2 templates (`templates/*.html`), vanilla ES6 modules (`static/*.js`, `static/style.css`)
- **Auth**: Token-based (X-Token header), roles: admin/petugas
- **Database**: SQLite file `data/rest_area.db`

## Key Patterns Implemented

### 1. SQLite CHECK Constraint Removal (for Dynamic Enums)
```python
def _ada_check(tabel: str) -> bool:
    row = conn.execute(
        "SELECT sql FROM sqlite_master WHERE type='table' AND name=?", (tabel,)
    ).fetchone()
    return bool(row and "CHECK (jenis" in (row[0] or ""))

# Rebuild without CHECK
conn.execute(f"ALTER TABLE {tabel} RENAME TO {tabel}_lama")
conn.execute(f"CREATE TABLE {tabel} (...no CHECK...)")
# Handle column differences (e.g., old had 'aktif')
kolom = "id, id_lokasi, jenis_kendaraan, ..."
if _kolom_ada(conn, f"{tabel}_lama", "aktif"):
    kolom += ", aktif"
conn.execute(f"INSERT INTO {tabel} ({kolom}) SELECT {kolom} FROM {tabel}_lama")
conn.execute(f"DROP TABLE {tabel}_lama")
```

### 2. Dynamic Enum Replacement (Hardcoded → DB-Driven)
```python
# logic.py - central helpers
def jenis_daftar(): return db.query("SELECT * FROM jenis_kendaraan ORDER BY urutan")
def jenis_kode_list(): return [r["kode"] for r in jenis_daftar()]
def jenis_label(kode): return db.query_one("SELECT label FROM jenis_kendaraan WHERE kode=?", (kode,))
def jenis_valid(kode): return db.query_one("SELECT id FROM jenis_kendaraan WHERE kode=?", (kode,)) is not None

# Replace all: if jenis not in JENIS_LIST → if not jenis_valid(jenis):
```

### 3. VMS Player Scaling (Exact C:\VMS Behavior)
```javascript
// iframe with transform-origin 0 0, NEVER upscale
function skala() {
  const f = document.querySelector("iframe");
  const { display_width: w, display_height: h } = CONFIG;
  const scale = Math.min(1, window.innerWidth / w, window.innerHeight / h);
  f.style.transform = `translate(0, 0) scale(${scale})`;
  f.style.transformOrigin = "0 0";
}
setInterval(skala, 100);
```

### 4. Public Display Cards (2×2 Grid, Split Layout)
```
┌─────────────────────────────────────┐
│  LABEL (yellow pill)                │
├──────────────┬──────────────────────┤
│   ICON       │   NUMBER (big)       │
│   (left)     │   "SLOT" caption     │
│              │   "XX / YY" sub-line │
└──────────────┴──────────────────────┘
```
- Grid: `grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr;`
- Card: `height: 100%; display: grid; grid-template-rows: auto 1fr;`
- Media query `@media (max-width: 640px)` for VMS 512×288 exact fit

### 5. Single-Page Admin CRUD Tab
```html
<!-- Form (create) -->
<div class="form-grid-2">
  <label>Kode<input id="jenisKode"></label>
  <label>Label<input id="jenisLabel"></label>
  <label>English<input id="jenisLabelEn"></label>
  <label>Bobot<input type="number" id="jenisBobot"></label>
</div>
<button id="tombolSimpanJenis">💾 Simpan</button>

<!-- Table (read/update/delete) -->
<table id="tabelJenis">
  <thead><tr><th>Urutan</th><th>Ikon</th><th>Kode</th><th>Label</th><th>English</th><th>Bobot</th><th>Aktif</th><th>Aksi</th></tr></thead>
  <tbody>...</tbody>
</table>
```
JS: Event delegation on table → handles save/put/delete/toggle/upload.

### 6. Cache Busting
```html
<link rel="stylesheet" href="/static/style.css?v=18">
<script src="/static/public.js?v=10"></script>
```
Bump: `sed -i 's|style.css?v=17|style.css?v=18|' templates/public.html`

### 7. Icon Handling (SVG + PNG Custom + BW)
- Default: `/static/ikon/ikon_{kode}.svg` (CSS filter yellow)
- Custom PNG: Upload → save as `ikon/ikon_{kode}.png` → DB `pengaturan_app.ikon_{kode}`
- Render: `<img class="ikon-custom" src="...">` with `filter: none`
- BW conversion: PIL luminance threshold 128 → white silhouette + transparent
- Anti-cache: Rename file (`ikon_mobil_v2.png`) + update DB → forces fresh fetch

## API Endpoints Added
- `GET /api/jenis` — list all vehicle types + icons
- `POST /api/jenis` — create new type (auto-seeds kapasitas rows for all locations)
- `PUT /api/jenis/{kode}` — update label/label_en/bobot/urutan/aktif
- `DELETE /api/jenis/{kode}` — delete type + kapasitas rows + icon setting
- `POST /api/pengaturan/ikon/{kode}` — upload custom icon (FormData)

## Database Schema Changes
- New table: `jenis_kendaraan` (kode PK, label, label_en, urutan, bobot, aktif)
- `kapasitas` rebuilt without CHECK constraint on `jenis_kendaraan`
- `riwayat_deteksi`, `riwayat_koreksi` rebuilt without CHECK