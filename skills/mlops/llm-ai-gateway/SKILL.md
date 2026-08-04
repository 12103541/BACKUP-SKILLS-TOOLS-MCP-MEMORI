---
name: llm-ai-gateway
description: Deploy LiteLLM Proxy for multi-provider LLM routing.
---

# LLM AI Gateway Integration (LiteLLM)

**Purpose**: Deploy and configure LiteLLM Proxy as a centralized AI Gateway for Laravel applications — unified OpenAI-format API, model routing/fallback, per-project cost tracking, guardrails, MCP/A2A gateway.

## When to Use
- Need to call multiple LLM providers (OpenRouter, OpenAI, Anthropic, Ollama, etc.) from Laravel
- Need automatic fallback when primary provider fails
- Need cost tracking per project/department/user (virtual keys)
- Need PII masking / prompt injection guardrails for sensitive ERP data
- Want to expose ERP functions as MCP tools to AI agents
- Want single API endpoint — swap models via config, zero code change

## Quick Start

### 1. Install
```bash
uv tool install 'litellm[proxy]'
```

### 2. Config (config.yaml)
```yaml
model_list:
  - model_name: deepseek-chat
    litellm_params:
      model: openrouter/deepseek/deepseek-chat
      api_key: "sk-or-..."
  - model_name: gpt-4o-mini
    litellm_params:
      model: openai/gpt-4o-mini
      api_key: "sk-..."
  - model_name: llama3-local
    litellm_params:
      model: ollama/llama3
      api_base: http://host.docker.internal:11434

router_settings:
  routing_strategy: "usage-based-routing"
  fallback_models: ["gpt-4o-mini"]
```

### 3. Run Proxy
```bash
litellm --config config.yaml --port 4000
# Dashboard: http://localhost:4000/ui
```

### 4. Laravel Service Wrapper
```php
// app/Services/LiteLLMService.php
class LiteLLMService
{
    private string $baseUrl;
    private string $virtualKey;

    public function __construct(string $baseUrl = 'http://localhost:4000/v1', string $virtualKey = 'test')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->virtualKey = $virtualKey;
    }

    public function chat(array $messages, string $model = 'deepseek-chat', array $options = []): array
    {
        $response = Http::withToken($this->virtualKey)
            ->timeout(120)
            ->post("{$this->baseUrl}/chat/completions", array_merge([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 2000,
                'temperature' => 0.1,
            ], $options));

        if ($response->failed()) {
            throw new \Exception("LiteLLM error: {$response->body()}");
        }

        return $response->json();
    }
}
```

## Windows Gotchas (from session)

| Issue | Fix |
|-------|-----|
| Config path not found | Use forward slashes: `litellm --config "C:/Users/xxx/config.yaml"` |
| OpenRouter model ambiguous | Use full model ID: `openrouter/deepseek/deepseek-chat` |
| API key from header ignored | Key MUST be in config.yaml `litellm_params.api_key` |
| Port already in use | Kill existing: `netstat -ano | findstr :4000` then `taskkill /PID xxx /F` |

## Virtual Keys (Per-Project Cost Tracking)
1. Open dashboard: `http://localhost:4000/ui`
2. Create virtual key → set `budget_id`, `model_access`, `rate_limit`
3. Use key in Laravel: `Http::withToken($projectVirtualKey)->post(...)`
4. Spend logs in PostgreSQL (enable with `--db_engine postgres`)

## Guardrails for ERP Data
```yaml
guardrails:
  - guardrail_name: "pii_masking"
    litellm_params:
      mode: "pre_call"
      pii_entities: ["PHONE_NUMBER", "EMAIL_ADDRESS", "CREDIT_CARD", "US_SSN"]
      action: "mask"
  - guardrail_name: "prompt_injection"
    litellm_params:
      mode: "pre_call"
      threshold: 0.8
      action: "block"
```

## MCP Gateway (Expose ERP Tools to AI)
```yaml
mcp_servers:
  - name: "erp-tools"
    type: "stdio"
    command: "php"
    args: ["artisan", "mcp:serve"]
    tools:
      - "get_kontrak_by_nomor"
      - "create_penawaran_draft"
      - "generate_bast_pdf"
```

## Production Deploy (Terraform)
- AWS: `terraform/litellm/aws` — ECS Fargate + Aurora + ElastiCache + ALB
- GCP: `terraform/litellm/gcp` — Cloud Run + Cloud SQL + Memorystore + HTTPS LB

## References
- `references/litellm-config-example.yaml` — full config with router, guardrails, MCP
- `references/laravel-service-wrapper.php` — copy-paste service class
- `references/windows-troubleshooting.md` — path, port, auth issues

## Ponytail Notes
- Deliberate simplification: single-file config shown; production uses multiple files + secrets management
- Upgrade path: add Helm chart for K8s, custom router strategies, enterprise SSO/RBAC
- Ceiling: LiteLLM proxy adds ~8ms latency; for ultra-low latency, consider direct SDK with Router class