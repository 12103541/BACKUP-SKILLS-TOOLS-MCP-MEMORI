# Blade Cache & Dual-Path Pitfalls — ERP Project

## Dual-Path Pitfall (CRITICAL)
The ERP project has TWO copies on disk:
- **`C:\laragon\www\PT.EXFERIA PUTRA INOVASI\`** — Apache DocumentRoot (WHAT THE BROWSER LOADS)
- **`C:\Users\62897\OneDrive\Desktop\laragon\www\PT.EXFERIA PUTRA INOVASI\`** — OneDrive sync copy (NOT served by Apache)

### Rules
1. **ALWAYS** edit files in `C:\laragon\www\` — that's what Apache serves
2. After editing, sync to OneDrive:
   ```bash
   cp "/c/laragon/www/PT.EXFERIA PUTRA INOVASI/resources/views/..." \
      "/c/Users/62897/OneDrive/Desktop/laragon/www/PT.EXFERIA PUTRA INOVASI/resources/views/..."
   ```
3. When **searching** for files (grep/find), search BOTH paths to find content
4. Apache config: `C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\extra\httpd-vhosts.conf`
5. DocumentRoot: `C:\laragon\www\PT.EXFERIA PUTRA INOVASI\public`

### How to Detect
If a page shows different content than expected, check which path the file is in:
```bash
ls -la "/c/laragon/www/PT.EXFERIA PUTRA INOVASI/resources/views/..."
ls -la "/c/Users/62897/OneDrive/Desktop/laragon/www/PT.EXFERIA PUTRA INOVASI/resources/views/..."
```
Compare modification times — the one with the latest mtime is the one that was edited.

---

## Blade Template Cache — Changes Not Reflecting
After editing any Blade template:
1. **`php artisan view:clear`** (mandatory)
2. **User hard-refreshes browser**: Ctrl+F5 or Ctrl+Shift+R
3. If still not reflecting: PHP OPcache may be serving old files — restart Laragon services

### Debug Marker Technique
When Blade edits don't appear in browser:
1. Add a visible debug marker at the TOP of the Blade file:
   ```html
   <div style="background:red;color:white;padding:4px 8px;font-size:11px;text-align:center;">
     DEBUG: filename v2
   </div>
   ```
2. Run `php artisan view:clear`
3. Reload the page in browser
4. **If red bar appears** → correct file is loaded, browser cache issue (Ctrl+F5)
5. **If red bar does NOT appear** → wrong file is being loaded, or stale compiled view cache
6. Remove debug marker after verification: `sed -i '/DEBUG:/d' file.blade.php && php artisan view:clear`

---

## Variable Computed But Not Used (Blade Bug Pattern)
Common pattern: PHP `@php` block computes a formatted variable but Blade template uses raw model property.

### Example (RAB Workbench)
```php
@php
  // This was computed...
  $hargaSatDisplay = number_format($item['harga_satuan'], 2, ',', '.');
@endphp
<!-- ...but this used the raw value -->
<input value="{{ $item['harga_satuan'] }}">
```

### Fix
Use the computed variable, or for input fields with formatted display:
```html
<input type="text" inputmode="numeric"
    wire:change="updateCell({{ $index }}, 'harga_satuan', $event.target.value.replace(/[^0-9]/g,''))"
    value="{{ $hargaSatDisplay }}">
```
- `type="text" inputmode="numeric"` shows formatted number on mobile
- `wire:change` strips non-numeric chars before sending to PHP
- `number_format()` provides thousand separators (Indonesian: dot separator)
