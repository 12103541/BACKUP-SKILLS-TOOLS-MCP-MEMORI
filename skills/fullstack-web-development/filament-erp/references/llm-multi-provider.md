# Multi-provider LLM (RAB Copilot Fase 3, 2026-07-31)

## Pattern: OpenAI-compatible chat/completions — satu payload, banyak provider

`app/Services/AiAnalysisService.php` — generalized dari Gemini-only ke multi-provider:

| Provider | base_url | default model |
|---|---|---|
| gemini | `https://generativelanguage.googleapis.com/v1beta/openai/chat/completions` | `gemini-1.5-flash` |
| deepseek | `https://api.deepseek.com/chat/completions` | `deepseek-chat` |
| openrouter | `https://openrouter.ai/api/v1/chat/completions` | `deepseek/deepseek-chat` |

CATATAN Gemini: endpoint OpenAI-compatible pakai header `Authorization: Bearer <key>` —
BUKAN format native `?key=` / `generateContent` / `candidates.0.content.parts.0.text`.

### Payload request
```php
Http::withHeaders(['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key])
    ->post($base_url, [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.3,
    ]);
```
### Parse response
```php
$response->json('choices.0.message.content');
```

### Pemilihan provider (`getLlmConfig()`)
```php
$provider = CompanySetting::get('llm_provider', 'gemini');   // gemini|deepseek|openrouter
$model    = CompanySetting::get('llm_model');                // kosong = default provider
$apiKey   = CompanySetting::get('llm_api_key')
    ?: env('LLM_API_KEY')
    ?: CompanySetting::get('gemini_api_key')                 // legacy key
    ?: env('GEMINI_API_KEY');
```
Tanpa key → method return null → pemanggil (analyzeRab/analyzeProject) fallback ke
`generateLocalRabReport()` / `generateLocalProjectReport()` (laporan markdown rule-based).
JANGAN pernah throw saat key kosong — graceful degradation adalah fitur.

## CompanySetting untuk key LLM

- Keys: `llm_provider`, `llm_model`, `llm_api_key` — group **'ai'** (Settings page mount()
  baca per group: `CompanySetting::whereIn('group', ['profil','ai'])`).
- `llm_api_key` WAJIB masuk `SENSITIVE_KEYS` (`CompanySetting.php`) → auto-encrypt
  via EncryptionService di get()/set()/getValueAttribute/setValueAttribute.

## PITFALL: CompanySetting::set() — kolom `label` NOT NULL

Bug (2026-07-31): `set()` lama pakai `updateOrCreate(['key'],[ 'value' ])` →
QueryException `SQLSTATE 1364 Field 'label' doesn't have a default value`.
Dan updateOrCreate menimpa `group` existing (settings berubah group).

Fix — update hanya value, label/group hanya saat create:
```php
public static function set(string $key, $value, ?string $label = null, string $group = 'umum'): void
{
    if (in_array($key, self::SENSITIVE_KEYS, true)) $value = EncryptionService::encrypt($value);
    $setting = static::firstOrNew(['key' => $key]);
    $setting->value = $value;
    if (!$setting->exists) {
        $setting->label = $label ?: ucwords(str_replace('_', ' ', $key));
        $setting->group = $group;
    }
    $setting->save();
}
```
Kalau key sudah terlanjur masuk group salah via set() lama: `->where('key','like','llm_%')->update(['group'=>'ai'])`.

## UI: section di Settings page

`ProfilPerusahaanPage` (group profil+ai): Select llm_provider (options gemini/deepseek/openrouter),
TextInput llm_model (placeholder default provider), TextInput llm_api_key `->password()->revealable()`
helperText "Disimpan terenkripsi. Kosong = AI mati (fallback laporan lokal)".

## Debugging settings-page "Simpan" diam-diam gagal

1. `php artisan optimize:clear` + `view:clear` dulu (cache stale sering biang kerok).
2. Tambah marker sementara di save(): `Log::info('PROFIL-SAVE', ['keys'=>array_keys($state),'llm'=>$state['llm_provider']??'MISSING'])`.
3. Verifikasi persist via `updated_at` row DB, bukan UI.
4. Form tak terfill (select kosong padahal DB ada value) → cek mount() `form->fill(['data'=>$values])`
   vs statePath('data') konsisten; dan value null vs '' di DB.

## Browser: interaksi Filament Select (CDP)

Klik option di MenuListPopup sering gagal `CDP error (DOM.getBoxModel): Could not compute box model`.
Pola andal: klik combobox → ArrowDown xN → Enter. Verifikasi nilai via
`document.querySelector('[data-field="llm_provider"] select').value`.
