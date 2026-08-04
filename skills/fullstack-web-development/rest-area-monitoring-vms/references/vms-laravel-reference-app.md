# Referensi: Aplikasi VMS Laravel (C:\VMS) — arsitektur & peta adopsi ke monitoring parkir

Read-only analysis 2026-08-02. User: "untuk bagian admin saya ingin konsep seperti aplikasi vms saya, dari penambahan vms sampai player vms yang menampilkan data display". C:\VMS TIDAK BOLEH diubah — inspeksi read-only saja (user: "jangan kamu rusak, atau rubah").

## Stack & lokasi

- Laravel (Breeze) + Blade + Tailwind + Vite, SQLite, Sanctum API tokens, Electron app sebagai player. Path: `C:\VMS\www`. Ada `CLAUDE.md` (rules analisis wajib) + `analysis-spec.md` (arsitektur lengkap).
- DB schema inti: `vms`, `vms_categories`, `controller_types`, `template_messages`, `content_assignments`, `dynamic_message_signs` (DMS), `content_histories`, `activity_logs`, `settings`, `token_generation_logs`, `software_updates`.

## Alur kerja VMS (inti konsep)

1. **Tambah VMS** — form: name, location, ip_address, status (active/inactive/maintenance), kategori, mode (sinkron/asinkron), controller_type_id, model, serial_number, lat/lng, display_width/height, orientation, cctv_url/cctv_username/cctv_password (encrypted cast). Simpan → `generateAccessToken()`: `access_token = bin2hex(random_bytes(32))` + `player_url = route('player.display', token)` otomatis.
2. **Konten 3 lapis** (YAGNI untuk parking — konten papan parking = data okupansi real-time, bukan template pesan):
   - `template_messages` — teks/gambar/video/DMS, prioritas, alignment.
   - `content_assignments` — vms_id, content_type, content_data (JSON), scheduled_at/expires_at, priority, duration, is_active, status. Jadwal aktif/nonaktif via cron 1 menit.
   - `dynamic_message_signs` — activate/deactivate.
3. **Player 3 jalur tampil**:
   - Web `/player/{token}` — kiosk fullscreen: `#content-display` ukuran display_width×display_height px, `transform-origin:0 0` + JS scale-to-viewport, cursor:none, refresh interval dari settings, auto_reload_minutes.
   - TB2 Widget `/tb2/display/{vms}` + `/check` — polling content_id utk ViPlex Express (no auth).
   - Electron — polling `api/player/{token}/data` (config + contents + display + settings) dan `/content`.
4. **Status & keamanan**:
   - Token expiry opsional (`token_expires_at`) + `isTokenExpired()`/`isTokenExpiringSoon(7)`.
   - Device binding (`device_id`) — token terikat 1 perangkat; mismatch → 403 `device_mismatch`.
   - Heartbeat: tiap fetch player → `heartbeat()` (last_heartbeat + is_online=true) + `last_fetch_at`; online jika < 5 menit (`checkOnlineStatus`, `isOnline`).
   - Status inactive/maintenance → player tampil layar status khusus (bukan konten), HTTP 403/503.
   - `getCctvEmbedUrl()` — embed Basic-Auth user:pass@host utk iframe kamera Dahua.
5. **Cron**: `schedule:update-content` tiap 1 menit (aktifkan assignment sesuai jadwal), traffic update, cleanup 90 hari, token expiry check 7 hari.

## Gap & peta adopsi ke monitoring parkir (rekomendasi, 6 poin)

Current parking app: tab VMS = master data only (CRUD + form), TANPA alur player, TANPA token, TANPA status online. Papan publik `/` + `?lokasi=N` polling /api/state 5s.

1. VMS = papan display per rest area → tiap VMS dapat `access_token` + `player_url`.
2. Player VMS: `/player/{token}` → render papan utk lokasi VMS tsb (token → id_lokasi). Papan existing jadi player VMS.
3. Heartbeat: player ping → `last_heartbeat`/`last_fetch_at` → badge ONLINE/OFFLINE di admin.
4. Status VMS: aktif/nonaktif/maintenance → maintenance = layar "Sedang Maintenance" (persis VMS).
5. Token: regenerate + expiry opsional.
6. Admin: tombol "Buka Player" + "Salin URL" + "Regenerate Token" per VMS + badge online/offline + last heartbeat.

**JANGAN adopsi** (YAGNI utk parking): template_messages / content_assignments / DMS / software updates / token generation logs — konten papan = data real-time okupansi, bukan template pesan. Cukup VMS ↔ lokasi 1:1.
