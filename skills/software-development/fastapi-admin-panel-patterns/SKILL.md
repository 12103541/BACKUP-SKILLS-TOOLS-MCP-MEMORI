---
name: fastapi-admin-panel-patterns
description: FastAPI admin patterns for IoT with multi-location settings.
trigger: Building FastAPI admin panels for monitoring/IoT with per-location settings and public displays.
---
# FastAPI Admin Panel Patterns

## Scope
Patterns for FastAPI + SQLite + Jinja2 admin panels with:
- Multi-location/tenant settings (per-location DB rows + pengaturan_app key-value)
- Dynamic entity CRUD (vehicle types, device types) with auto-seed on migration
- Public display rendering via polling /api/state (5s interval)
- VMS player integration (token auth, heartbeat, iframe scaling)
- File upload via base64 data URL to static files
- Cache busting via query params (?v=N)

## Core Patterns

### 1. Multi-Location Settings
**Tables**: pengaturan_umum (per-location cols) + pengaturan_app (key-value: ikon_{type}, logo_papan_{lid})
**Logic**: get_pengaturan(lokasi_id) merges both; set_pengaturan() handles both tables
**API**: GET /api/state?lokasi=N returns merged settings for public display

### 2. Dynamic Entity CRUD (Vehicle Types)
**Table**: jenis_kendaraan (kode UNIQUE, label, label_en, urutan, bobot, aktif)
**Migration**: Seed 3 defaults in migrate(); rebuild kapasitas/riwayat_* tables dropping CHECK constraints
**Logic helpers**: jenis_daftar(), jenis_kode_list(), jenis_label(), jenis_valid(), jenis_bobot_map()
**CRUD functions**: jenis_tambah() (auto-creates kapasitas rows for ALL locations), jenis_ubah(), jenis_hapus() (cascades kapasitas + ikon)
**Admin UI**: Single page - form tambah + table with inline edit (label, label_en, bobot, urutan, switch aktif, upload ikon, hapus)

### 3. Public Display Polling
**Endpoint**: GET /api/state?lokasi=N - pengaturan, kapasitas[], kamera[], alert[], waktu
**Client**: static/public.js - render(data) called every 5s; computes tersedia = maks - (masuk - keluar)
**Template**: templates/public.html - header (logo + PARKIR TERSEDIA + pill lokasi), 2x2 grid kartu per jenis, footer status
**Kartu**: split bagian-ikon | bagian-slot (angka + caption SLOT + sub-slot tersedia / maks)
**Media query**: @media (max-width: 640px) for VMS 512x288 - all sizing in CSS, no JS scaling

### 4. VMS Player Integration
**Tables**: vms (access_token 64hex, token_expires_at, last_heartbeat, is_online, player_url, status CHECK includes maintenance)
**Auth**: Token = credential; GET /api/player/{token}/info (public), POST /api/player/{token}/heartbeat (60s)
**Player page**: GET /player/{token} - kiosk iframe to public display, heartbeat 60s, reload 5m
**Scaling**: transform-origin: 0 0; scale = min(1, vw/512, vh/288) - never upscale, anchor top-left (matches C:\VMS reference)

### 5. File Upload to Static Files
**Admin**: input type=file - FileReader.readAsDataURL() - base64 in JSON body
**API**: PUT /api/pengaturan with logo_papan: "data:image/svg+xml;base64,..."
**Server**: Decode base64, detect SVG vs PNG, save to static/ikon/logo_papan_{lid}.svg|png, upsert pengaturan_app
**Limit**: 200KB; SVG preferred for crisp rendering at 64x64

### 6. Cache Busting
**Pattern**: href="/static/style.css?v=21", src="/static/public.js?v=12"
**Update**: sed -i 's|style.css?v=19|style.css?v=20|' templates/public.html after CSS/JS changes
**Version**: Increment per file; both admin.html and public.html track separately

## Pitfalls & Fixes

| Issue | Fix |
|-------|-----|
| SQLite CHECK constraint blocks new vehicle types | Rebuild table: ALTER RENAME, CREATE TABLE without CHECK, INSERT SELECT, DROP |
| Migration runs twice (dirty state) | Idempotent: INSERT OR IGNORE seed; _kolom_ada() checks before ALTER |
| Logo not showing on public page | render() must set logoDiv.style.backgroundImage from pengaturan.logo_papan |
| Player iframe tiny on large screen | Remove min(1, ...) cap for full-screen; keep for true 512x288 |
| Icon upload overwrites wrong file | Use per-location filename logo_papan_{lid}.svg; CSS background: url() not img |
| Hardcoded JENIS_LIST in simulator/logic | Replace with jenis_kode_list() / jenis_bobot_map() from DB |

## References
- references/migration-rebuild-check.md - SQLite CHECK constraint rebuild recipe
- references/vms-player-scaling.md - Transform-origin 0 0 scaling formula
- references/dynamic-entity-crud.md - Vehicle type CRUD with auto-kapasitas
- references/public-display-polling.md - 5s polling render pattern

## Verification Checklist
- [ ] python -m py_compile app/*.py passes
- [ ] node --check static/*.js passes
- [ ] GET /api/state?lokasi=N returns logo_papan per location
- [ ] Admin tab Jenis Kendaraan CRUD works end-to-end
- [ ] Player /player/{token} loads, heartbeats, shows ONLINE badge
- [ ] Public display 512x288: no scrollbar, kartu 2x2, logo 64x64 top-left