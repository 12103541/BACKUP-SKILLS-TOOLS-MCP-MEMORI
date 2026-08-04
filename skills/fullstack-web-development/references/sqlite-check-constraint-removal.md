# SQLite CHECK Constraint Removal Pattern

## Problem
SQLite does not support `ALTER TABLE ... DROP CHECK`. When you need to remove CHECK constraints (e.g., hardcoded enums like `CHECK (jenis IN ('mobil','motor','bus'))`) to allow dynamic values from a new table, you must rebuild the table.

## Solution: Rebuild Table Without CHECK

### Step 1: Check if CHECK exists
```python
def _ada_check(tabel: str) -> bool:
    row = conn.execute(
        "SELECT sql FROM sqlite_master WHERE type='table' AND name=?", (tabel,)
    ).fetchone()
    return bool(row and "CHECK (jenis" in (row[0] or ""))
```

### Step 2: Check for column differences
```python
def _kolom_ada(conn, tabel: str, kolom: str) -> bool:
    row = conn.execute(f"PRAGMA table_info({tabel})").fetchall()
    return any(r[1] == kolom for r in row)
```

### Step 3: Rebuild
```python
# Rename old table
conn.execute(f"ALTER TABLE {tabel} RENAME TO {tabel}_lama")

# Create new table WITHOUT CHECK constraint
conn.execute(f"""
    CREATE TABLE {tabel} (
        id              INTEGER PRIMARY KEY,
        id_lokasi       INTEGER NOT NULL DEFAULT 1,
        jenis_kendaraan TEXT NOT NULL,  -- no CHECK
        kapasitas_maks  INTEGER NOT NULL DEFAULT 0,
        masuk           INTEGER NOT NULL DEFAULT 0,
        keluar          INTEGER NOT NULL DEFAULT 0,
        diperbarui_pada TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        UNIQUE (id_lokasi, jenis_kendaraan)
    )
""")

# Build column list for INSERT (handle old columns that may not exist in new)
kolom = "id, id_lokasi, jenis_kendaraan, kapasitas_maks, masuk, keluar, diperbarui_pada"
if _kolom_ada(conn, f"{tabel}_lama", "aktif"):
    kolom += ", aktif"

conn.execute(f"INSERT INTO {tabel} ({kolom}) SELECT {kolom} FROM {tabel}_lama")

# Drop old table
conn.execute(f"DROP TABLE {tabel}_lama")
```

## Key Patterns

### Handling Column Mismatches
Old table may have columns not in new schema (e.g., `aktif` column existed in old `kapasitas` but removed in new):
- Use `PRAGMA table_info()` to detect columns
- Dynamically build INSERT column list
- Only include columns that exist in BOTH old and new

### Auto-Seed New Enum Values
When creating the new `jenis_kendaraan` table, seed defaults AND create capacity rows for ALL existing locations:
```python
# In migration/rebuild
for jenis in ["mobil", "motor", "bus"]:
    for lok in db.query("SELECT id FROM lokasi"):
        db.execute(
            "INSERT OR IGNORE INTO kapasitas (id_lokasi, jenis_kendaraan, kapasitas_maks) VALUES (?, ?, 0)",
            (lok["id"], jenis)
        )
```

### CRUD Integration
Replace all hardcoded enum checks with DB lookups:
```python
# logic.py - central helpers
def jenis_valid(kode: str) -> bool:
    return db.query_one("SELECT 1 FROM jenis_kendaraan WHERE kode=?", (kode,)) is not None

# Usage
if not jenis_valid(jenis):
    raise HTTPException(status_code=400, detail="Jenis kendaraan tidak dikenal")
```

## When to Use
- Removing hardcoded enums for dynamic DB-driven values
- SQLite migration where CHECK blocks new valid values
- Any schema change that requires column addition/removal with CHECK constraints

## Limitations
- Requires exclusive DB access (no concurrent writers)
- All data must be copied (slow for huge tables)
- Must handle column mismatches carefully
- Test on copy first in production

## Related
- Dynamic Enum Replacement (hardcoded → DB-driven)
- Migration with data preservation
- SQLite ALTER TABLE limitations