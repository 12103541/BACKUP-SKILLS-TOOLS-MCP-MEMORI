---
name: filament-multi-provider-llm
description: Add multi-provider LLM to Filament with encrypted keys.
tags: [filament, laravel, llm, ai, multi-provider, settings]
---

# Filament Multi-Provider LLM Configuration

## Overview
Pattern for adding provider-agnostic LLM support to Laravel + Filament applications. Each provider gets its own API key field, model config, and optional custom endpoint. Keys stored encrypted via `CompanySetting::set()`.

## Database Schema
```php
// company_settings table (existing)
key (string) — e.g., 'gemini_api_key', 'deepseek_api_key', 'openrouter_api_key', 'custom_api_key', 'custom_base_url', 'custom_model', 'llm_provider', 'llm_model'
group (string) — 'ai'
label (string) — UI label
type (string) — 'password' | 'text' | 'select'
value (text) — encrypted for password type
```

## Filament Settings Page (`app/Filament/Pages/CompanySettingPage.php`)

### AI Section Fields — **FLAT STATE (no `settings.` prefix)**
```php
// Provider select
Select::make('llm_provider')
    ->options([
        'gemini' => 'Google Gemini',
        'deepseek' => 'DeepSeek',
        'openrouter' => 'OpenRouter',
        'custom' => 'Custom (OpenAI-compatible)',
    ])
    ->live(onBlur: true)

// Model input
TextInput::make('llm_model')
    ->placeholder('kosongkan utk default provider')

// Per-provider API keys (password + revealable)
TextInput::make('gemini_api_key')
    ->password()->revealable()->columnSpanFull()
TextInput::make('deepseek_api_key')
    ->password()->revealable()->columnSpanFull()
TextInput::make('openrouter_api_key')
    ->password()->revealable()->columnSpanFull()

// Custom provider fields
TextInput::make('custom_api_key')
    ->password()->revealable()->columnSpanFull()
TextInput::make('custom_base_url')
    ->placeholder('https://api.example.com/v1/chat/completions')
TextInput::make('custom_model')
```

### Mount — **FLAT STATE**
```php
public function mount(): void
{
    $settings = CompanySetting::pluck('value', 'key')->toArray();  // ['key' => 'value']
    $this->form->fill($settings);  // direct flat fill
}
```

### Save Method — **FLAT STATE + CompanySetting::set()**
```php
public function save(): void
{
    $data = $this->form->getState();  // flat: ['llm_provider' => 'openrouter', 'openrouter_api_key' => 'sk-...', ...]
    foreach ($data as $key => $value) {
        CompanySetting::set($key, $value);  // auto-encrypt for SENSITIVE_KEYS
    }
}
```

### ⚠️ PITFALLS (Session 2026-07-31)
| Wrong Pattern | Correct Pattern | Why |
|---------------|-----------------|-----|
| `Select::make('settings.llm_provider')` | `Select::make('llm_provider')` | Form state is flat, not nested |
| `$this->form->fill(['settings' => $nested])` | `$this->form->fill($flatArray)` | Mount expects flat key→value |
| `$data = $this->form->getState()['settings']` | `$data = $this->form->getState()` | State is already flat |
| `CompanySetting::where('key', $k)->update(['value' => $v])` | `CompanySetting::set($k, $v)` | **Bypasses encryption** for SENSITIVE_KEYS |

## Service Layer (`app/Services/AiAnalysisService.php`)

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

## CompanySetting Model — Sensitive Keys
```php
class CompanySetting extends Model
{
    protected const SENSITIVE_KEYS = [
        'gemini_api_key',
        'deepseek_api_key',
        'openrouter_api_key',
        'custom_api_key',
        // ... existing sensitive keys
    ];

    public static function set(string $key, $value, string $label = null, string $group = null): self
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->value = $value;
        if ($label) $setting->label = $label;
        if ($group) $setting->group = $group;
        $setting->save();
        return $setting;
    }

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting?->value ?? $default;
    }
}
```

## Migration Script (one-time)
```php
$keys = [
    'gemini_api_key' => ['label' => 'Gemini API Key', 'group' => 'ai', 'type' => 'password'],
    'deepseek_api_key' => ['label' => 'DeepSeek API Key', 'group' => 'ai', 'type' => 'password'],
    'openrouter_api_key' => ['label' => 'OpenRouter API Key', 'group' => 'ai', 'type' => 'password'],
    'custom_api_key' => ['label' => 'Custom Provider API Key', 'group' => 'ai', 'type' => 'password'],
    'custom_base_url' => ['label' => 'Custom Provider Base URL', 'group' => 'ai', 'type' => 'text'],
    'custom_model' => ['label' => 'Custom Provider Model', 'group' => 'ai', 'type' => 'text'],
];
foreach ($keys as $key => $cfg) {
    CompanySetting::firstOrCreate(['key' => $key], $cfg);
}
// Migrate legacy llm_api_key -> gemini_api_key
if ($old = CompanySetting::get('llm_api_key')) {
    CompanySetting::set('gemini_api_key', $old, 'Gemini API Key', 'ai');
}
```

## Pitfalls & Gotchas
| Issue | Fix |
|-------|-----|
| `update(['value' => ...])` bypasses encryption | **Always** use `CompanySetting::set($key, $value)` |
| `formatStateUsing(number_format)` on numeric inputs breaks programmatic fill | Don't use `formatStateUsing` on fields filled via `$this->form->fill()` |
## RAB AI Copilot Integration (Session 2026-07-31)
The ERP includes a local-first RAB generator (`app/Services/RabCopilotService.php`) that works without any LLM API key. It uses templates + internal price sources (sparepart → HargaReferensi → riwayat → estimasi).

### Service Pattern
```php
class RabCopilotService {
    protected array $templates = [
        'pemasangan_pju' => [
            ['uraian' => 'Tiang PJU Octagonal 9m', 'satuan' => 'unit', 'per' => 'titik'],
            ['uraian' => 'Lampu LED PJU 120W', 'satuan' => 'unit', 'per' => 'titik'],
            ['uraian' => 'Kabel NYY 2x6mm', 'satuan' => 'm', 'per' => 'titik', 'volume_per' => 37.5],
            // ...
        ],
        'perawatan_pju' => [ /* monthly items */ ],
    ];

    public function generate(string $jenis, float $volume): array {
        foreach ($this->templates[$jenis] ?? [] as $tpl) {
            $vol = match ($tpl['per']) {
                'titik', 'bulan' => round($volume * ($tpl['volume_per'] ?? 1), 2),
                'ls' => 1,
                'fixed' => $tpl['volume'],
            };
            [$harga, $sumber] = $this->resolveHarga($tpl['uraian']);

            $items[] = [
                'pilih' => true,
                'uraian_pekerjaan' => $tpl['uraian'],
                'volume' => $vol,
                'satuan' => $tpl['satuan'],
                'harga_satuan' => $harga,
                'jumlah_harga' => round($vol * $harga, 2),
                'sumber' => $sumber,
            ];
        }

        return $items;
    }
}
```

### Filament CreateRab Page — Action Modal with Repeater
```php
// Header action in CreateRab.php
protected function getHeaderActions(): array {
    return [
        Action::make('ai_generate')
            ->label('✨ Buat RAB dengan AI')
            ->icon('heroicon-o-sparkles')
            ->form([  // ← MUST use form(), NOT schema()
                Select::make('jenis')->options($this->rabCopilotService->jenisOptions())->required(),
                TextInput::make('volume')->numeric()->required()->default(8),
                Action::make('generate')
                    ->label('⚡ Generate Draft')
                    ->action(fn() => $this->generateDraft()),  // populates $this->draftKomponen
                Repeater::make('draft_komponen')
                    ->schema([
                        Checkbox::make('pilih')->default(true)->label('Pilih'),
                        TextInput::make('uraian_pekerjaan')->required(),
                        TextInput::make('volume')->numeric()->required(),
                        Select::make('satuan')->options($satuanOptions)->required(),
                        TextInput::make('harga_satuan')->numeric()->required()
                            ->helperText('Harga dari: sparepart / referensi / riwayat / estimasi'),
                        TextInput::make('sumber')->disabled(),
                    ])
                    ->columns(6),
                Action::make('apply')
                    ->label('Terapkan ke RAB')
                    ->action(fn() => $this->applyDraft()),
            ])
            ->modalWidth('6xl'),
    ];
}

// Apply draft to main form
protected function applyDraft(): void {
    $selected = collect($this->draft_komponen)->where('pilih', true)->values()->toArray();
    $this->data['komponen'] = $selected;
    $this->form->fill(['komponen' => $selected]);
}
```

### HargaReferensi Seeding (Fase 2)
```php
// Historical from RabKomponen (min/avg/max per item)
RabKomponen::select('uraian_pekerjaan', 'satuan', DB::raw('MIN(harga_satuan) as min'), DB::raw('AVG(harga_satuan) as avg'), DB::raw('MAX(harga_satuan) as max'))
    ->groupBy('uraian_pekerjaan', 'satuan')
    ->each(fn($r) => HargaReferensi::create([... 'sumber' => 'historis', 'harga_terendah' => $r->min, ...]));

// Supplier from Sparepart.harga_jual
Sparepart::whereNotNull('harga_jual')->each(fn($s) => HargaReferensi::create([... 'sumber' => 'supplier', 'harga_rata2' => $s->harga_jual, ...]));

// cariHarga() uses keyword matching with stopwords filter
```

### AI Price Dashboard (`/admin/rab/ai-price`)
- Lists all 36 HargaReferensi rows with source badges
- "Analisa RAB" dropdown per RAB → calls `AiAnalysisService::analyzeRab()`
- Report shows matched/overpriced/safe items + LLM narrative (or local fallback)

### Verified Results (2026-07-31)
- **Fase 1**: Generate 8 titik PJU → 10 komponen, Rp 109.040.000 material + 30% markup = **Rp 141.752.000** (identik RAB riil)
- **Fase 2**: 36 HargaReferensi rows seeded, 9/10 matched on test RAB, AI Price dashboard live
- **Fase 3**: Multi-provider LLM (Gemini/DeepSeek/OpenRouter/Custom) + encrypted keys + UI in CompanySettingPage

## References
- `references/rab-ai-copilot.md` — Complete RAB AI Copilot documentation (all 3 phases)
- `references/ai-multi-provider-llm.md` — Multi-provider LLM config details

## Test Script
- `scripts/test-rab-ai-copilot.php` — Run `php _test_rab_ai_copilot.php` from project root to verify all 3 phases

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
echo \App\Models\CompanySetting::get("deepseek_api_key");
$raw = DB::table("company_settings")->where("key","deepseek_api_key")->value("value");
echo "RAW: " . substr($raw, 0, 20) . "..."; // encrypted JSON
'
```

## Extending to New Providers
1. Add provider to select options in `CompanySettingPage.php`
2. Add `*_api_key` field to migration + UI (password + revealable)
3. Add case in `getLlmConfig()` match + switch
4. Add env var fallback (e.g., `ANTHROPIC_API_KEY`)
5. Test with real API call