# CCTV / Parking-Capacity Monitoring (VMS-style) Patterns

Session: REST AREA MONITORING SYSTEM (C:\REST AREA MONITORING SYSTEM) — FastAPI + SQLite (WAL) + YOLOv8n + WebSocket, Indonesian UI. Retrofit dari "2 kamera per lokasi" menjadi model device ala VMS + lisensi kuota + sanitasi payload publik.

## 1. Device model upgrade (kamera -> device ala VMS)

Kolom minimal device kamera RTSP:
`host, port (default 554), username, password_enc, kanal, zona, merk, last_seen` — di samping yang lama: `nama, posisi (masuk/keluar), url_rtsp, garis_deteksi, status`.

- Unik (lokasi, posisi) HARUS dihapus — 1 gate bisa punya N kamera. Ganti `CREATE UNIQUE INDEX ... ON kamera(host, kanal) WHERE host <> ''` (partial index: device tanpa host tidak ikut).
- Migrasi DB lama: `ALTER TABLE ... ADD COLUMN` per kolom + backfill host/port/kanal dengan `urllib.parse.urlparse(url_rtsp)` (scheme -> default port: rtsp=554, http=80, https=443; kanal = path.lstrip('/')).
- Deteksi harus punya `_url_kamera(k)` builder: host+port+kanal+user+password (dekripsi) -> `rtsp://user:pass@host:port/kanal`; fallback `url_rtsp` lama bila host kosong. Ini menjamin device lama (cuma url_rtsp) tetap jalan tanpa edit.

## 2. Enkripsi kredensial device (Fernet)

```python
# app/kripto.py — kunci data/device.key auto-generate saat pertama kali
from cryptography.fernet import Fernet
KEY_PATH = os.path.join(db.DATA_DIR, "device.key")
_fernet = Fernet(_key())          # _key(): baca file atau generate+write
enc = lambda s: _fernet.encrypt(s.encode()).decode() if s else ""
dec = lambda s: _fernet.decrypt(s.encode()).decode() if s else ""
```

- JANGAN pernah kirim `password_enc` ke frontend. Kirim `password_terisi: bool` saja (pop kolom di endpoint).
- Edit: password kosong / sentinel = pertahankan lama (pola sama dgn sentinel `__SIMPAN__` di alerts.py).
- Peringatan ke user: `data/device.key` jangan dihapus — hilang = semua password device tak bisa didekripsi.

## 3. Lisensi / kuota perangkat (model "ukuran VMS")

- Tabel satu baris: `lisensi (id CHECK (id=1), edisi, kuota_device, berlaku_sampai, diupdate_pada)`. Seed `INSERT OR IGNORE ... (1,'STANDARD',16)`.
- `get_lisensi()`: SELECT baris 1 + `COUNT(*) FROM kamera` -> `{...row, terpakai}`.
- Enforce: cek `terpakai >= kuota_device` SEBELUM INSERT di POST /api/kamera -> HTTP 400 dengan pesan "Device x/y (edisi Z)".
- PUT lisensi: admin-only (`peran != 'admin'` -> 403). GET lisensi: cukup login.
- UI: badge `Device 6/16 · STANDARD` di header tab kamera; panel edit di tab Pengaturan.

## 4. Sanitasi payload publik vs admin

Pola dua endpoint:
- `GET /api/state` (publik, tanpa auth) -> kamera lewat `kamera_publik()`: hanya id, nama, posisi, url_rtsp (userinfo dibuang!), garis_deteksi, status.
- `GET /api/kamera` (wajib login) -> SELECT * + password_terisi.

```python
def _santasi_url(url):
    if "@" not in url: return url
    skema, _, sisa = url.partition("://")
    return f"{skema}://{sisa.split('@',1)[1]}" if "@" in sisa else url
```

Pitfall asli yang diperbaiki: url_rtsp bisa berisi `rtsp://user:pass@host/...` dan bocor ke papan publik via WS — siapa pun di jaringan bisa lihat kredensial kamera.

## 5. Detection engine (FastAPI + thread)

- Satu thread worker loop: `cv2.VideoCapture.read()` blocking + inferensi YOLO sequential -> SEMUA kamera diproses berurutan. Bottleneck di >=8 device: 1 kamera lemot = semua lemot. **SUDAH DI-UPGRADE** ke engine paralel (1 thread/kamera + cache frame) — lihat §8.
- Pre-check TCP (`socket.create_connection` timeout 2s) sebelum `cv2.VideoCapture` — hindari hang handshake RTSP (cv2/ffmpeg timeout tidak selalu dihormati).
- Heartbeat: update `last_seen` tiap frame terbaca + saat /test; cooldown retry 10s per kamera gagal; auto-recovery status putus -> aktif.
- Fallback simulator TANPA penanda = bahaya (operator kira data nyata). Mode fallback wajib diberi flag sumber di payload.

## 6. Curl test recipe (MSYS/Windows)

```bash
PORT=9099 python run.py   # background terminal; jangan pakai '&' di foreground
TOKEN=$(curl -s -X POST http://127.0.0.1:9099/api/login -H "Content-Type: application/json" \
  -d '{"username":"X","password":"Y"}' | python -c "import sys,json;print(json.load(sys.stdin)['token'])")
```

- **MSYS quirk**: pipeline `curl ... | python -c` sering exit 23 setelah output sukses — output tetap valid, jangan panik. Untuk body yang perlu dibaca lagi: `curl -s ... -o /tmp/x.json` lalu `cat`.
- Test matrix yang dipakai: 401 tanpa token (GET /api/kamera), 409 duplikat (host,kanal), 200 host sama kanal beda, 400 over-quota, 403 petugas PUT lisensi, sanitasi url publik.
- Cleanup setelah test: DELETE row test (kamera/pengguna), restore lisensi (STANDARD/16), kill server test.
- Server lama di port produksi tetap jalan pakai kode lama setelah edit — restart wajib; cek `netstat -ano | grep LISTENING`.
- Password default sering sudah diganti user — jangan asumsi `admin123`; verifikasi via `auth.verify_password` sebelum test login.

## 7. Prioritas retrofit (urutan yang terbukti)

1. Device model + enkripsi + sanitasi (paling kecil, dampak terbesar, langsung cocok konsep VMS) — ✅ done
2. Lisensi kuota — ✅ done
3. Video wall (snapshot JPEG / MJPEG multipart, <img>, garis editor di atas frame asli) — ✅ done, lihat §8
4. Engine paralel per kamera — ✅ done, lihat §8
5. RBAC per lokasi (hak_akses pengguna_id x lokasi_id) — ✅ done, lihat §8

## 8. Fase 2 terimplementasi (engine paralel + live view + RBAC + retensi)

### 8a. Engine paralel: KameraWorker per kamera + cache frame

- Satu `KameraWorker` (thread sendiri) per kamera: VideoCapture + instance YOLO sendiri (yolov8n ~6MB, murah di CPU). Kamera lemot tidak memblokir kamera lain. Orchestrator `DetectionEngine` (thread loop 1x/interval) sinkronkan worker dengan tabel kamera: baru -> start, dihapus -> stop.
- **Frame cache untuk live view**: worker simpan JPEG frame terakhir (`imencode` max 1x/detik, downscale 960px) — snapshot/stream dibaca dari cache. JANGAN buka koneksi RTSP kedua untuk live view: kamera murah sering putus/kelebihan sesi.
- Mode: `auto` (YOLO bila ≥1 stream terbuka, fallback simulator bila semua putus), `yolo` (paksa), `simulator` (eksplisit, hormati status kamera). `mode_efektif` dilaporkan ke frontend agar operator tahu data asli vs simulasi.
- Model load cooldown 30s per worker (hindari retry import berat tiap tick); `_stream_terjangkau()` TCP pre-check + cooldown 10s per kamera gagal.
- Tracking centroid: JARAK_COCOK 140px radius match antar bingkai, FRAME_HILANG_MAKS 40 bingkai -> track dianggap hilang; penyilangan garis via tanda sisi (cross product); hindari double-count kendaraan mondar-mandir (jangan buat track baru dalam 0.7×JARAK_COCOK dari track yang baru counted).

### 8b. Live monitoring endpoints (FastAPI)

```python
GET /api/kamera/semua            # semua device yang boleh dilihat user (filter IN izin RBAC) + flag live
GET /api/kamera/{id}/snapshot    # JPEG dari cache worker; 404 bila belum ada frame
GET /api/kamera/{id}/stream      # MJPEG multipart/x-mixed-replace, yield cache tiap 0.4s
```

- **MJPEG auth via query token**: `<img src>` tidak bisa kirim header `X-Token`, jadi stream pakai `?token=`. Tradeoff: token bocor ke log/history browser — hanya boleh untuk LAN admin, jangan untuk endpoint publik. `_user_alt(request, token)` menerima header ATAU query.
- RBAC scope: `kamera.semua` filter `WHERE id_lokasi IN (izin)` untuk petugas; snapshot/stream cek `_user_lokasi` per kamera.

### 8c. RBAC per-lokasi (petugas dibatasi lokasi, admin semua)

- Tabel `hak_akses (pengguna_id, lokasi_id)`; `lokasi_diizinkan(user)` -> set lokasi atau `None` = semua. Helper guard: `_admin()` (login+password diganti), `_admin_saja()` (role admin: kamera CRUD, lisensi, notifikasi, deteksi, pengguna), `_user_lokasi()` (login + izin lokasi: pengaturan, kapasitas, koreksi, riwayat, ekspor).
- `/api/me` kirim `hak_akses` (sorted list atau null=semua); `PUT /api/pengguna/{id}/akses` body `{lokasi_ids: []}` (kosong = semua). Admin role tak bisa di-set akses (otomatis semua).
- Frontend: tab Pengguna + kartu admin (lisensi/retensi/notifikasi) + tombol tambah kamera di-hide via `userInfo.peran !== 'admin'`; dropdown lokasi di-filter `meInfo.hak_akses.includes(l.id)`. Simpan `meInfo` dari /api/me saat tampilkanDasbor.
- Guard umum per endpoint (bukan per halaman) — cek tiap `@app.` route lama saat retrofit: notifikasi & deteksi sering hanya `_admin` padahal harus `_admin_saja`.

### 8d. Retensi data riwayat (purge otomatis)

- `retensi_hari` (default 90) disimpan di pengaturan; `purge_riwayat()` delete dari riwayat_deteksi/kapasitas/koreksi/alert `WHERE waktu < datetime('now','localtime','-N days')`. Jalankan saat startup + loop asyncio tiap 6 jam (`asyncio.create_task(_cek_purge())` di lifespan).
- GET /api/retensi = login; PUT = admin-saja.

### 8e. Frontend pitfalls (fase 2)

- **Garis editor di atas frame asli**: muat snapshot SEKALI di `bukaEditorGaris()` sebagai background (`garisBg = {img}`) — JANGAN fetch ulang per mousemove/drag (request flood). `gambarGaris()` draw bg + overlay line + handles.
- renderWall() dipanggil dari `muatSemua()` (refresh tiap WS tick juga OK); `<img loading="lazy">` untuk stream.
- Patch HTML via `patch` tool berisiko menghapus tag form bila old_string kurang unik — verifikasi dengan grep setelahnya.
- Testing: buat user test sementara (`tesadmin/tesadmin123`), jangan sentuh password admin asli yang sudah diganti user; cleanup user+kamera test, restore lisensi STANDARD/16.

### 8f. Urutan eksekusi retrofit multi-item (terbukti)

1. Backend dulu semua (schema -> logic -> main.py -> engine), `python -m py_compile app/*.py` sebelum frontend.
2. Frontend: HTML -> CSS -> JS -> i18n, `node --check` per file JS.
3. Server test port terpisah (9099), curl matrix (401/403/404/409), cleanup artefak test, kill server test.
4. Restart server produksi (PID lama masih kode lama — cek `netstat -ano | grep LISTENING`).
