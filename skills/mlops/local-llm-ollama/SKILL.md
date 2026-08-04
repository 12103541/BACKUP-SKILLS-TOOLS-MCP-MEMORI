---
name: local-llm-ollama
description: Configure Hermes Agent to use local LLMs via Ollama — discover GGUF files, import models, switch provider, test connectivity, and verify tool calling support.
version: 1.0.0
author: Agent
license: MIT
platforms: [windows, linux, macos]
dependencies: [ollama]
metadata:
  hermes:
    tags: [ollama, local-model, gguf, hermes-config, openai-compatible]
---

# Local LLM via Ollama (Hermes Agent)

Use this skill when you need to switch Hermes Agent from a cloud provider to a **local model served by Ollama**. Covers model discovery, config changes, connectivity testing, and common pitfalls.

## When to use

- User asks "aktifkan model lokal" / "gunakan model AI dari drive D/E" / "switch ke local model"
- User has GGUF files on a local drive and wants Hermes to use them
- User has Ollama installed and wants to use a local model instead of a cloud provider
- Verify that a local model supports tool calling before switching

## Prerequisites

- **Ollama** must be installed and the server running at `http://localhost:11434`
  - Check: `which ollama && ollama --version`
  - Check server: `curl -s http://localhost:11434/api/tags`
- GGUF model must be imported into Ollama or available on disk

## Workflow

### 1. Discover Local Models

Check what models are already in Ollama:

```bash
curl -s http://localhost:11434/api/tags | python -c "
import sys, json
for m in json.load(sys.stdin).get('models', []):
    caps = ', '.join(m.get('capabilities', []))
    print(f\"  {m['name']:35s} {m['details']['parameter_size']:8s} [{caps}]\")
"
```

**Tool calling support is critical** — models without `tools` capability cannot use Hermes tools (terminal, file, etc.). They'll work for chat only.

Supported (`tools` in capabilities): qwen2.5, llama3.1, gemma4, qwen3
Not supported: gemma3, llava, most vision-only models

### 2. Import a GGUF File into Ollama

If the user has a `.gguf` file on a local drive (e.g., `D:/models/`):

```bash
# Check what exists
ls -lah /d/models/*.gguf 2>/dev/null

# Create Ollama Modelfile
cat > /tmp/Modelfile << 'EOF'
FROM D:\\models\\your-model-name.gguf

PARAMETER temperature 0.7
PARAMETER top_p 0.9
PARAMETER num_ctx 8192

TEMPLATE """{{ .Prompt }}"""

SYSTEM "Jawab dengan jelas dan lengkap."
EOF

# Create the model in Ollama
ollama create my-model-name -f /tmp/Modelfile
```

### 3. Switch Hermes to Local Model

```bash
# Set Ollama as the provider (OpenAI-compatible API)
hermes config set model.provider openai
hermes config set model.base_url http://localhost:11434/v1
hermes config set model.default <model-name>
```

**Verify the config:**

```bash
hermes config show | grep -A3 "Model:"
```

Expected output:
```
Model:        {'base_url': 'http://localhost:11434/v1', 'default': 'gemma4:latest', 'provider': 'openai'}
```

### 4. Test the Model

```bash
curl -s http://localhost:11434/api/generate \
  -d '{"model":"gemma4:latest","prompt":"Halo, jawab dalam 1 kalimat","stream":false}' \
  | python -c "import sys,json; print(json.load(sys.stdin)['response'])"
```

### 5. Begin Using It

The config change takes effect on the **next session**. Tell the user to type `/new` or `/reset` in chat, or start a fresh `hermes` from terminal.

One-shot test without switching session:
```bash
hermes chat -q "Halo, apa kabar?"
```

## Pitfalls

- **`PermissionError: [WinError 5]` on config.yaml** — First `hermes config set` may succeed, subsequent ones fail due to file locking. Retry each setting individually. If persistent, edit via `hermes config edit`.
- **Session must be restarted** — `model.default` and `model.base_url` are read at session start. Always tell user to type `/new`.
- **No tool calling** — Without `tools` capability, Hermes has no terminal/file/web tools. Session becomes chat-only.
- **Ollama not running** — If API check fails, run `ollama serve` or start from system tray (Windows).
- **Case-sensitive model names** — Use exact names from `ollama list` or the API tags response.
- **Slow first load** — First request may take 30-60s loading model into memory.
- **Context length** — Models with 131K context may need lower `num_ctx` in Modelfile if RAM is limited.

## Model Recommendations for Hermes

| Model | Size | Tool Calling | Thinking | Notes |
|-------|------|:---:|:--------:|-------|
| **gemma4:latest** | 8B | ✅ | ✅ | Best balance, newest |
| **qwen2.5:7b** | 7.6B | ✅ | ❌ | Stable, lightweight |
| **llama3.1:8b** | 8B | ✅ | ❌ | Classic, reliable |
| **qwen2.5:3b** | 3.1B | ✅ | ❌ | Fastest, lowest quality |
| **qwen3.6:latest** | 36B MoE | ✅ | ✅ | Most capable, needs 24GB+ RAM |