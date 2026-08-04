# AI / LLM Multi-Provider Configuration (2026-07-31)

## Overview
Generalized `AiAnalysisService` from Gemini-only to multi-provider OpenAI-compatible. Configuration via CompanySetting group `ai`.

## Providers
| Provider | Base URL | Default Model |
|----------|----------|---------------|
| gemini | `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key=` | gemini-1.5-flash |
| deepseek | `https://api.deepseek.com/chat/completions` | deepseek-chat |
| openrouter | `https://openrouter.ai/api/v1/chat/completions` | deepseek/deepseek-chat |

## CompanySetting Keys (group: `ai`)
| Key | Label | Type | Default | Notes |
|-----|-------|------|---------|-------|
| llm_provider | Llm Provider | Select | gemini | gemini/deepseek/openrouter |
| llm_model | Llm Model | Text | NULL | e.g. deepseek-chat, gemini-1.5-flash |
| llm_api_key | API Key | Password | NULL | Encrypted via SENSITIVE_KEYS |

## Key Methods
- `AiAnalysisService::getLlmConfig()` → reads CompanySetting, returns `{api_key, model, base_url}`
- `AiAnalysisService::callLLM($prompt)` — OpenAI-compatible payload, calls provider endpoint
- Key fallback: `llm_api_key` → `LLM_API_KEY` (env) → `gemini_api_key` → `GEMINI_API_KEY` (env)
- No key → fallback local analysis (no LLM call)

## UI Integration
Section "🤖 AI / LLM (RAB Copilot)" added to:
1. `ProfilPerusahaanPage` — mount groups `['profil','ai']`, HasForms required
2. `CompanySettingPage` — dynamic group `ai` with custom fields (Select, password, text), save uses `CompanySetting::set()`

## Critical Fixes Applied
1. **ProfilPerusahaanPage missing `implements HasForms`** — caused form fields empty in DOM despite server state populated. Added 2026-07-31.
2. **Form fill wrap bug** — `$this->form->fill(['data' => $values])` vs statePath flat. Fixed to `fill($values)`.
3. **Save statePath mismatch** — `$this->form->getState()['data']` expected nested, actual flat. Fixed to `$state['data'] ?? $state`.
4. **CompanySetting::set() refactored** — now uses `firstOrNew` with `label` + `group` for new records (was missing for AI keys).
5. **Generic CompanySettingPage save** — changed from raw `where()->update()` to `CompanySetting::set()` for encryption.

## Verification
```bash
# Check config
php artisan tinker --execute='
$svc = app(\App\Services\AiAnalysisService::class);
$m = new ReflectionMethod($svc, "getLlmConfig"); $m->setAccessible(true);
echo json_encode($m->invoke($svc));
'

# Check DB
php artisan tinker --execute='
\App\Models\CompanySetting::all()->where("group","ai")->each(fn($s)=>echo "$s->key | $s->value\n");
'
```

## Files Modified
- `app/Services/AiAnalysisService.php` — callLLM, getLlmConfig, LLM_PROVIDERS constants
- `app/Models/CompanySetting.php` — SENSITIVE_KEYS + llm_api_key, set() refactor
- `app/Filament/Pages/Settings/ProfilPerusahaanPage.php` — HasForms, mount, form, save fixes
- `app/Filament/Pages/CompanySettingPage.php` — group ai + custom fields + set() save