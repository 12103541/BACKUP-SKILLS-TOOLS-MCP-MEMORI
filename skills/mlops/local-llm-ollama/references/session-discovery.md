# Ollama Model Discovery & Provider Switching

Session-specific reference for the `local-llm-ollama` skill. Documents the exact steps that worked on this Windows host.

## Environment

- **Host:** Windows 10, Hermes Desktop GUI
- **Ollama:** `ollama version 0.30.10`, installed at `/c/Users/62897/AppData/Local/Programs/Ollama/ollama`
- **Ollama Server:** Running at `http://localhost:11434` (verified via `curl -s http://localhost:11434/api/tags`)
- **GGUF Location:** `D:/models/` (model: `gemma-3-4b-it-uncensored-v2_Q4_K_M.gguf`, ~2.5 GB)
- **Hermes Config:** `C:\Users\62897\AppData\Local\hermes\config.yaml`

## Full Session Transcript

### Step 1: Discover models on drive D
```
ls -la /d/models/
```
Found `gemma-3-4b-it-uncensored-v2_Q4_K_M.gguf` + `Modelfile` referencing it.

### Step 2: Discover Ollama models
```
curl -s http://localhost:11434/api/tags
```
8 models found. Key ones:
- `gemma4:latest` — 8B, tools+thinking support ✅
- `qwen2.5:7b` — 7.6B, tools support ✅
- `qwen2.5:3b` — 3.1B, tools support ✅
- `llama3.1:8b` — 8B, tools support ✅
- `qwen3.6:latest` — 36B MoE, tools+thinking+vision ✅
- `gemma3-4b-it-uncensored:latest` — 3.9B, NO tools ❌
- `gemma3-4b:latest` — 3.9B, NO tools ❌
- `llava:7b` — 7B, vision only ❌

### Step 3: Config change (hit permission error)
```bash
hermes config set model.provider openai          # ✅ success
hermes config set model.base_url http://localhost:11434/v1  # ❌ PermissionError WinError 5
hermes config set model.default gemma4:latest    # ❌ failed (same batch)
```
**Retry individually:**
```bash
hermes config set model.base_url "http://localhost:11434/v1"  # ✅
hermes config set model.default gemma4:latest                 # ✅
```

### Step 4: Verify
```bash
hermes config show | grep -A3 "Model:"
```
→ `{'base_url': 'http://localhost:11434/v1', 'default': 'gemma4:latest', 'provider': 'openai'}` ✅

### Step 5: Test generation
```bash
curl -s http://localhost:11434/api/generate \
  -d '{"model":"gemma4:latest","prompt":"Halo, jawab dalam 1 kalimat","stream":false}' \
  | python -c "import sys,json; print(json.load(sys.stdin)['response'])"
```
→ ✅ Responded in Indonesian: "Saya dapat membantu Anda dengan..."

### Step 6: Inform user
Model change takes effect on `/new` (new session). Config applies to Hermes Desktop GUI after session restart.

## Key Learnings

1. **Batch config set fails** — `hermes config set` with multiple keys in one command fails on Windows with `PermissionError` after the first key succeeds. Always run them one at a time.
2. **`patch` tool won't touch config.yaml** — Agent's `patch` tool blocks writes to Hermes config for security. Use `hermes config set` CLI instead.
3. **Gemma 4 (8B) is recommended** — supports both tools AND thinking, best balance for Hermes agent work.
4. **User language preference** — Indonesian for explanations, English for code/config. Confirmed in user profile memory.