# Dynamic Entity CRUD with Auto-Kapasitas

## Pattern: Vehicle Type CRUD (Single Admin Page)

### Database Schema
```sql
CREATE TABLE jenis_kendaraan (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    kode       VARCHAR(20) NOT NULL UNIQUE,   -- 'mobil', 'motor', 'bus', 'van'
    label      VARCHAR(40) NOT NULL,           -- 'Mobil', 'Motor', 'Bus/Truk'
    label_en   VARCHAR(40) DEFAULT '',         -- 'Car', 'Motorcycle', 'Bus/Truck'
    urutan     INTEGER NOT NULL DEFAULT 0,     -- display order
    bobot      INTEGER NOT NULL DEFAULT 10,    -- simulator weight
    aktif      INTEGER NOT NULL DEFAULT 1      -- 1=show on public display
);
```

### Migration Seed (in migrate())
```python
conn.executescript("""
    INSERT OR IGNORE INTO jenis_kendaraan (kode, label, label_en, urutan, bobot) VALUES
    ('mobil', 'Mobil', 'Car', 1, 45),
    ('motor', 'Motor', 'Motorcycle', 2, 40),
    ('bus', 'Bus/Truk', 'Bus/Truck', 3, 15);
""")
```

### Logic Helpers (app/logic.py)
```python
def jenis_daftar() -> list:
    rows = db.query("SELECT * FROM jenis_kendaraan ORDER BY urutan, id")
    if not rows:
        # Fallback seed for old DBs
        for k, l, le in (("mobil", "Mobil", "Car"), ("motor", "Motor", "Motorcycle"), ("bus", "Bus/Truk", "Bus/Truck")):
            db.execute("INSERT OR IGNORE INTO jenis_kendaraan (kode, label, label_en, urutan, bobot) VALUES (?, ?, ?, ?, ?)",
                       (k, l, le, {"mobil": 1, "motor": 2, "bus": 3}[k], {"mobil": 45, "motor": 40, "bus": 15}[k]))
        rows = db.query("SELECT * FROM jenis_kendaraan ORDER BY urutan, id")
    return rows

def jenis_kode_list() -> list:
    return [r["kode"] for r in jenis_daftar()]

def jenis_label(kode: str) -> str:
    r = db.query_one("SELECT label FROM jenis_kendaraan WHERE kode = ?", (kode,))
    return (r or {}).get("label") or JENIS_LABEL.get(kode, kode)

def jenis_bobot_map() -> dict:
    return {r["kode"]: r["bobot"] for r in jenis_daftar()}

def jenis_valid(kode: str) -> bool:
    return db.query_one("SELECT id FROM jenis_kendaraan WHERE kode = ?", (kode,)) is not None
```

### CRUD Functions
```python
def jenis_tambah(kode: str, label: str, label_en: str = "", bobot: int = 10) -> dict:
    """Tambah jenis + buat kapasitas 0 di SEMUA lokasi."""
    kode = (kode or "").strip().lower()
    label = (label or "").strip()
    if not kode or not label:
        raise ValueError("Kode dan label wajib diisi")
    if not re.fullmatch(r"[a-z0-9_]+", kode):
        raise ValueError("Kode hanya huruf kecil, angka, garis bawah")
    if jenis_valid(kode):
        raise ValueError("Kode jenis sudah dipakai")
    urutan = (db.query_one("SELECT COALESCE(MAX(urutan),0)+1 AS n FROM jenis_kendaraan") or {}).get("n", 1)
    db.execute(
        "INSERT INTO jenis_kendaraan (kode, label, label_en, urutan, bobot) VALUES (?, ?, ?, ?, ?)",
        (kode, label, label_en or "", urutan, max(1, int(bobot))),
    )
    # Auto-create kapasitas rows for ALL locations
    for l in db.query("SELECT id FROM lokasi"):
        db.execute(
            "INSERT OR IGNORE INTO kapasitas (id_lokasi, jenis_kendaraan, kapasitas_maks) VALUES (?, ?, 0)",
            (l["id"], kode),
        )
    return db.query_one("SELECT * FROM jenis_kendaraan WHERE kode = ?", (kode,))

def jenis_ubah(kode: str, label: str = None, label_en: str = None,
               bobot: int = None, urutan: int = None, aktif: bool = None) -> dict:
    """Ubah atribut jenis kendaraan."""
    if not jenis_valid(kode):
        raise ValueError("Jenis kendaraan tidak ditemukan")
    set_clauses = []
    params = []
    for field, value in [("label", label), ("label_en", label_en), ("bobot", bobot), ("urutan", urutan), ("aktif", aktif)]:
        if value is not None:
            if field == "label" and not str(value).strip():
                raise ValueError("Label wajib diisi")
            set_clauses.append(f"{field} = ?")
            params.append(value if field != "aktif" else (1 if value else 0))
    if set_clauses:
        db.execute(f"UPDATE jenis_kendaraan SET {', '.join(set_clauses)} WHERE kode = ?", (*params, kode))
    return db.query_one("SELECT * FROM jenis_kendaraan WHERE kode = ?", (kode,))

def jenis_hapus(kode: str) -> None:
    """Hapus jenis + kapasitas semua lokasi + ikon pengaturan."""
    if not jenis_valid(kode):
        raise ValueError("Jenis kendaraan tidak ditemukan")
    db.execute("DELETE FROM kapasitas WHERE jenis_kendaraan = ?", (kode,))
    db.execute("DELETE FROM pengaturan_app WHERE kunci = ?", (f"ikon_{kode}",))
    db.execute("DELETE FROM jenis_kendaraan WHERE kode = ?", (kode,))
```

### API Endpoints (app/main.py)
```python
@app.get("/api/jenis")
async def api_jenis_list(request: Request):
    _user(request)
    out = []
    for r in logic.jenis_daftar():
        ikon = db.query_one("SELECT nilai FROM pengaturan_app WHERE kunci = ?", (f"ikon_{r['kode']}",))
        out.append({**r, "ikon": ikon["nilai"] if ikon else ""})
    return out

@app.post("/api/jenis")
async def api_jenis_tambah(body: dict, request: Request):
    _admin_saja(request)
    try:
        hasil = logic.jenis_tambah(
            str(body.get("kode", "")), str(body.get("label", "")),
            str(body.get("label_en", "")), int(body.get("bobot", 10)),
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    await _broadcast_langsung()
    return hasil

@app.put("/api/jenis/{kode}")
async def api_jenis_ubah(kode: str, body: dict, request: Request):
    _admin_saja(request)
    try:
        hasil = logic.jenis_ubah(
            kode,
            label=body.get("label"),
            label_en=body.get("label_en"),
            bobot=int(body["bobot"]) if body.get("bobot") is not None else None,
            urutan=int(body["urutan"]) if body.get("urutan") is not None else None,
            aktif=body.get("aktif"),
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    await _broadcast_langsung()
    return hasil

@app.delete("/api/jenis/{kode}")
async def api_jenis_hapus(kode: str, request: Request):
    _admin_saja(request)
    try:
        logic.jenis_hapus(kode)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    await _broadcast_langsung()
    return {"ok": True}
```

### Admin UI (Single Page)
- Form tambah: kode, label, label_en, bobot
- Table: urutan (editable), ikon (preview + upload), kode, label, label_en, bobot, switch aktif, simpan/hapus
- Event delegation on `#tabelJenis` for click (simpan/hapus) and change (switch aktif, upload ikon)

### Replace Hardcoded Lists
```python
# Before (hardcoded)
JENIS_LIST = ["mobil", "motor", "bus"]
for jenis in JENIS_LIST: ...

# After (dynamic)
for jenis in jenis_kode_list(): ...
# Simulator weight
bobot = logic.jenis_bobot_map()
jenis = random.choices(list(bobot), weights=list(bobot.values()))[0]
# Validation
if not jenis_valid(jenis): raise ValueError(...)
```