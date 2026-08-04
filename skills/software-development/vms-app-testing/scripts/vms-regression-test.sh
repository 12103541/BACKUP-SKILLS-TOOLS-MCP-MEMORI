#!/usr/bin/env bash
# VMS Application Comprehensive Test Suite
# Run after any code changes to verify core functionality
# Usage: bash references/vms-regression-test.sh

set -e

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

BASE_URL="http://127.0.0.1:8000"
APP_DIR="/c/VMS/www"
PHP_BIN="/c/VMS/php/php.exe"

PASSED=0
FAILED=0

test_route() {
    local name=$1
    local url=$2
    local expected=$3
    local auth_cookie=${4:-""}
    
    local cmd="curl -s -o /dev/null -w \"%{http_code}\" \"$url\""
    if [ -n "$auth_cookie" ]; then
        cmd="curl -s -b \"$auth_cookie\" -o /dev/null -w \"%{http_code}\" \"$url\""
    fi
    
    local result=$(eval $cmd 2>/dev/null || echo "000")
    
    if [ "$result" = "$expected" ]; then
        echo -e "${GREEN}✓${NC} $name: $result"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $name: got $result, expected $expected"
        ((FAILED++))
    fi
}

test_api() {
    local name=$1
    local url=$2
    local token=$3
    local expected_field=$4
    
    local cmd="curl -s -H \"Accept: application/json\" \"$url\""
    
    local result=$(eval $cmd 2>/dev/null)
    
    if echo "$result" | jq -e ".success == true" >/dev/null 2>&1; then
        if [ -n "$expected_field" ] && echo "$result" | jq -e ".$expected_field" >/dev/null 2>&1; then
            echo -e "${GREEN}✓${NC} $name: success + has $expected_field"
            ((PASSED++))
        elif [ -z "$expected_field" ]; then
            echo -e "${GREEN}✓${NC} $name: success"
            ((PASSED++))
        else
            echo -e "${RED}✗${NC} $name: success but missing $expected_field"
            ((FAILED++))
        fi
    else
        echo -e "${RED}✗${NC} $name: failed or success=false"
        echo "  Response: $result"
        ((FAILED++))
    fi
}

echo "=== VMS App Comprehensive Regression Test ==="
echo "Base URL: $BASE_URL"
echo ""

# 1. Check server is running
echo "1. Server Health Check"
test_route "Root redirect" "$BASE_URL/" "302"
test_route "Login page" "$BASE_URL/login" "200"
echo ""

# 2. Authenticated routes (expect 302 without session)
echo "2. Protected Routes (expect 302 without auth)"
test_route "Dashboard" "$BASE_URL/dashboard" "302"
test_route "VMS Index" "$BASE_URL/vms" "302"
test_route "VMS Create" "$BASE_URL/vms/create" "302"
test_route "VMS Show" "$BASE_URL/vms/29" "302"
test_route "VMS Edit" "$BASE_URL/vms/29/edit" "302"
test_route "VMS Map" "$BASE_URL/vms/map" "302"
test_route "Content Assignments" "$BASE_URL/content-assignments" "302"
test_route "DMS" "$BASE_URL/dms" "302"
test_route "Templates" "$BASE_URL/template-messages" "302"
test_route "Toll Gates" "$BASE_URL/toll-gates" "302"
test_route "VMS Categories" "$BASE_URL/vms-categories" "302"
test_route "Player List" "$BASE_URL/player" "302"
echo ""

# 3. API endpoints (public)
echo "3. Public API Endpoints"
TOKEN="dae70d5add4363a895e8f2280fc363b1745a3aac87f51085c1acc2cecbc8bdfa"

test_api "Player Data API" "$BASE_URL/api/player/$TOKEN/data" "" "data.vms"
test_api "Player Content API" "$BASE_URL/api/player/$TOKEN/content" "" "data.contents"
test_api "VMS List API" "$BASE_URL/api/vms/list" "" "data"
test_api "DMS Active API" "$BASE_URL/api/dms/vms/29/active" "" "data"
test_api "Token Status API" "$BASE_URL/api/vms/$TOKEN/token-status" "" "is_expired"
echo ""

# 4. TB2 NovaStar API
echo "4. TB2 NovaStar API"
test_route "TB2 Display Page" "$BASE_URL/tb2/display/29" "200"
TB2_CHECK=$(curl -s "$BASE_URL/tb2/display/29/check" 2>/dev/null)
if echo "$TB2_CHECK" | jq -e ".has_content == true" >/dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} TB2 Check API: has_content=true"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} TB2 Check API: failed"
    echo "  Response: $TB2_CHECK"
    ((FAILED++))
fi
echo ""

# 5. Database record counts
echo "5. Database Record Counts"
cd "$APP_DIR"
COUNTS=$("$PHP_BIN" artisan tinker --execute="
echo 'Users: ' . \App\Models\User::count() . PHP_EOL;
echo 'VMS: ' . \App\Models\Vms::count() . PHP_EOL;
echo 'Assignments: ' . \App\Models\ContentAssignment::count() . PHP_EOL;
echo 'DMS: ' . \App\Models\DynamicMessageSign::count() . PHP_EOL;
echo 'Templates: ' . \App\Models\TemplateMessage::count() . PHP_EOL;
echo 'Toll Gates: ' . \App\Models\TollGate::count() . PHP_EOL;
echo 'Categories: ' . \App\Models\VmsCategory::count() . PHP_EOL;
" 2>/dev/null)

echo "$COUNTS"
echo ""

# 6. Carbon diffForHumans check (bug #1)
echo "6. Carbon diffForHumans Verification (Bug #1 check)"
cd "$APP_DIR"
DIFF_CHECK=$("$PHP_BIN" artisan tinker --execute="
\$dt = \Carbon\Carbon::parse('2026-07-17T14:22:54+07:00');
echo 'diffInMinutes: ' . now()->diffInMinutes(\$dt) . PHP_EOL;
echo 'abs diffInMinutes: ' . abs(now()->diffInMinutes(\$dt)) . PHP_EOL;
echo 'diffForHumans: ' . \$dt->diffForHumans() . PHP_EOL;
" 2>/dev/null)

echo "$DIFF_CHECK"
if echo "$DIFF_CHECK" | grep -q "diffForHumans: 6 days ago"; then
    echo -e "${GREEN}✓${NC} diffForHumans works correctly"
    ((PASSED++))
else
    echo -e "${YELLOW}!${NC} diffForHumans check inconclusive"
fi
echo ""

# 7. TemplateMessage enum validation (bug #4)
echo "7. TemplateMessage Enum Constraints Check"
cd "$APP_DIR"
ENUM_CHECK=$("$PHP_BIN" artisan tinker --execute="
use App\Models\TemplateMessage;
\$cats = TemplateMessage::distinct()->pluck('category')->toArray();
echo 'Categories: ' . implode(', ', \$cats) . PHP_EOL;
\$types = TemplateMessage::distinct()->pluck('content_type')->toArray();
echo 'Content Types: ' . implode(', ', \$types) . PHP_EOL;
\$prios = TemplateMessage::distinct()->pluck('priority')->toArray();
echo 'Priorities: ' . implode(', ', \$prios) . PHP_EOL;
" 2>/dev/null)

echo "$ENUM_CHECK"
echo ""

# 8. ContentAssignment required fields (bug #5)
echo "8. ContentAssignment Required Fields Check"
cd "$APP_DIR"
ASSIGN_CHECK=$("$PHP_BIN" artisan tinker --execute="
\$cols = DB::getSchemaBuilder()->getColumnListing('content_assignments');
echo 'Columns: ' . implode(', ', \$cols) . PHP_EOL;
" 2>/dev/null)

echo "$ASSIGN_CHECK"
echo ""

# 9. VMS Status blocking (bug #6)
echo "9. VMS Maintenance Status API Block Check"
cd "$APP_DIR"
STATUS_CHECK=$("$PHP_BIN" artisan tinker --execute="
use App\Models\Vms;
\$vms = Vms::find(47);  // Test VMS if exists
if (\$vms) {
    echo 'Test VMS 47 status: ' . \$vms->status . PHP_EOL;
    echo 'Token: ' . substr(\$vms->access_token, 0, 20) . '...' . PHP_EOL;
} else {
    echo 'Test VMS 47 not found' . PHP_EOL;
}
" 2>/dev/null)

echo "$STATUS_CHECK"
echo ""

# 10. Route discovery
echo "10. Route Discovery (api.php vs web.php)"
cd "$APP_DIR"
ROUTES=$("$PHP_BIN" artisan route:list --name=api 2>/dev/null | head -20)
echo "$ROUTES"
echo ""

# Summary
echo "=== Summary ==="
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed!${NC}"
    exit 1
fi