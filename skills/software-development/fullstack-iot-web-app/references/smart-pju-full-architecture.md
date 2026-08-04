# SMART PJU — Complete Architecture Map (July 2026)

## Docker Services (5)
| Service | Container | Image | Ports |
|---------|-----------|-------|-------|
| backend | smartpju-backend | smart-pju-backend:latest | 8081→5000 |
| frontend | smartpju-frontend | smart-pju-frontend:latest | 3000→80 |
| mysql | smartpju-db | mysql:8.0 | 3307→3306 |
| redis | smartpju-redis | redis:7-alpine | 6379 |
| mqtt | smartpju-mqtt | eclipse-mosquitto:2 | 1883, 9001 |

## Backend Structure (Node.js/Express/Sequelize)
- **Entry**: src/index.js — Express + HTTP/HTTPS + Socket.IO
- **Framework**: Express 4.18, Sequelize 6.35, Socket.IO 4.7
- **DB**: MySQL 8.0 (prod), SQLite (dev fallback)
- **Security**: JWT (15min access, 7d refresh), bcrypt 12 rounds, Helmet, CORS, rate limiting

## 16 Database Models
User, Device, Zone, Schedule, ScheduleConfig, Rule, MaintenanceTicket,
EnergyLog, DataQueue, DeviceConfig, SyncLog, Firmware, Notification,
UserNotificationPreference, AuditLog, (+ implicit join associations)

### Key Model Fields
- **Device**: id (string "TL-TOLL-00001"), mode (hybrid/online/local), syncStatus (synced/pending/conflict), protocolConfig (AES-256-GCM encrypted), firmwareVersion, configVersion
- **User**: roles: admin/pengelola/teknisi, zone assignment, whatsappNumber
- **Rule**: conditions (JSON array), actions (JSON array), scope (global/zone/device), conditionType (sensor/time/schedule/weather/hybrid)
- **ScheduleConfig**: sunrise/sunset/fixed_time, SunCalc-based, offsetMinutes, scope
- **Firmware**: version, checksum (SHA-256), signature (HMAC-SHA256), encrypted (AES-128-CBC), status (draft/published/rollback)

## 25 API Route Groups (100+ endpoints)
auth, devices, monitoring, control, maintenance, reports, users, protocols,
modes, queues, sync, firmwares, notifications, rules, schedules, zones,
maintenance-analysis, energy-optimization, weather, audit-logs, predictive,
simulator, backup, + /api/health

### Key Endpoints
- POST /api/auth/login — JWT access+refresh tokens, rate limited 5/min
- POST /api/control/device — on/off/dim via MQTT
- POST /api/sync/:id/config — push config to device with SHA1 checksum
- POST /api/firmwares/upload — multer, AES-128 encrypted, HMAC signed
- GET /api/predictive/dashboard — health scores, risk levels, trend data
- POST /api/energy-optimization/apply/:deviceId — brightness optimization

## 25+ Background Services
| Service | Interval | Purpose |
|---------|----------|---------|
| SimulatorService | 15s | Fake telemetry for 50 devices, connection drop simulation |
| ModeService | 60s | Auto hybrid/online/local switching, 2-min offline threshold, priority system (server>teknisi>schedule) |
| MonitoringJobService | 5min/10min/1min/8AM | Anomaly detection, SLA escalation, device status, daily summary emails |
| OfflineQueueService | 30s | Queue telemetry when offline, retry with backoff, flush on reconnect |
| SyncService | 60s | Push full config packages to connected devices, SHA1 checksums |
| RuleEngineService | 30s | Evaluate rule conditions, execute brightness/power/mode actions |
| ScheduleService | Sunrise/sunset recalc daily | SunCalc-based scheduling, fixed_time, offset from sunrise/sunset |
| WeatherService | 10min | Simulated or OpenWeatherMap, drives automation conditions |
| ProtocolService | One-shot init | MQTT, Modbus (RTU/TCP), LoRaWAN (ChirpStack), HTTP handlers |
| NotificationService | Event-driven | Email (nodemailer), SMS (mock/Twilio), WhatsApp (mock/Twilio/Baileys), push (WebSocket) |
| FirmwareService | Event-driven | Upload with encryption, publish, rollback, OTA push |
| PredictionService | On-demand | Health scores, risk levels, trend analysis from energy logs |

## Multi-Protocol Architecture
- **MQTT** (primary): topic pattern `smartpju/{deviceId}/telemetry` / `command`
- **Modbus**: RTU (serial) and TCP, register mapping for telemetry
- **LoRaWAN**: ChirpStack API integration, OTAA activation, hex-encoded commands
- **HTTP**: REST API devices, basic/bearer/apiKey auth, polling-based subscribe
- All behind ProtocolAdapter interface (connect/disconnect/sendCommand/subscribe)

## Security Layers
- AES-256-GCM encryption for device protocol config
- AES-128-CBC encryption for firmware binaries
- HMAC-SHA256 signatures for firmware authenticity
- SHA-1 checksums for config package integrity
- JWT with password hash fragment in refresh token (for revocation)
- Role-based access: admin > pengelola > teknisi

## Frontend (React/Vite)
- 16 pages: login, dashboard, map, monitoring-realtime, control, maintenance, maintenance-analysis, energy-optimization, predictive-maintenance, schedules, automation, reports, users, admin, audit-logs, notifications, backup, profile
- Libraries: Leaflet maps, Chart.js, Socket.IO, i18n (id/en)
- Nginx proxy: /api → backend:5000, /socket.io → backend:5000

## Seed Data
- 4 users (admin, pengelola1, teknisi1, teknisi2)
- 50 devices (TL-TOLL-00001 to -00050) across 4 Jakarta toll road zones
- 3 schedules, 3 firmware versions
- 4 zones with GeoJSON boundaries (Dalam Kota, Jagorawi, Jakarta-Tangerang, Pelabuhan)

## Environment Variables
```
DB_DIALECT=mysql, DB_HOST=mysql, DB_NAME=smart_pju
MQTT_HOST=mqtt, MQTT_TOPIC_PREFIX=smartpju
REDIS_HOST=redis
JWT_EXPIRES_IN=24h
DEVICE_OFFLINE_THRESHOLD_MINUTES=30
VOLTAGE_MIN=160, VOLTAGE_MAX=270, TEMPERATURE_MAX=60
ELECTRICITY_TARIFF=1500 (Rp/kWh)
SIMULATOR_ENABLED=true
```
