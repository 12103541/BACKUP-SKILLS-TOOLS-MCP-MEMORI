---
name: model-switch
description: Switch Hermes Agent between cloud (OpenRouter/DeepSeek) and local (Ollama/Gemma 4) models using profiles
version: 1.0.0
author: Agent
platforms: [windows]
---

# Model Switch — Cloud ↔ Lokal

Gunakan skill ini saat kamu ingin gonta-ganti model antara **cloud (DeepSeek V4 Flash)** dan **lokal (Gemma 4 via Ollama)** tanpa rewrite config manual.

## Profil yang Tersedia

| Profile | Model | Provider | Cara Pakai |
|---------|-------|----------|------------|
| `cloud` | DeepSeek V4 Flash | OpenRouter | `hermes --profile cloud` |
| `default` | Gemma 4:latest | Ollama (local) | `hermes` (tanpa --profile) |

## Cara Switching

### Opsi 1: Script Switcher (Terminal/Luar Hermes)

```bash
# Dari terminal, langsung pilih:
~/AppData/Local/hermes/scripts/hermes-switch.sh

# Atau langsung:
~/AppData/Local/hermes/scripts/hermes-switch.sh cloud   # → Cloud
~/AppData/Local/hermes/scripts/hermes-switch.sh local   # → Lokal
```

### Opsi 2: Langsung pake profile (Terminal)

```bash
hermes --profile cloud      # → Cloud DeepSeek
hermes                       # → Lokal Gemma 4 (default profile)
```

### Opsi 3: Dalam session Hermes (Chat)

Kalau lagi di dalam session Hermes dan mau ganti model:

1. Ketik `/new` atau `/reset` — start session baru
2. Pastikan config sudah sesuai:
   - **Cloud**: `hermes config set model.provider openrouter` + `hermes config set model.default "deepseek/deepseek-v4-flash"`
   - **Lokal**: `hermes config set model.provider openai` + `hermes config set model.base_url http://localhost:11434/v1` + `hermes config set model.default gemma4:latest`

## Cara Cek Model Aktif

```bash
# Cek config saat ini
hermes config show | grep -A4 "Model:"

# Atau dari dalam session, ketik:
/model
```

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| OpenRouter gak konek di profile cloud | `hermes auth add openrouter` — isi API key |
| Ollama gak jalan di lokal | `ollama serve` atau start dari system tray |
| Model gak muncul setelah switch | `/new` atau restart Hermes — config baru butuh session baru |
| Profile cloud error "no OpenRouter key" | Copy key dari .env default: `cp ~/AppData/Local/hermes/.env ~/AppData/Local/hermes/profiles/cloud/.env` |

## Catatan

- **Model config cuma berlaku di session baru** — `/new` atau keluar-masuk Hermes
- Profile `default` sudah di-set ke Gemma 4 lokal (`provider: openai`, `base_url: http://localhost:11434/v1`)
- Profile `cloud` pakai OpenRouter dengan DeepSeek V4 Flash
- Dua profile independent — confignya terpisah di `~/.hermes/profiles/{cloud,default}/`
