# SQLite CHECK Constraint Removal Pattern

**Context**: SQLite cannot `ALTER TABLE ... DROP CHECK`. To remove a CHECK (e.g., `jenis IN ('mobil','motor','bus')`) so new values can be inserted, you must rebuild the table.

## Pattern (used in `database.py::migrate()` for kapasitas, riwayat_deteksi, riwayat_koreksi, and vms)

```python
def _ada_check(tabel: str) -> bool:
    row = conn.execute(
        "SELECT sql FROM sqlite_master WHERE type='table' AND name=?", (tabel,)
    ).fetchone()
    return bool(row and "CHECK (jenis" in (row[0] or ""))

# REBUILD TABLE WITHOUT CHECK
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
            aktif           INTEGER NOT NULL DEFAULT 1,  -- column added later!
            UNIQUE (id_lokasi, jenis_kendaraan)
        )
    """)
    # INSERT SELECT with dynamic column list (old table may have gained columns)
    kolom = "id, id_lokasi, jenis_kendaraan, kapasitas_maks, masuk, keluar, diperbarui_pada"
    if _kolom_ada(conn, "kapasitas_lama", "aktif"):
        kolom += ", aktif"
    conn.execute(f"INSERT INTO kapasitas ({kolom}) SELECT {kolom} FROM kapasitas_lama")
    conn.execute("DROP TABLE kapasitas_lama")
```

## Why dynamic columns matter
- Old `kapasitas` table was seeded without `aktif` column
- Later migration added `aktif` column
- `SELECT * FROM kapasitas_lama` would include `aktif` (8 cols) but new `CREATE TABLE` might only declare 7
- Result: `sqlite3.OperationalError: table kapasitas has 7 columns but 8 values were supplied` → server fails at startup
- Fix: always detect extra columns with `_kolom_ada()` and include them in the INSERT column list

## Same pattern used for
1. `kapasitas` — remove CHECK(jenis), add dynamic columns
2. `riwayat_deteksi` — remove CHECK(jenis)
3. `riwayat_koreksi` — remove CHECK(jenis)
4. `vms` table — rebuild when `access_token` column missing (cannot ALTER CHECK constraint on status)

## Key takeaway
Any time you need to change a CHECK constraint in SQLite, you must:
1. Detect existing CHECK via `sqlite_master`
2. Rename old table
2. Create new table WITHOUT CHECK (but WITH all columns the old table accumulated)
3. Dynamic INSERT SELECT (list columns explicitly, detect extras)
4. Drop old table
5. Clean up any leftover `*_lama` tables from failed attempts before retrying