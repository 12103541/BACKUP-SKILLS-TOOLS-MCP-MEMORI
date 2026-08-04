# Role permission map (F-22, 2026-08-03)

Endpoint → permission key gate (`_izin(request, "<kunci>")`) in app/main.py.
Keep this in sync when adding/removing endpoints.

## kelola_kamera (16)
- POST /api/kamera
- PUT /api/kamera/{kamera_id}
- DELETE /api/kamera/{kamera_id}
- DELETE /api/kamera
- POST /api/kamera/{kamera_id}/status
- POST /api/kamera/{kamera_id}/test
- POST /api/pengaturan/ikon/{jenis}

## kelola_vms (5)
- POST /api/vms
- PUT /api/vms/{vms_id}
- DELETE /api/vms/{vms_id}
- DELETE /api/vms
- POST /api/vms/{vms_id}/token

## kelola_lokasi (2)
- POST /api/lokasi
- DELETE /api/lokasi/{lokasi_id}

## kelola_jenis (4)
- POST /api/jenis
- PUT /api/jenis/{kode}
- DELETE /api/jenis/{kode}
- POST /api/pengaturan/ikon/{jenis}  (ikon upload = kelola_jenis, not kelola_kamera)

## kelola_kapasitas (0 gates — uses `_user_lokasi` only)
- PUT /api/kapasitas/{jenis}/maks
- PUT /api/kapasitas/{jenis}/koreksi
- PUT /api/kapasitas/{jenis}/aktif
NOTE: these are location-RBAC only (any logged-in user with lokasi access). No
permission key applied — decided 2026-08-03 since petugas needs daily koreksi.

## kelola_deteksi (1)
- POST /api/deteksi

## pengaturan_umum (2)
- PUT /api/player-template
- PUT /api/retensi
(GET endpoints stay `_user`/`_admin` read-only)

## kelola_notifikasi (3)
- GET /api/notifikasi
- PUT /api/notifikasi
- POST /api/notifikasi/uji

## kelola_lisensi (1)
- PUT /api/lisensi        (was `_admin(request)` + manual peran check)
- GET /api/lisensi stays `_user` (read-only)

## kelola_pengguna (6)
- GET /api/pengguna
- POST /api/pengguna
- PUT /api/pengguna/{id}/akses
- PUT /api/pengguna/{id}/izin
- DELETE /api/pengguna/{id}      (cascades pengguna_permissions + hak_akses)
- POST /api/pengguna/{id}/reset-password
- GET /api/izin                  (list + role defaults)

## Unchanged auth (still peran-based)
- GET /api/state, /api/state-semua, /api/kamera/semua, /api/vms list,
  /api/lisensi, /api/me, /api/jenis GET → `_user` / `_user_lokasi`
- Public endpoints (/, /player/{token}, heartbeat, state) — no auth

## Escalation guard
POST /api/pengguna with peran="admin" from a non-admin (even with
kelola_pengguna) → 403. Admin account permissions are immutable (has_permission
short-circuits True; override endpoints reject target peran==admin).

## Verification recipe (E2E, read-only-safe after consent)
1. login admin → POST /api/pengguna {petugas} → login petugas
2. GET /api/me → izin should be [] (default) → PUT /api/kapasitas/mobil/maks → 200 (user_lokasi gate only)
3. DELETE /api/kamera/1 → 403 (no kelola_kamera)
4. PUT /api/pengguna/{id}/izin {kunci:{kelola_kamera:true}} → now 200 on kamera ops
5. clean up: DELETE /api/pengguna/{id} (verify cascade: pengguna_permissions rows gone)
