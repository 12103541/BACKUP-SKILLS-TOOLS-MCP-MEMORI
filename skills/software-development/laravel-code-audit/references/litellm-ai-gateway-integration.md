# LiteLLM AI Gateway Integration for Laravel ERP

**Session**: 2026-07-31  
**Context**: Evaluating LiteLLM as AI Gateway for ERP AI features (RAB summary, BAST generation, document extraction, MCP tools)

---

## Key Findings

### 1. Auth Model — Critical Difference
**LiteLLM Proxy ignores client `Authorization` header**. API key MUST be in:
- `config.yaml` → `model_list[].litellm_params.api_key`
- Server env var: `OPENROUTER_API_KEY`, `OPENAI_API_KEY`, etc.

```yaml
# config.yaml (correct)
model_list:
  - model_name: deepseek-chat
    litellm_params:
      model: openrouter/deepseek-chat
      api_key: "sk-or-xxxx"  # server-side only
```

**Wrong** (doesn't work):
```bash
curl -H "Authorization: Bearer sk-or-xxxx" http://proxy:4000/v1/chat/completions
```

### 2. Virtual Keys = RBAC Integration Point
Virtual keys are **server-generated** (via `/key/generate` admin API or dashboard), scoped to:
- `model_access`: ["deepseek-chat", "gpt-4o-mini"]
- `budget_limit`: 500000 (rupiah/cents)
- `rpm`: 60, `tpm`: 100000
- `metadata`: `{"project_id": 12, "department": "estimator"}`

**Laravel middleware pattern**:
```php
// app/Http/Middleware/InjectVirtualKey.php
public function handle($request, Closure $next) {
    $virtualKey = VirtualKey::where('user_id', auth()->id())->first()?->key;
    $request->headers->set('Authorization', 'Bearer ' . $virtualKey);
    return $next($request);
}
```

### 3. Fallback Routing (Production Requirement)
```yaml
# config.yaml with fallback
model_list:
  - model_name: primary
    litellm_params:
      model: openrouter/deepseek-chat
      api_key: "sk-or-xxxx"
    priority: 1
  - model_name: fallback
    litellm_params:
      model: openai/gpt-4o-mini
      api_key: "sk-xxxx"
    priority: 2
```
Router tries `primary` → on error/timeout → `fallback`.

### 4. Cost Tracking per Project (Finance Flow Alignment)
Spend logs table (PostgreSQL) has: `project_id`, `user_id`, `model`, `prompt_tokens`, `completion_tokens`, `cost`, `timestamp`.

**Query for ERP billing**:
```sql
SELECT project_id, SUM(cost) as total_ai_cost, COUNT(*) as calls
FROM spend_logs
WHERE DATE_TRUNC('month', timestamp) = DATE_TRUNC('month', NOW())
GROUP BY project_id;
```

### 5. Guardrails for ERP Sensitive Data
Before sending to LLM, sanitize in Laravel service:
```php
// app/Services/AiGuardrails.php
public function sanitize(array $messages): array {
    $piiPatterns = [
        '/\b\d{2}\.\d{3}\.\d{3}\.\d-\d{3}\.\d{3}\b/' => '[NPWP]',      // NPWP
        '/\b\d{16}\b/' => '[NIK]',                                       // NIK
        '/\b\d{10,}\b/' => '[REKENING]',                                 // Rekening
        '/Rp\s?[\d.,]+/' => '[HARGA]',                                   // Harga
    ];
    foreach ($messages as &$msg) {
        foreach ($piiPatterns as $pattern => $replacement) {
            $msg['content'] = preg_replace($pattern, $replacement, $msg['content']);
        }
    }
    return $messages;
}
```

### 6. MCP Gateway — Expose ERP Functions to AI
**MCP Server** (standalone PHP/Python) registers tools:
```json
{
  "tools": [
    {"name": "get_kontrak", "description": "Get kontrak by nomor", "inputSchema": {"nomor": "string"}},
    {"name": "generate_bast_draft", "description": "Generate BAST markdown", "inputSchema": {"kontrak_id": "int", "progress": "string"}},
    {"name": "search_sparepart", "description": "Search sparepart by nama/sku", "inputSchema": {"query": "string"}}
  ]
}
```

**LiteLLM config.yaml**:
```yaml
mcp_servers:
  - name: erp-tools
    url: http://mcp-server:8000/mcp
    transport: streamable_http
```

**AI call with tools**:
```python
response = client.chat.completions.create(
    model="deepseek-chat",
    messages=[{"role": "user", "content": "Buat BAST untuk KNT-2024-001"}],
    tools=[{"type": "mcp", "server_url": "litellm_proxy/mcp/erp-tools", "server_label": "erp"}]
)
```

---

## Integration Checklist for Laravel ERP

| Component | Status | Notes |
|-----------|--------|-------|
| Proxy deploy (Docker) | ⏳ | `docker run -p 4000:4000 -v config.yaml:/app/config.yaml ghcr.io/berriai/litellm:main-stable` |
| Config.yaml with fallback | ⏳ | Primary: DeepSeek, Fallback: gpt-4o-mini |
| Virtual key per project | ⏳ | Migration: `virtual_keys` table |
| Middleware inject key | ⏳ | `InjectVirtualKey` middleware |
| Guardrails service | ⏳ | PII masking for NPWP, NIK, rekening, harga |
| MCP server (3 tools) | ⏳ | `get_kontrak`, `generate_bast`, `search_sparepart` |
| Langfuse callback | ⏳ | Trace: user_id, project_id, model, tokens, latency |
| Feature flag | ⏳ | `config('features.ai_gateway_enabled')` |

---

## Grilling Verdict (from grilling-plan-validation skill)

**CONDITIONAL PASS** — Fix before implementation:
1. Spike: Proxy + config.yaml + 1 virtual key + fallback (30 min)
2. Spike: MCP server with 3 ERP tools (2-4 hrs)
3. Decide: Guardrails v1 in Laravel or LiteLLM?
4. Skip admin dashboard v1 (YAGNI)

---

## Related Files in This Skill
- `references/erp-audit-example.md` — General ERP audit patterns
- `references/erp-cipali-production-setup-20260730.md` — Production chain: RAB→BOM→Sparepart→TransaksiKeluar→Faktur