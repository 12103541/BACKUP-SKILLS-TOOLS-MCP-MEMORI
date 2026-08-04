# Session Reference: LiteLLM Integration for Laravel/Filament ERP (2026-07-31)

## Context
- **Project:** PT EXFERIA PUTRA INOVASI ERP (Laravel 11, Filament 3.3, MySQL 8.0, PHP 8.2)
- **Existing AI:** `AiAnalysisService` using Gemini API directly
- **Goal:** Replace with LiteLLM proxy for multi-provider routing, fallback, cost tracking

---

## What Was Done

### 1. Installed LiteLLM Proxy
```bash
uv tool install 'litellm[proxy]'
# Version: 1.94.1
```

### 2. Config Evolution

**Attempt 1 (Failed - SQLite):**
```yaml
database_url: "sqlite:///litellm.db"
```
→ LiteLLM rejects SQLite for virtual keys/spend tracking. Requires PostgreSQL.

**Attempt 2 (Failed - Model naming):**
```yaml
model: openrouter/deepseek-chat
```
→ OpenRouter error: "Model ID 'deepseek-chat' is ambiguous"

**Attempt 3 (Success):**
```yaml
model_list:
  - model_name: deepseek-chat
    litellm_params:
      model: openrouter/deepseek/deepseek-chat
      api_key: "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"
    model_info:
      input_cost_per_token: 0.00000014
      output_cost_per_token: 0.00000028
  - model_name: gpt-4o-mini
    litellm_params:
      model: openrouter/openai/gpt-4o-mini
      api_key: "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"
    model_info:
      input_cost_per_token: 0.00000015
      output_cost_per_token: 0.0000006

general_settings:
  master_key: "sk-litellm-master-2024"
```

### 3. Windows-Specific Issues

| Issue | Solution |
|-------|----------|
| Config file not found with `/c/Users/...` | Use `C:/Users/...` or `C:\Users\...` |
| Port conflicts | Proxy picks random port (27070, 40831, 32896) — check logs for actual port |
| Auth with `Bearer test` fails | Must use master key or virtual key from DB |
| Docker not running | Cannot spin up Postgres/Redis for virtual keys |

### 4. Laravel Integration Created

**Files:**
- `app/Services/LiteLlmService.php` — service wrapper with health check, model listing
- `config/litellm.php` — Laravel config
- `.env` additions:
  ```
  LITELLM_BASE_URL=http://localhost:32896/v1
  LITELLM_MASTER_KEY=sk-litellm-master-2024
  LITELLM_TIMEOUT=60
  LITELLM_DEFAULT_MODEL=deepseek-chat
  LITELLM_FALLBACK_MODEL=gpt-4o-mini
  ```

**Usage:**
```php
$s = new LiteLlmService();
$s->healthCheck();                    // bool
$s->ask('prompt');                    // simple
$s->ask('prompt', ['model' => 'gpt-4o-mini']);
$s->analyze($data, $systemPrompt);    // with system prompt
$s->getModels();                      // list available
```

### 5. Integration Point Identified

Existing `AiAnalysisService::callGemini()` can be replaced:
```php
// Before: Direct Gemini HTTP call
// After: LiteLLM proxy call with fallback
protected function callGemini(string $prompt): ?string
{
    return $this->llm->ask($prompt, [
        'model' => 'deepseek-chat',
        'temperature' => 0.3,
        'max_tokens' => 3000,
    ]);
}
```

---

## What Was Deferred (Production)

| Feature | Blocker | Next Step |
|---------|---------|-----------|
| Virtual keys per project | Requires PostgreSQL | Install Postgres (Laragon/Docker/cloud) |
| Spend tracking / budgets | Requires PostgreSQL + Redis | Enable `--db_engine postgres` |
| Admin dashboard | Requires DB | `litellm --config config.yaml --port 4000` then `/ui` |
| Guardrails (PII, prompt injection) | Requires DB for config persistence | Add to config.yaml after DB setup |
| MCP Gateway (ERP tools) | Requires artisan MCP command | Build `php artisan mcp:serve` |

---

## Lessons for Skill Update

1. **Add Windows path troubleshooting** to skill references
2. **Clarify OpenRouter model ID format** — must use full provider/model path
3. **Document master key vs virtual key auth difference** — master key works without DB
4. **Note router config differences** — proxy `router_settings` vs Python SDK `Router` class
5. **Add Laravel service wrapper template** to references
6. **Document deferred features** with clear blockers and next steps