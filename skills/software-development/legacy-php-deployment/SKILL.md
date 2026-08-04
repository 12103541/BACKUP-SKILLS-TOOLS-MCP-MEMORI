---
name: legacy-php-deployment
category: software-development
description: Deploy and run legacy PHP applications (CodeIgniter, Yii, etc.) on modern local dev environments (Laragon, XAMPP). Covers PHP 8.x compatibility fixes, database setup, authentication library quirks, and subdirectory hosting.
triggers:
  - Running a legacy PHP app (CI2/CI3, Yii, CakePHP, old Laravel) on modern PHP 8.x
  - PHP deprecation errors with CodeIgniter dynamic properties
  - Ion_auth or old authentication library login/password issues
  - Deploying old PHP apps as subdirectories on Laragon/XAMPP
  - Importing legacy SQL dumps and configuring database connections
  - Sharing Laravel/PHP app on local network (LAN access from other computers)
  - Apache VirtualHost setup for LAN access on Laragon
  - Windows Firewall port opening for web apps
---

# Legacy PHP Application Deployment

Deploy and run legacy PHP applications (especially CodeIgniter 2/3) on modern local dev environments like Laragon with PHP 8.2+ and MySQL 8.0.

## User Preferences
**Language**: Explanations in Bahasa Indonesia, code in English
**Communication**: Direct action — set up, fix, verify. Short status updates.
**Environment**: Laragon at `C:\laragon\`, project goes to `C:\laragon\www\<project-name>\`

## Deployment Workflow

### Step 1: Project Setup
```bash
# Copy project to Laragon www
cp -r "/c/source/path/project" "/c/laragon/www/project-name"

# Or for Windows-native paths
cp -r "C:\source\project" "C:\laragon\www\project-name"
```

### Step 2: Database Setup
```bash
# Find MySQL binary in Laragon
ls /c/laragon/bin/mysql/mysql-*/bin/mysql.exe

# Create database (match the name in application/config/database.php)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS db_name CHARACTER SET utf8 COLLATE utf8_general_ci;"

# Import SQL dump (look for *.sql files in the project)
mysql -u root db_name < "/c/laragon/www/project-name/path/to/dump.sql"

# Verify
mysql -u root db_name -e "SHOW TABLES;"
```

### Step 3: Configuration Fixes

**base_url** — Update `application/config/config.php`:
```php
$config['base_url'] = 'http://localhost/project-name/';
```

**Subdirectory .htaccess** — Add `RewriteBase` when not at web root:
```apache
RewriteEngine On
RewriteBase /project-name/          # <-- MUST match the subdirectory
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]
```

**Database credentials** — Check `application/config/database.php`:
```php
'dbdriver' => 'mysqli',  // must be 'mysqli', NOT 'mysql' (removed in PHP 7)
'hostname' => 'localhost',
'username' => 'root',
'password' => '',
'database' => 'your_db_name',
```

### Step 4: PHP 8.x Compatibility Fix (CRITICAL for CI3)

CodeIgniter 3 uses dynamic properties on core classes (`CI_Controller`, `CI_URI`, `CI_Router`), which are deprecated in PHP 8.2. This produces dozens of `Severity: 8192` errors that flood the page.

**Quick fix** — Filter `E_DEPRECATED` in `index.php`:
```php
case 'development':
    error_reporting(E_ALL & ~E_DEPRECATED);   // NOT error_reporting(-1)
    ini_set('display_errors', 1);
break;
```

**Nuclear option** — If app is completely broken, suppress ALL errors:
```php
error_reporting(0);
ini_set('display_errors', 0);
```

**Proper fix** (if time allows) — Add `#[AllowDynamicProperties]` attribute to CI3 core classes or patch individual classes. Not recommended for quick deployment.

## Pitfalls

### Ion_auth Login Failures
CI3 apps commonly use `ion_auth` for authentication. Common gotchas:

1. **Identity field is 'email'** — `application/config/ion_auth.php` sets `$config['identity'] = 'email'`, so the login form REQUIRES email format (`user@domain.com`), not username. Entering a bare username triggers a **native HTML5 email validation alert** (`"Please include an '@' in the email address"` — browser blocks form submission before it even reaches PHP). Use `admin@admin.com` format, not `administrator`.

2. **Default passwords don't match** — SQL dumps often contain passwords hashed with different bcrypt rounds/salts. The hash `$2y$08$...` in the DB may not correspond to "password" anymore.

3. **Reset admin password** via direct bcrypt:
   ```bash
   # Generate new bcrypt hash
   php -r "echo password_hash('password', PASSWORD_BCRYPT, ['cost' => 8]);"

   # Update in database
   mysql -u root db_name -e "UPDATE users SET password='NEW_HASH_HERE' WHERE id=1;"
   ```

4. **Max password length** — Ion_auth default is 20 chars max. Bcrypt hashes are 60 chars. If users can't login despite correct password, check `$config['max_password_length']` in ion_auth.php.

### PHP Driver Mismatch
CI3 `database.php` may reference `'dbdriver' => 'mysql'` which was removed in PHP 7.0. Must be `'mysqli'`.

### mod_rewrite Not Working
Laragon Apache should have mod_rewrite enabled by default. If 404 errors occur on clean URLs:
1. Check `.htaccess` exists in project root
2. Verify `RewriteBase` matches the subdirectory
3. Ensure Apache `AllowOverride All` for the directory (Laragon default handles this)

### Session Path Not Set
CI3 `config.php` may have `$config['sess_save_path'] = NULL;`. On PHP 8.x, this can cause session errors. Set to a writable temp directory or leave as NULL (CI3 will use system default).

## Verification Checklist

```bash
# 1. App loads (HTTP 200)
curl -s -o /dev/null -w "%{http_code}" http://localhost/project-name/

# 2. Login page accessible
curl -s http://localhost/project-name/auth/login | grep -o '<title>.*</title>'

# 3. Database connection works (check for SQL errors in page)

# 4. No fatal errors (deprecation warnings are OK, fatal = broken)
curl -s http://localhost/project-name/ | grep -c "A PHP Error was encountered"
# Should be 0 for fatal errors; deprecation warnings (8192) are harmless
```

## Starting MySQL Manually (Laragon)

If MySQL is not running (connection refused on localhost:3306), start it manually:

```bash
# Start mysqld in background
/c/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqld.exe --console

# Verify it's alive (wait 3 seconds after start)
sleep 3 && /c/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqladmin.exe -u root ping
# Should return: "mysqld is alive"
```

If using Laragon GUI, click "Start All" or specifically start MySQL from the menu.

## Full Cleanup/Removal

When removing a legacy PHP app completely from Laragon:

```bash
# 1. Drop database
mysql -u root -e "DROP DATABASE IF EXISTS db_name;"

# 2. Remove project from Laragon www
rm -rf "/c/laragon/www/project-name"

# 3. (Optional) Remove original source folder
rm -rf "/c/original/source/project-name"
```

**Verify cleanup:**
```bash
# Confirm database gone
mysql -u root -e "SHOW DATABASES LIKE 'db_name';"  # should return empty

# Confirm folder gone
ls /c/laragon/www/project-name  # should fail (No such file)
```

## Sharing PHP App on Local Network (LAN)

Make a Laragon-hosted PHP app accessible from other computers on the same network.

### Step 1: Find Your LAN IP
```bash
ipconfig | grep "IPv4" | grep -v "169.254"
# e.g. 192.168.0.6
```
**Pitfall**: The IP may change on DHCP reconnect. Recheck each time you share.

### Step 2: Create VirtualHost (Clean URL)
Without this, the app lives at `http://192.168.x.x/Project%20Name/public/` (ugly spaces).

Create a vhost file in Laragon:
```bash
cat > /c/laragon/etc/apache2/sites-enabled/myapp.conf << 'EOF'
<VirtualHost *:80>
    DocumentRoot "C:/laragon/www/my-project/public"
    <Directory "C:/laragon/www/my-project/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
```

To serve as the default site (root URL), rename/disable the default vhost:
```bash
mv /c/laragon/etc/apache2/sites-enabled/00-default.conf /c/laragon/etc/apache2/sites-enabled/00-default.conf.bak
```

**Pitfall**: `mklink /D` (symlinks) does NOT work from Git Bash on Windows. Use the vhost approach instead. `mklink /J` (junction) requires `cmd.exe` elevated.

### Step 3: Update APP_URL
```bash
# Laravel projects
cd "/c/laragon/www/my-project" && sed -i 's|APP_URL=http://localhost:5500|APP_URL=http://192.168.0.6|' .env
php artisan config:cache && php artisan route:cache
```

### Step 4: Open Windows Firewall (Port 80)
**Pitfall**: `netsh advfirewall` requires admin elevation. Git Bash runs unprivileged.

**Workaround** — PowerShell elevation via Git Bash:
```bash
powershell.exe -Command "Start-Process -FilePath 'netsh' -ArgumentList 'advfirewall firewall add rule name=\"MyApp - HTTP\" dir=in action=allow protocol=tcp localport=80' -Verb RunAs -Wait"
```
Verify: `netsh advfirewall firewall show rule name="MyApp - HTTP"`

### Step 5: Restart Apache
Apache must reload the new vhost config:
```bash
# Kill Apache (Laragon auto-restarts it)
powershell.exe -Command "Stop-Process -Name httpd -Force"
sleep 5
# Verify it's back
netstat -ano | grep ":80 " | grep LISTEN
```
**Pitfall**: `taskkill` via Git Bash often doesn't work for httpd.exe. Use `powershell.exe -Command "Stop-Process"` instead.

### Step 6: Verify
```bash
curl -s -o /dev/null -w "%{http_code}" "http://192.168.0.6/admin/login"
# Should return 200
```

### Quick Recap
| Step | Command | Why |
|------|---------|-----|
| Find IP | `ipconfig \| grep IPv4` | Client needs this IP |
| Create vhost | Write to `/c/laragon/etc/apache2/sites-enabled/` | Clean URL, no spaces |
| Update APP_URL | `sed -i 's\|...\|http://IP\|' .env` | Laravel generates correct URLs |
| Open firewall | `netsh advfirewall ... add rule ... localport=80` | Allows inbound TCP/80 |
| Restart Apache | `Stop-Process -Name httpd` (PowerShell) | Reload vhost config |

## Laragon Quick Reference

| Component | Path |
|-----------|------|
| Apache | `C:\laragon\bin\apache\` |
| MySQL | `C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe` |
| PHP | `C:\laragon\bin\php\php-8.2.32-Win32-vs16-x64\php` |
| WWW Root | `C:\laragon\www\` |
| MySQL User | `root` (no password by default) |

## Support Files

- **`references/ci3-php82-compat.md`** — Specific CI3 core file patches for PHP 8.2 compatibility
