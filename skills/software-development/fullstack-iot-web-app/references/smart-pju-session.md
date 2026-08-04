# SMART PJU Session - Reference Notes

**Project**: SMART PJU (Smart Street Lighting Management System)
**Session Date**: 2026-06-20
**Domain**: IoT device management for toll road lighting

## Project Overview

SMART PJU adalah sistem manajemen penerangan jalan tol dengan:
- 10,000+ device points (controllers, sensors)
- Real-time monitoring (voltage, current, power, energy)
- Remote control (on/off/dim by schedule or condition)
- Maintenance ticketing system
- Energy management & reporting
- Interactive map visualization

## Architecture Implemented

```
┌─────────────────┐
│   IoT Devices   │ (Simulated)
│  TL-TOLL-00001  │
│  TL-TOLL-00002  │
│       ...       │
└────────┬────────┘
         │ MQTT (simulated)
         ▼
┌─────────────────┐      WebSocket      ┌──────────────────┐
│  Backend API    │ ◄─────────────────► │   Frontend       │
│  Node.js +      │                     │   React + Vite   │
│  Express        │                     │   + Tailwind     │
│  Port: 5000     │                     │   Port: 5173     │
└────────┬────────┘                     └──────────────────┘
         │
         │ Sequelize ORM
         ▼
┌─────────────────┐
│   SQLite DB     │ (Development)
│  data/          │
│  smart_pju      │
│  .sqlite        │
└─────────────────┘
```

## Key Implementation Decisions

### 1. Storage Strategy: Sequelize over Custom JSON Store

**Initial Problem:**
Code memiliki dual model layer:
- Custom Collection class dengan JSON file storage
- Sequelize ORM models (User.js, Device.js, dll)

**Conflict Symptom:**
- `models/index.js` menggunakan Collection, TETAPI meng-export nama yang sama dengan Sequelize models
- Controllers meng-import dari `../models` dan mendapat Collection wrapper
- Seed script dan auth controller tidak kompatibel

**Resolution:**
Rewrite `models/index.js` untuk menggunakan Sequelize ORM consistently.

**Before (problematic):**
```javascript
const Collection = require('../store/Collection');
const collections = {
  User: new Collection('users', { dataDir: DATA_DIR, primaryKey: 'id' }),
  Device: new Collection('devices', { dataDir: DATA_DIR, primaryKey: 'id' }),
  // ...
};
// Custom wrapper functions...
```

**After (Sequelize):**
```javascript
const sequelize = require('../config/database');
const User = require('./User');
const Device = require('./Device');
const MaintenanceTicket = require('./MaintenanceTicket');

// Associations
MaintenanceTicket.belongsTo(User, { as: 'assignee', foreignKey: 'assignedTo' });
Device.hasMany(MaintenanceTicket);

module.exports = { sequelize, User, Device, MaintenanceTicket, syncDatabase, Op, fn, includeJoin };
```

**Why Sequelize Won:**
- PRD target: 10,000+ devices → JSON file storage too slow
- Foreign key constraints & relational integrity
- Time-series data (EnergyLog) needs efficient querying
- Production-ready (can switch to PostgreSQL without code changes)

### 2. Password Hashing via Model Hooks

**Issue:**
Login gagal dengan "Username atau password salah" meskipun username benar.

**Root Cause:**
Password di database tidak ter-hash karena:
1. Seed script pakai `bulkCreate()` yang TIDAK menjalankan hooks
2. Auth controller pakai `User.comparePassword(password, user.password)` static method, tapi seharusnya instance method

**Fix Applied:**

**User.js model:**
```javascript
const User = sequelize.define('User', {
  // ... fields
}, {
  hooks: {
    beforeCreate: async (user) => {
      user.password = await bcrypt.hash(user.password, 12);
    },
    beforeUpdate: async (user) => {
      if (user.changed('password')) {
        user.password = await bcrypt.hash(user.password, 12);
      }
    },
  },
});

User.prototype.comparePassword = async function (candidatePassword) {
  return bcrypt.compare(candidatePassword, this.password);
};
```

**authController.js:**
```javascript
// BEFORE (wrong):
if (!user || !(await User.comparePassword(password, user.password))) {

// AFTER (correct):
if (!user || !(await user.comparePassword(password))) {
```

### 3. Port Conflicts on Windows

**Issue Repeated:**
Server gagal start dengan `EADDRINUSE: address already in use :::5000`

**Why:**
Previous node process still holding port, even after Hermes session ends.

**Working Solution:**
```bash
# Find PID using port 5000
netstat -ano | findstr ":5000"

# Kill with PowerShell (from bash)
powershell -Command "Stop-Process -Id <PID> -Force"

# Verify port is free
netstat -ano | findstr ":5000" || echo "Port 5000 is free"
```

**Note:** `taskkill /F /PID <pid>` gagal di MSYS bash karena path parsing issue. PowerShell works.

### 4. Missing sqlite3 Package

**Error:**
```
Error: Please install sqlite3 package manually
```

**Quick Fix:**
```bash
cd backend
npm install sqlite3 --save
```

**Why Not Installed Initially:**
Package dependencies in `package.json` tidak include sqlite3 explicitly because:
- Sequelize supports multiple dialects
- User mungkin pilih PostgreSQL di production
- sqlite3 adalah optional dependency untuk Sequelize

### 5. Sync Database Order Matters

**Issue:**
Foreign key constraint failures when syncing tables.

**Solution:**
Sync in correct order (parent tables first, then children):

```javascript
const syncDatabase = async (options = {}) => {
  await User.sync(options);                    // No foreign keys
  await Device.sync(options);                  // No foreign keys
  await Schedule.sync(options);                // No foreign keys
  await MaintenanceTicket.sync(options);       // FK → User, Device
  await EnergyLog.sync(options);               // FK → Device
  console.log('✅ Database synced');
};
```

## API Endpoints Implemented

### Authentication
- `POST /api/auth/login` → JWT token
- `GET /api/auth/profile` → Current user (protected)
- `PUT /api/auth/profile` → Update profile (protected)
- `PUT /api/auth/change-password` → Change password (protected)

### Devices
- `GET /api/devices` → List all devices (paginated, filtered)
- `GET /api/devices/:id` → Device detail + recent energy logs
- `POST /api/devices` → Add device (admin only)
- `PUT /api/devices/:id` → Update device (admin only)
- `DELETE /api/devices/:id` → Remove device (admin only)
- `GET /api/devices/zones` → List all zones
- `GET /api/devices/summary` → Dashboard summary

### Monitoring
- `GET /api/monitoring/realtime` → Real-time telemetry
- `GET /api/monitoring/energy-trend` → 7-day energy trend
- `GET /api/monitoring/history/:id` → Device history

### Control
- `POST /api/control/device` → Control single device
- `POST /api/control/group` → Control device group
- `POST /api/control/zone` → Control entire zone
- `GET /api/control/schedules` → List schedules
- `POST /api/control/schedules` → Create schedule (admin/pengelola)

### Maintenance
- `GET /api/maintenance` → List tickets (filtered)
- `GET /api/maintenance/:id` → Ticket detail
- `POST /api/maintenance` → Create ticket
- `PUT /api/maintenance/:id/status` → Update status
- `PUT /api/maintenance/:id/assign` → Assign to technician
- `GET /api/maintenance/stats` → Statistics

### Reports
- `GET /api/reports/energy` → Energy consumption report
- `GET /api/reports/performance` → Device performance report
- `GET /api/reports/maintenance` → Maintenance summary

### Users (Admin)
- `GET /api/users` → List users
- `POST /api/users` → Create user
- `PUT /api/users/:id` → Update user
- `DELETE /api/users/:id` → Remove user

## Seeded Data

### Users
```
admin / admin123      → admin role
pengelola1 / pengelola123 → pengelola role (Zona A)
teknisi1 / teknisi123 → teknisi role (Zona A)
teknisi2 / teknisi123 → teknisi role (Zona B)
```

### Devices
- 50 sample devices: TL-TOLL-00001 to TL-TOLL-00050
- 4 zones: Zona A, B, C, D
- Coordinates around Jakarta: -6.2, 106.8
- Status distribution: normal/7, rusak/13, offline/19, maintenance/19

### Schedules
- Jadwal Malam Zona A (18:00-05:00, 100% brightness)
- Jadwal Redup Zona B (22:00-04:00, 50% brightness)
- Sensor Cahaya Zona C (auto on/off based on light sensor)

## Testing Commands

```bash
# Health check
curl http://localhost:5000/api/health

# Login
curl -X POST http://localhost:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Get devices (with token)
curl http://localhost:5000/api/devices \
  -H "Authorization: Bearer <TOKEN>"

# Get device summary
curl http://localhost:5000/api/devices/summary \
  -H "Authorization: Bearer <TOKEN>"
```

## Background Process Pattern

Start backend as background process in Hermes:

```python
terminal(
  command="cd backend && node src/index.js",
  background=True,
  notify_on_complete=True,  # CRITICAL: always set this for long jobs
)

# Poll status
process(action="poll", session_id="proc_xxx")

# Or wait for completion
process(action="wait", session_id="proc_xxx", timeout=300)
```

## Frontend Integration (Pending)

Frontend sudah built dengan React + Vite + Tailwind:
- Login page with JWT storage
- Dashboard dengan summary cards
- Interactive map (Leaflet) dengan device markers
- Device list dengan filtering & pagination
- Maintenance ticket management
- Energy charts (ApexCharts)

**To test frontend:**
```bash
cd frontend
npm run dev  # Vite dev server on port 5173
```

Then open http://localhost:5173 in browser.

## Docker Deployment

Full stack tersedia via docker-compose:

```bash
docker-compose up -d
# Services:
# - PostgreSQL (5432)
# - Mosquitto MQTT (1883, 9001)
# - Redis (6379)
# - Backend API (5000)
# - Frontend (80)
```

## Files Modified This Session

1. `backend/src/models/index.js` - Rewritten for Sequelize ORM
2. `backend/src/controllers/authController.js` - Fixed password comparison
3. `backend/src/store/` - Moved to `backend/src/store.backup/` (unused)

## Lessons Learned

1. **Always verify model layer** before debugging controllers
2. **Password hashing hooks** harus tested dengan seed data
3. **Port management** critical on Windows development
4. **Background processes** need proper notification setup
5. **Sequelize sync order** matters for foreign keys
6. **Collection wrapper pattern** conflicts with Sequelize - pick one

## Next Steps for Production

1. Migrate SQLite → PostgreSQL for production
2. Add real MQTT broker (Mosquitto) integration
3. Deploy device simulator to actual hardware
4. Add HTTPS/TLS encryption
5. Configure Redis for WebSocket scaling
6. Add monitoring (health checks, metrics)
7. Set up CI/CD pipeline
8. Add automated tests (Jest for backend, React Testing Library for frontend)

---

**Session Status**: Backend 100% operational ✅, Frontend build pending
**Server Running**: http://localhost:5000
**Database**: SQLite (development)
**Devices**: 50 simulated devices updating every 15s