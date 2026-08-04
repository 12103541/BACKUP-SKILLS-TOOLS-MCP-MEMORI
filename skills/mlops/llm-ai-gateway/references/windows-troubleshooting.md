# Windows Troubleshooting for LiteLLM Proxy

## Config File Path Issues

**Problem**: `Exception: Config file not found: C:Users62897config.yaml`

**Cause**: Windows backslashes in path get mangled when passed to Python subprocess.

**Fix**: Use forward slashes or MSYS-style paths:
```bash
# Good - forward slashes
litellm --config "C:/Users/62897/config.yaml" --port 4000

# Good - MSYS path
litellm --config "/c/Users/62897/config.yaml" --port 4000

# Bad - backslashes (breaks)
litellm --config "C:\Users\62897\config.yaml" --port 4000
```

## OpenRouter Model Ambiguous Error

**Problem**: 
```
BadRequestError: Model ID 'deepseek-chat' is ambiguous — it matches multiple models:
deepseek/deepseek-chat, deepseek/deepseek-chat-v2.5
```

**Cause**: OpenRouter has multiple models matching short name.

**Fix**: Use full model ID in config:
```yaml
model_list:
  - model_name: deepseek-chat
    litellm_params:
      model: openrouter/deepseek/deepseek-chat  # full path
      api_key: "sk-or-..."
```

## API Key from Header Ignored

**Problem**: `AuthenticationError: No cookie auth credentials found` even with `Authorization: Bearer sk-or-...`

**Cause**: LiteLLM Proxy **requires API keys in config.yaml**, not passed via request header. The header `Authorization` is for virtual keys (proxy auth), not provider API keys.

**Fix**: Put provider API keys in config:
```yaml
model_list:
  - model_name: deepseek-chat
    litellm_params:
      model: openrouter/deepseek/deepseek-chat
      api_key: "sk-or-v1-xxxxxxxxxxxx"  # MUST be here
```

Virtual keys (for per-project auth) are created in dashboard and passed as `Authorization: Bearer <virtual-key>`.

## Port Already in Use

**Problem**: `OSError: [Errno 98] Address already in use` or proxy starts on random port.

**Fix**: Find and kill existing process:
```cmd
# Find PID using port 4000
netstat -ano | findstr :4000

# Kill it
taskkill /PID <PID> /F

# Or use PowerShell
Get-Process -Id (Get-NetTCPConnection -LocalPort 4000).OwningProcess | Stop-Process -Force
```

## Proxy Starts on Random Port (Not 4000)

**Observation**: Log shows `Uvicorn running on http://0.0.0.0:27070` instead of 4000.

**Cause**: Port 4000 busy, LiteLLM picks random available port.

**Fix**: Explicitly specify port AND ensure it's free:
```bash
litellm --config "C:/Users/xxx/config.yaml" --port 4000
```

## Environment Variables Not Loading

**Problem**: `$env:OPENROUTER_API_KEY` set but proxy still fails auth.

**Cause**: Windows PowerShell env vars don't always propagate to uv tool subprocess.

**Fix**: Use config.yaml with `${OPENROUTER_API_KEY}` and set in system env, or hardcode in config for dev.

## Background Process Management

**Problem**: Can't stop proxy started in background.

**Fix**: Use process management:
```bash
# List background processes
hermes process list

# Kill by session_id
hermes process kill <session_id>

# Or find via netstat + taskkill (see above)
```

## Virtual Keys Not Working

**Problem**: `401 Unauthorized` with virtual key from dashboard.

**Causes & Fixes**:
1. **Master key not set** — Add `general_settings.master_key` in config.yaml
2. **Key not created properly** — Use dashboard UI: `http://localhost:4000/ui` → Virtual Keys → Create
3. **Wrong base URL** — Virtual key auth works on `/v1/*` endpoints, not `/health` or `/models` without key

## PostgreSQL/Redis Connection Failed

**Problem**: Proxy fails to start with database errors.

**Fix**: For dev, disable persistence:
```yaml
# Remove or comment out:
# database_url: "postgresql://..."
# redis_url: "redis://..."
```
LiteLLM works fine in-memory for development.

## Cost Tracking Shows $0.00

**Problem**: Usage shows but cost is 0.

**Cause**: Model not in LiteLLM's built-in cost map.

**Fix**: Add cost info to model config:
```yaml
- model_name: deepseek-chat
  litellm_params:
    model: openrouter/deepseek/deepseek-chat
    api_key: "..."
  model_info:
    input_cost_per_token: 0.00000014
    output_cost_per_token: 0.00000028
    max_tokens: 8192
```

## Dashboard UI Not Accessible

**Problem**: `http://localhost:4000/ui` returns 404.

**Fix**: Enable UI in config:
```yaml
general_settings:
  ui: true
  ui_path: "/ui"
```

## Streaming Not Working in Laravel

**Problem**: Stream returns all at once or times out.

**Fix**: Use proper SSE parsing (see `laravel-service-wrapper.php` reference) and increase timeout:
```php
$client = Http::withToken($key)
    ->timeout(300)  // 5 min for long streams
    ->acceptJson()
    ->withHeaders(['Accept' => 'text/event-stream'])
    ->post(...);
```

## Quick Debug Commands

```bash
# Test proxy health
curl http://localhost:4000/health

# Test models list
curl -H "Authorization: Bearer test" http://localhost:4000/v1/models

# Test chat completion
curl -X POST http://localhost:4000/v1/chat/completions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer test" \
  -d '{"model": "deepseek-chat", "messages": [{"role": "user", "content": "hi"}], "max_tokens": 10}'

# Check logs (run in foreground to see)
litellm --config "C:/Users/xxx/config.yaml" --port 4000 --detailed_debug
```

## Session-Specific Notes (July 31, 2026)

- Config that worked: `C:/Users/62897/config.yaml` with forward slashes
- Model that worked: `openrouter/deepseek/deepseek-chat` (full ID)
- Port used: 27070 (random, 4000 was busy)
- API key: `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`
- Test response: 29 tokens, $0.000022 cost, provider DeepInfra (OpenRouter routed)