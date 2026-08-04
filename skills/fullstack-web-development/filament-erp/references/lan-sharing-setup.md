# Laravel/Laragon LAN Sharing Setup — Session 2026-07-24

## Problem
User wanted to share their Laravel ERP app so it can be accessed from other computers on the same network.

## Environment
- Laragon at `C:\laragon\` with Apache 2.4.54 + MySQL 8.0.30 + PHP 8.2
- Project: `C:\laragon\www\PT.EXFERIA PUTRA INOVASI\`
- APP_URL was `http://localhost:5500`
- Apache already running on port 80, bound to 0.0.0.0

## Steps Taken

### 1. Created VirtualHost
Default vhost served `C:/laragon/www` (parent directory). Created `erp-exferia.conf`:
```apache
<VirtualHost *:80>
    DocumentRoot "C:/laragon/www/PT.EXFERIA PUTRA INOVASI/public"
    <Directory "C:/laragon/www/PT.EXFERIA PUTRA INOVASI/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Disabled `00-default.conf` by renaming to `.bak`.

### 2. Updated APP_URL
Changed from `http://localhost:5500` to `http://<LAN_IP>` in `.env`.

### 3. Opened Windows Firewall
Used PowerShell elevated `netsh` to add HTTP port 80 rule:
```bash
powershell.exe -Command "Start-Process -FilePath 'netsh' -ArgumentList 'advfirewall firewall add rule name=\"ERP Exferia - HTTP\" dir=in action=allow protocol=tcp localport=80' -Verb RunAs -Wait"
```

### 4. Restarted Apache
Used `powershell.exe -Command "Stop-Process -Name httpd -Force"` then manually started with full path to httpd.exe.

### 5. Cleared caches
```bash
php artisan config:cache && php artisan route:cache
```

## Additional Fix: Dual Login Redirect Alignment

After LAN setup, user reported "login dengan jaringan masih menggunakan login main app, bukan Filament". Root cause: root `/` and `/login` routes redirected to custom AuthController login, not Filament's `/admin/login`.

### Fixed routes:
- Root `/` → redirect to `/admin/login`
- `/login` GET → redirect to `/admin/login`
- AuthController `showLogin()` → redirect to `/admin/login`
- AuthController `logout()` → redirect to `/admin/login`

### Also fixed: Custom login accepts email
AuthController `login()` changed from `User::where('username', ...)` to:
```php
$user = User::where('username', $input)->orWhere('email', $input)->first();
```
Login blade label updated: "Username" → "Username atau Email"

## Pitfalls Discovered
1. **`mklink /D` and `/J` don't work from Git Bash** — use `cmd.exe //C "mklink /J ..."` or PowerShell
2. **Apache on Windows doesn't auto-restart** after `taskkill` — must start manually or let Laragon GUI respawn
3. **`00-default.conf` serves parent directory** — creates URLs with spaces (`PT.EXFERIA%20PUTRA%20INOVASI/public/`). Override with custom vhost for clean root URL
4. **LAN IP changes with DHCP** — `ipconfig` may show different IP after reboot. APP_URL needs updating.
5. **Route cache must be cleared** after any redirect/route changes — stale cache causes old redirects to persist
