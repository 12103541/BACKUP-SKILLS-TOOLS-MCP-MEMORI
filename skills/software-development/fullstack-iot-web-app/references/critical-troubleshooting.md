# Critical Troubleshooting Issues

## Session Date: June 20-21, 2026
## Project: SMART PJU IoT Management System

---

## Issue #1: Model Layer Conflict (CRITICAL)

**Severity:** 🔴 Blocking - prevented backend from starting

### Symptoms
- `syncDatabase is not a function` errors
- `fn is not a function` in controllers
- Models not found or undefined
- `Database ready (JSON file storage)` appearing despite Sequelize setup
- Login returns "Terjadi kesalahan internal server"

### Root Cause
Legacy custom JSON store in `src/store/` was conflicting with Sequelize ORM implementation. Multiple issues:

1. Old `models/index.js` exported JSON Collection wrapper instead of Sequelize models
2. Virtual getters calling `fn()` that wasn't exported from models/index.js
3. Module caching kept loading old code despite file updates
4. Mixed architecture message: "JSON file storage" when should be Sequelize

### Solution Steps

#### Step 1: Remove Custom Store
```bash
cd backend/src
rm -rf store/  # Legacy JSON store folder
rm models/index.js  # Will rewrite fresh
```

#### Step 2: Rewrite models/index.js with Sequelize Only
```javascript
const sequelize = require('../config/database');
const User = require('./User');
const Device = require('./Device');
const MaintenanceTicket = require('./MaintenanceTicket');
const Schedule = require('./Schedule');
const EnergyLog = require('./EnergyLog');

// IMPORTANT: Export Sequelize helpers for controllers
const { fn, col, literal, Op } = require('sequelize');

// Associations
MaintenanceTicket.belongsTo(User, { as: 'assignee', foreignKey: 'assignedTo' });
MaintenanceTicket.belongsTo(Device, { foreignKey: 'deviceId' });
Device.hasMany(MaintenanceTicket);

const syncDatabase = async (options = {}) => {
  await User.sync(options);
  await Device.sync(options);
  await MaintenanceTicket.sync(options);
  await Schedule.sync(options);
  await EnergyLog.sync(options);
  console.log('✅ Database synced');
};

module.exports = { 
  sequelize, 
  User, 
  Device, 
  MaintenanceTicket, 
  Schedule, 
  EnergyLog,
  syncDatabase,
  fn,
  col,
  literal,
  Op
};
```

#### Step 3: Remove Problematic Virtual Getters
Check all model files for virtual getters calling `fn()`:
```javascript
// BAD - causes "fn is not a function"
get isOnline() {
  return fn('UNIX_TIMESTAMP', this.col('lastSeen')) > fn('UNIX_TIMESTAMP') - 900;
}

// GOOD - use computed property in controller
// or use Sequelize literal in queries
```

#### Step 4: Clear Module Cache & Restart
```bash
# Kill all node processes
taskkill /F /IM node.exe

# Delete SQLite file (if starting fresh)
rm data/smart_pju.sqlite

# Restart server
node src/index.js
```

#### Step 5: Verify Sequelize Working
```bash
node -e "const { syncDatabase } = require('./src/models'); syncDatabase().then(() => console.log('✅ SUCCESS'))"
```

### Verification Commands

Before declaring backend working:
```bash
# Check syntax
node -c src/controllers/authController.js && echo "✓ Syntax OK"

# Check models export
node -e "const m = require('./src/models'); console.log('Exports:', Object.keys(m).join(', '))"

# Test database sync
node -e "const { syncDatabase } = require('./src/models'); syncDatabase().then(() => console.log('✅ DB Ready'))"

# Test server start
timeout 5 node src/index.js || echo "Server test completed"
```

---

## Issue #2: JWT Import Missing (CRITICAL)

**Severity:** 🔴 Blocking - login completely broken

### Symptoms
- Login endpoint returns "Terjadi kesalahan internal server"
- 500 error on POST /api/auth/login
- Console shows "jwt is not defined"

### Root Cause
`src/controllers/authController.js` was missing `const jwt = require('jsonwebtoken');` import at top of file.

### Solution
Add complete imports at top of authController.js:
```javascript
const jwt = require('jsonwebtoken');
const config = require('../config');
const { User, Device, MaintenanceTicket, Schedule, EnergyLog, Op, sequelize } = require('../models');
const logger = require('../utils/logger');
```

### Verification
```bash
# Before saying "login working", test imports:
node -e "const jwt = require('jsonwebtoken'); console.log('✓ JWT available')"
node -c src/controllers/authController.js && echo "✓ Controller syntax OK"
```

---

## Issue #3: Password Hashing Failures (HIGH)

**Severity:** 🟠 High - login fails for seeded users

### Symptoms
- Login returns "Username atau password salah"
- User exists in database but authentication fails
- Fresh seed creates users but login impossible

### Root Causes Found

#### Cause 1: Password Not Hashed in Seed Script
```javascript
// WRONG - stores plain text password
await User.bulkCreate([
  { username: 'admin', password: 'admin123', role: 'admin' }
])

// RIGHT - hash manually (bulkCreate skips hooks)
const bcrypt = require('bcryptjs');
await User.bulkCreate([
  { username: 'admin', password: await bcrypt.hash('admin123', 12), role: 'admin' }
])
```

#### Cause 2: Hooks Not Running in bulkCreate
Sequelize `beforeCreate` hooks don't run by default with bulkCreate:
```javascript
// Option 1: Enable individualHooks
await User.bulkCreate(users, { individualHooks: true })

// Option 2: Hash manually (faster)
const hashed = await bcrypt.hash('password', 12)
```

#### Cause 3: Double Hashing
```javascript
// WRONG - hooks hash + manual hash = double hashed
const hashed = await bcrypt.hash('password', 12)
await User.create({ username: 'admin', password: hashed }) // hooks hash again!

// RIGHT - use create() with plain password (hooks will hash)
await User.create({ username: 'admin', password: 'admin123' })
```

### Solution Pattern

**Correct User Model Hook:**
```javascript
const bcrypt = require('bcryptjs');

User.init({
  username: { type: DataTypes.STRING, unique: true, allowNull: false },
  password: { type: DataTypes.STRING, allowNull: false },
  // ... other fields
}, {
  sequelize,
  modelName: 'User',
  hooks: {
    beforeCreate: async (user) => {
      user.password = await bcrypt.hash(user.password, 12);
    },
    beforeUpdate: async (user) => {
      if (user.changed('password')) {
        user.password = await bcrypt.hash(user.password, 12);
      }
    }
  }
});
```

**Correct Seed Script:**
```javascript
const bcrypt = require('bcryptjs');

// Option A: bulkCreate with manual hashing (fast)
const users = [
  { username: 'admin', password: await bcrypt.hash('admin123', 12), role: 'admin' },
  { username: 'pengelola1', password: await bcrypt.hash('pengelola123', 12), role: 'pengelola' },
  { username: 'teknisi1', password: await bcrypt.hash('teknisi123', 12), role: 'teknisi' },
];
await User.bulkCreate(users);

// Option B: create() loop with hooks (slower but automatic)
for (const userData of usersPlain) {
  await User.create(userData); // beforeCreate hook will hash
}
```

**Correct Login Controller:**
```javascript
const login = async (req, res) => {
  const { username, password } = req.body;
  const user = await User.findOne({ where: { username } });
  
  if (!user) {
    return res.status(401).json({ message: 'Username atau password salah' });
  }
  
  const valid = await bcrypt.compare(password, user.password);
  if (!valid) {
    return res.status(401).json({ message: 'Username atau password salah' });
  }
  
  // Generate JWT token...
};
```

### Verification
```bash
# Check if password is hashed in database (should start with $2a$ or $2b$)
sqlite3 data/smart_pju.sqlite "SELECT username, password FROM users LIMIT 1;"

# Expected output: admin|$2b$12$xyz... (long string starting with $2)
# NOT: admin|admin123 (plain text = BUG)
```

---

## Issue #4: Port Already in Use (EADDRINUSE) (MEDIUM)

**Severity:** 🟡 Medium - server won't start

### Symptoms
```
Error: listen EADDRINUSE: address already in use :::5000
```

### Solution on Windows (from bash)

**Find process:**
```bash
netstat -ano | findstr ":5000"
```

**Kill by PID (PowerShell):**
```bash
powershell -Command "Stop-Process -Id <PID> -Force"
```

**Alternative: taskkill wrapper:**
```bash
netstat -ano | grep ":5000.*LISTENING" | awk '{print $NF}' | xargs -I {} cmd.exe //c "taskkill /F /PID {}"
```

### Prevention
Add to startup script:
```bash
#!/bin/bash
# Kill existing node processes
taskkill /F /IM node.exe 2>/dev/null || true
sleep 2

# Start server
node src/index.js
```

---

## Issue #5: SQLite3 Package Missing (LOW)

**Severity:** 🟢 Low - easy fix

### Symptoms
```
Error: Please install sqlite3 package manually
```

### Solution
```bash
npm install sqlite3 --save
```

---

## Issue #6: Frontend API Integration (MEDIUM)

**Severity:** 🟡 Medium - dashboard shows 0 devices

### Symptoms
- Backend API returns data correctly when tested with curl
- Frontend dashboard shows "0 Devices" or empty state
- Console shows 401 Unauthorized or 500 errors

### Root Causes Found

#### Cause 1: Token Not Sent in Headers
Frontend makes API call but token not included:
```javascript
// WRONG - missing headers
axios.get('/api/devices/summary')

// RIGHT - axios interceptor or manual headers
axios.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
```

#### Cause 2: Wrong Data Structure Access
```javascript
// Backend returns: { data: { devices: [...] } }
const res = await deviceApi.getAll();

// WRONG - trying to access directly
setDevices(res.data)

// RIGHT - access nested data
setDevices(res.data.devices || [])
```

#### Cause 3: Dashboard Load Error (500)
Caused by device controller trying to use virtual getter `isOnline` that calls `fn()` not exported from models:
- See Issue #1 for full solution
- Virtual getter removed or converted to controller logic

### Verification Flow
```javascript
// Test API from frontend with logged-in state
// 1. Login via UI
// 2. Open browser DevTools → Console
// 3. Check localStorage
localStorage.getItem('token') // Should exist

// 4. Test API call
fetch('/api/devices/summary', {
  headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
}).then(r => r.json()).then(d => console.log('Devices:', d))
// Should return { devices: [...] }
```

---

## Issue #7: Build Failures (MEDIUM)

**Severity:** 🟡 Medium - cannot create production build

### Symptoms
```
[vite]: Rollup failed to resolve import "recharts"
```

### Root Cause
Missing dependency in package.json

### Solution
```bash
cd frontend
npm install recharts  # or other missing package
npm run build
```

### Common Missing Packages for IoT Apps
```bash
# Charts
npm install recharts

# Maps
npm install leaflet react-leaflet

# Icons
npm install lucide-react

# State
npm install zustand

# Notifications
npm install react-hot-toast

# Routing
npm install react-router-dom

# HTTP client
npm install axios
```

---

## Quick Diagnostic Checklist

When backend has issues, run this sequence:

```bash
# 1. Check imports
echo "=== CHECKING IMPORTS ==="
node -c src/controllers/authController.js && echo "✓ Auth controller syntax OK"
node -c src/models/index.js && echo "✓ Models index syntax OK"

# 2. Check models export
echo "=== CHECKING MODELS EXPORT ==="
node -e "const m = require('./src/models'); console.log('Models exports:', Object.keys(m).join(', '))"

# 3. Check Sequelize connection
echo "=== CHECKING DATABASE ==="
node -e "const { syncDatabase } = require('./src/models'); syncDatabase().then(() => console.log('✅ DB sync OK')).catch(e => console.error('❌ DB Error:', e.message))"

# 4. Test server start
echo "=== TESTING SERVER START ==="
timeout 5 node src/index.js 2>&1 || echo "Server test completed (timeout expected)"

# 5. Test health endpoint
echo "=== TESTING HEALTH ENDPOINT ==="
curl -s http://localhost:5000/api/health | python3 -c "import sys,json; d=json.load(sys.stdin); print('Health:', d.get('status', 'UNKNOWN'))"

# 6. Test login
echo "=== TESTING LOGIN ==="
curl -s -X POST http://localhost:5000/api/auth/login -H "Content-Type: application/json" -d '{"username":"admin","password":"admin123"}' | head -c 100
```

---

## Session Retries That Worked

### Port Conflict Resolution
**Attempt 1:** `netstat -ano | findstr ":5000"` → Found PID  
**Attempt 2:** `powershell -Command "Stop-Process -Id <PID> -Force"` → Success  
**Retry count:** 2  
**Time saved:** ~5 minutes vs manual Process Explorer search

### Model Layer Conflict
**Attempt 1:** Modified models/index.js to export Sequelize helpers → Still had issues  
**Attempt 2:** Discovered legacy `src/store/` folder still present → Removed completely → Success  
**Retry count:** 3  
**Time invested:** ~45 minutes debugging and testing  

### Password Hashing Issue
**Attempt 1:** Fixed User model hooks → Still failing  
**Attempt 2:** Discovered seed script uses bulkCreate without individualHooks → Fixed seed script → Success  
**Retry count:** 2  
**Root cause:** Silent hook skipping in bulk operations

---

## Prevention Checklist

For future IoT web app projects, verify these BEFORE starting development:

### Backend Setup
- [ ] Sequelize ORM installed, not custom JSON store
- [ ] `models/index.js` exports Sequelize helpers (fn, col, literal, Op)
- [ ] No virtual getters in models calling Sequelize functions
- [ ] Auth controller has jwt import
- [ ] User model has beforeCreate hook for password hashing
- [ ] Seed script either uses individualHooks or hashes manually

### Frontend Setup
- [ ] Axios interceptors configured for JWT
- [ ] All dependencies installed (react-router-dom, leaflet, recharts, etc.)
- [ ] localStorage persistence strategy defined
- [ ] Error handling pattern consistent (try-catch-finally + toast)

### Development Workflow
- [ ] Kill node processes before starting server
- [ ] Clear module cache when debugging import issues
- [ ] Test with curl/postman before saying "API working"
- [ ] Verify authentication flow end-to-end

---

**Last updated:** June 21, 2026  
**Session:** SMART PJU IoT Management System  
**Severity scale:** 🔴 Blocking | 🟠 High | 🟡 Medium | 🟢 Low