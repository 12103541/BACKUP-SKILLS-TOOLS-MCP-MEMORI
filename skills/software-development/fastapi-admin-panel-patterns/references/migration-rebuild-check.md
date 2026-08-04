# SQLite CHECK Constraint Rebuild Recipe

## Problem
SQLite doesn't support `ALTER TABLE ... DROP CHECK`. When a table has a `CHECK (jenis IN (...))` constraint, new entity types cannot be inserted.

## Solution: Table Rebuild Pattern

```python
def _ada_check(conn, tabel: str) -> bool:
    """Cek apakah tabel memiliki CHECK constraint pada kolom jenis."""
    row = conn.execute(
        "SELECT sql FROM sqlite_master WHERE type='table' AND name=?", (tabel,)
    ).fetchone()
    return bool(row and "CHECK (jenis" in (row[0] or ""))

def _kolom_ada(conn, tabel: str, kolom: str) -> bool:
    """Cek apakah kolom ada di tabel."""
    row = conn.execute(f"PRAGMA table_info({tabel})").fetchall()
    return any(r[1] == kolom for r in row)

# Rebuild kapasitas (has id_lokasi + unique constraint)
if _ada_check("kapasitas"):
    conn.execute("ALTER TABLE kapasitas RENAME TO kapasitas_lama")
    conn.execute("""
        CREATE TABLE kapasitas (
            id              INTEGER PRIMARY KEY,
            id_lokasi       INTEGER NOT NULL DEFAULT 1,
            jenis_kendaraan TEXT NOT NULL,
            kapasitas_maks  INTEGER NOT NULL DEFAULT 0,
            masuk           INTEGER NOT NULL DEFAULT 0,
            keluar          INTEGER NOT NULL DEFAULT 0,
            diperbarui_pada TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            aktif           INTEGER NOT NULL DEFAULT 1,
            UNIQUE (id_lokasi, jenis_kendaraan)
        )
    """)
    kolom = "id, id_lokasi, jenis_kendaraan, kapasitas_maks, masuk, keluar, diperbarui_pada"
    if _kolom_ada(conn, "kapasitas_lama", "aktif"):
        kolom += ", aktif"
    conn.execute(f"INSERT INTO kapasitas ({kolom}) SELECT {kolom} FROM kapasitas_lama")
    conn.execute("DROP TABLE kapasitas_lama")

# Rebuild riwayat_deteksi (auto-increment id)
if _ada_check("riwayat_deteksi"):
    conn.execute("ALTER TABLE riwayat_deteksi RENAME TO riwayat_deteksi_lama")
    conn.execute("""
        CREATE TABLE riwayat_deteksi (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            id_lokasi INTEGER NOT NULL DEFAULT 1,
            waktu     TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            jenis     TEXT NOT NULL,
            arah      TEXT NOT NULL CHECK (arah IN ('masuk','keluar')),
            id_kamera INTEGER,
            FOREIGN KEY (id_kamera) REFERENCES kamera(id)
        )
    """)
    conn.execute(
        "INSERT INTO riwayat_deteksi (id, id_lokasi, waktu, jenis, arah, id_kamera) "
        "SELECT id, id_lokasi, waktu, jenis, arah, id_kamera FROM riwayat_deteksi_lama"
    )
    conn.execute("DROP TABLE riwayat_deteksi_lama")

# Rebuild riwayat_koreksi (auto-increment id)
if _ada_check("riwayat_koreksi"):
    conn.execute("ALTER TABLE riwayat_koreksi RENAME TO riwayat_koreksi_lama")
    conn.execute("""
        CREATE TABLE riwayat_koreksi (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            id_lokasi   INTEGER NOT NULL DEFAULT 1,
            waktu       TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            jenis       TEXT NOT NULL,
            nilai_lama  INTEGER NOT NULL,
            nilai_baru  INTEGER NOT NULL,
            id_pengguna INTEGER
        )
    """)
    conn.execute(
        "INSERT INTO riwayat_koreksi (id, id_lokasi, waktu, jenis, nilai_lama, nilai_baru, id_pengguna) "
        "SELECT id, id_lokasi, waktu, jenis, nilai_lama, nilai_baru, id_pengguna FROM riwayat_koreksi_lama"
    )
    conn.execute("DROP TABLE riwayat_koreksi_lama")
```

## Key Points
- Use `_kolom_ada()` to handle dirty migrations where old table may have extra columns
- Keep `INSERT` column list explicit to match new schema
- `AUTOINCREMENT` only needed for tables with auto PK (riwayat_*); `kapasitas` uses composite unique
- Run in `migrate()` after creating new `jenis_kendaraan` table