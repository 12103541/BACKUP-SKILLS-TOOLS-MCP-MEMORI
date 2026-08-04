---
name: fastapi-monitoring-app
description: Build/test FastAPI camera/VMS monitoring apps.
---

# FastAPI Monitoring App (camera/VMS) — patterns & pitfalls

Covers building and testing FastAPI + SQLite + WebSocket monitoring applications:
camera feeds (RTSP), occupancy counters, admin dashboards, device licensing.
Reference project: `C:\REST AREA MONITORING SYSTEM` (YOLOv8n detection, WAL SQLite).

## Proven architecture patterns

- **Device model + license quota**: `kamera` table with `host, port, kanal, username,
  password_enc, zona, merk, last_seen`; unique index `(host, kanal)` (N devices per
  gate, different channels). `lisensi` table (edisi, kuota_device, single row id=1).
  Quota check BEFORE insert on POST — reject with 400 when `terpakai >= kuota_device`.
- **Encrypt device passwords**: Fernet (cryptography lib), key file `data/device.key`
  auto-created on first use. Never store plaintext. Public payloads must strip
  username/password/host/port — rebuild clean `rtsp://host:port/kanal` for display.
- **Per-device worker engine**: one worker thread per camera (own VideoCapture +
  own YOLO model), so one dead camera never blocks others. Cache latest frame as
  JPEG (max 1 encode/sec, downscale to ~960px) for snapshot + MJPEG endpoints —
  avoids opening a second RTSP connection per viewer (cheap cameras drop).
  TCP pre-check (`socket.create_connection(host, port, timeout=2)`) BEFORE
  `cv2.VideoCapture` — FFMPEG timeout isn't reliable; retry cooldown ~10s.
- **RBAC per location**: `hak_akses` table (id_pengguna, id_lokasi); helper chain
  `_user → _admin → _admin_saja` (role) and `_user_lokasi` (location scope, admin
  bypass, `lokasi_diizinkan` returning None = all locations). Apply location guard
  to EVERY data endpoint (riwayat, ekspor, pengaturan, kapasitas), not just camera.
- **Live monitoring list endpoint**: `GET /api/kamera/semua` returns every device
  the user may see — build the SQL with `WHERE id_lokasi IN (?,?,…)` placeholders
  when `lokasi_diizinkan` is a list, no filter when None (admin). Attach `live`
  bool per camera from the frame cache. Snapshot endpoint returns 404 when no
  frame cached yet (simulator mode / camera dead) — frontend falls back to a
  "Tidak ada stream" placeholder, that's expected, not a bug.
- **Data retention (purge)**: single-row `pengaturan` key `retensi_hari` (default
  90); `purge_riwayat()` deletes old rows from riwayat_deteksi/kapasitas/koreksi/
  alert in ONE call; run at startup AND from a `while True: sleep(6*3600)` asyncio
  task (`asyncio.create_task` in lifespan). API: GET any-authenticated, PUT
  admin-only (validate `int`, clamp range).
- **Config/notifications/device CRUD = admin-only**; petugas gets read + own-location
  mutations. `GET /api/kamera` must be auth'd (leaks camera topology otherwise).

## PITFALL: query-token auth only passes the FIRST guard (critical)

Endpoints serving `<img src>` (MJPEG stream, snapshot) can't send `X-Token` headers,
so accept `?token=` in the query. Bug seen: token passed `_user_alt` (reads header OR
query) but the NEXT nested guard `_user_lokasi` read the header only → **401 on a
valid token** (log shows 200 for header+query, 401 for query-only on same token).

Fix: propagate the token param through EVERY nested guard:

```python
def _user_alt(request, token=None):          # header OR query
    user = auth.cek_token(request.headers.get("X-Token") or token)
    ...
def _admin_alt(request, token=None):          # same token source
    user = _user_alt(request, token)
    ...
def _user_lokasi(request, lokasi_id, token=None):
    user = _admin_alt(request, token)          # ← pass token, not bare request
    ...
@app.get("/api/kamera/{id}/stream")
async def api_kamera_stream(kamera_id: int, request: Request, token: str | None = Query(None)):
    _user_lokasi(request, k["id_lokasi"], token)   # ← MUST forward token
```

Diagnosis recipe: `curl -v` to confirm query arrives; check uvicorn access log for
the 401 line; compare header-auth vs query-auth on the SAME token.

## Accumulated simulator counters → display "PENUH forever" (needs reset)

Symptom: display works and numbers tick, but EVERY card shows 0 tersedia / PENUH
and the user reports "display tidak jalan". Root cause: simulator counters
(masuk/keluar) accumulate since first boot and exceed kapasitas_maks (e.g. mobil
870 > 100) → `tersedia = max(0, maks - terpakai)` = 0 forever. Engine is fine;
the data is saturated. Verify by diffing /api/state twice (counters DO move) —
then it's saturation, not a dead engine.

Fix: admin-only reset endpoint (POST /api/deteksi {"reset": true}):
1. `UPDATE kapasitas SET masuk=0, keluar=0` (all locations)
2. `DELETE FROM riwayat_deteksi/koreksi/kapasitas/alert` (clears charts too)
3. `engine.deteksi_total = 0`; then `_broadcast_langsung()` for live boards.
Frontend: red button + `confirm()`, admin-only (`hidden` toggled from peran).
Verify: counters 0, re-poll after ~10s — values climbing from 0 = engine re-feeds.

## Per-location capacity settings: total slots, per-type active switch, custom icons

Recurring VMS/display feature trio — full impl in `references/capacity-settings-reset.md`:
- Total capacity: `kapasitas_total` column on `pengaturan_umum` (0 = auto = sum of
  active types' kapasitas_maks). Public board shows this as "Total slot" when > 0.
- Per-type on/off: `aktif` column on `kapasitas` (default 1). Non-active types are
  HIDDEN from the public board (`filter(k => k.aktif !== false)`) but rows stay in
  DB (counters preserved). Endpoint `PUT /api/kapasitas/{jenis}/aktif`.
- Custom icons: `POST /api/pengaturan/ikon/{jenis}` (admin-only, UploadFile,
  png/jpg/webp/gif/svg ≤1 MB) → `static/ikon/ikon_{jenis}.{ext}`, path stored in
  key-value `pengaturan_app` (`ikon_{jenis}`); state joins it per type, board
  renders `<img src="/static/...">` when set, else inline SVG symbol fallback.
- Migration: plain `ALTER TABLE ... ADD COLUMN` behind `_kolom_ada` guards — no
  table rebuild for these.

## More pitfalls

- **fetch wrapper + FormData**: an api() helper that ALWAYS sets
  `Content-Type: application/json` breaks multipart uploads (FastAPI → 422; the
  browser must generate the boundary). Guard:
  `if (!(opsi.body instanceof FormData)) headers["Content-Type"] = "application/json";`
- **`db.execute()` returns lastrowid, NOT rowcount** — never branch on its return
  to detect "0 rows updated". SELECT the row first, then UPDATE.
- **Native `confirm()` blocks every browser tool** (navigate/console/press all
  fail with "dialog is blocking the page") and the harness has no dialog-accept
  tool. Test confirm-gated buttons by stubbing `window.confirm = () => true`
  BEFORE clicking (fresh page load), or have the user click in their browser.

## Testing pitfalls (Windows git-bash / MSYS)

- **MJPEG streams never terminate** → `curl` hangs forever. Use `--max-time 4` and
  judge auth by the first response code; a live stream shows 200 then times out
  (expected, not a bug).
- **MSYS curl exits 23 on piped output** → write body to a file with `-o`, process
  the file in a separate command.
- **In-memory session store**: restarting the server invalidates ALL tokens silently
  (old tokens 401). Re-login after every restart before continuing tests.
- **Session-scoped env vars don't persist between terminal calls** — `TOK=$(...)`
  assignment in one call is gone in the next; export + read file in the same call,
  or re-derive the token from a saved login JSON each call.
- **MSYS `taskkill` needs SINGLE slashes**: `taskkill //PID 20520 //F` → "Invalid
  argument/option"; use `taskkill /PID 20520 /F` (works in git-bash). Verify with
  `netstat -ano | grep ":PORT" | grep LISTEN` before declaring the port free.
- **MSYS `/tmp` ≠ Python temp dir**: files written by curl to `/tmp/x.json` are
  at `C:\Users\<user>\AppData\Local\Temp\x.json` for Python (`open('/tmp/...')`
  from execute_code → FileNotFoundError). Use `C:/Users/.../Temp/` in Python.

## PITFALL: engine "Stop/Jeda" kills the simulator fallback → frozen capacity

Symptom reported as "mode simulator tidak jalan, data kapasitas parkir beku":
`GET /api/deteksi` shows `running=False` while `mode_efektif=simulator`. Root
cause: the admin "⏸ Jeda" button POSTs `{"running":false}` →
`engine.stop()` stops the WHOLE orchestrator thread — including the simulator
that keeps the dashboard alive. Status still *says* simulator, but nothing ticks.

Fix both sides:
- Backend: only allow stop when a real YOLO stream is active, OR expose a
  separate `engine_paused` flag that leaves simulator ticks running. Simplest
  correct: don't let `running=false` stop simulator mode.
- Frontend guard (admin.js):
  ```js
  if (d.mode_efektif === "simulator" && d.running) {
    notifikasi("Mode simulator selalu berjalan — jeda hanya untuk YOLO nyata", false);
    return;
  }
  ```
Diagnosis: `running=False` + kapasitas frozen = orchestrator dead. Verify engine
in isolation (standalone script): `DetectionEngine(); start(); sleep(3); status()`
→ `running=True` proves code is fine, server-side stop was the cause. Note the
lifespan `engine.start()` on boot means a full server restart auto-heals.

## Simulator-liveness verification recipe

Simulator is silent — prove it ticks, don't assume:
1. `GET /api/state`, snapshot `kapasitas[*].masuk/keluar/terpakai`.
2. Sleep 8s, fetch again, diff. Any counter moved = engine alive.
3. Or `GET /api/deteksi` twice: `deteksi_total` increment > 0 = ticking.
Key on `jenis` not `id` when diffing kapasitas (payload has no id field).

## Password recovery (forgotten admin password)

Hash never matches what the user "remembers". Verify candidates directly against
the DB before touching anything:
```python
from app import auth, database as db
db.init_db()
h = db.query_one("SELECT password_hash FROM pengguna WHERE username='admin'")["password_hash"]
for p in ("password123", "password 123", "Password123", ...):
    if auth.verify_password(p, h): print("COCOK", p)
```
If nothing matches (user misremembered — happens), reset explicitly and report
the change: `auth.reset_password(user_id, "password123", paksa_ganti=False)`.
Then confirm via real HTTP login (200), not just verify_password. Session store
is in-memory: old tokens die on server restart — re-login after reset.

## Non-destructive test workflow (don't touch real admin)

1. Temp script inserting test users via app modules: `auth.buat_pengguna(...)` +
   `logic.set_hak_akses(...)`. NOTE: `auth.buat_pengguna` may return a cursor, not
   the new id — always re-fetch id by username: `db.query_one("SELECT id FROM
   pengguna WHERE username = ?", ...)`. Wrong id → orphan `hak_akses` row (id 0).
2. Test matrix: no-token → 401; petugas own-location → 200; petugas other-location
   → 403; petugas admin-only endpoint → 403; admin → 200.
3. Cleanup via API DELETE; admin deleting SELF returns 400 (guard) — delete test
   admins while logged in as the real admin. Deleting a user via API leaves
   ORPHAN `hak_akses` rows (no FK cascade) — sweep with
   `DELETE FROM hak_akses WHERE id_pengguna NOT IN (SELECT id FROM pengguna)`.
   Prefer API calls over raw `python -c` DB DELETEs: direct DB writes hit the
   terminal approval gate and skip app invariants.
4. After code changes: `python -m py_compile app/*.py` + `node --check static/*.js`
   (lint may false-fail on MSYS path quoting — ignore, run node --check directly).

See `references/auth-guard-query-token.md` for the full bug transcript and test matrix.
