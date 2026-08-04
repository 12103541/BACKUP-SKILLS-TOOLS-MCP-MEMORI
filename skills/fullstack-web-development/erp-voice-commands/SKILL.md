---
name: erp-voice-commands
description: "Use when user speaks voice commands to buka halaman ERP."
version: 1.0.0
triggers:
  - "buka"
  - "buka dashboard"
  - "voice command"
  - "buka erp"
  - "suara"
---

# ERP Voice Commands

Voice commands untuk ERP PT EXFERIA PUTRA INOVASI.
User speaks → STT transcribes → Hermes executes.

## Setup

STT built-in Hermes:
- **Gateway**: voice message otomatis ditranskrip jadi prompt
- **CLI**: `/voice on` atau `/voice tts`

### CLI Voice Mode (voice-to-voice, mic langsung)

```bash
/voice on    # voice-to-voice: dengar suara + jawab suara
/voice tts   # jawaban selalu suara, input ketik
/voice off   # matikan
```

Persyaratan & install (Windows, venv hermes tanpa pip — pakai uv):

```bash
# 1. STT lokal gratis (faster-whisper) — install ke venv hermes
export VIRTUAL_ENV="C:/Users/62897/AppData/Local/hermes/hermes-agent/venv"
export PATH="$VIRTUAL_ENV/Scripts:$PATH"
"C:/Users/62897/AppData/Local/hermes/bin/uv" pip install faster-whisper

# 2. TTS Edge gratis (default) — voice Indonesia
hermes config set tts.edge.voice id-ID-ArdiNeural

# 3. Restart CLI (config dibaca saat startup), lalu /voice on
```

Config terkait (`C:\Users\62897\AppData\Local\hermes\config.yaml`):
- `stt.enabled: true`, `stt.provider: local` (bisa groq/openai/mistral), `stt.local.model: base`
- `tts.provider: edge` (default gratis; alternatif elevenlabs/openai/minimax/mistral/neutts)

Pitfall:
- Pertama kali dipakai, whisper unduh model `base` (~150MB) dari HuggingFace
- Model `base` akurasi sedang — upgrade: `hermes config set stt.local.model small`
- Mic aktif saat CLI jalan; CLI dengar langsung dari microphone

## URL Dasar

```
http://localhost
```

## Command Map

Cocokkan ucapan user, lalu jalankan action.

| Ucapan | Aksi |
|--------|------|
| "buka dashboard ERP" / "buka admin" | `browser_navigate(url)` → `/admin` |
| "buka faktur" | Navigasi ke halaman Faktur |
| "buka kontrak" | Navigasi ke halaman Kontrak |
| "buka pekerjaan" | Navigasi ke halaman Pekerjaan |
| "buka pelanggan" / "buka klien" | Navigasi ke halaman Klien |
| "buka pembayaran" | Navigasi ke halaman Pembayaran |
| "buka kalender" | Navigasi ke calendar/index |
| "buka notifikasi" | Navigasi ke notifications/index |
| "buka laporan" | Navigasi ke halaman Laporan/Keuangan |

## Cara Kerja

1. User kirim voice / ketik command
2. Cocokkan dengan command map
3. Jalankan aksi:

```
browser_navigate(url="http://localhost/admin")
reply("✅ Dashboard ERP terbuka")
```

Atau via terminal (browser default):

```
terminal(command="start http://localhost/admin")
```

## Jika Tidak Cocok

```
reply("❌ Command tidak dikenal. Coba: buka Dashboard, buka Faktur, buka Kontrak, buka Pekerjaan, buka Klien, buka Pembayaran")
```

## Catatan

- Command case-insensitive
- Bisa ditambah sesuai kebutuhan
- Untuk command kompleks (filter, export, cetak), user cukup bicara natural — Hermes pahami sebagai NL prompt biasa
