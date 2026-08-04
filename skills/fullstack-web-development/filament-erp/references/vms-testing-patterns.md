# VMS (Variable Message Sign) Testing Patterns

Reference for testing Laravel + Filament v3 VMS applications with NovaStar/TB2 device communication.

## Environment Setup

```bash
# PHP built-in server for VMS (Windows/Laragon)
# Document root: www/public
# PHP runtime: C:\VMS\php\php.exe (has sqlite3, pdo_sqlite)

# Start server in background
/c/VMS/php/php.exe -S 127.0.0.1:8000 -t /c/VMS/www/public &

# Verify server responds
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/
# Should return 302 (redirect to login)
```

## Authentication Testing

```bash
# Get credentials from seeder
/c/VMS/php/php.exe artisan tinker --execute='
echo "Email: superadmin@vms.com\n";
echo "Password: (seeder default)\n";
'

# Login via browser automation
# 1. Navigate to /
# 2. Fill email: superadmin@vms.com
# 3. Fill password: password
# 4. Click submit
# 5. Should redirect to /dashboard
```

## Comprehensive Route Testing

```bash
# Test ALL web routes (with auth cookies)
routes=(
    "/dashboard"
    "/vms"
    "/vms/create"
    "/vms/1"
    "/vms/map"
    "/content-assignments"
    "/content-assignments/create"
    "/dms"
    "/dms/create"
    "/template-messages"
    "/template-messages/create"
    "/toll-gates"
    "/toll-gates/create"
    "/vms-categories"
    "/vms-categories/create"
    "/player"
    "/software-updates"
    "/activity-logs"
    "/users"
    "/roles"
    "/permissions"
    "/admin/import-export"
    "/settings"
    "/settings/maintenance"
    "/generate-token"
)

# Test with auth (via browser session)
# Test WITHOUT auth (should all return 302 redirect to login)
```

## API Endpoint Testing (NovaStar / TB2 / Player)

```bash
# Get valid VMS token
TOKEN=$(/c/VMS/php/php.exe artisan tinker --execute="echo \App\Models\Vms::where('access_token','!=',null)->first()->access_token;")

# Player API (Electron app / Web player)
curl -s "http://127.0.0.1:8000/api/player/${TOKEN}/data" -H "Accept: application/json"
curl -s "http://127.0.0.1:8000/api/player/${TOKEN}/content" -H "Accept: application/json"

# VMS List for player selection
curl -s "http://127.0.0.1:8000/api/vms/list" -H "Accept: application/json"

# Token status check (device binding, expiry)
curl -s "http://127.0.0.1:8000/api/vms/${TOKEN}/token-status" -H "Accept: application/json" -H "X-Player-Token: ${TOKEN}"

# TB2 NovaStar Controller API
curl -s "http://127.0.0.1:8000/tb2/display/29/check"
# Returns: {"content_id":69,"content_type":"dms","content_name":null,"duration":5,"updated_at":1784874578,"has_content":true}

curl -s "http://127.0.0.1:8000/tb2/display/29" -o /dev/null -w "%{http_code}"
# Returns 200 (full HTML display page)

# DMS API
curl -s "http://127.0.0.1:8000/api/dms/vms/29/active" -H "Accept: application/json"

# Web Player (requires FULL 64-char token, not truncated)
curl -s "http://127.0.0.1:8000/player/${TOKEN}" -o /dev/null -w "%{http_code}"
```

## CRUD Testing via Tinker (Faster than Browser)

```bash
# CREATE VMS
/c/VMS/php/php.exe artisan tinker --execute='
use App\Models\Vms;
$v = Vms::create([
    "name" => "VMS CRUD TEST",
    "location" => "TEST LOC",
    "ip_address" => "192.168.1.99",
    "cctv_url" => "rtsp://192.168.1.99:554/stream1",
    "latitude" => -6.2,
    "longitude" => 106.8,
    "status" => "active",
    "model" => "TEST-MODEL",
    "display_width" => 960,
    "display_height" => 240,
    "orientation" => "landscape",
    "mode" => "sync",
]);
$v->generateAccessToken(now()->addDays(365));
echo "Created VMS ID: " . $v->id . "\n";
echo "Token: " . $v->access_token . "\n";
'

# CREATE TemplateMessage
/c/VMS/php/php.exe artisan tinker --execute='
use App\Models\TemplateMessage;
$t = TemplateMessage::create([
    "category" => "umum",
    "title" => "Test Template CRUD",
    "content_type" => "text",
    "content" => json_encode(["text"=>"TEST","font_size"=>24,"color"=>"#FFF","alignment"=>"center"]),
    "priority" => "medium",
]);
echo "Created Template ID: " . $t->id . "\n";
'

# CREATE ContentAssignment (links VMS + Template)
/c/VMS/php/php.exe artisan tinker --execute='
use App\Models\ContentAssignment;
use App\Models\Vms;
$vms = Vms::where("name","VMS CRUD TEST")->first();
$a = ContentAssignment::create([
    "vms_id" => $vms->id,
    "template_id" => 6,
    "name" => "Assignment Test CRUD",
    "content_type" => "text",
    "content_data" => json_encode(["text"=>"TEST CRUD MESSAGE","font_size"=>24,"color"=>"#FFFFFF","alignment"=>"center"]),
    "schedule_type" => "now",
    "priority" => 10,
    "duration" => 5,
    "status" => "active",
    "is_active" => true,
    "scheduled_at" => now(),
    "expires_at" => now()->addDays(30),
]);
echo "Created Assignment ID: " . $a->id . "\n";
'

# Verify via API
curl -s "http://127.0.0.1:8000/api/player/${TOKEN}/data" | jq '.data.contents[0]'

# UPDATE
/c/VMS/php/php.exe artisan tinker --execute='
use App\Models\ContentAssignment;
$a = ContentAssignment::find(74);
$a->update([
    "name" => "Assignment UPDATED",
    "priority" => 5,
    "duration" => 10,
    "content_data" => json_encode(["text"=>"UPDATED MESSAGE","font_size"=>30,"color"=>"#FFD700","alignment"=>"center"]),
]);
echo "Updated: " . $a->name . " priority: " . $a->priority . "\n";
'

# DELETE
/c/VMS/php/php.exe artisan tinker --execute='
use App\Models\ContentAssignment;
use App\Models\Vms;
ContentAssignment::find(74)->delete();
Vms::where("name","VMS CRUD TEST")->delete();
echo "Deleted test records\n";
'
```

## Role-Based Access Testing

```bash
# Users table has: superadmin@vms.com (Super-admin), admin@vms.com (Admin), user@vms.com (User)
# Roles: super-admin, admin, user

# Test permissions via middleware
# Each admin route has: check.permission:xxx
# Example: manage-software-updates, import-export, access-generate-token

# Verify role-based menu visibility in sidebar
# Super Admin sees ALL sections: MAIN, VMS MANAGEMENT, ADMINISTRATION
# Admin sees subset
# User sees minimal
```

## Import/Export Testing

```bash
# Export VMS (via controller directly - bypasses CSRF)
/c/VMS/php/php.exe artisan tinker --execute='
use App\Http\Controllers\ImportExportController;
use Illuminate\Http\Request;

$ctrl = new ImportExportController();
$request = new Request();
$response = $ctrl->exportVms($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content-Type: " . $response->headers->get("Content-Type") . "\n";
echo "First 500 chars:\n" . substr($response->getContent(), 0, 500) . "\n";
'

# Import VMS (requires CSV with specific columns)
# Columns: ID, Name, Location, IP Address, Serial Number, Model, Status, Latitude, Longitude, Display Width, Display Height, Orientation
# Sample CSV available at /admin/import-export -> Download Sample CSV
```

## Settings/Maintenance Testing

```bash
# Database Maintenance page: /settings/maintenance
# Features tested:
# - Download Backup: Returns .sqlite file
# - Restore Database: Upload .sqlite, auto-backup current before restore
# - Auto Backup Config: Enable/disable, frequency (hourly/daily/weekly/monthly), time, retention
# - Trigger Manual Backup: Creates backup immediately
# - Reset Database: DANGEROUS - wipes all app data (keeps users/roles/permissions/settings/categories)

# Test download backup
curl -s "http://127.0.0.1:8000/settings/maintenance/backup/database.sqlite" -o /dev/null -w "%{http_code}"
# Should return 200 with file download
```

## Bug Found & Fixed: Dashboard Negative Time Display

**Issue**: DMS Live Traffic widget showed `-10015 menit lalu` for data from July 17 when current date is July 24.

**Root Cause**: `now()->diffInMinutes($futureDate)` returns negative for future dates.

**File**: `resources/views/dashboard.blade.php` line 155

**Fix**:
```php
// BEFORE (buggy)
$latest = $a->content_data['meta']['updated_at'] ?? null;
$mins = $latest ? now()->diffInMinutes($latest) : 999;
<p class="text-xs text-gray-500">{{ $mins }} menit lalu</p>

// AFTER (fixed)
$latest = $a->content_data['meta']['updated_at'] ?? null;
$latestDt = $latest ? \Carbon\Carbon::parse($latest) : null;
$mins = $latestDt ? abs(now()->diffInMinutes($latestDt)) : 999;
$timeAgo = $latestDt ? $latestDt->diffForHumans() : 'Never';
<p class="text-xs text-gray-500">{{ $timeAgo }}</p>
```

**Lesson**: Always use `abs()` + `diffForHumans()` for relative time display when data may have future timestamps.

## Bug Found & Fixed: TemplateMessage content_type Enum Expansion

**Issue**: Migration `2025_10_20_082613` created `template_messages` table with `content_type` enum limited to `['text']`. The application workflow uses 4 content types: `text`, `dms`, `image`, `video` (and `template`). UI dropdown only showed "text" — invalid types rejected at DB level but not at model validation.

**Root Cause**: SQLite doesn't support `CHANGE COLUMN` or enum modification directly. Need table recreation with new CHECK constraint.

**Fix**: Created migration `2026_07_24_152555_expand_template_message_content_type_enum.php` that:
1. Creates new table with `CHECK(content_type IN ('text','image','video','dms'))` constraint
2. Copies all existing data (6 templates preserved)
3. Drops old table, renames new table
4. Invalid values rejected at DB level (QueryException)

**Migration Pattern for SQLite Enum Expansion**:
```php
public function up(): void
{
    $driver = DB::connection()->getDriverName();
    
    if ($driver === 'sqlite') {
        // SQLite: recreate table with new CHECK constraint
        DB::statement("ALTER TABLE template_messages ADD COLUMN content_type_new TEXT DEFAULT 'text'");
        DB::statement("UPDATE template_messages SET content_type_new = content_type");
        DB::statement("CREATE TABLE template_messages_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category TEXT NOT NULL CHECK(category IN ('darurat', 'lalu_lintas', 'umum')),
            title TEXT NOT NULL,
            content_type TEXT NOT NULL DEFAULT 'text' CHECK(content_type IN ('text', 'image', 'video', 'dms')),
            content TEXT,
            media_path TEXT,
            media_url TEXT,
            priority TEXT NOT NULL DEFAULT 'medium' CHECK(priority IN ('low', 'medium', 'high', 'urgent')),
            text_alignment TEXT NOT NULL DEFAULT 'left' CHECK(text_alignment IN ('left', 'center', 'right')),
            is_bold INTEGER NOT NULL DEFAULT 0,
            is_italic INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME,
            updated_at DATETIME
        )");
        DB::statement("INSERT INTO template_messages_new (id, category, title, content_type, content, media_path, media_url, priority, text_alignment, is_bold, is_italic, created_at, updated_at)
            SELECT id, category, title, content_type_new, content, media_path, media_url, priority, text_alignment, is_bold, is_italic, created_at, updated_at FROM template_messages");
        DB::statement("DROP TABLE template_messages");
        DB::statement("ALTER TABLE template_messages_new RENAME TO template_messages");
    } else {
        // MySQL/PostgreSQL: use change()
        Schema::table('template_messages', function (Blueprint $table) {
            $table->enum('content_type', ['text', 'image', 'video', 'dms'])->default('text')->change();
        });
    }
}
```

**Verification**:
```php
// Valid types accepted
TemplateMessage::create(['category' => 'umum', 'title' => 'Test', 'content_type' => 'dms', 'priority' => 'medium']);
// Invalid type rejected
TemplateMessage::create(['category' => 'umum', 'title' => 'Test', 'content_type' => 'invalid', 'priority' => 'medium']);
// → QueryException: CHECK constraint failed
```

**Lesson**: For SQLite enum columns, always use table recreation with CHECK constraints. Test both valid and invalid values after migration.

## Git Cleanup Pattern (Preserve History)

```bash
# 1. Update .gitignore FIRST
# Add: php/, vendor/, node_modules/, *.sql, *.bat, storage/uploads, *.exe, *.dll, *.dat

# 2. Remove binaries from tracking (KEEP local files)
git rm --cached -r php/
git rm --cached mysql-8.4-2026-06-11_165801.sql
git rm --cached run-app.bat vms-server-postinstall.bat

# 3. Stage changes
git add .gitignore CLAUDE.md www/app/Http/Controllers/VmsController.php www/resources/views/vms/map.blade.php

# 4. Commit with descriptive message
git commit -m "chore: cleanup repo - remove binaries, SQL dumps, bat files from tracking; update .gitignore; map & controller fixes"

# 5. Normal push (NOT force push)
git push origin main

# Result: 349 tracked files (was 418), history preserved, binaries stay on disk
```

## VMS Workflow End-to-End Verification

```
✅ 1. Login (superadmin@vms.com / password)
✅ 2. Dashboard loads (KPIs, DMS Live Traffic fixed, VMS list with filters)
✅ 3. Create VMS (name, location, IP, CCTV, coords, display config, sync/async mode)
✅ 4. Create Template (category, text formatting, colors)
✅ 5. Create ContentAssignment (multi-step: VMS select → Schedule → Content type)
   - Types: Text, DMS, Image, Video, Template
   - Split mode: Single / Front-Back
   - Priority, duration, schedule (now/scheduled)
✅ 6. Player Display: /player/{token} (web) OR /api/player/{token}/data (Electron)
✅ 7. TB2 NovaStar: /tb2/display/{id} + /check API (10s auto-refresh)
✅ 8. Map: /vms/map (17 markers, click → content preview)
✅ 9. Heartbeat: VMS hits API → updates last_fetch_at, last_heartbeat
✅ 10. Update/Delete via UI or API
✅ 11. Activity Logs: every action logged with user, menu, action, description
✅ 12. RBAC: Users, Roles, Permissions - full CRUD
✅ 13. Import/Export: CSV round-trip for VMS, Templates, Users, Logs
✅ 14. Settings/Maintenance: Backup, Restore, Auto-backup, Reset DB
✅ 15. Software Update: Upload .bin, set active, OTA download
✅ 16. Token Management: Generate, history, device binding, expiry
```

## Key Files for VMS Testing

```
www/app/Http/Controllers/
  - VmsController.php (CRUD + token + map)
  - ContentAssignmentController.php (multi-step wizard)
  - PlayerController.php (web player)
  - Api/VmsPlayerController.php (device APIs)
  - DmsController.php
  - SoftwareUpdateController.php
  - ImportExportController.php
  - ActivityLogController.php
  - UserController.php / RoleController.php / PermissionController.php
  - SettingController.php (maintenance)

www/app/Models/
  - Vms.php (token, heartbeat, activeContentAssignments)
  - ContentAssignment.php (syncToDms, schedule logic)
  - TemplateMessage.php
  - DynamicMessageSign.php
  - SoftwareUpdate.php
  - ActivityLog.php

www/resources/views/
  - dashboard.blade.php (fixed diffInMinutes bug)
  - vms/map.blade.php (Leaflet + 17 markers)
  - vms/create.blade.php / edit.blade.php / show.blade.php
  - content-assignments/create.blade.php (multi-step wizard)
  - tb2-display.blade.php (NovaStar controller page)
  - player/display.blade.php (web player)
  - admin/import-export/index.blade.php
  - settings/maintenance.blade.php
```

## Common Test Commands Reference

```bash
# Start server
cd /c/VMS && /c/VMS/php/php.exe -S 127.0.0.1:8000 -t www/public &

# Check server
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/

# Run specific tests via tinker
/c/VMS/php/php.exe artisan tinker --execute="<php code>"

# Check database
/c/VMS/php/php.exe artisan tinker --execute="echo \App\Models\Vms::count();"
/c/VMS/php/php.exe artisan tinker --execute="echo \App\Models\ContentAssignment::count();"

# Clear caches
/c/VMS/php/php.exe artisan config:clear && /c/VMS/php/php.exe artisan route:clear && /c/VMS/php/php.exe artisan cache:clear

# View routes
/c/VMS/php/php.exe artisan route:list --name=admin | head -30
/c/VMS/php/php.exe artisan route:list | grep -E "tb2|player|api/vms"
```