# Windows Laragon: Writing PHP/Blade Files Persistently

## Problem
`cat > file << 'EOF'` heredocs fail on Windows/bash when:
- Content contains `$`, backticks, `&`, or `${}`
- Content exceeds ~10KB (bash line limit in MSYS)
- Content has single quotes that interfere with heredoc delimiters

**Root cause:** The assistant's `write_file` tool writes to a virtual workspace, NOT the actual Laragon filesystem. Files visible in the tool's read_file are invisible to `ls`, `cat`, `php -l` from terminal.

---

## Solution A: `execute_code` (Preferred — Handles Any Size)

Use the `execute_code` Python tool with `write_file` — it persists to the REAL filesystem:

```python
from hermes_tools import write_file

content = """<?php
// ... full file content ...
"""
write_file(path="C:\\laragon\\www\\PROJECT\\app\\TargetFile.php", content=content)

import subprocess
r = subprocess.run(['php', '-l', 'C:\\laragon\\www\\PROJECT\\app\\TargetFile.php'], capture_output=True, text=True)
print(r.stdout)
```

**Works reliably for files of any size** (tested up to ~25KB Blade files).

---

## Solution B: `php artisan tinker` (For Multi-line Edits)

Use tinker for surgical edits of existing files:

```php
php artisan tinker --execute="
$c = file_get_contents(app_path('Services/MyService.php'));
$c = str_replace('old string', 'new string', $c);
file_put_contents(app_path('Services/MyService.php'), $c);
echo 'Done';
"
```

**CRITICAL pitfalls with this approach:**
1. Nested single-quote escaping via `chr(39)` is error-prone — prefer `storage/fix.php` script (Solution C)
2. `str_replace` may silently fail due to line-ending mismatches (LF vs CRLF)
3. PHP variable interpolation conflicts with tinker's own parsing

---

## Solution C: Temporary PHP Script (Best for Complex Logic)

Write a helper script via `cat >` heredoc:

```bash
cat > storage/fix.php << 'SCRIPTEOF'
<?php
$c = file_get_contents("app/Services/BackupService.php");
// Replace and rearrange multi-line blocks
$lines = explode("\n", $c);
$lines[65] = "        \$cmd .= ' ' . escapeshellarg(\$db);";  // DB NAME FIRST
array_splice($lines, 66, 0, ["        if (!empty(\$tables)) \$cmd .= ' ' . implode(' ', array_map('escapeshellarg', \$tables));"]);
file_put_contents("app/Services/BackupService.php", implode("\n", $lines));
echo "Fixed!\n";
SCRIPTEOF
php storage/fix.php
rm storage/fix.php
```

**Key: Use `<< 'SCRIPTEOF'`** (single-quoted delimiter) to prevent shell variable expansion.

---

## Solution D: `php -r` (For Very Small Snippets)

```bash
php -r "file_put_contents('path.php', 'content here');"
```
Quick for <200 bytes but `\$` escaping gets painful with multi-line strings.

---

## Diagnostics — Check If File Actually Persisted

```bash
# After write_file, verify the file is REAL
php -l "C:/laragon/www/PROJECT/app/MyFile.php"   # "No syntax errors" = REAL
# OR
ls -la app/MyFile.php   # If not found, file didn't persist
```
