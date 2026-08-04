# Auth guard query-token bug — transcript & verified matrix

Project: `C:\REST AREA MONITORING SYSTEM` (FastAPI + SQLite WAL + YOLOv8n).

## Bug: valid ?token= rejected with 401 by nested guard

Symptom (uvicorn access log, same token, same camera):

```
GET /api/kamera/1/stream?token=<petugas>  → 200 OK      # header ALSO sent
GET /api/kamera/1/stream?token=<admin>    → 401 Unauthorized  # query only
GET /api/kamera/1/stream?token=<petugas>  → 401 Unauthorized  # query only
```

`curl -v` confirmed the query string arrived intact
(`GET /api/kamera/1/stream?token=23d3... HTTP/1.1`). Python urllib repro also 401.
So the token WAS in `request.query_params` — the second guard simply never read it.

Root cause chain:
1. `_user_alt(request, token)` — reads `X-Token` header OR query → PASSED.
2. `_user_lokasi(request, lokasi_id)` — reads `X-Token` header ONLY → token=None → 401.
   (Called by the stream handler with no token arg.)

Fix (3 edits, same pattern):
- `_admin_alt(request, token=None)` — `_user_alt` + forced-password check.
- `_user_lokasi(request, lokasi_id, token=None)` — calls `_admin_alt(request, token)`.
- stream handler: `_user_lokasi(request, k["id_lokasi"], token)`.

Verified after fix: petugas own-location stream → 200; petugas other-location
stream → 403 (RBAC still enforced, not just auth).

Lesson: when a request can authenticate via TWO channels (header OR query), every
nested auth/RBAC helper must accept and forward the same token. Grep for
`_user_lokasi(request` / `_admin(request` calls that don't pass `token` when the
endpoint declares a `token: str | None = Query(None)` param.

## Verified RBAC test matrix (server port 9099, temp users)

| Request | Token | Result |
|---|---|---|
| GET /api/kamera | none | 401 |
| GET /api/kamera/semua | petugas (hak [1]) | 200, only lokasi-1 cameras |
| GET /api/kamera/3/snapshot (lokasi 2) | petugas | 403 |
| GET /api/kamera/3/stream?token= (lokasi 2) | petugas | 403 |
| GET /api/kamera/1/stream?token= (lokasi 1) | petugas | 200 |
| PUT /api/retensi | petugas | 403 "Hanya admin" |
| PUT /api/retensi | admin | 200 |
| POST /api/kamera | petugas | 403 |
| POST /api/deteksi | petugas | 403 |
| PUT /api/pengguna/5/akses | admin | 200 → me reflects new hak_akses |
| DELETE /api/pengguna/<self> | admin | 400 (self-delete guard) |
| GET /api/deteksi | admin | mode_efektif simulator, workers = 6 |

Other observations:
- Snapshot 404 = no cached frame yet (simulator mode / camera dead) — NOT an error.
- `/api/me` returns `hak_akses: null` for admin (null = all), `[1,2]` for scoped petugas.
- Session store in-memory: after server restart ALL tokens die; re-login (this bit
  the admin token mid-test: 200 → 401 after restart).
- Engine status shape: `{running, mode, mode_efektif, interval, fps,
  deteksi_total, pesan, yolo_tersedia, kamera: [{id, nama, fps, status, deteksi,
  pesan, live}]}` — `yolo_tersedia` true even in simulator fallback (model loaded).
