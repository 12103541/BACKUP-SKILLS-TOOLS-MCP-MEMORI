---
name: hermes-voice
description: "Set up or troubleshoot Hermes voice: STT/TTS, /voice modes."
version: 1.0.0
triggers:
  - "voice"
  - "suara"
  - "voice command"
  - "speech"
  - "STT"
  - "TTS"
  - "bicara"
---

# Hermes Voice (STT/TTS)

Voice interaction with Hermes: mic input (STT) + audio output (TTS). Works in CLI (voice mode) and messaging gateways (voice notes auto-transcribed to prompts).

## CLI voice modes

- `/voice on` — voice-to-voice: listens + replies with audio
- `/voice tts` — replies always audio, input stays typed
- `/voice off` — disable
- Config is read at CLI startup → **exit and relaunch** after any config change.

## STT (voice → text)

Provider priority (auto-detected): local faster-whisper (free, no key) > groq > openai > mistral > xai > elevenlabs.

```yaml
stt:
  enabled: true
  provider: local        # local, groq, openai, mistral, xai, elevenlabs
  local:
    model: base          # tiny, base, small, medium, large-v3
    language: ''         # '' = auto-detect; set 'id' to force Indonesian
```

- `local` = faster-whisper. First run downloads model (~150MB for `base`) from HuggingFace; `small` for better accuracy.
- faster-whisper is NOT bundled — install it into Hermes' pip-less venv: see `uv-tool-install` skill (VIRTUAL_ENV + `uv pip install faster-whisper` technique).
- Verify: `"$VIRTUAL_ENV/Scripts/python" -c "import faster_whisper; print(faster_whisper.__version__)"`
- `stt.enabled: true` can already be set while the package is missing — the failure is `ModuleNotFoundError: No module named 'faster_whisper'`, which is an install gap, not a config problem.

## TTS (text → voice)

Providers: edge (default, free, no key), elevenlabs, openai, minimax, mistral, neutts (local).

```yaml
tts:
  provider: edge
  edge:
    voice: id-ID-ArdiNeural
```

- Indonesian edge voices: `id-ID-ArdiNeural` (male), `id-ID-GadisNeural` (female). Default is `en-US-AriaNeural` — switch for Indonesian: `hermes config set tts.edge.voice id-ID-ArdiNeural`
- User's voice-driven workflow targets Indonesian; voice replies should use id-ID unless told otherwise.

## Windows config location

On Windows this machine: HERMES_HOME = `C:\Users\<user>\AppData\Local\hermes` (NOT `~/.hermes` — that's the POSIX default the hermes-agent skill documents). Get the real path with `hermes config path`. Config: `<HERMES_HOME>\config.yaml`, secrets: `<HERMES_HOME>\.env`.

## Gateway voice notes

Voice messages on messaging platforms (Telegram etc.) are auto-transcribed to text prompts — same `stt.*` config applies. Audio replies via the `text_to_speech` tool or `/voice tts`.

## Troubleshooting

1. `ModuleNotFoundError: No module named 'faster_whisper'` → install it (uv-tool-install skill), not a config issue.
2. Changes not taking effect → CLI: exit + relaunch; gateway: `/restart`.
3. Wrong language → set `stt.local.language` ('' = auto) and `tts.edge.voice` to an id-ID voice.
4. First `/voice on` run downloads the whisper model — allow time/network.

## Related

- `erp-voice-commands` (user-owned) — ERP page-navigation voice map ("buka faktur" → open page). This skill covers platform-level voice setup; that one covers domain commands. If both grow, consider merging.
