---
name: fullstack-iot-web-app
category: software-development
description: Build full-stack IoT web applications with Node.js/Python backend, React/Vue frontend, real-time WebSocket updates, MQTT integration, and SQL/NoSQL databases. Covers architecture patterns, dual storage strategies, and common pitfalls.
tags: [nodejs, react, iot, mqtt, websocket, sequelize, postgresql, sqlite, real-time]
---

# Full-Stack IoT Web Application Development

Build production-ready IoT management systems (smart lighting, sensors, device monitoring) with real-time updates, device telemetry, and maintenance workflows.

## Architecture Pattern

```
┌─────────────┐      MQTT       ┌──────────────┐
│ IoT Devices │ ───────────────►│  MQTT Broker │
└─────────────┘                 │ (Mosquitto)  │
                                └──────┬───────┘
                                       │
                                       ▼
┌─────────────┐      REST/WS    ┌──────────────┐
│   Frontend  │ ◄──────────────►│   Backend    │
│  (React)    │                 │ (Node.js)    │
└─────────────┘                 └──────┬───────┘
                                       │
                                       ▼
                                ┌──────────────┐
                                │  PostgreSQL  │
                                │   + Redis    │
                                └──────────────┘
```

## Tech Stack Recommendations

### Backend
- **Runtime**: Node.js 18+ or Python 3.11+
- **Framework**: Express.js / FastAPI
- **ORM**: Sequelize (Node) or SQLAlchemy (Python)
- **Database**: PostgreSQL (production) / SQLite (development)
- **Real-time**: Socket.io or WebSocket
- **MQTT**: mqtt.js (Node) or paho-mqtt (Python)
- **Validation**: express-validator / pydantic

### Frontend
- **Framework**: React 18+ with Vite or Vue 3
- **State**: Zustand / Redux Toolkit / Pinia
- **UI**: Tailwind CSS / Material UI
- **Maps**: Leaflet / Mapbox (for device geolocation)
- **Charts**: Chart.js / ApexCharts
- **Real-time**: socket.io-client

### Infrastructure
- **Message Broker**: Eclipse Mosquitto (MQTT)
- **Cache**: Redis (session, real-time pub/sub)
- **Database**: PostgreSQL + TimescaleDB (time-series)
- **Deployment**: Docker + docker-compose

## Project Structure

```
project/
├── backend/
│   ├── src/
│   │   ├── config/          # Environment & database config
│   │   ├── models/          # Sequelize/SQLAlchemy models
│   │   │   ├── index.js     # Model registry & associations
│   │   │   ├── User.js
│   │   │   ├── Device.js
│   │   │   └── ...
│   │   ├── controllers/     # Business logic
│   │   ├── routes/          # API endpoints
│   │   ├── middleware/      # Auth, validation, error handling
│   │   ├── services/        # MQTT, WebSocket, background jobs
│   │   └── utils/           # Logger, helpers
│   ├── data/                # SQLite file (dev only)
│   ├── .env
│   └── package.json
├── frontend/
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── store/           # State management
│   │   └── api/             # API client
│   ├── package.json
│   └── vite.config.js
├── docker-compose.yml       # PostgreSQL, MQTT, Redis, backend, frontend
└── README.md
```

## Critical Implementation Steps

### 1. Database Layer Setup (Sequelize)

**DO:**
- Create individual model files (User.js, Device.js, etc.)
- Centralize model registry in `models/index.js`
- Define associations (belongsTo, hasMany) in registry
- Use hooks for password hashing, timestamps
- Export Sequelize helpers: `fn`, `col`, `literal`, `Op` for controllers

**DON'T:**
- Mix custom JSON store with Sequelize ORM (causes conflicts)
- Put all models in one file (hard to maintain)
- Forget to sync models in correct order (foreign key issues)
- Use virtual getters that call Sequelize functions not exported from index.js
- Import from models before database is fully initialized

**Example `models/index.js`:**
```javascript
const sequelize = require('../config/database');
const User = require('./User');
const Device = require('./Device');
const MaintenanceTicket = require('./MaintenanceTicket');

// Associations
MaintenanceTicket.belongsTo(User, { as: 'assignee', foreignKey: 'assignedTo' });
MaintenanceTicket.belongsTo(Device, { foreignKey: 'deviceId' });
Device.hasMany(MaintenanceTicket);

const syncDatabase = async (options = {}) => {
  await User.sync(options);
  await Device.sync(options);
  await MaintenanceTicket.sync(options);
  console.log('✅ Database synced');
};

module.exports = { sequelize, User, Device, MaintenanceTicket, syncDatabase };
```

### 2. Authentication Middleware

**Required features:**
- JWT token verification
- Role-based access control (Admin, Pengelola, Teknisi)
- Password hashing with bcrypt (12 rounds)

**Example middleware/auth.js:**
```javascript
const authenticate = async (req, res, next) => {
  const token = req.headers.authorization?.split(' ')[1];
  const decoded = jwt.verify(token, config.jwt.secret);
  req.user = await User.findByPk(decoded.id);
  next();
};

const authorize = (...roles) => (req, res, next) => {
  if (!roles.includes(req.user.role)) {
    return res.status(403).json({ message: 'Akses ditolak' });
  }
  next();
};
```

### 3. Input Validation

**Always validate** POST/PUT requests with express-validator:

```javascript
router.post('/devices', [
  body('id').notEmpty().withMessage('Device ID required'),
  body('latitude').isFloat({ min: -90, max: 90 }),
  body('longitude').isFloat({ min: -180, max: 180 }),
], validate, deviceController.create);
```

### 4. MQTT Service Integration

**Key considerations:**
- Connect to MQTT broker on startup
- Subscribe to telemetry topics: `smartpju/+/telemetry`
- Parse JSON payloads and update database
- Broadcast to WebSocket clients for real-time UI updates
- Fallback to simulator mode if MQTT unavailable

### 5. WebSocket Real-time Updates

**Broadcast on:**
- Device telemetry updates
- Alert/gangguan notifications
- Maintenance ticket status changes

**Example:**
```javascript
io.on('connection', (socket) => {
  socket.on('subscribe:device', (deviceId) => {
    socket.join(`device:${deviceId}`);
  });
});

// Broadcast update
io.to(`device:${deviceId}`).emit('device:data', telemetry);
```

## Common Pitfalls & Solutions

### 🚨 JWT Import Missing in Controllers

**Problem:** Login endpoint returns "Terjadi kesalahan internal server" or "jwt is not defined"

**Root cause:** Controller file missing `const jwt = require('jsonwebtoken');` import

**Solution:** Always include at top of authController.js:
```javascript
const jwt = require('jsonwebtoken');
const config = require('../config');
const { User } = require('../models');
```

**Verification:** Before saying "login working", test with authController import check:
```bash
node -c src/controllers/authController.js && echo "✓ Syntax OK"
```

### 🚨 Dual Model Layer Conflict

**Problem:** Having both custom JSON store and Sequelize ORM causes conflicts.

**Symptoms:**
- `syncDatabase is not a function` errors
- Models not found or undefined
- Data not persisting correctly
- "Database ready (JSON file storage)" messages appearing despite Sequelize setup
- Virtual getter errors: `fn is not a function`

**Root Causes (from SMART PJU session 2026-06-20):**
1. Legacy `src/store/` folder with custom Collection class still present
2. Old `src/models/index.js` exporting JSON store wrapper instead of Sequelize models
3. Virtual getters in models calling `fn()` that's not exported from models/index.js
4. Module caching - old code still loaded after updates

**Solution:**
1. **Remove custom store completely:**
   ```bash
   cd backend/src
   rm -rf store/  # or mv to backup
   rm src/models/index.js  # will rewrite fresh
   ```

2. **Rewrite models/index.js with Sequelize only:**
   ```javascript
   const sequelize = require('../config/database');
   const User = require('./User');
   const Device = require('./Device');
   
   // Export Sequelize helpers for controllers
   const Op = require('sequelize').Op;
   const fn = sequelize.fn;
   const col = sequelize.col;
   const literal = sequelize.literal;
   
   module.exports = { sequelize, User, Device, syncDatabase, Op, fn, col, literal };
   ```

3. **Remove problematic virtual getters:**
   - Check model definitions for getters calling `fn()`
   - Moving virtual logic to controllers or computed fields
   - Test: `node -e "const m = require('./src/models'); console.log(Object.keys(m));"`

4. **Clear module cache and restart:**
   ```bash
   # Kill all node processes
   taskkill /F /IM node.exe
   
   # Delete database file (if starting fresh)
   rm data/smart_pju.sqlite
   
   # Restart server
   node src/index.js
   ```

5. **Verify Sequelize is working:**
   ```bash
   node -e "const { syncDatabase } = require('./src/models'); syncDatabase().then(() => console.log('✅ SUCCESS'))"
   ```

**Prevention:** Always check `models/index.js` exports - if you see `Collection` or `Collection.find`, you're using legacy JSON store code instead of Sequelize.

### 🚨 Password Hashing Issues

**Problem:** Login fails with "Username atau password salah" after seeding.

**Cause:** Password stored in database is not hashed, or double-hashed.

**Solution:**
1. Ensure `beforeCreate` hook in User model hashes password
2. Use `bcrypt.compare(candidate, hashed)` in login, NOT direct string comparison
3. If seed script uses `bulkCreate`, hooks might not run - hash manually in seed or use `.create()` loop
4. After seeding, verify password is hashed in database (should start with `$2a$` or `$2b$`)

**Correct User model hook:**
```javascript
User.init({
  // ... fields
}, {
  hooks: {
    beforeCreate: async (user) => {
      user.password = await bcrypt.hash(user.password, 12);
    },
  },
});
```

**Seed script with proper hashing:**
```javascript
const bcrypt = require('bcryptjs');

await User.bulkCreate([
  { username: 'admin', password: await bcrypt.hash('admin123', 12), role: 'admin' },
], { individualHooks: false }); // bulkCreate skips hooks, so hash manually
```

**Alternative: Use .create() loop (hooks will run):**
```javascript
const users = [
  { username: 'admin', password: 'admin123', role: 'admin' },
  { username: 'teknisi1', password: 'teknisi123', role: 'teknisi' },
];

for (const userData of users) {
  await User.create(userData); // beforeCreate hook will hash password
}
```

### 🚨 Port Already in Use (EADDRINUSE)

**Problem:** `Error: listen EADDRINUSE: address already in use :::5000`

**Solution on Windows:**
```bash
# Find process using port 5000
netstat -ano | findstr ":5000"

# Kill by PID (PowerShell from bash)
powershell -Command "Stop-Process -Id <PID> -Force"

# Alternative: taskkill via bash wrapper
netstat -ano | grep ":5000.*LISTENING" | awk '{print $NF}' | xargs -I {} cmd.exe //c "taskkill /F /PID {}"
```

**Prevention:** Always kill processes before starting development server:
```bash
# Startup script
taskkill /F /IM node.exe 2>/dev/null || true
sleep 2
node src/index.js
```

### 🚨 SQLite3 Package Missing

**Problem:** `Error: Please install sqlite3 package manually`

**Solution:**
```bash
npm install sqlite3 --save
```

## Testing Checklist

Before declaring backend ready:

- [ ] Database syncs without errors (`npm run seed`)
- [ ] Server starts on port 5000
- [ ] Health endpoint responds: `GET /api/health`
- [ ] Login works with seeded users
- [ ] Protected endpoints reject unauthenticated requests
- [ ] Role-based authorization works (admin-only endpoints)
- [ ] Input validation rejects invalid data
- [ ] WebSocket clients can connect
- [ ] Device simulator generates telemetry (if MQTT disabled)
- [ ] API returns correct JSON structure

## Seed Data Example

Create initial users, devices, and schedules:

```javascript
await User.bulkCreate([
  { username: 'admin', password: await bcrypt.hash('admin123', 12), role: 'admin' },
  { username: 'teknisi1', password: await bcrypt.hash('teknisi123', 12), role: 'teknisi' },
]);

await Device.bulkCreate([
  { id: 'TL-001', name: 'PJU Zone A-1', latitude: -6.2, longitude: 106.8, status: 'normal' },
  // ... 50+ devices
]);
```

## Deployment Notes

### Development (Local)
- Use SQLite for simplicity
- Enable device simulator (no MQTT broker needed)
- Run backend + frontend separately (ports 5000 + 5173)
- Hot reload with nodemon + Vite dev server

### Production
- PostgreSQL with connection pooling
- MQTT broker (Mosquitto) required
- Redis for WebSocket pub/sub scaling
- Docker-compose for all services
- SSL/TLS for HTTPS and MQTTS
- Environment variables for secrets (JWT_SECRET, DB_PASSWORD)

## Session-Specific Notes

For troubleshooting details and session-specific learnings, see:

- `references/smart-pju-session.md` - Complete PRD requirements and architecture
- `references/smart-pju-troubleshooting.md` - Critical issues encountered and solutions (model layer conflicts, password hashing, port conflicts)
- `references/smart-pju-audit-2026-07-24.md` - Full-stack audit results: API endpoint matrix, 6 missing routes, score 75/100, JWT truncation workaround
- `references/cctv-monitoring-vms-patterns.md` - CCTV/parking-capacity monitoring (FastAPI + YOLO + WS): device model ala VMS (host/port/kanal + Fernet credential encryption), lisensi kuota device, sanitasi payload publik vs admin, engine deteksi paralel (1 thread/kamera + cache frame), live view (snapshot/MJPEG via query token utk <img>), RBAC per-lokasi, retensi purge, curl test recipe (MSYS exit-23 quirk, cleanup, restart server lama)

## Related Skills

- **trading-bot-development**: Similar full-stack pattern with database + CLI
- **node-inspect-debugger**: Debug Node.js backend issues
- **systematic-debugging**: Root cause analysis for backend errors
- **github-pr-workflow**: Git workflow for collaborative development

## Systematic Page Building Pattern

When building multiple frontend pages for IoT management systems, follow this **proven pattern** for efficiency and consistency:

### Page Template Structure (20-30KB per page)

Each page should follow this consistent structure:

```jsx
import { useState, useEffect } from 'react'
import { specificApi } from '../services/api'
import toast from 'react-hot-toast'
import { RelevantIcon } from 'lucide-react'

export default function FeaturePage() {
  // 1. State declarations (3-5 state variables)
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [filter, setFilter] = useState({ status: 'all', zone: 'all' })

  // 2. Initial data load (useEffect)
  useEffect(() => { loadData() }, [])

  // 3. Load function with try-catch + toast
  const loadData = async () => {
    try {
      setLoading(true)
      const res = await featureApi.getSomething()
      setData(res.data.something || [])
    } catch (error) {
      console.error('Load error:', error)
      toast.error('Gagal memuat data')
    } finally {
      setLoading(false)
    }
  }

  // 4. CRUD handlers (create, update, delete)
  // 5. Filter logic (computed values)
  // 6. Render (header → stats → form → table/list)
}
```

### Page Building Order (Recommended)

1. **Dashboard** - Overview with stats cards + charts
2. **Interactive Map** - Device geolocation with Leaflet
3. **Control Panel** - Manual/Schedule/Auto modes
4. **Maintenance** - Ticket workflow with priority/assignment
5. **Reports** - Analytics with Recharts (LineChart, PieChart)
6. **Admin** - Device & User CRUD with modal forms

### Per-Page Features Checklist

Each page type has **standard features** that should be included:

#### **Dashboard Page**
- [ ] Summary stat cards (4-5 metrics with icons)
- [ ] Trend charts (7-day period)
- [ ] Recent activity/issues table
- [ ] Quick action buttons

#### **Map Page**
- [ ] Leaflet MapContainer with TileLayer
- [ ] Custom markers per device status (color-coded)
- [ ] Popup with device telemetry on marker click
- [ ] Left sidebar: Device list with search/filter
- [ ] Right sidebar: Selected device detail
- [ ] Zone/status filter controls

#### **Control Page**
- [ ] Tabs: Manual | Schedule | Auto
- [ ] **Manual:** Device toggle buttons, brightness slider, group control
- [ ] **Schedule:** Time picker, zone selection, brightness setting
- [ ] **Auto:** Sensor thresholds, energy saving mode toggle
- [ ] Real-time device state display

#### **Maintenance Page**
- [ ] Stats cards (Pending, In Progress, Resolved, High Priority)
- [ ] Create ticket form (modal) with priority selection
- [ ] Ticket table with status badges
- [ ] Filter controls (status, priority, zone)
- [ ] Action buttons: Assign, Resolve, Cancel
- [ ] Workflow: Pending → Assign → In Progress → Resolve

#### **Reports Page**
- [ ] Date range picker
- [ ] Tabs: Energy | Performance | Maintenance
- [ ] **Energy:** LineChart (trend), PieChart (distribution), cost table
- [ ] **Performance:** KPI cards (Uptime, MTTR, MTBF, Response Time)
- [ ] **Maintenance:** Placeholder or ticket analytics
- [ ] Export button (PDF/Excel - UI ready, backend optional)

#### **Admin Page**
- [ ] Tabs: Devices | Users
- [ ] Search bar with real-time filter
- [ ] Add button (opens modal form)
- [ ] CRUD table with Edit/Delete actions
- [ ] Modal form for Create/Edit (device or user fields)
- [ ] Delete confirmation dialog

### Icons & UI Components

**Recommended icon mapping:**
```javascript
import { 
  LayoutDashboard, Map, Lightbulb, Wrench, BarChart3, Settings,
  Users, Plus, Edit, Trash2, Search, AlertTriangle, CheckCircle,
  Clock, Zap, UserPlus, FileText, Download, Bell, LogOut
} from 'lucide-react'
```

**Consistent UI patterns:**
- Header with title + icon + description
- Stats cards in grid (4-5 cols on desktop)
- Filters in white card with shadow
- Tables with hover:bg-gray-50
- Toast notifications for all actions
- Loading states for all async operations

---

## Exploring an Existing Dockerized Codebase

When source code lives inside running Docker containers (not on the host filesystem), use this systematic approach to map the entire codebase:

### Step 1: Find the Project Root
```bash
# Find which containers are running
docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Ports}}"

# Get the compose project path from container labels
docker inspect <container-name> --format '{{json .Config.Labels}}' | grep "config_files"
```
If the project directory no longer exists on disk (moved/deleted), all code must be read from inside containers.

### Step 2: Map Directory Structure
```bash
# List all source files in a container
docker exec <container> find /app/src -type f | sort

# For nginx-based frontends (compiled bundles)
docker exec <container> ls -la /usr/share/nginx/html/assets/
```

### Step 3: Read Source Files from Containers
```bash
# Read individual files
docker exec <container> cat /app/src/routes/devices.js

# Batch-read multiple files
for f in auth devices monitoring control; do
  echo "===== routes/$f.js ====="
  docker exec <container> cat "/app/src/routes/$f.js"
done
```

### Step 4: Reconstruct Missing Config
When docker-compose.yml is inaccessible, reconstruct it from container metadata:
```bash
# Exposed ports
docker inspect <c> --format '{{json .Config.ExposedPorts}}'

# Environment variables
docker inspect <c> --format '{{json .Config.Env}}'

# Container aliases (service names)
docker inspect <c> --format '{{json .NetworkSettings.Networks}}' | grep Aliases

# Image names
docker inspect <c> --format '{{.Config.Image}}'

# Dependencies (boot order)
docker inspect <c> --format '{{json .Config.Labels}}' | grep depends_on
```

### Step 5: Extract Frontend Info from Compiled Bundles
For Vite/webpack-built frontends with minified JS:
```bash
# Extract route paths
docker exec <frontend> cat /usr/share/nginx/html/assets/index-*.js | grep -oE 'path:"[^"]*"' | sort -u

# Extract nginx proxy config
docker exec <frontend> cat /etc/nginx/conf.d/default.conf

# Extract page component names from lazy-load chunks
docker exec <frontend> ls /usr/share/nginx/html/assets/ | grep -oE '^[A-Z][a-z]+[A-Za-z]+'
```

### ⚠️ Pitfall: Path with Spaces
Docker compose project paths may contain spaces (e.g., `C:\SMART PJU\smart-pju`). Standard `ls`/`cat` in git-bash will fail. Always fall back to `docker exec` to read from inside containers rather than trying to access the host path.

### ⚠️ Pitfall: `curl --unix-socket` may be blocked
On Windows Docker Desktop, `curl --unix-socket /var/run/docker.sock` is often blocked by security policies. Use `docker inspect` instead — it provides the same metadata.

### ⚠️ Pitfall: Frontend source may not exist
If the frontend was built as a multi-stage Docker image, only the compiled output exists in the running container. You cannot extract the original React/Vue source — only the built JS bundles. Extract what you can (routes, page names, API patterns) from the minified output.

### 🚨 Terminal Output Truncation (JWT Token Pitfall)

**Problem:** `terminal()` truncates long output strings. JWT tokens (200+ chars) get silently cut to ~13 characters, causing every authenticated API call to fail with "Token tidak valid."

**Root cause:** The terminal tool has an output character limit that truncates long strings without warning. JSON responses containing long token strings appear complete but are actually cut short.

**Solution:** Always save tokens to files before using in curl commands:
```bash
# 1. Login and save full response
curl -s -X POST http://localhost:<port>/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' > /tmp/login_response.json

# 2. Extract token to file (avoids terminal truncation)
TOKEN=$(cat /tmp/login_response.json | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
echo -n "$TOKEN" > /tmp/api_token.txt

# 3. Use token from file in all subsequent requests
curl -s -H "Authorization: Bearer $(cat /tmp/api_token.txt)" http://localhost:<port>/api/devices

# Alternative: generate token inside Docker to bypass host terminal limits
docker exec <backend> node -e "
const jwt = require('jsonwebtoken');
const config = require('./src/config');
const token = jwt.sign({id:'<user-uuid>', role:'admin'}, config.jwt.secret, {expiresIn:'1h'});
require('fs').writeFileSync('/tmp/api_token.txt', token);
"
docker exec <backend> cat /tmp/api_token.txt > /tmp/full_token.txt
curl -s -H "Authorization: Bearer $(cat /tmp/full_token.txt)" http://localhost:<port>/api/devices
```

**Verification:** After loading a token, check its length:
```bash
wc -c /tmp/api_token.txt  # Should be ~200+ chars, not 13
```

---

## Full-Stack Audit Workflow

When asked to review/audit an existing running IoT application (not build one), use this systematic 5-phase approach:

### Phase 1: Infrastructure Health (Terminal)
```bash
# Container status + health
docker ps -a --filter name=<project>
docker inspect --format='{{.State.Health.Status}}' <container>

# Logs — check for errors
docker logs --tail=30 <container> 2>&1 | grep -iE "error|warn|fail|exception"

# Resource usage
docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}" \
  $(docker ps -q --filter name=<project>)

# Network connectivity
docker network inspect <network-name>

# Health endpoint
curl -s http://localhost:<port>/api/health
```

### Phase 2: API Endpoint Discovery (Terminal)
Find all routes from source code, then test each one:
```bash
# Find route files
docker exec <backend> find /app -name "*.js" -path "*/routes/*"

# Find mount points
docker exec <backend> grep "app.use" /app/src/index.js

# Auth middleware pattern
docker exec <backend> cat /app/src/middleware/auth.js

# Test each endpoint group with JWT auth (use file-based token!)
TOKEN=$(cat /tmp/full_token.txt)
for ep in devices devices/summary devices/zones maintenance/tickets notifications; do
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TOKEN" \
    "http://localhost:<port>/api/$ep")
  echo "  /api/$ep → $STATUS"
done
```

### Phase 3: Browser UI Exploration
Navigate every sidebar/nav link, check:
- Page loads without errors (`browser_console()`)
- Data renders correctly (not zeros where values expected)
- Interactive elements work (buttons, forms, filters)
- Filters return correct subsets
- Real-time data updates (WebSocket/MQTT indicators)

### Phase 4: Logic & Workflow Verification
For each major feature, trace the expected workflow:
1. **Device Monitoring**: Device → MQTT → Backend → WS → Frontend
2. **Maintenance**: Ticket creation → Status workflow → Resolution
3. **Control**: Command → Backend → MQTT → Device → Status update
4. **Notifications**: Event → Multi-channel dispatch (Push/Email/WA)
5. **Predictive**: Sensor data → Health score → Risk level → Recommendation

### Phase 5: Best Practices Comparison
Compare against domain standards:
- **IoT Architecture**: MQTT hierarchy, gateway pattern, edge computing
- **Smart PJU Specific**: Dimming schedules, sunrise/sunset, weather integration, traffic-based control
- **Energy Monitoring**: kWh tracking, cost calculation, CO₂ reduction
- **Maintenance**: Preventive vs corrective vs predictive workflows
- **Security**: RBAC, JWT handling, audit logging

Generate a structured report with:
- Score (X/100) for overall maturity
- ✅ Features that match best practices
- ⚠️ Features partially implemented
- ❌ Missing features from domain standards

## Session Notes Template

For each new IoT web app project, capture:

1. **Domain specifics**: Device types, protocols, telemetry schema
2. **Custom models**: Additional entities beyond User/Device/Ticket
3. **API extensions**: Domain-specific endpoints
4. **Frontend pages**: Custom dashboards, maps, controls
5. **Deployment quirks**: Provider constraints, network setup

---

## Reference Implementation: SMART PJU (2026-06-20)

**Complete smart street lighting management system:**

### Deliverables
- ✅ **6 Frontend Pages** - All production-ready (109KB total)
  - MapPage.jsx (14.8KB) - Leaflet with 50 device markers
  - ControlPage.jsx (21KB) - Manual/Schedule/Auto tabs
  - MaintenancePage.jsx (18KB) - Full ticket workflow
  - ReportsPage.jsx (15.7KB) - Charts with Recharts
  - AdminPage.jsx (27.9KB) - Device & User CRUD
  - Layout.jsx (4.9KB) - Responsive sidebar navigation
  
- ✅ **Backend API** - 40+ endpoints across 7 modules
  - Auth, Devices, Monitoring, Control, Maintenance, Reports, Users
  - WebSocket real-time updates (15s refresh)
  - Device simulator with telemetry generation

- ✅ **Documentation** - 4 comprehensive guides (41KB total)
  - README.md - Setup & API reference
  - DOKUMENTASI_PERANGKAT.md - 5-chapter user guide
  - IMPLEMENTATION_STATUS.md - PRD compliance matrix
  - PROJECT_COMPLETE.md - Final summary

### PRD Compliance: 100/100
- All 17 functional requirements (2.1-2.7) implemented
- All 13 non-functional requirements (3.1-3.13) met
- Production build: 1.01MB, 5.12s build time
- Total code: ~12,000 LOC
- Implementation time: ~10 hours

### Key Learnings
1. **Systematic page building** - Follow the template pattern above
2. **Component isolation** - Each page is self-contained with own API calls
3. **Consistent state management** - Zustand for auth, local state for page data
4. **Real-time via WebSocket** - 15-second telemetry refresh
5. **Production-ready patterns** - Error handling, loading states, validation

See `references/smart-pju-prd-implementation.md` for full requirements analysis and testing results.
See `references/smart-pju-troubleshooting.md` for critical issues encountered and solutions.
See `references/smart-pju-page-patterns.md` for detailed page-by-page breakdown.
See `references/smart-pju-full-architecture.md` for the complete evolved architecture (16 models, 25 routes, 25+ services, multi-protocol, predictive maintenance, energy optimization).

---