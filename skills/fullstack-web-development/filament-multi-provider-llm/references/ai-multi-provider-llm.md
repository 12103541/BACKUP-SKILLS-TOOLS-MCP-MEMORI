# Multi-Provider LLM Configuration for Filament (2026-07-31)

## Overview
Provider-agnostic LLM support for Laravel + Filament ERP. Each provider gets its own API key field, model config, and optional custom endpoint. Keys stored encrypted via `CompanySetting::set()`.

## Database Schema
```php
// company_settings table
key (string) — e.g., 'gemini_api_key', 'deepseek_api_key', 'openrouter_api_key', 'custom_api_key', 'custom_base_url', 'custom_model', 'llm_provider', 'llm_model'
group (string) — 'ai'
label (string) — UI label
type (string) — 'password' | 'text' | 'select'
value (text) — encrypted for password type (SENSITIVE_KEYS)
```

## CompanySetting Model — Sensitive Keys (UPDATED)
```php
class CompanySetting extends Model
{
    private const SENSITIVE_KEYS = [
        'gemini_api_key',
        'deepseek_api_key',      // ADDED 2026-07-31
        'openrouter_api_key',    // ADDED 2026-07-31
        'custom_api_key',        // ADDED 2026-07-31
        'llm_api_key',           // legacy
    ];

    public static function set(string $key, $value, ?string $label = null, string $group = 'umum'): void
    {
        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            $value = EncryptionService::encrypt($value);
        }
        $setting = static::firstOrNew(['key' => $key]);
        $setting->value = $value;
        if (!$setting->exists) {
            $setting->label = $label ?: ucwords(str_replace('_', ' ', $key));
            $setting->group = $group;
        }
        $setting->save();
    }

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) return $default;
        
        $value = $setting->value;
        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return EncryptionService::decrypt($value);
        }
        return $value;
    }
}
```

## Service Layer (`AiAnalysisService.php`)

### `getLlmConfig()` — Provider-aware config
```php
protected function getLlmConfig(): array
{
    $provider = strtolower((string) CompanySetting::get('llm_provider', 'gemini'));
    $model = CompanySetting::get('llm_model');

    $apiKey = match ($provider) {
        'deepseek' => CompanySetting::get('deepseek_api_key') ?: env('DEEPSEEK_API_KEY'),
        'openrouter' => CompanySetting::get('openrouter_api_key') ?: env('OPENROUTER_API_KEY'),
        'custom' => CompanySetting::get('custom_api_key') ?: env('CUSTOM_API_KEY'),
        'gemini' => CompanySetting::get('gemini_api_key') ?: env('GEMINI_API_KEY') ?: env('LLM_API_KEY'),
        default => CompanySetting::get('gemini_api_key') ?: env('GEMINI_API_KEY') ?: env('LLM_API_KEY'),
    };

    switch ($provider) {
        case 'deepseek':
            return ['api_key' => $apiKey, 'model' => $model ?: 'deepseek-chat', 'base_url' => 'https://api.deepseek.com/chat/completions'];
        case 'openrouter':
            return ['api_key' => $apiKey, 'model' => $model ?: 'deepseek/deepseek-chat', 'base_url' => 'https://openrouter.ai/api/v1/chat/completions'];
        case 'custom':
            return [
                'api_key' => $apiKey,
                'model' => CompanySetting::get('custom_model') ?: $model ?: 'custom-model',
                'base_url' => CompanySetting::get('custom_base_url') ?: env('CUSTOM_BASE_URL'),
            ];
        case 'gemini':
        default:
            return ['api_key' => $apiKey, 'model' => $model ?: 'gemini-1.5-flash', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions'];
    }
}
```

### `callLLM()` — OpenAI-compatible call
```php
protected function callLLM(string $prompt): ?string
{
    $cfg = $this->getLlmConfig();
    if (!$cfg['api_key']) return null; // fallback to local report

    try {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $cfg['api_key'],
        ])->post($cfg['base_url'], [
            'model' => $cfg['model'],
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.3,
        ]);
        return $response->successful() ? $response->json('choices.0.message.content') : null;
    } catch (\Exception $e) {
        return null; // fallback
    }
}
```

## Filament Settings Page — FLAT STATE Pattern (CRITICAL)

### ❌ WRONG (nested `settings.` prefix — causes save failure)
```php
Select::make('settings.llm_provider')
TextInput::make('settings.openrouter_api_key')

// Mount
$this->form->fill(['settings' => $nestedArray]);

// Save
$data = $this->form->getState()['settings'] ?? [];
```

### ✅ CORRECT (flat state — key directly)
```php
// Field names = DB key directly
Select::make('llm_provider')
TextInput::make('openrouter_api_key')

// Mount — pluck value by key, flat array
public function mount(): void
{
    $settings = CompanySetting::pluck('value', 'key')->toArray();  // ['key' => 'value']
    $this->form->fill($settings);
}

// Save — iterate flat state, CompanySetting::set() auto-encrypts
public function save(): void
{
    $data = $this->form->getState();  // flat: ['llm_provider' => 'openrouter', 'openrouter_api_key' => 'sk-...', ...]
    foreach ($data as $key => $value) {
        CompanySetting::set($key, $value);  // handles encryption for SENSITIVE_KEYS
    }
}
```

### Pitfalls Table
| Wrong Pattern | Correct Pattern | Why |
|---------------|-----------------|-----|
| `Select::make('settings.llm_provider')` | `Select::make('llm_provider')` | Form state is flat, not nested |
| `$this->form->fill(['settings' => $nested])` | `$this->form->fill($flatArray)` | Mount expects flat key→value |
| `$data = $this->form->getState()['settings']` | `$data = $this->form->getState()` | State is already flat |
| `update(['value' => $v])` | `CompanySetting::set($k, $v)` | **Bypasses encryption** for SENSITIVE_KEYS |

## Provider Endpoints (OpenAI-compatible)

| Provider | Base URL | Model Example |
|----------|----------|---------------|
| Google Gemini | `https://generativelanguage.googleapis.com/v1beta/openai/chat/completions` | `gemini-1.5-flash` |
| DeepSeek | `https://api.deepseek.com/chat/completions` | `deepseek-chat` |
| OpenRouter | `https://openrouter.ai/api/v1/chat/completions` | `deepseek/deepseek-chat` |
| Custom | `custom_base_url` (user-defined) | `custom_model` (user-defined) |

## Migration (One-time)
```php
$keys = [
    'gemini_api_key' => ['label' => 'Gemini API Key', 'group' => 'ai', 'type' => 'password'],
    'deepseek_api_key' => ['label' => 'DeepSeek API Key', 'group' => 'ai', 'type' => 'password'],
    'openrouter_api_key' => ['label' => 'OpenRouter API Key', 'group' => 'ai', 'type' => 'password'],
    'custom_api_key' => ['label' => 'Custom Provider API Key', 'group' => 'ai', 'type' => 'password'],
    'custom_base_url' => ['label' => 'Custom Provider Base URL', 'group' => 'ai', 'type' => 'text'],
    'custom_model' => ['label' => 'Custom Provider Model', 'group' => 'ai', 'type' => 'text'],
    'llm_provider' => ['label' => 'LLM Provider', 'group' => 'ai', 'type' => 'select', 'options' => '["gemini","deepseek","openrouter","custom"]'],
    'llm_model' => ['label' => 'LLM Model', 'group' => 'ai', 'type' => 'text'],
];

foreach ($keys as $key => $cfg) {
    CompanySetting::firstOrCreate(['key' => $key], $cfg);
}

// Migrate legacy llm_api_key -> gemini_api_key
if ($old = CompanySetting::get('llm_api_key')) {
    CompanySetting::set('gemini_api_key', $old, 'Gemini API Key', 'ai');
}
```

## Verification Commands
```bash
# Test provider config
php artisan tinker --execute='
$svc = app(\App\Services\AiAnalysisService::class);
$m = new ReflectionMethod($svc, "getLlmConfig"); $m->setAccessible(true);
echo json_encode($m->invoke($svc), JSON_PRETTY_PRINT);
'

# Check encrypted key storage
php artisan tinker --execute='
echo \App\Models\CompanySetting::get("openrouter_api_key");
$raw = DB::table("company_settings")->where("key","openrouter_api_key")->value("value");
echo "RAW: " . substr($raw, 0, 20) . "..."; // encrypted JSON
'
```

## Extending to New Providers
1. Add provider to select options in `CompanySettingPage.php`
2. Add `*_api_key` field to migration + UI (password + revealable)
3. Add case in `getLlmConfig()` match + switch
4. Add env var fallback (e.g., `ANTHROPIC_API_KEY`)
5. Test with real API call