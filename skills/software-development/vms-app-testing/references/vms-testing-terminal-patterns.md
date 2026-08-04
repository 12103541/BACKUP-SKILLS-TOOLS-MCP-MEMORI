# VMS App Testing via Terminal

Patterns for testing VMS application endpoints using curl and Laravel artisan tinker, avoiding browser automation overhead.

## Server Management

```bash
# Start PHP built-in server (background)
cd /c/VMS/www && /c/VMS/php/php.exe -S 127.0.0.1:8000 -t public > /tmp/vms-server.log 2>&1 &

# Check if server running
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/

# Get VMS token for API testing
cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute="echo \App\Models\Vms::find(29)->access_token;"
```

## Authentication Testing

```bash
# Test login page loads
curl -s "http://127.0.0.1:8000/login" -o /dev/null -w "%{http_code}"

# Test protected routes redirect to login (302)
curl -s "http://127.0.0.1:8000/dashboard" -o /dev/null -w "%{http_code}"
curl -s "http://127.0.0.1:8000/vms" -o /dev/null -w "%{http_code}"
curl -s "http://127.0.0.1:8000/vms/create" -o /dev/null -w "%{http_code}"
```

## CRUD Page Testing (All GET)

```bash
# Test all major CRUD index pages
for route in "/vms" "/content-assignments" "/dms" "/template-messages" "/toll-gates" "/vms-categories"; do
  echo -n "$route: "
  curl -s "http://127.0.0.1:8000$route" -o /dev/null -w "%{http_code}\n"
done

# Test create pages (should redirect to login without auth)
for route in "/vms/create" "/content-assignments/create" "/dms/create" "/template-messages/create"; do
  echo -n "$route: "
  curl -s "http://127.0.0.1:8000$route" -o /dev/null -w "%{http_code}\n"
done
```

## API Endpoint Testing

### Player API (NovaStar/Device Communication)

```bash
TOKEN="dae70d5add4363a895e8f2280fc363b1745a3aac87f51085c1acc2cecbc8bdfa"

# Get player data (full config + content)
curl -s "http://127.0.0.1:8000/api/player/${TOKEN}/data" -H "Accept: application/json" | jq '.'

# Get current content only
curl -s "http://127.0.0.1:8000/api/player/${TOKEN}/content" -H "Accept: application/json" | jq '.'

# Get VMS list for Electron app
curl -s "http://127.0.0.1:8000/api/vms/list" -H "Accept: application/json" | jq '.'
```

### TB2 NovaStar API

```bash
# TB2 Display page (HTML)
curl -s "http://127.0.0.1:8000/tb2/display/29" -o /dev/null -w "%{http_code}"

# TB2 Content Check API (JSON)
curl -s "http://127.0.0.1:8000/tb2/display/29/check" | jq '.'

# Response: {"content_id":69,"content_type":"dms","content_name":null,"duration":5,"updated_at":1784874513,"has_content":true}
```

### DMS API

```bash
# Get active DMS for VMS
curl -s "http://127.0.0.1:8000/api/dms/vms/29/active" -H "Accept: application/json" | jq '.'
```

### Token Status API

```bash
curl -s "http://127.0.0.1:8000/api/vms/${TOKEN}/token-status" -H "Accept: application/json" | jq '.'
```

## Database Verification via Tinker

```bash
# Create test VMS
cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute='
use App\Models\Vms;
$vms = Vms::create([
    "name" => "VMS CRUD TEST",
    "location" => "TEST LOC",
    "ip_address" => "192.168.1.100",
    "cctv_url" => "rtsp://192.168.1.100:554/stream1",
    "status" => "active",
    "vms_category_id" => 1,
    "mode" => "sync",
    "model" => "TEST-1000",
    "serial_number" => "SN-TEST-001",
    "description" => "CRUD test",
    "latitude" => -6.2088,
    "longitude" => 106.8456,
    "display_width" => 1920,
    "display_height" => 1080,
    "orientation" => "landscape",
    "access_token" => hash("sha256", "test-token-" . time()),
    "token_expires_at" => now()->addDays(30),
]);
echo "Created: ID=" . $vms->id . " Token=" . substr($vms->access_token,0,20) . "...";
'

# Create TemplateMessage (with valid enum values)
cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute='
use App\Models\TemplateMessage;
$t = new TemplateMessage();
$t->category = "umum";  // Valid: darurat, lalu_lintas, umum
$t->title = "Test Template";
$t->content_type = "text";  // Valid: text only (migration enum)
$t->content = json_encode(["text" => "TEST", "font_size" => 24, "color" => "#FFF", "alignment" => "center"]);
$t->priority = "medium";  // Valid: low, medium, high, urgent
$t->text_alignment = "center";
$t->is_bold = false;
$t->is_italic = false;
$t->icon = "road";
$t->save();
echo "Template ID: " . $t->id;
'

# Create ContentAssignment (requires schedule_type)
cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute='
use App\Models\ContentAssignment;
use App\Models\Vms;
$vms = Vms::find(47);  // Use created VMS ID
$a = ContentAssignment::create([
    "vms_id" => $vms->id,
    "template_id" => 6,
    "name" => "Test Assignment",
    "content_type" => "text",
    "content_data" => json_encode(["text" => "TEST", "font_size" => 24]),
    "schedule_type" => "now",  // REQUIRED: now, scheduled, recurring
    "priority" => 10,
    "duration" => 5,
    "status" => "active",
    "is_active" => true,
    "scheduled_at" => now(),
    "expires_at" => now()->addDays(30),
]);
echo "Assignment ID: " . $a->id;
'

# Verify API returns content
cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute='
use App\Models\Vms;
$vms = Vms::find(47);
echo "Token: " . $vms->access_token . "\n";
'
```

## Update & Delete Testing

```bash
# Update VMS
cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute='
use App\Models\Vms;
$vms = Vms::find(47);
$vms->update(["location" => "UPDATED LOC", "status" => "maintenance"]);
echo "Updated: " . $vms->location . " / " . $vms->status;
'

# Verify API reflects maintenance status
curl -s "http://127.0.0.1:8000/api/player/${TOKEN}/data" -H "Accept: application/json" | jq '.message'
# Returns: "VMS sedang dalam maintenance"

# Reactivate
cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute='
use App\Models\Vms;
$vms = Vms::find(47);
$vms->update(["status" => "active"]);
echo "Reactivated: " . $vms->status;
'

# Delete ContentAssignment
cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute='
use App\Models\ContentAssignment;
$a = ContentAssignment::find(74);
$a->delete();
echo "Deleted assignment 74";
'

# Delete VMS
cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute='
use App\Models\Vms;
$vms = Vms::find(47);
$vms->delete();
echo "Deleted VMS 47. Remaining: " . Vms::count();
'
```

## Full End-to-End Workflow Test

```bash
#!/bin/bash
# test-vms-workflow.sh

echo "=== VMS Workflow Test ==="

# 1. Create VMS
VMS_ID=$(cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute='
use App\Models\Vms;
$vms = Vms::create([
    "name" => "Workflow Test VMS",
    "location" => "Workflow Loc",
    "ip_address" => "192.168.1.200",
    "status" => "active",
    "vms_category_id" => 1,
    "mode" => "sync",
    "display_width" => 1920,
    "display_height" => 1080,
    "orientation" => "landscape",
    "access_token" => hash("sha256", "workflow-" . time()),
    "token_expires_at" => now()->addDays(30),
]);
echo $vms->id;
' 2>/dev/null | tail -1)

echo "Created VMS ID: $VMS_ID"

# 2. Get token
TOKEN=$(cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute="echo \App\Models\Vms::find($VMS_ID)->access_token;" 2>/dev/null | tail -1)
echo "Token: ${TOKEN:0:20}..."

# 3. Create Template
TEMPLATE_ID=$(cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute='
use App\Models\TemplateMessage;
$t = new TemplateMessage();
$t->category = "umum";
$t->title = "Workflow Template";
$t->content_type = "text";
$t->content = json_encode(["text" => "WORKFLOW TEST", "font_size" => 30]);
$t->priority = "medium";
$t->text_alignment = "center";
$t->is_bold = false;
$t->is_italic = false;
$t->icon = "road";
$t->save();
echo $t->id;
' 2>/dev/null | tail -1)

echo "Created Template ID: $TEMPLATE_ID"

# 4. Create Assignment
ASSIGNMENT_ID=$(cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute="
use App\Models\ContentAssignment;
\$a = ContentAssignment::create([
    'vms_id' => $VMS_ID,
    'template_id' => $TEMPLATE_ID,
    'name' => 'Workflow Assignment',
    'content_type' => 'text',
    'content_data' => json_encode(['text' => 'WORKFLOW TEST', 'font_size' => 30]),
    'schedule_type' => 'now',
    'priority' => 5,
    'duration' => 10,
    'status' => 'active',
    'is_active' => true,
    'scheduled_at' => now(),
    'expires_at' => now()->addDays(30),
]);
echo \$a->id;
" 2>/dev/null | tail -1)

echo "Created Assignment ID: $ASSIGNMENT_ID"

# 5. Test Player API
echo "Testing Player API..."
curl -s "http://127.0.0.1:8000/api/player/${TOKEN}/data" -H "Accept: application/json" | jq '.data.contents[0] | {type, assignment_id, value}'

# 6. Test TB2 API
echo "Testing TB2 API..."
curl -s "http://127.0.0.1:8000/tb2/display/${VMS_ID}/check" | jq '.'

# 7. Cleanup
cd /c/VMS/www && /c/VMS/php/php.exe artisan tinker --execute="
use App\Models\ContentAssignment;
use App\Models\TemplateMessage;
use App\Models\Vms;
ContentAssignment::find($ASSIGNMENT_ID)->delete();
TemplateMessage::find($TEMPLATE_ID)->delete();
Vms::find($VMS_ID)->delete();
echo 'Cleanup done';
" 2>/dev/null

echo "=== Test Complete ==="
```

## Route Discovery

```bash
# List all routes
cd /c/VMS/www && /c/VMS/php/php.exe artisan route:list

# List only API routes
cd /c/VMS/www && /c/VMS/php/php.exe artisan route:list --name=api

# List web routes with pattern
cd /c/VMS/www && /c/VMS/php/php.exe artisan route:list --path=vms
```

## Error Log Checking

```bash
# Check Laravel logs
tail -50 /c/VMS/www/storage/logs/laravel.log | grep -i "error\|exception\|404\|500"

# Real-time log tail
tail -f /c/VMS/www/storage/logs/laravel.log
```

## Key Findings from Testing

1. **GET pages** - All return 200 (with auth) or 302 (without auth)
2. **API endpoints** - Working correctly for Player, TB2, DMS
3. **CRUD Create** - Requires valid enum values for TemplateMessage (category, content_type, priority)
4. **ContentAssignment** - Requires `schedule_type` field (NOT NULL, no default)
5. **VMS Status** - 'maintenance' blocks Player API (403), 'inactive' also blocks
6. **Token format** - 64-char SHA256 hex string, used in URL path for Player API
7. **Route prefixes** - `/api/*` routes defined in `routes/api.php`, `/tb2/*` and `/player/*` in `routes/web.php`