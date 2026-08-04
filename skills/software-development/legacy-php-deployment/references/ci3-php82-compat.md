# CodeIgniter 3 & PHP 8.2 Compatibility

## The Problem
CI3 uses dynamic property creation (`$this->config = ...`) on core stdClass objects, which triggers PHP 8.2 deprecation:

```
Severity: 8192
Message: Creation of dynamic property CI_URI::$config is deprecated
```

Every request produces 30-50 deprecation warnings, flooding the page but not breaking it.

## Quick Fix (recommended for deployment)
In `index.php`, change dev error reporting to filter deprecations:

```php
// BEFORE (shows all errors including deprecations)
case 'development':
    error_reporting(-1);
    ini_set('display_errors', 1);
break;

// AFTER (hide deprecation warnings, keep other errors)
case 'development':
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', 1);
break;
```

## Permanent Fix (for production-quality code)
Add the `#[AllowDynamicProperties]` attribute to CI3 core classes that use dynamic properties.

### Files to patch (system/core/):
- `Controller.php` — line ~75, the `__get` magic method triggers this for every core property
- `URI.php` — line ~101 creates `$this->config` dynamically
- `Router.php` — line ~127 creates `$this->uri` dynamically
- `Model.php` — same pattern as Controller
- `Loader.php` — loads dynamic properties

### Alternative: One-line monkey patch in index.php
```php
// Add before require_once BASEPATH.'core/CodeIgniter.php';
// Suppress dynamic property deprecation globally
// PHP 8.2+ only
if (PHP_VERSION_ID >= 80200) {
    // Report everything except deprecation notices
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}
```

## Bcrypt Verification
CI3 ion_auth uses bcrypt with cost 8. When setting passwords manually:

```bash
# Generate password hash matching ion_auth's default config
php -r "echo password_hash('new_password', PASSWORD_BCRYPT, ['cost' => 8]);"
```

Store the output (like `$2y$08$...`) in the `users.password` column.

## Session Issues
CI3 `$config['sess_save_path'] = NULL;` works fine with PHP 8.2 files driver. Only breaks if:
1. Wrong syntax — must be null, not empty string
2. Database driver is configured but DB table doesn't exist
