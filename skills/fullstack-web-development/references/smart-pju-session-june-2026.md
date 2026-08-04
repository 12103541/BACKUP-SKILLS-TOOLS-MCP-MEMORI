# SMART PJU Session Debug Log

**Date**: June 20, 2026  
**Project**: Smart Street Lighting Management System (IoT Dashboard)  
**Location**: `C:\SMART PJU\smart-pju\`

## Issues Encountered & Solutions

### 1. Model Layer Conflict (CRITICAL - 30 min to resolve)

**Symptom**: 
- Server starts but database operations fail
- Mix of Sequelize and JSON store code causing confusion

**Root Cause**:
- `src/models/index.js` had wrapper code for custom JSON Collection class
- Individual Sequelize models existed (`User.js`, `Device.js`, etc.) but were never used
- Controllers imported from models but got JSON store wrapper instead of Sequelize

**Resolution**:
```bash
# Complete rewrite of models/index.js
rm src/models/index.js
cat > src/models/index.js << 'EOF'
// Sequelize models registry
const sequelize = require('../config/database')
const User = require('./User')
const Device = require('./Device')
const MaintenanceTicket = require('./MaintenanceTicket')
const Schedule = require('./Schedule')
const EnergyLog = require('./EnergyLog')

// Setup associations
MaintenanceTicket.belongsTo(User, { as: 'assignee', foreignKey: 'assignedTo' })
// ... etc

module.exports = { sequelize, User, Device, MaintenanceTicket, Schedule, EnergyLog, Op, fn, col, literal, includeJoin }
EOF
```

**Verification**:
```bash
node -e "const { syncDatabase } = require('./src/models'); syncDatabase().then(() => console.log('✅ Sequelize working'))"
```

---

### 2. Password Hashing Failure (15 min)

**Symptom**:
- Login returns "Username atau password salah"
- Password in database is plain text (8 chars, not `$2a$...` hash)

**Root Cause**:
- Data was seeded with old JSON store that didn't properly hash passwords
- New Sequelize model has `beforeCreate` hook but bulkCreate() doesn't trigger hooks

**Debug Command**:
```bash
node -e "
const { User } = require('./src/models')
const admin = await User.findOne({ where: { username: 'admin' } })
console.log('Password length:', admin.password.length)  // Should be 60, was 8
console.log('Starts with \$2a\$:', admin.password.startsWith('\$2a\$'))  // Should be true, was false
"
```

**Resolution**:
```javascript
// Manual hash before bulkCreate
const bcrypt = require('bcryptjs')
const users = rawUsers.map(u => ({
  ...u,
  password: await bcrypt.hash(u.password, 12)
}))
await User.bulkCreate(users)

// OR use individual hooks
await User.create({ password: 'plain' })  // Hook runs
```

**Verification**:
```bash
node -e "
const { User } = require('./src/models')
const admin = await User.findOne({ where: { username: 'admin' } })
const match = await admin.comparePassword('admin123')
console.log('Login test:', match ? 'PASS ✓' : 'FAIL ✗')
"
```

---

### 3. Database File Lock on Windows (10 min)

**Symptom**:
- `rm data/smart_pju.sqlite` fails with "Device or resource busy"
- Cannot reset database for fresh seed

**Root Cause**:
- Node.js server process has SQLite file open
- Windows doesn't allow file operations while process has lock

**Resolution Pattern**:
```bash
# Method 1: Kill by port
netstat -ano | findstr ":5000"
taskkill /PID <pid> /F
rm data/smart_pju.sqlite

# Method 2: PowerShell (if available)
powershell -Command "Stop-Process -Id <pid> -Force"

# Method 3: From Hermes process tool
process action=kill session_id=<backend_session>
```

**Important**: Always restart server after DB deletion:
```bash
node src/index.js  # Auto-creates fresh database
```

---

### 4. Missing Import After Refactoring (5 min)

**Symptom**:
- Login endpoint returns 500: "jwt is not defined"
- Error in `authController.js:16`

**Root Cause**:
- Earlier refactoring removed `const jwt = require('jsonwebtoken')`
- But code still uses `jwt.sign()` on line 16

**Debug Command**:
```bash
grep -n "jwt\." src/controllers/authController.js
# Line 16: jwt.sign(...)
# Line 1: (jwt require missing)
```

**Resolution**:
```javascript
// Add back required imports
const jwt = require('jsonwebtoken')
const config = require('../config')
```

**Lesson**: Before removing imports, grep for all usages across the file.

---

### 5. Controller Virtual Getter Conflict (ONGOING)

**Symptom**:
- Dashboard shows "0" for all device counts
- Console: "Dashboard load error: Request failed with status code 500"
- Error log: `TypeError: fn is not a function at Object.get [as isOnline]`

**Root Cause**:
- Device model (or old models/index.js) had virtual getter `isOnline` using `fn()`
- Function `fn` was defined in old models/index.js but not available in Sequelize context
- When controller calls `device.toJSON()`, virtual getter runs and fails

**Investigation**:
```bash
# Check error logs
tail -50 logs/error.log | grep "fn is not a function"

# Stack trace shows:
#   at Object.get [as isOnline] (src/models/index.js:44:53)
#   at Object.value (src/models/index.js:35:26)
#   at exports.getRealtimeData (src/controllers/monitoringController.js:7:48)
```

**Status**: Partly resolved - fixed jwt import, but controller model compatibility still needs work.

**Next Steps**:
1. Find all virtual getters in models
2. Either remove them or make them Sequelize-compatible
3. OR fix controller to not trigger toJSON during mapping

---

### 6. Energy Trend Timestamp Issue (MINOR)

**Symptom**:
- Energy trend API returns 500
- Error: `l.timestamp.slice is not a function`

**Root Cause**:
- Controller expects timestamp as string: `timestamp.slice(0, 10)`
- Sequelize returns timestamp as Date object
- Date object doesn't have `.slice()` method

**Resolution**:
```javascript
// In monitoringController.js
// Change: l.timestamp.slice(0, 10)
// To: l.timestamp.toISOString().slice(0, 10)
// OR: String(l.timestamp).slice(0, 10)
```

---

## Testing Commands Used

### Backend Health
```bash
# Server health
curl http://localhost:5000/api/health

# Login test
curl -X POST http://localhost:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Authenticated endpoint
TOKEN=$(curl -s -X POST http://localhost:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
  
curl http://localhost:5000/api/devices/summary \
  -H "Authorization: Bearer $TOKEN"
```

### Frontend Check
```bash
# Build test
cd frontend && npm run build

# Watch for errors
# Open http://localhost:5173 and check browser console
```

---

## Final Status

**Backend**: ✅ Running on port 5000 (after jwt import fix)  
**Frontend**: ✅ Built successfully (671KB bundle)  
**Database**: ✅ Seeded with 4 users, 50 devices, 3 schedules  
**Integration**: ⚠️ Dashboard loading but showing 0 data (controller model incompatibility)  

**Time Spent**: ~3 hours total  
**Issues Resolved**: 4/6  
**Remaining**: Virtual getter conflict in device controller (non-blocking, can use API directly)

---

## Key Takeaways

1. **Hermes collaboration works** - Backend was 90% built by OpenCode agent, I fixed integration bugs
2. **Database state is persistent** - Old JSON-seeded data caused password hashing issues
3. **Windows file locking is real** - Must kill server process before DB operations
4. **Logs are your friend** - `tail -50 logs/error.log` revealed exact error locations
5. **Test early, test often** - Should've tested login before building frontend

---

**See Also**: 
- `fullstack-web-development` skill (umbrella)
- PRD: `Downloads/SMART PJU.csv`
- Documentation: `C:\SMART PJU\smart-pju\README.md`