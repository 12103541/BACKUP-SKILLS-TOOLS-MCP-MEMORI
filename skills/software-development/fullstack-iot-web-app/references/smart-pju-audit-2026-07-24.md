# Smart PJU Audit Case Study — 24 July 2026

## Context
Comprehensive review of Smart PJU (Smart Street Lighting) application running in Docker.
5 containers: backend (Node.js/Express), frontend (React/Nginx), MySQL 8.0, Redis 7, Mosquitto 2.

## Infrastructure Findings
- All 5 containers running, backend + MySQL healthy
- Resource usage: ~613MB total (MySQL dominant at 481MB)
- MQTT broker connected with backend client (`smartpju-server-*`)
- All on same Docker bridge network (`smartpju-network`)

## API Endpoint Matrix

| Endpoint | Status | Notes |
|----------|--------|-------|
| `/api/auth/login` | ✅ 200 | JWT auth, returns token + user |
| `/api/devices/summary` | ✅ 200 | total=50, normal=41, rusak=7 |
| `/api/devices/zones` | ✅ 200 | 4 zones (A,B,C,D) |
| `/api/devices?page=&limit=` | ✅ 200 | Paginated, 17 pages |
| `/api/maintenance/tickets` | ✅ 200 | Ticket list (empty) |
| `/api/notifications` | ✅ 200 | 1476 notifications |
| `/api/rules` | ✅ 200 | Automation rules |
| `/api/schedules` | ✅ 200 | Sun schedules |
| `/api/audit-logs` | ✅ 200 | Audit log list |
| `/api/weather/current` | ✅ 200 | Real-time weather |
| `/api/monitoring/realtime` | ✅ 200 | Real-time device data |
| `/api/maintenance-analysis/*` | ❌ 404 | Route not mounted |
| `/api/predictive/*` | ❌ 404 | Route not mounted |
| `/api/energy-optimization/*` | ❌ 404 | Route not mounted |
| `/api/backup/list` | ❌ 404 | Route not mounted |
| `/api/reports/energy-summary` | ❌ 404 | Route not mounted |
| `/api/control/status` | ❌ 404 | Route not mounted |

**6 routes the frontend calls but backend doesn't serve (404).**

## Frontend Pages (16 pages)
All loaded successfully except Real-time Monitoring (redirects to dashboard — route missing).

Key observations:
- Dashboard: KPI cards, weather widget, energy chart, recent activity
- Interactive Map: Leaflet + OpenStreetMap, 50 markers, zone/status filters
- Control: Manual/Jadwal/Otomatis/Mode/Sync/Firmware tabs, brightness slider
- Maintenance: Ticket workflow (Baru→Ditugaskan→Diterima→Dikerjakan→Selesai→Ditutup)
- Maintenance Analysis: Health scores per device (72% average), zone breakdown
- Predictive Maintenance: Risk ranking, failure probability, trend analysis
- Energy Optimization: Per-device recommendations, savings projections
- Reports: PDF/Excel/CSV export, date range, 3 tabs (Energi/Kinerja/Pemeliharaan)
- Users: 4 users (Admin/Pengelola/Teknisi), full CRUD
- Audit Logs: Login/create/update/delete tracking with IP
- Notifications: Multi-channel (Push/Email/WA), auto-ticket for offline devices
- Automation: Sensor data table (V/A/W/°C/dBm), rule creation
- Sun Schedules: Sunrise/sunset scheduling
- Backup: SQLite + zip, JSON export
- Management: Device CRUD + simulator (15s interval)

## Score: 75/100

### What works well
- Complete IoT architecture (MQTT + WebSocket + REST)
- Predictive maintenance with health scoring
- Multi-channel notifications with auto-ticketing
- RBAC with 3 roles
- Device simulator for testing
- Weather integration

### What needs fixing
1. 6 API routes not mounted in Express app
2. Real-time Monitoring page route missing
3. Energy Optimization shows all zeros (not hitting API)
4. MQTT status shows "Offline" in header despite broker running
5. Some data still empty (tickets, schedules, rules)

## JWT Token Truncation Bug
Terminal output truncates JWT tokens to ~13 characters. Must save tokens to files:
```bash
docker exec backend node -e "..." > /tmp/token.txt
curl -H "Authorization: Bearer $(cat /tmp/token.txt)" ...
```
See main skill for full workaround.
