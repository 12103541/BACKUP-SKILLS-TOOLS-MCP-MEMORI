# CompanySetting::set() Refactor & Dynamic Settings Page (2026-07-31)

## set() Refactor — `firstOrNew` Pattern
**Problem:** Old `set()` used `updateOrCreate` which required all fields. For new AI settings (`llm_provider`, `llm_model`, `llm_api_key`), `label` and `group` were missing → "Field 'label' doesn't have a default value" error.

**Fix:** Changed to `firstOrNew` — update only `value` on existing, create with full `label` + `group` on new:
```php
public static function set(string $key, $value, ?string $label = null, ?string $group = null): void
{
    $setting = self::firstOrNew(['key' => $key]);
    $setting->value = $value;
    if ($label) $setting->label = $label;
    if ($group) $setting->group = $group;
    $setting->save();
}
```

**Usage:** All Settings pages must call `CompanySetting::set($key, $value)` — NEVER `CompanySetting::where('key', $key)->update(['value' => $value])` (bypasses encryption for sensitive keys).

## Generic CompanySettingPage Pattern
`app/Filament/Pages/CompanySettingPage.php` — renders ALL groups from DB dynamically:

```php
// mount
$settings = CompanySetting::all()->groupBy('group');
$this->form->fill(['settings' => $settings->toArray()]);

// form — statePath('settings'), field names: settings.settings.{key}
$groups = [
    'profil' => 'Profil Perusahaan',
    // ...
    'ai' => '🤖 AI / LLM (RAB Copilot)',
];

foreach ($groups as $groupKey => $groupLabel) {
    if (!isset($settings[$groupKey])) continue;
    // custom fields per key for 'ai' group
    // standard fields via match($setting->type) for others
}

// save — uses CompanySetting::set() for encryption
foreach ($settings as $key => $value) {
    CompanySetting::set($key, $value);
}
```

**Custom field handling for 'ai' group:**
- `llm_provider` → Select with options (gemini/deepseek/openrouter)
- `llm_api_key` → password + revealable + columnSpanFull
- `llm_model` → text with placeholder/helperText

**Benefits:** New settings groups auto-appear without code changes. Just add row to `company_settings` table with correct `group`.

## Sensitive Keys Encryption
`CompanySetting::SENSITIVE_KEYS = ['gemini_api_key', 'llm_api_key', ...]`
- On `get()`: decrypts via `EncryptionService::decrypt()`
- On `set()`: encrypts via `EncryptionService::encrypt()`
- Raw DB value = base64 JSON with iv/ciphertext/tag

## Files
- `app/Models/CompanySetting.php` — SENSITIVE_KEYS, set() refactor
- `app/Filament/Pages/CompanySettingPage.php` — dynamic form + ai group
- `app/Filament/Pages/Settings/ProfilPerusahaanPage.php` — mount groups ['profil','ai']