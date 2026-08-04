Filament permission flow: hasPermission() = R00 bypass → user_permissions override (grant/revoke) → role_permissions lookup. Every Resource/Page MUST have canAccess() or it's visible to ALL users. Never hardcode role checks in canAccess — always use hasPermission(). R07 (HRD) had zero role_permissions; added 10 permissions 2026-07-25.
§
ERP stack: Laravel 11 + PHP 8.2 + MySQL 8.0. Filament v3.3.54. Path: C:\laragon\www\PT.EXFERIA PUTRA INOVASI\
§
ERP audit checklist: (1) undefined constants, (2) PPN 11% via CompanySetting/config, (3) batch update kills Eloquent events, (4) mutateFormDataBeforeCreate overwrites termin, (5) badge vs DB, (6) dup sync RM, (7) loop code gen, (8) wrong columns. Enums: kontrak.jenis, TK.tipe=keluar|retur, lokasi_km decimal. RAB final=is_active=false. Kontrak::complete() idempotent sejak 2026-07-31. TK.quantity & spareparts.stok DECIMAL(12,2). penawaran.nomor varchar(20).
§
PPN 11% tarif umum UU HPP (keputusan user 2026-07-31; 12% salah). Sumber: CompanySetting ppn_rate → config pajak.tarif_ppn_keluaran (env, default 11); alias config('pajak.ppn')=11. JANGAN pakai fallback 12 di kode baru.
§
grilling-plan-validation: grill AI plans.
§
RAB AI Copilot DIHAPUS (RabCopilotService gone; AiAnalysisService audit-only). workflow:monitor hourly (skill erp-filament-antipatterns #31).
§
browser-harness: editable uv install fork ~/Developer/browser-harness v0.1.0. Shim ~/.local/bin direstore ln -sf ke AppData/Roaming/uv/tools/browser-harness/Scripts/.
§
2026-08-01: User model pakai SoftDeletes (dulu hard delete → FK 1451). Guard: hapus diri/R00 diblokir. Kebijakan: 1 user/divisi (6 aktif).
§
Display publik: 1 halaman=1 lokasi, grid 2×2, kartu ikon|angka 50/50, header hitam+P kuning, pill putih, max-640px (VMS 512×288). VMS player /player/{token}+heartbeat, maintenance→layar status. CCTV kredensial di form kamera—JANGAN duplikat di form VMS. Login: admin/password123, petugas/petugas123.
§
Rest Area VMS desain (2026-08-03): desain PER DEVICE (vms.config_json, NULL=ikut template lokasi); kapasitas PER LOKASI=fakta fisik. Lokasi-aktif global+sync dinilai rumit→ganti per-device. Minta pendapat jujur, diskusi dulu sebelum implementasi.