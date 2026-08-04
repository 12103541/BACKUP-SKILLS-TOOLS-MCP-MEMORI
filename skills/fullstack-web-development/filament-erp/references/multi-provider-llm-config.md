# Multi-Provider LLM Configuration Pattern (2026-07-31)

## Context
ERP system (PT EXFERIA PUTRA INOVASI) uses AI for RAB pricing analysis and project health reports. Switched from single-provider (Gemini) to multi-provider (Gemini/DeepSeek/OpenRouter) with per-provider API keys.

## DB Schema (company_settings table)
| key | group | type | label | notes |
|-----|-------|------|-------|-------|
| llm_provider | ai | select | Llm Provider | gemini/deepseek/openrouter |
| llm_model | ai | text | Llm Model | optional override |
| gemini_api_key | ai | password | Gemini API Key | encrypted |
| deepseek_api_key | ai | password | DeepSeek API Key | encrypted |
| openrouter_api_key | ai | password | OpenRouter API Key | encrypted |

## UI: CompanySettingPage (Filament Settings Page)
File: `app/Filament/Pages/CompanySettingPage.php`

### Key Patterns
```php
// Mount: fill flat array matching field names
public function mount(): void {
    $settings = CompanySetting::all()->groupBy('group');
    $this->form->fill(['settings' => $settings->toArray()]); // ← flat, NOT ['data' => ...]
}

// Form: dynamic schema per group, custom fields for 'ai' group
public function form(Form $form): Form {
    $settings = CompanySetting::all()->groupBy('group');
    $schema = [];
    
    foreach ($groups as $groupKey => $groupLabel) {
        if (!isset($settings[$groupKey])) continue;
        
        $fields = [];
        foreach ($settings[$groupKey] as $setting) {
            if ($setting->type === 'file') continue;
            
            // Custom: per-provider password fields
            if ($groupKey === 'ai' && in_array($setting->key, ['gemini_api_key', 'deepseek_api_key', 'openrouter_api_key'])) {
                $field = Forms\Components\TextInput::make("settings.{$setting->key}")
                    ->label($setting->label)
                    ->password()
                    ->revealable()
                    ->columnSpanFull()
                    ->helperText("API Key untuk {$provider}. Disimpan terenkripsi.");
            }
            // ... other fields
            $fields[] = $field;
        }
        
        $schema[] = Forms\Components\Section::make($groupLabel)->schema($fields)->columns(2);
    }
    
    return $form->schema($schema)->statePath('settings');
}

// Save: ALWAYS use CompanySetting::set() for encryption
public function save(): void {
    $data = $this->form->getState();
    $settings = $data['settings'] ?? [];
    
    foreach ($settings as $key => $value) {
        CompanySetting::set($key, $value); // ← handles encryption
    }
}
```

## Service: AiAnalysisService::getLlmConfig()
File: `app/Services/AiAnalysisService.php`

```php
protected function getLlmConfig(): array {
    $provider = strtolower((string) CompanySetting::get('llm_provider', 'gemini'));
    $model = CompanySetting::get('llm_model');
    
    // Per-provider API key lookup
    $apiKey = match ($provider) {
        'deepseek' => CompanySetting::get('deepseek_api_key') ?: env('DEEPSEEK_API_KEY'),
        'openrouter' => CompanySetting::get('openrouter_api_key') ?: env('OPENROUTER_API_KEY'),
        'gemini' => CompanySetting::get('gemini_api_key') ?: env('GEMINI_API_KEY') ?: env('LLM_API_KEY'),
        default => CompanySetting::get('gemini_api_key') ?: env('GEMINI_API_KEY') ?: env('LLM_API_KEY'),
    };
    
    return [
        'api_key' => $apiKey,
        'model' => $model ?: ($provider === 'deepseek' ? 'deepseek-chat' : ($provider === 'openrouter' ? 'deepseek/deepseek-chat' : 'gemini-1.5-flash')),
        'base_url' => match ($provider) {
            'deepseek' => 'https://api.deepseek.com/chat/completions',
            'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
            default => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
        },
    ];
}
```

## Critical Pitfalls Fixed (2026-07-31)

### 1. HasForms Contract Required
```php
// ❌ WRONG - trait only, no contract
class MyPage extends Page { use InteractsWithForms; }

// ✅ CORRECT - implements HasForms + trait
class MyPage extends Page implements HasForms { use InteractsWithForms; }
```
Without `implements HasForms`: form renders but fields empty, save() silent, no validation.

### 2. Form Fill Must Match StatePath (No Wrapper)
```php
// statePath = 'settings', fields named 'settings.llm_provider', etc.

// ❌ WRONG - wraps in 'data', breaks binding
$this->form->fill(['data' => $values]);

// ✅ CORRECT - flat array matching field names
$this->form->fill($values); // $values = ['llm_provider' => 'deepseek', ...]
```
Symptom of bug: `$this->data` empty while `$this->data['data']` has values.

### 3. CompanySetting::set() for Encryption
```php
// ❌ WRONG - bypasses encryption
CompanySetting::where('key', $key)->update(['value' => $value]);

// ✅ CORRECT - handles encryption for SENSITIVE_KEYS
CompanySetting::set($key, $value);
```

### 4. Select Live Update (Future Enhancement)
```php
// On provider select, re-render form to show/hide provider-specific API key fields
->live(onBlur: true)
```

## Testing Commands
```bash
# Verify keys in DB
php artisan tinker --execute='
echo "provider: ".\App\Models\CompanySetting::get("llm_provider")."\n";
echo "model: ".\App\Models\CompanySetting::get("llm_model")."\n";
echo "gemini: ".\App\Models\CompanySetting::get("gemini_api_key")."\n";
echo "deepseek: ".\App\Models\CompanySetting::get("deepseek_api_key")."\n";
echo "openrouter: ".\App\Models\CompanySetting::get("openrouter_api_key")."\n";
' 2>&1 | grep -v DEPRECATED

# Test service config
php artisan tinker --execute='
$svc = app(\App\Services\AiAnalysisService::class);
$m = new ReflectionMethod($svc, "getLlmConfig"); $m->setAccessible(true);
echo json_encode($m->invoke($svc))."\n";
' 2>&1 | grep -v DEPRECATED
```

## Files Modified This Session
- `app/Filament/Pages/CompanySettingPage.php` — dynamic form + per-provider API key fields + HasForms
- `app/Services/AiAnalysisService.php` — getLlmConfig() match per provider
- `app/Models/CompanySetting.php` — SENSITIVE_KEYS includes all 3 API keys
- Migration: 3 new rows in company_settings (gemini_api_key, deepseek_api_key, openrouter_api_key)