# VMS Testing Bug Registry

## Bug 1: Carbon diffInMinutes Returns Negative for Past/Future Dates

### Context
Discovered during VMS dashboard testing on 2026-07-24. The DMS Live Traffic widget on the dashboard displayed `-10015 menit lalu` for content with `updated_at` of 2026-07-17 (7 days ago).

### Root Cause
```php
// dashboard.blade.php line 155
$latest = $a->content_data['meta']['updated_at'] ?? null;
$mins = $latest ? now()->diffInMinutes($latest) : 999;
```

`Carbon::diffInMinutes()` returns negative values when the compared date is in the past (or future). The signed diff is intended for "time until" calculations, not display.

### Correct Patterns
```php
// Option 1: Absolute minutes
$mins = $latest ? abs(now()->diffInMinutes($latest)) : 999;

// Option 2: Human readable (used correctly in VMS cards at line 419)
$vms->last_fetch_at->diffForHumans()  // "6 days ago"
```

### Files Affected
- `resources/views/dashboard.blade.php` lines 155, 164

### Similar Patterns to Check
- Any `diffInMinutes()`, `diffInHours()`, `diffInDays()` used for display
- The VMS cards at line 419 correctly use `diffForHumans()`

### Verification
```bash
cd /c/VMS/www
/c/VMS/php/php.exe artisan tinker --execute="
\$dt = \Carbon\Carbon::parse('2026-07-17T14:22:54+07:00');
echo 'diffInMinutes: ' . now()->diffInMinutes(\$dt) . PHP_EOL;
echo 'abs: ' . abs(now()->diffInMinutes(\$dt)) . PHP_EOL;
echo 'diffForHumans: ' . \$dt->diffForHumans() . PHP_EOL;
"
# Output:
# diffInMinutes: -10021
# abs: 10021
# diffForHumans: 6 days ago
```