# BackupService — Laragon + mysqldump Pitfalls

## Problem: mysqldump Not in PATH
Laragon does NOT add MySQL binaries to the system PATH. `mysqldump` is buried inside:
```
C:/laragon/bin/mysql/mysql-X.X.XX-winx64/bin/mysqldump.exe
```
The exact version directory changes with MySQL version.

**CRITICAL — Windows PHP path syntax:** Use `C:/laragon/bin/mysql` (forward slashes), NOT `/c/laragon/bin/mysql` (MSYS syntax). PHP on Windows does NOT understand MSYS `/c/` paths — `is_dir('/c/laragon/bin/mysql')` returns `false` even though the directory exists. Only bash/MSYS understands `/c/` prefixes.

## Fix: Auto-Detect Path
```php
protected function findMysqldump(): string
{
    $mysqldump = 'mysqldump'; // fallback
    // Use Windows paths, NOT MSYS
    foreach (['C:/laragon/bin/mysql', 'C:/Program Files/MySQL', 'C:/xampp/mysql/bin'] as $base) {
        if (is_dir($base)) {
            foreach (scandir($base) as $sub) {
                $p = $base . '/' . $sub . '/bin/mysqldump.exe';
                if (file_exists($p)) { return $p; }
                $p2 = $base . '/mysqldump.exe';
                if (file_exists($p2)) { return $p2; }
            }
        }
    }
    return $mysqldump;
}
```

## Problem: mysqldump Argument Order
Common mistake — placing table names BEFORE database name.
**Wrong:** `mysqldump table1 table2 dbname > out.sql`
**Right:** `mysqldump dbname table1 table2 > out.sql`

The database name MUST come first, then optional table names.

## Correct Command Construction
```php
$mysqldump = $this->findMysqldump();
$cmd = "\"{$mysqldump}\" --host={$h} --port={$po} --user={$u} --password={$pw} --single-transaction --routines --triggers";
$cmd .= ' ' . escapeshellarg($db);  // DB NAME FIRST
if (!empty($tables)) {
    $cmd .= ' ' . implode(' ', array_map('escapeshellarg', $tables)); // TABLES AFTER
}
$cmd .= ' > ' . escapeshellarg($filePath) . ' 2>&1';
```

## Problem: backupFull() Return Field Mismatch
`backupFull()` returns `total_size` but consumers expect `size`.
Ensure both return `['size' => ...]` consistently across all backup methods.

## Verification
After fixing, test via Tinker:
```php
// php artisan tinker --execute="..."
$path = "C:/laragon/bin/mysql"; // Windows path, NOT /c/laragon/
$dirs = scandir($path);
foreach ($dirs as $d) {
    $p = $path."/".$d."/bin/mysqldump.exe";
    if (file_exists($p)) echo "FOUND: ".$p;
}
```

## Pitfall: File Edits via Tinker str_replace Fail Silently
When using `php artisan tinker --execute="str_replace(...)"` with multi-line strings containing PHP variables and special chars, the replacement silently fails because:
- PHP variable interpolation inside single-quoted strings in tinker args
- Line ending differences (LF vs CRLF)
- Nested quotes conflict with shell quoting

**Workaround:** When tinker str_replace fails, use a temporary PHP script file:
```bash
cat > storage/fix.php << 'EOF'
<?php
$c = file_get_contents("app/Services/BackupService.php");
// ... do replacements ...
file_put_contents("app/Services/BackupService.php", $c);
EOF
php storage/fix.php
```
Or use `sed` for simple line swaps.
$path = "/c/laragon/bin/mysql";
$dirs = scandir($path);
foreach ($dirs as $d) {
    $p = $path."/".$d."/bin/mysqldump.exe";
    if (file_exists($p)) echo "FOUND: ".$p;
}
'
```
