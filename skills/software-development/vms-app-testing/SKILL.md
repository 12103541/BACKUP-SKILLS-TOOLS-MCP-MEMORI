# VMS Application Testing Patterns

## Overview
Testing patterns discovered during comprehensive VMS (Variable Message Sign) Management System functional testing on Laravel + Filament v3.2 with PHP built-in server on Laragon (Windows).

## Session Context
- **App**: VMS Management System (NovaStar/TB2 controller integration)
- **Stack**: Laravel 12, Filament v3.2, SQLite, PHP 8.2
- **Server**: PHP built-in server `php/php.exe -S 127.0.0.1:8000 -t www/public`
- **Test Date**: 2026-07-24
- **Data**: 17 VMS, 51 Content Assignments, 51 DMS, 5 Templates, 9 Toll Gates

## API Endpoints Verified Working

| Endpoint | Method | Auth | Status | Notes |
|----------|--------|------|--------|-------|
| `/api/player/{token}/data` | GET | Token in URL | ✅ 200 | Full VMS config + content + settings |
| `/api/player/{token}/content` | GET | Token in URL | ✅ 200 | Content array for rotation |
| `/api/vms/list` | GET | None | ✅ 200 | List active VMS for Electron app |
| `/api/dms/vms/{vmsId}/active` | GET | None | ✅ 200 | DMS assignments for VMS |
| `/api/vms/{token}/token-status` | GET | Header X-Player-Token | ✅ 200 | Token expiry check |
| `/tb2/display/{vmsId}` | GET | None | ✅ 200 | TB2 NovaStar display page |
| `/tb2/display/{vmsId}/check` | GET | None | ✅ 200 | JSON content check API |
| `/player/{token}` | GET | Token in URL (64-char) | ✅ 200 | Web player display |

## Pages Verified Working (Auth Required)

| Route | Feature | Status |
|-------|---------|--------|
| `/dashboard` | KPI cards, DMS live traffic, token monitor, VMS grid | ✅ 200 |
| `/vms` | Index with search/filter | ✅ 200 |
| `/vms/create` | Full form: coords map, CCTV, display settings | ✅ 200 |
| `/vms/{id}` | Detail: preview, CCTV, history, info | ✅ 200 |
| `/vms/{id}/edit` | Edit form | ✅ 200 |
| `/vms/map` | Leaflet map + 16 markers + popup | ✅ 200 |
| `/content-assignments` | Complex form: DMS/Text/Image/Video, split, live | ✅ 200 |
| `/dms` | CRUD Dynamic Message Sign | ✅ 200 |
| `/template-messages` | CRUD Templates | ✅ 200 |
| `/toll-gates` | CRUD Gerbang Tol | ✅ 200 |
| `/vms-categories` | CRUD Kategori VMS | ✅ 200 |
| `/player` | Player VMS list | ✅ 200 |

## Bugs Found

### 1. Carbon diffInMinutes Negative for Historical/Future Dates
**File**: `resources/views/dashboard.blade.php:155-164`
**Issue**: `$mins = $latest ? now()->diffInMinutes($latest) : 999` returns negative for dates in past/future
**Display**: Shows `-10015 menit lalu` instead of `6 days ago`
**Fix**: Use `abs(now()->diffInMinutes($latest))` or `$latest->diffForHumans()`

```php
// WRONG
$mins = $latest ? now()->diffInMinutes($latest) : 999;
<p>{{ $mins }} menit lalu</p>

// CORRECT
$mins = $latest ? abs(now()->diffInMinutes($latest)) : 999;
// OR use diffForHumans like VMS cards do (line 419)
$vms->last_fetch_at->diffForHumans()
```

### 2. API Routes Inconsistent Location
**Issue**: TB2 and Player API routes in `routes/web.php` not `routes/api.php`
**Impact**: Not protected by `auth:sanctum` middleware, no rate limiting
**Routes**: `/tb2/display/*`, `/player/*`, `/api/vms/{token}/token-status`

### 3. Create POST Not Tested
**Status**: Form loads (GET 200) but POST submit not verified
**Risk**: Validation, unique constraints, token generation on create

### 4. TemplateMessage Model Enum Constraints
**File**: Migration `2025_10_20_082613_create_template_messages_table.php`
**Issue**: Database CHECK constraints on `category`, `content_type`, `priority` columns
- `category` enum: `'darurat', 'lalu_lintas', 'umum'` only
- `content_type` enum: `'text'` only (image/video not in migration!)
- `priority` enum: `'low', 'medium', 'high', 'urgent'` only
**Impact**: Cannot create DMS templates via Eloquent without valid enum values
**Workaround**: Use existing valid categories/content_types from DB

### 5. ContentAssignment Required Fields
**Model**: `App\Models\ContentAssignment`
**Required**: `schedule_type` (NOT NULL, no default)
**Valid values**: `now`, `scheduled`, `recurring`
**Impact**: Create fails with "NOT NULL constraint failed: content_assignments.schedule_type"

### 6. VMS Status Blocks API Responses
**Behavior**: VMS with `status = 'maintenance'` returns 403 from `/api/player/{token}/data`
```json
{"success":false,"message":"VMS sedang dalam maintenance","vms_status":"maintenance","vms_name":"..."}
```
**Impact**: Testing player API requires VMS status = 'active'

### 7. API Route Prefix Mismatch
**Actual routes** (from `php artisan route:list`):
- `GET api/player/{token}/data` → `api.player.data`
- `GET api/player/{token}/content` → `api.player.content`
- `GET api/vms/list` → `api.vms.list`
- `GET api/dms/vms/{vmsId}/active` → `api.dms.active`
- `GET api/vms/{token}/token-status` → `api.vms.token-status`

**Note**: No `/api/` prefix in route definitions but `route:list` shows it — prefix likely added in RouteServiceProvider or panel config.

## Reference Files
- `references/carbon-diffinminutes-bug.md` — Carbon diffInMinutes negative date bug
- `references/vms-testing-terminal-patterns.md` — Comprehensive terminal-based testing patterns (curl + artisan tinker)
- `scripts/vms-regression-test.sh` — Regression test script

## Key Models
- `App\Models\Vms` — VMS device, token, heartbeat, last_fetch_at
- `App\Models\ContentAssignment` — Links VMS to content (DMS/Text/Image/Video)
- `App\Models\DynamicMessageSign` — Live traffic destinations
- `App\Models\TemplateMessage` — Reusable content templates
- `App\Models\TollGate` — Gerbang tol for DMS destinations
- `App\Models\VmsCategory` — VMS kategori

## Workflow End-to-End
1. Create VMS (auto-generates access_token) → `/vms/create`
2. Create Template/DMS → `/template-messages/create` or `/dms/create`
3. Create Content Assignment (link VMS + content) → `/content-assignments/create`
4. Player hits `/api/player/{token}/data` every 30s → updates `last_fetch_at`, `last_heartbeat`
5. TB2 Controller hits `/tb2/display/{vmsId}/check` → gets current content JSON
6. Dashboard shows real-time status via `last_fetch_at->diffForHumans()`

## Regression Test Checklist
- [ ] All GET routes return 200 (authenticated) or 302 (unauthenticated)
- [ ] All API endpoints return valid JSON with `success: true`
- [ ] Dashboard KPI cards show correct counts
- [ ] VMS map loads with markers
- [ ] Content Assignment create supports all types (DMS, Text, Image, Video, Split)
- [ ] TB2 check API returns correct content for assigned VMS
- [ ] Player display renders with full 64-char token
- [ ] Token status API returns expiry info
- [ ] No console JS errors on any page
- [ ] Carbon diff displays human-readable (not negative minutes)

## Testing Recipe

### Start Server
```bash
cd C:\VMS
php/php.exe -S 127.0.0.1:8000 -t www/public &
```

### Test All Routes (No Auth)
```bash
for route in /vms /vms/create /vms/29 /vms/map /content-assignments /dms /template-messages /toll-gates /vms-categories /player; do
  curl -s "http://127.0.0.1:8000$route" -o /dev/null -w "%{http_code}\n"
done
# All should be 302 (redirect to login)
```

### Test Authenticated (With Session)
Login via browser first, then test with cookies.

### Test API Endpoints
```bash
TOKEN="dae70d5add4363a895e8f2280fc363b1745a3aac87f51085c1acc2cecbc8bdfa"
curl -s "http://127.0.0.1:8000/api/player/$TOKEN/data" -H "Accept: application/json" | jq .
curl -s "http://127.0.0.1:8000/tb2/display/29/check" | jq .
```

### Database Check Commands
```bash
cd /c/VMS/www
/c/VMS/php/php.exe artisan tinker --execute="
echo 'Users: ' . \App\Models\User::count();
echo 'VMS: ' . \App\Models\Vms::count();
echo 'Assignments: ' . \App\Models\ContentAssignment::count();
echo 'DMS: ' . \App\Models\DynamicMessageSign::count();
"
```

---

*Last updated: 2026-07-24*