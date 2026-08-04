# API Testing Cheatsheet for Full-Stack Apps

Quick reference for testing common endpoints with curl.

## Authentication

### Login
```bash
RESPONSE=$(curl -s -X POST http://localhost:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}')

TOKEN=$(echo "$RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
echo "Token: $TOKEN"
```

### Get Profile
```bash
curl http://localhost:5000/api/auth/profile \
  -H "Authorization: Bearer $TOKEN"
```

### Change Password
```bash
curl -X PUT http://localhost:5000/api/auth/change-password \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"oldPassword":"admin123","newPassword":"newpass123"}'
```

## Devices

### Summary Stats
```bash
curl http://localhost:5000/api/devices/summary \
  -H "Authorization: Bearer $TOKEN"
# Returns: { "total": 50, "normal": 49, "rusak": 1, "offline": 0, "totalEnergy": 62500 }
```

### List Devices (Paginated)
```bash
curl "http://localhost:5000/api/devices?page=1&limit=10" \
  -H "Authorization: Bearer $TOKEN"
```

### Filter by Status
```bash
curl "http://localhost:5000/api/devices?status=rusak&zone=Zona+A" \
  -H "Authorization: Bearer $TOKEN"
```

### Get Single Device
```bash
curl http://localhost:5000/api/devices/TL-TOLL-00001 \
  -H "Authorization: Bearer $TOKEN"
```

### Create Device (Admin Only)
```bash
curl -X POST http://localhost:5000/api/devices \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "TL-TOLL-00051",
    "name": "PJU Tol 051",
    "type": "controller",
    "zone": "Zona A",
    "latitude": -6.2,
    "longitude": 106.8,
    "status": "normal"
  }'
```

### Update Device
```bash
curl -X PUT http://localhost:5000/api/devices/TL-TOLL-00001 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"brightness": 80, "status": "maintenance"}'
```

### Delete Device
```bash
curl -X DELETE http://localhost:5000/api/devices/TL-TOLL-00001 \
  -H "Authorization: Bearer $TOKEN"
```

### Get Zones
```bash
curl http://localhost:5000/api/devices/zones \
  -H "Authorization: Bearer $TOKEN"
# Returns: { "zones": ["Zona A", "Zona B", "Zona C", "Zona D"] }
```

## Monitoring

### Real-time Data
```bash
curl http://localhost:5000/api/monitoring/realtime \
  -H "Authorization: Bearer $TOKEN"
```

### Energy Trend (7 days)
```bash
curl "http://localhost:5000/api/monitoring/energy-trend?days=7" \
  -H "Authorization: Bearer $TOKEN"
```

### Device Telemetry History
```bash
curl "http://localhost:5000/api/monitoring/history/TL-TOLL-00001?limit=100" \
  -H "Authorization: Bearer $TOKEN"
```

## Device Control

### Control Single Device
```bash
curl -X POST http://localhost:5000/api/control/device \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "deviceId": "TL-TOLL-00001",
    "action": "dim",
    "value": 50
  }'
```

### Control Zone
```bash
curl -X POST http://localhost:5000/api/control/zone \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "zone": "Zona A",
    "action": "off"
  }'
```

### Control Group
```bash
curl -X POST http://localhost:5000/api/control/group \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "deviceIds": ["TL-TOLL-00001", "TL-TOLL-00002"],
    "action": "on",
    "value": 100
  }'
```

### Get Schedules
```bash
curl http://localhost:5000/api/control/schedules \
  -H "Authorization: Bearer $TOKEN"
```

### Create Schedule
```bash
curl -X POST http://localhost:5000/api/control/schedules \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jadwal Malam",
    "type": "waktu",
    "zone": "Zona A",
    "startTime": "18:00",
    "endTime": "05:00",
    "action": "on",
    "brightnessValue": 100,
    "isActive": true
  }'
```

## Maintenance Tickets

### List Tickets
```bash
curl "http://localhost:5000/api/maintenance?status=baru,terima&priority=tinggi" \
  -H "Authorization: Bearer $TOKEN"
```

### Get Stats
```bash
curl http://localhost:5000/api/maintenance/stats \
  -H "Authorization: Bearer $TOKEN"
# Returns: { "total": 5, "open": 3, "closed": 2, "byPriority": [...] }
```

### Create Ticket
```bash
curl -X POST http://localhost:5000/api/maintenance \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "deviceId": "TL-TOLL-00001",
    "issueType": "lampu_mati",
    "description": "Lampu tidak menyala",
    "priority": "tinggi"
  }'
```

### Update Ticket Status
```bash
curl -X PUT http://localhost:5000/api/maintenance/TKT-123/status \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "diterima",
    "findings": "Kabel putus",
    "rootCause": "Usia pakai"
  }'
```

### Assign Ticket
```bash
curl -X PUT http://localhost:5000/api/maintenance/TKT-123/assign \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "assignedTo": "user-uuid-here"
  }'
```

## Reports

### Energy Report
```bash
curl "http://localhost:5000/api/reports/energy?startDate=2026-06-01&endDate=2026-06-20" \
  -H "Authorization: Bearer $TOKEN"
```

### Performance Report
```bash
curl "http://localhost:5000/api/reports/performance?period=monthly" \
  -H "Authorization: Bearer $TOKEN"
```

### Maintenance Report
```bash
curl "http://localhost:5000/api/reports/maintenance?month=2026-06" \
  -H "Authorization: Bearer $TOKEN"
```

## Users (Admin Only)

### List Users
```bash
curl "http://localhost:5000/api/users?role=teknisi&zone=Zona+A" \
  -H "Authorization: Bearer $TOKEN"
```

### Create User
```bash
curl -X POST http://localhost:5000/api/users \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "teknisi3",
    "email": "teknisi3@smartpju.com",
    "password": "teknisi123",
    "fullName": "Teknisi Baru",
    "role": "teknisi",
    "zone": "Zona C"
  }'
```

### Update User
```bash
curl -X PUT http://localhost:5000/api/users/user-uuid \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "phoneNumber": "+628123456789",
    "isActive": false
  }'
```

### Delete User
```bash
curl -X DELETE http://localhost:5000/api/users/user-uuid \
  -H "Authorization: Bearer $TOKEN"
```

## Common Error Responses

### 401 Unauthorized
```json
{
  "message": "Akses ditolak. Token tidak ditemukan."
}
```

### 403 Forbidden
```json
{
  "message": "Akses ditolak. Hak akses tidak mencukupi."
}
```

### 400 Validation Error
```json
{
  "message": "Data tidak valid",
  "errors": [
    { "field": "username", "message": "Username wajib diisi" },
    { "field": "email", "message": "Email tidak valid" }
  ]
}
```

### 500 Internal Server Error
```json
{
  "message": "Terjadi kesalahan internal server."
}
```

## Testing Workflow

### 1. Health Check First
```bash
curl http://localhost:5000/api/health
# Should return: { "status": "ok", "timestamp": "...", "uptime": ... }
```

### 2. Login & Save Token
```bash
TOKEN=$(curl -s -X POST http://localhost:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' | \
  python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
```

### 3. Test Authenticated Endpoint
```bash
curl http://localhost:5000/api/devices/summary \
  -H "Authorization: Bearer $TOKEN"
```

### 4. Check Error Logs if 500
```bash
tail -50 backend/logs/error.log
```

### 5. Reset if Needed
```bash
# Stop server
# Kill process on port 5000
netstat -ano | findstr ":5000"
taskkill /PID <pid> /F

# Delete DB
rm backend/data/smart_pju.sqlite

# Restart server
cd backend && node src/index.js

# Seed data
npm run seed
```

## WebSocket Testing (Browser Console)

```javascript
// Connect
const socket = io('http://localhost:5000')

// Listen for device updates
socket.on('device:update', (data) => {
  console.log('Device update:', data)
})

// Listen for alerts
socket.on('alert', (alert) => {
  console.log('Alert:', alert)
})

// Subscribe to specific device
socket.emit('subscribe:device', 'TL-TOLL-00001')

// Unsubscribe
socket.emit('unsubscribe:device', 'TL-TOLL-00001')
```

## Quick One-Liners

```bash
# Test if server is up
curl -s http://localhost:5000/api/health | grep -q "ok" && echo "✓ Server running" || echo "✗ Server down"

# Get token and test endpoint in one line
TOKEN=$(curl -s -X POST http://localhost:5000/api/auth/login -H "Content-Type: application/json" -d '{"username":"admin","password":"admin123"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])") && curl -s http://localhost:5000/api/devices/summary -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

# Count devices by status
curl -s http://localhost:5000/api/devices/summary -H "Authorization: Bearer $TOKEN" | python3 -c "import sys,json; d=json.load(sys.stdin); print(f'Total: {d[\"total\"]}, Normal: {d[\"normal\"]}, Rusak: {d[\"rusak\"]}, Offline: {d[\"offline\"]}')"
```

---

**Tip**: For interactive testing, consider using:
- **Postman** or **Insomnia** for GUI-based API testing
- **httpie** for simpler curl syntax: `http POST :5000/api/auth/login username=admin password=admin123`
- **jq** for JSON parsing: `curl ... | jq '.data.devices[] | {id, status}'`