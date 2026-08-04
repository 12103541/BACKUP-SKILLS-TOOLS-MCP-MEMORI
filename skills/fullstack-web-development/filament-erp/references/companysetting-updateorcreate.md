# CompanySetting::set() — updateOrCreate Fix

## Bug
`CompanySetting::set()` used `static::where('key', $key)->update(['value' => $value])` — if a setting key didn't exist in DB, save silently did nothing.

## Fix (2026-07-30)
Changed to `updateOrCreate`:
```php
public static function set(string $key, $value): void
{
    if (in_array($key, self::SENSITIVE_KEYS, true)) {
        $value = EncryptionService::encrypt($value);
    }
    static::updateOrCreate(['key' => $key], ['value' => $value]);
}
```

## Impact
- New settings added to form fields on Settings pages auto-create DB rows
- Encrypted keys (gemini_api_key) still handled correctly
- File in: `app/Models/CompanySetting.php`
