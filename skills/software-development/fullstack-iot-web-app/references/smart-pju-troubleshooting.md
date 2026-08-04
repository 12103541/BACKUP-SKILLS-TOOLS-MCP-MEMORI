# SMART PJU Session - Troubleshooting Notes

**Session Date**: 2026-06-20  
**Project Type**: Smart Street Lighting Management System (IoT)  
**Tech Stack**: Node.js + Express + Sequelize ORM + React + Vite + SQLite/PostgreSQL

## Critical Issues Encountered & Solutions

### 1. Dual Model Layer Conflict

**Symptom:** `syncDatabase is not a function`

**Root Cause:** The `models/index.js` file had both custom JSON store (Collection class) and Sequelize ORM, causing export conflicts.

**Resolution:**
1. Completely overwrite `models/index.js` with pure Sequelize implementation
2. Remove JSON store: `mv src/store src/store.backup`
3. Verify: `node -e "const { syncDatabase } = require('./src/models'); syncDatabase()"`

### 2. Password Hashing Mismatch

**Symptom:** Login fails with "Username atau password salah"

**Root Cause:** Old seed data had plaintext passwords; Sequelize `bulkCreate()` hooks don't run by default.

**Resolution:**
```javascript
const bcrypt = require('bcryptjs');
const hashed = await bcrypt.hash('admin123', 12);
await User.create({ username: 'admin', password: hashed });
```

### 3. Port Conflicts

**Symptom:** `Error: listen EADDRINUSE: address already in use :::5000`

**Resolution on Windows (bash):**
```bash
netstat -ano | grep ":5000.*LISTENING" | awk '{print $NF}' | xargs -I {} cmd.exe //c "taskkill /F /PID {}"
```

### 4. SQLite3 Missing

**Resolution:** `npm install sqlite3 --save`

### 5. Database File Lock

**Resolution:** Kill processes → wait 2s → delete DB → restart → re-seed

## Test Commands

### Backend Health
```bash
curl http://localhost:5000/api/health
```

### Login & Protected Endpoint
```bash
# Get token
TOKEN=$(curl -s -X POST http://localhost:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

# Test endpoint
curl http://localhost:5000/api/devices/summary -H "Authorization: Bearer *** -m json.tool
```

### Frontend Build
```bash
cd frontend && npm run build
```

## Lessons Learned

1. **Sequelize over JSON store** - file storage won't scale for 10k+ devices
2. **Test password hashing immediately** - verify with `comparePassword()` before proceeding
3. **Complete file rewrite** - when model layer conflicts, rewrite completely not patch
4. **Kill processes first** - port conflicts waste more time than prevention
5. **Seed with fresh DB** - if hashing fails, drop DB and re-seed rather than debugging legacy data

**Status**: ✅ Production-ready (backend + frontend working)  
**Last Verified**: 2026-06-20 23:30 WIB