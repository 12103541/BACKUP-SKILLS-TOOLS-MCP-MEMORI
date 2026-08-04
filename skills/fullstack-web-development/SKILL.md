---
name: fullstack-web-development
category: software-development
description: Build production-ready web applications with modern stacks (React/Node.js), covering architecture, authentication, API design, database migrations, and deployment patterns.
triggers:
  - Building web apps from PRD/requirements
  - Full-stack development (backend + frontend)
  - API design with authentication
  - Database migrations (custom → ORM)
  - Real-time features (WebSocket, MQTT, IoT)
  - Production deployment setup
---

# Full-Stack Web Development

Systematic approach to building production-ready web applications with modern JavaScript/TypeScript stacks.

## User Preferences & Communication Style

**Language**: Explanations in Bahasa Indonesia, code/examples in English  
**Communication**: Action-oriented - provide 3-line status update first (working/pending/blocked), then ask to continue or pivot  
**Decision Making**: Present explicit options with recommendations before big execution  
**Problem Solving**: Prefer direct fixes over prolonged debugging; pivot to simpler stack if blocking persists  

## Typical Architecture

```
Frontend: React 18 + Vite + Tailwind CSS + Zustand
Backend: Node.js + Express + Sequelize ORM
Database: PostgreSQL (prod) / SQLite (dev)
Real-time: WebSocket (Socket.io) + MQTT (IoT devices)
Auth: JWT with role-based access control
```

## Workflow

### Phase 1: Foundation Setup
1. **Requirement Analysis** - Parse PRD into functional modules
2. **Tech Stack Selection** - Match tools to requirements
3. **Project Scaffolding** - Create backend/frontend directories with proper structure
4. **Environment Config** - Setup `.env`, database config, development vs production modes

### Phase 2: Backend Development
1. **Database Models** - Define Sequelize models with proper associations
2. **Authentication** - JWT setup with bcrypt password hashing
3. **API Routes** - RESTful endpoints with validation (express-validator)
4. **Middleware** - Auth verification, role-based authorization, error handling
5. **Services** - Business logic, external integrations (MQTT, WebSocket)
6. **Testing** - Verify each endpoint with curl/Postman

### Phase 3: Frontend Development
1. **Component Architecture** - Pages, components, layouts
2. **State Management** - Zustand stores for auth, data, UI state
3. **API Integration** - Axios instances with interceptors for auth tokens
4. **Real-time Features** - WebSocket client for live updates
5. **Dark Mode Implementation** - Tailwind CSS dark mode strategy with useDarkMode hook
6. **Build & Verify** - Production build, check bundle size, fix warnings

### Phase 4: Integration & Deployment
1. **End-to-End Testing** - Login → Dashboard → Features workflow
2. **Bug Fixing** - Check console logs, backend error logs, fix controller issues
3. **Documentation** - README with setup, API endpoints, troubleshooting
4. **Deployment Prep** - Docker-compose, environment configs, production checklist

## Today's Session Learnings (June 21, 2026)

### Dark Mode Implementation
Implement dark mode using Tailwind CSS class strategy with useDarkMode hook. See `references/dark-mode-implementation.md` for detailed steps.

### Dashboard Blank Page Resolution Pattern
When dashboard renders completely blank (no header/content) with NO console errors:

1. **Missing Child Components**: Verify all imported components exist
   ```bash
   ls -la src/components/DashboardStats.jsx
   ls -la src/components/EnergyChart.jsx
   ls -la src/components/RecentAlerts.jsx
   ```

2. **Auth Store Export Issues**: Vite cache can cause false "not exported" errors
   - **Solution**: Completely kill Node processes and restart dev server
   - Command: `taskkill /F /IM node.exe` (Windows) or `pkill -f node` (Mac/Linux)
   - Then: `npm run dev`

3. **Routing Structure Errors**: Layout not receiving children
   - **Wrong**: Separate routes for `/` (Layout) and `/dashboard` (Page)
   - **Correct**: Nested routes where Layout wraps page components
   ```javascript
   <Route path="/" element={<Layout />}>
     <Route index element={<Navigate to="/dashboard" replace />} />
     <Route path="dashboard" element={<DashboardPage />} />
   </Route>
   ```

4. **Component File Creation**: When debugging reveals missing files
   - Create missing components with basic structure first
   - Add props and functionality incrementally
   - Test after each addition

### Communication & Workflow Preferences
- **Status First**: Always provide 3-line update before continuing:
  ```
  WORKING: [what's functional]
  PENDING: [what needs work]
  BLOCKED: [what's stopped progress] (if any)
  ```
- **Options Before Action**: Present 2-3 clear paths forward with recommendation
- **Direct Fixes**: When debugging >15min without progress, consider alternative approach
- **Language**: Explanations in Bahasa Indonesia, code/examples in English

### API Backend Integration and Auth Store Restoration
When transitioning from mock data to real backend API and restoring proper auth store:

1. **Verify Backend Health**: Ensure all required services are running
   ```bash
   # Check backend health endpoint
   curl -s http://localhost:5000/api/health
   # Should return {"status":"ok",...}
   ```

2. **Test Authentication Endpoint**: Confirm login returns token and user data
   ```bash
   TOKEN=*** -s -X POST http://localhost:5000/api/auth/login -H "Content-Type: application/json" -d '{"username":"admin","password":"admin123"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])"
   ```

3. **Validate Protected Endpoints**: Test with token to ensure data returns
   ```bash
   # Example: device summary
   curl -s -X GET http://localhost:5000/api/devices/summary -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
   # Example: monitoring energy trend
   curl -s -X GET http://localhost:5000/api/monitoring/energy-trend -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
   ```

4. **Restore Auth Store to Use Zustand**: Replace localStorage-only login with proper store
   - In `src/pages/LoginPage.jsx`: revert to using `useAuthStore` login function
   - Ensure `useAuthStore` includes `rehydrate()` call in `App.jsx` or `main.jsx`
   - Example store rehydrate pattern:
     ```javascript
     // In App.jsx or main.jsx
     useEffect(() => {
       useAuthStore.getState().rehydrate()
     }, [])
     ```
   - Ensure token is saved to localStorage in login action for persistence across refreshes

5. **Replace Mock Data with API Calls**: Update frontend components to use real API
   - In `src/pages/DashboardPage.jsx`: remove mock `useState` defaults, re-enable `useEffect` with API calls
   - Add proper loading states and error handling
   - Example:
     ```javascript
     useEffect(() => {
       const load = async () => {
         try {
           const [sumRes, trendRes, ticketRes] = await Promise.all([
             deviceApi.getSummary(),
             monitoringApi.getEnergyTrend({ days: 7 }),
             maintenanceApi.getTickets({ limit: 5 })
           ])
           setSummary(sumRes.data)
           setTrend(trendRes.data)
           setTickets(ticketRes.data)
         } catch (err) {
           console.error('Error fetching dashboard data:', err)
           // Optionally keep mock fallbacks for development
         }
       }
       load()
     }, [])
     ```

6. **Test Full Integration Flow**: Verify end-to-end works
   - Login → Dashboard shows real data (not mock)
   - Navigate to other pages (Map, Control, Maintenance, Reports, Admin) and verify data loads
   - Check WebSocket connection for real-time updates (if implemented)
   - Ensure no console errors and network tab shows successful API calls

7. **Troubleshooting API 500 Errors**: If endpoints return 500
   - Check backend logs: `tail -f logs/error.log`
   - Common causes:
     * Missing environment variables (check `.env`)
     * Database connection issues (ensure PostgreSQL/SQLite accessible)
     * Sequelize model association errors
     * Controller logic errors (e.g., trying to call `.toJSON()` on null)
   - Fix: Address the specific error in logs, restart backend, retest


## Critical Pitfalls

### Component Import Structure

**BLANK PAGES WITHOUT ERRORS** - Most common issue when dashboard pages don't render:

1. **Missing Child Components**: Parent imports 3 children but only 2 exist → silent failure
2. **Wrong Import Paths**: `../components/Dashboard/Stats` vs `../components/Stats` 
3. **Nested Directories**: Don't create `components/Dashboard/` unless absolutely necessary
4. **Layout Without Children**: Routes set up as `<Route path="/" element={<Layout />}><Route path="/dashboard" element={<DashboardPage />} /></Route>` renders Layout **without** DashboardPage inside it. DashboardPage exists but is not a child of Layout, so the `<main>{children}</main>` stays empty.
5. **Zustand Store Not Rehydrating**: Auth token not loaded from localStorage on app mount, causing isAuthenticated=false even after login
6. **Vite Import Cache Issues**: `"useAuthStore" is not exported by "src/store/authStore.js"` error appears EVEN WHEN the export IS correct. This is a Vite dev server cache issue.

**Solution**: Use **flat component structure**, verify all imports exist before testing, and **restart dev server completely** when seeing import/export errors that don't match the actual code.

**Debug Flow**: White/blank screen, no console errors:
```bash
1. **Check build errors** (not runtime): Run `npm run build` to see actual compilation errors
2. **Check route structure**: Does Layout wrap the page component? Look for:
   - WRONG: Route "/" has Layout, Route "/dashboard" has DashboardPage (separate routes)
   - RIGHT: Route "/" has Layout with nested Route "/dashboard" as children
3. **Check Network tab**: API calls returning data or 401/500?
4. **Check Console**: Add debug logs in useEffect to see if component mounted
5. **Check localStorage**: Token exists after login? (`localStorage.getItem('token')`)
6. **Vite cache issue**: Kill dev server COMPLETELY and restart:
   - Kill all Node processes: `taskkill /F /IM node.exe` (Windows) or `pkill -f node` (Mac/Linux)
   - Verify port free: `netstat -ano | findstr ":5173"` should show nothing
   - Restart: `npm run dev`
   - Wait for "VITE v5.x.x ready in XXX ms" message
7. **Import circular dependencies**: Check if authStore imports from api.js which imports authStore
```

**Vite-Specific Pitfall**: When seeing `"X" is not exported by "Y"` but the export IS in the file:
- This is **NOT a code issue** - it's Vite's module cache being stale
- **DO NOT** keep editing the file - it won't help
- **DO** completely kill Node processes and restart dev server
- The error will disappear after clean restart

**Case Study (June 2026)**: Dashboard showed blank page for 30+ minutes. Browser tools showed no errors. Build revealed `useAuthStore` import error. File had correct export. Solution: `taskkill /F /IM node.exe` + restart → worked immediately.

### Zustand Store Pattern (Auth Example)

**Common Issue**: User logs in successfully, token saved to localStorage, but on page refresh the store state is not rehydrated → user appears logged out and API calls fail.

**Wrong Pattern**:
```javascript
// Store does NOT rehydrate on mount
const useAuthStore = create((set) => ({
  user: null,
  token: null,
  isAuthenticated: false,
  login: (user, token) => {
    localStorage.setItem('token', token)
    set({ user, token, isAuthenticated: true })
  }
}))
```

**Right Pattern** (with rehydrate function):
```javascript
const useAuthStore = create((set) => ({
  user: null,
  token: null,
  isAuthenticated: false,
  login: (user, token) => {
    console.log('🔐 Store LOGIN:', user.username)
    if (typeof window !== 'undefined') {
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(user))
    }
    set({ user, token, isAuthenticated: true })
  },
  logout: () => {
    if (typeof window !== 'undefined') {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
    }
    set({ user: null, token: null, isAuthenticated: false })
  },
  // MUST call this in App.jsx or main.jsx on mount
  rehydrate: () => {
    if (typeof window === 'undefined') return
    const token = localStorage.getItem('token')
    const userStr = localStorage.getItem('user')
    if (token && userStr) {
      try {
        set({ token, user: JSON.parse(userStr), isAuthenticated: true })
        console.log('✅ Store rehydrated from localStorage')
      } catch (e) {
        console.error('Failed to parse stored user:', e)
      }
    }
  }
}))

// Usage in App.jsx:
useEffect(() => {
  useAuthStore.getState().rehydrate()
}, [])
```

**Alternative**: Use Zustand's `persist` middleware for automatic storage/rehydration.

**Debug Checklist** when login works but store is empty on refresh:
1. Check localStorage manually (F12 → Application → Local Storage)
2. Add console.log in login function to verify token is set
3. Verify rehydrate() is called somewhere (App/main/store export)
4. Check for undefined errors in login response (user.fullName vs user.dataValues.fullName)
5. Test: after login, do `localStorage.getItem('token')` in console - should exist

📚 **See**: `references/dashboard-component-patterns.md` - Complete guide with examples

### Sequelize Migration Issues
- **Problem**: Mixing custom JSON store with Sequelize ORM causes conflicts
- **Fix**: Choose ONE data layer, completely remove the other
- **Pattern**:
  ```javascript
  // Remove old store files
  rm -rf src/store/
  // Rewrite models/index.js to use Sequelize
  // Update all controllers to use Sequelize methods
  ```

### Password Hashing with bulkCreate
- **Problem**: `beforeCreate` hooks don't run with `bulkCreate()`
- **Fix**: Manually hash passwords before bulk insert
  ```javascript
  // WRONG - hooks won't run
  await User.bulkCreate([{ password: 'plain' }])
  
  // RIGHT - hash manually
  const users = rawUsers.map(u => ({
    ...u,
    password: await bcrypt.hash(u.password, 12)
  }))
  await User.bulkCreate(users)
  ```

### Database File Locking (Windows)
- **Problem**: SQLite file locked by running server process
- **Fix**: Kill server process BEFORE deleting/moving DB file
  ```bash
  # Find process using port
  netstat -ano | findstr ":5000"
  # Kill by PID
  taskkill /PID <pid> /F
  # Now safe to delete DB
  rm data/smart_pju.sqlite
  ```

### Controller Model Incompatibility
- **Problem**: Controllers written for custom store don't work with Sequelize
- **Symptoms**: `fn is not a function`, `toJSON is not a function`, virtual getter errors
- **Fix**: 
  1. Check error logs: `tail -50 logs/error.log`
  2. Identify incompatible method calls
  3. Replace with Sequelize-native patterns
  4. Remove virtual getters that conflict with Sequelize
  5. Restart server and verify

### Missing Imports After Refactoring
- **Problem**: Remove imports during refactoring but code still uses them
- **Symptom**: `jwt is not defined`, `config is not defined`
- **Fix**: Check all references before removing imports, or add back:
  ```javascript
  const jwt = require('jsonwebtoken')
  const config = require('../config')
  ```

### Tailwind CSS Content Missing CSS Files
**Problem**: Dark mode not applying despite correct use of `dark:` variants and `useDarkMode` hook. The HTML element gets `dark` class but styles do not change.

**Cause**: Tailwind's `content` array in `tailwind.config.js` does not include CSS files, so Tailwind does not scan for dark mode classes defined in your CSS (e.g., in `index.css`).

**Fix**: Ensure the `content` array includes CSS files:
```javascript
content: [
  "./index.html",
  "./src/**/*.{js,ts,jsx,tsx,css}", // Note the addition of ,css
],
```

**Verification**: After updating, restart the dev server and run `npm run build` to confirm dark mode classes are generated in the output CSS.


## API Design Patterns

### Standard Response Shape
```javascript
// Success
{
  "message": "Login berhasil",
  "data": { ... },
  "total": 50,
  "page": 1,
  "totalPages": 2
}

// Error
{
  "message": "Akses ditolak. Token tidak ditemukan.",
  "errors": [
    { "field": "username", "message": "Username wajib diisi" }
  ]
}
```

### Auth Middleware Pattern
```javascript
// middleware/auth.js
const authenticate = async (req, res, next) => {
  const authHeader = req.headers.authorization
  if (!authHeader?.startsWith('Bearer ')) {
    return res.status(401).json({ message: 'Token tidak ditemukan.' })
  }
  const token = authHeader.split(' ')[1]
  const decoded = jwt.verify(token, config.jwt.secret)
  req.user = await User.findByPk(decoded.id)
  next()
}

const authorize = (...roles) => (req, res, next) => {
  if (!roles.includes(req.user.role)) {
    return res.status(403).json({ message: 'Akses ditolak.' })
  }
  next()
}
```

## Testing Strategy

### Backend Health Check
```bash
# Verify server running
curl http://localhost:5000/api/health

# Test login
curl -X POST http://localhost:5000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Test authenticated endpoint
curl http://localhost:5000/api/devices/summary \
  -H "Authorization: Bearer <token>"
```

### Frontend Verification
1. Open `http://localhost:5173`
2. Login with test credentials
3. Check browser console for errors
4. Verify dashboard data loads (not showing 0 for all stats)
5. Test WebSocket connection in console

## Deployment Considerations

### Database Choice
- **Development**: SQLite (file-based, no setup)
- **Production**: PostgreSQL (scalable, concurrent access)
- **Migration**: Easy via Sequelize config switch

### Environment Variables Required
```env
NODE_ENV=development|production
PORT=5000
DB_DIALECT=sqlite|postgres
DB_HOST=localhost
DB_PORT=5432
DB_NAME=smart_pju
DB_USER=postgres
DB_PASSWORD=***
JWT_SECRET=your-secret-key
MQTT_HOST=localhost
MQTT_PORT=1883
```

### Docker Deployment
```yaml
# docker-compose.yml
services:
  postgres:
    image: postgres:15-alpine
  mqtt:
    image: eclipse-mosquitto:2
  backend:
    build: ./backend
    depends_on: [postgres, mqtt]
  frontend:
    build: ./frontend
    depends_on: [backend]
```

## Tools & Commands

### Quick Start Commands
```bash
# Install all
npm install

# Development
npm run dev        # backend
npm run dev        # frontend (separate terminal)

# Production build
npm run build      # frontend

# Seed database
npm run seed       # backend

# Check logs
tail -f logs/error.log
```

### Database Reset Pattern
```bash
# Stop server
# Find and kill process on port 5000
netstat -ano | findstr ":5000"
taskkill /PID <pid> /F

# Delete DB
rm data/smart_pju.sqlite

# Restart server (auto-creates fresh DB)
node src/index.js

# Seed initial data
npm run seed
```

## Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| 401 Token not found | Frontend not storing token | Check localStorage set in login action |
| 500 fn is not a function | Virtual getter conflict | Remove custom getters, use Sequelize native |
| EADDRINUSE | Port already in use | Kill process on that port |
| SequelizeUniqueConstraintError | Duplicate seed data | Clear tables before seeding or use findOrCreate |
| WebSocket not connecting | Server not started or CORS | Check backend logs, verify CORS config |

## Success Criteria

- [ ] Backend running on configured port (check `/api/health`)
- [ ] Login returns valid JWT token
- [ ] Authenticated endpoints return data (not 401/500)
- [ ] Frontend builds without errors
- [ ] Frontend console shows no errors on dashboard load
- [ ] WebSocket connection established
- [ ] Database seeded with test data
- [ ] Documentation up to date (README with credentials)

## Related Skills

- `test-driven-development` - For writing tests as you build
- `systematic-debugging` - When troubleshooting integration issues
- `github-pr-workflow` - For managing code reviews and version control
- `docker-compose` - For production deployment patterns

## Support Files

- **`references/smart-pju-session-june-2026.md`** - Detailed debug log from IoT dashboard build (June 2026), includes 6 specific issues encountered and resolutions
- **`references/rest-area-monitoring-patterns.md`** - REST Area Monitoring System (Python FastAPI + SQLite + Vanilla JS) patterns: SQLite CHECK removal, dynamic enums, VMS player scaling, public display cards, single-page admin CRUD, cache busting, icon handling
- **`templates/api-testing-cheatsheet.md`** - Quick reference for testing all API endpoints with curl, including authentication, devices, monitoring, control, maintenance, and reports
- **`scripts/fullstack-troubleshoot.sh`** - Automated diagnostic script that checks backend/frontend status, database health, dependencies, and suggests fixes

## Today's Session Learnings (August 2, 2026) - REST Area Monitoring System (Python FastAPI + SQLite + Vanilla JS)

### Stack Context
This project uses a **Python FastAPI + SQLite + Vanilla JS** stack (not React/Node):
- Backend: `app/main.py` (FastAPI), `app/database.py`, `app/logic.py`, `app/simulator.py`
- Frontend: Jinja2 templates (`templates/*.html`) + vanilla ES6 modules (`static/*.js`)
- Database: SQLite with custom migration logic in `database.py`
- Auth: Token-based (X-Token header), role-based (admin/petugas)

### Player Template Configuration (Multi-Area Per-Component Theming)
**Problem**: Each VMS display (per rest area) needs independent theming - header colors, pill colors, footer visibility, upscale behavior, font family.

**Solution**: Separate `player_template` table with JSON config per location + dynamic CSS variable application.

```python
# database.py - new table
CREATE TABLE IF NOT EXISTS player_template (
    id_lokasi     INTEGER PRIMARY KEY,
    config_json   TEXT NOT NULL DEFAULT '{}',
    diperbarui_pada TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (id_lokasi) REFERENCES lokasi(id)
);
```

```python
# logic.py - helpers
def get_player_template(lokasi_id: int | None = None) -> dict:
    """Ambil config template player VMS untuk lokasi, default sensible defaults."""
    lokasi_id = lokasi_id or state.get_lokasi_aktif()
    row = db.query_one("SELECT config_json FROM player_template WHERE id_lokasi = ?", (lokasi_id,))
    default = {
        "warna_header": "#000000",
        "warna_judul": "#ffd600",
        "warna_pill_bg": "#ffffff",
        "warna_pill_teks": "#0066ff",
        "show_footer": True,
        "upscale": False,
        "font_family": "Arial, sans-serif",
        "header_height_px": 140,
        "grid_gap_px": 26,
        "grid_padding_px": [56, 34, 30],
        "kartu_gap_px": 8,
        "label_padding_px": [8, 20],
        "area_data_gap_px": 14,
        "ikon_size_px": 72,
        "angka_font_px": 110,
        "caption_font_px": 17,
        "sub_slot_font_px": 13,
        "footer_font_px": 13,
    }
    if row and row["config_json"]:
        try:
            user_cfg = json.loads(row["config_json"])
            default.update(user_cfg)
        except Exception:
            pass
    return default

def set_player_template(config: dict, lokasi_id: int | None = None) -> dict:
    """Simpan config template player VMS per lokasi."""
    lokasi_id = lokasi_id or state.get_lokasi_aktif()
    import json
    db.execute(
        "INSERT OR REPLACE INTO player_template (id_lokasi, config_json, diperbarui_pada) VALUES (?, ?, datetime('now','localtime'))",
        (lokasi_id, json.dumps(config)),
    )
    return get_player_template(lokasi_id)
```

```python
# main.py - API endpoints
@app.get("/api/player-template")
async def api_player_template(request: Request):
    _user(request)
    lid = state.get_lokasi_aktif()
    return logic.get_player_template(lid)

@app.put("/api/player-template")
async def api_player_template_ubah(body: dict, request: Request):
    _admin_saja(request)
    try:
        hasil = logic.set_player_template(body)
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))
    await _broadcast_langsung()
    return hasil
```

**Player Page (templates/player.html)** - Loads template config on each render:
```javascript
async function loadTemplate() {
  try {
    const res = await fetch(`/api/player-template`);
    if (res.ok) {
      PLAYER_CFG = await res.json();
      applyTemplateCSS();
    }
  } catch (e) {
    PLAYER_CFG = { /* defaults */ };
  }
}

function applyTemplateCSS() {
  const root = document.documentElement;
  root.style.setProperty("--pt-header-bg", PLAYER_CFG.warna_header || "#000000");
  root.style.setProperty("--pt-judul", PLAYER_CFG.warna_judul || "#ffd600");
  root.style.setProperty("--pt-pill-bg", PLAYER_CFG.warna_pill_bg || "#ffffff");
  root.style.setProperty("--pt-pill-teks", PLAYER_CFG.warna_pill_teks || "#0066ff");
  root.style.setProperty("--pt-font", PLAYER_CFG.font_family || "Arial, sans-serif");
  root.style.setProperty("--pt-upscale", PLAYER_CFG.upscale ? "1" : "0");
  if (!PLAYER_CFG.show_footer) {
    root.style.setProperty("--pt-footer-hide", "1");
  }
}

/* Skala with upscale toggle */
function skala() {
  const allowUpscale = PLAYER_CFG.upscale === true;
  const sk = allowUpscale
    ? Math.min(window.innerWidth / w, window.innerHeight / h)
    : Math.min(1, window.innerWidth / w, window.innerHeight / h);
  // ...
}
```

**Public Page (static/public.js)** - Applies template from `/api/state` response:
```javascript
const tpl = pengaturan.player_template || {};
const root = document.documentElement;
if (tpl.warna_header) root.style.setProperty("--pt-header-bg", tpl.warna_header);
if (tpl.warna_judul) root.style.setProperty("--pt-judul", tpl.warna_judul);
if (tpl.warna_pill_bg) root.style.setProperty("--pt-pill-bg", tpl.warna_pill_bg);
if (tpl.warna_pill_teks) root.style.setProperty("--pt-pill-teks", tpl.warna_pill_teks);
if (tpl.font_family) root.style.setProperty("--pt-font", tpl.font_family);
if (tpl.show_footer === false) root.style.setProperty("--pt-footer-hide", "1");
else root.style.removeProperty("--pt-footer-hide");
```

**CSS Variables (static/style.css)** - Fallback defaults in `:root`:
```css
:root {
  --pt-header-bg: #000000;
  --pt-judul: #ffd600;
  --pt-pill-bg: #ffffff;
  --pt-pill-teks: #0066ff;
  --pt-font: "Arial, sans-serif";
  --pt-upscale: 0;
  --pt-footer-hide: 0;
}
.header-tol { background: var(--pt-header-bg, #000); }
.header-tol .kotak-p { background: var(--pt-judul, #ffd600); }
.header-tol h1 { color: var(--pt-judul, #ffd600); font-family: var(--pt-font, "Arial, sans-serif"); }
.publik .header-tol .pill-lokasi { 
  background: var(--pt-pill-bg, #fff); 
  color: var(--pt-pill-teks, #0066ff); 
}
.footer-papan { /* hide via --pt-footer-hide */ }
@media (max-width: 640px) {
  .footer-papan:has(var(--pt-footer-hide): 1) { display: none; }
}
```

**Admin UI (templates/admin.html)** - Form for template config:
```html
<form id="formPlayerTemplate">
  <div class="grup-form"><label>Warna Header<input id="ptWarnaHeader" type="color" value="#000000"></label></div>
  <div class="grup-form"><label>Warna Judul & Kotak P<input id="ptWarnaJudul" type="color" value="#ffd600"></label></div>
  <div class="grup-form"><label>Warna Pill Background<input id="ptWarnaPill" type="color" value="#ffffff"></label></div>
  <div class="grup-form"><label>Warna Pill Teks<input id="ptWarnaPillTeks" type="color" value="#0066ff"></label></div>
  <div class="grup-form"><input type="checkbox" id="ptShowFooter"><label for="ptShowFooter">Tampilkan Footer</label></div>
  <div class="grup-form"><input type="checkbox" id="ptUpscale"><label for="ptUpscale">Izinkan Upscale (fullscreen)</label></div>
  <div class="grup-form"><label>Font Family<select id="ptFontFamily">...</select></label></div>
  <button type="submit">💾 Simpan Template Player</button>
</form>
```

**Admin JS (static/admin.js)** - Load/save:
```javascript
async function muatPlayerTemplate() {
  try {
    const cfg = await api("/api/player-template");
    $("ptWarnaHeader").value = cfg.warna_header || "#000000";
    // ... other fields
    $("ptShowFooter").checked = cfg.show_footer !== false;
    $("ptUpscale").checked = cfg.upscale === true;
    $("ptFontFamily").value = cfg.font_family || "Arial, sans-serif";
  } catch (e) { /* ignore */ }
}

$("formPlayerTemplate").addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    warna_header: $("ptWarnaHeader").value,
    warna_judul: $("ptWarnaJudul").value,
    warna_pill_bg: $("ptWarnaPill").value,
    warna_pill_teks: $("ptWarnaPillTeks").value,
    show_footer: $("ptShowFooter").checked,
    upscale: $("ptUpscale").checked,
    font_family: $("ptFontFamily").value,
  };
  await api("/api/player-template", { method: "PUT", body: JSON.stringify(body) });
  notifikasi("Template Player disimpan");
});
```

**Key Features**:
- Per-location config (each rest area has independent theme)
- Runtime CSS variable injection (no rebuild needed)
- Upscale toggle (never upscale vs fullscreen fill)
- Footer show/hide
- Font family selection
- Auto-loaded on player page refresh (every 5 min) and public page poll (5 sec)
- Defaults in `:root` ensure working without DB config

### SQLite CHECK Constraint Removal Pattern
SQLite doesn't support `DROP CHECK`. To remove CHECK constraints from tables (e.g., to allow dynamic enum values from a new `jenis_kendaraan` table):

```python
def _ada_check(tabel: str) -> bool:
    row = conn.execute(
        "SELECT sql FROM sqlite_master WHERE type='table' AND name=?", (tabel,)
    ).fetchone()
    return bool(row and "CHECK (jenis" in (row[0] or ""))

# Rebuild table without CHECK
conn.execute(f"ALTER TABLE {tabel} RENAME TO {tabel}_lama")
conn.execute(f"""
    CREATE TABLE {tabel} (
        id              INTEGER PRIMARY KEY,
        id_lokasi       INTEGER NOT NULL DEFAULT 1,
        jenis_kendaraan TEXT NOT NULL,  -- no CHECK constraint
        ...
    )
""")
# Handle column differences between old/new (e.g., old had 'aktif' column)
kolom = "id, id_lokasi, jenis_kendaraan, kapasitas_maks, masuk, keluar, diperbarui_pada"
if _kolom_ada(conn, f"{tabel}_lama", "aktif"):
    kolom += ", aktif"
conn.execute(f"INSERT INTO {tabel} ({kolom}) SELECT {kolom} FROM {tabel}_lama")
conn.execute(f"DROP TABLE {tabel}_lama")
```

### Dynamic Enum Replacement (Hardcoded → DB-Driven)
**Problem**: Vehicle types hardcoded as `JENIS_LIST = ["mobil", "motor", "bus"]` in multiple files.

**Solution**: Create `jenis_kendaraan` table + helper functions in logic layer:
```python
# logic.py - central helpers
def jenis_daftar(): return db.query("SELECT * FROM jenis_kendaraan ORDER BY urutan")
def jenis_kode_list(): return [r["kode"] for r in jenis_daftar()]
def jenis_label(kode): return db.query_one("SELECT label FROM jenis_kendaraan WHERE kode=?", (kode,))
def jenis_valid(kode): return db.query_one("SELECT id FROM jenis_kendaraan WHERE kode=?", (kode,)) is not None

# Replace all hardcoded checks:
# OLD: if jenis not in JENIS_LIST:
# NEW: if not jenis_valid(jenis):
```

**Auto-seed on migrate**: Insert default 3 types + create kapasitas rows for ALL locations (0).

### VMS Player Scaling Formula (Exact C:\VMS Behavior)
```javascript
// Player page (kiosk): iframe with transform-origin 0 0, NEVER upscale
function skala() {
  const f = document.querySelector("iframe");
  const { display_width: w, display_height: h } = CONFIG;
  const scale = Math.min(1, window.innerWidth / w, window.innerHeight / h);
  f.style.transform = `translate(0, 0) scale(${scale})`;
  f.style.transformOrigin = "0 0";  // anchor top-left
}
setInterval(skala, 100);  // responsive on resize
```
**Key**: `transform-origin: 0 0` (not center), `scale = min(1, vw/w, vh/h)` — content renders at native 512×288, centered top-left on larger screens.

### Public Display Card Pattern (2×2 Grid, 2-Part Split)
Each card = 2 vertical halves (50/50):
```
┌─────────────────────────────────────┐
│  LABEL (yellow pill)                │
├──────────────┬──────────────────────┤
│   ICON       │   NUMBER (big)       │
│   (left)     │   "SLOT" caption     │
│              │   "XX / YY" sub-line │
└──────────────┴──────────────────────┘
```
- Grid: `grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr;`
- Card: `height: 100%; display: grid; grid-template-rows: auto 1fr;`
- Area-data: `display: flex; flex-direction: row; gap: 3px;`
- Media query `@media (max-width: 640px)` for VMS 512×288 exact fit

### Single-Page Admin CRUD Tab Pattern
Admin UI tab with full CRUD in one panel:
```html
<!-- Form (create) -->
<div class="form-grid-2">
  <label>Kode<input id="jenisKode"></label>
  <label>Label<input id="jenisLabel"></label>
  <label>English<label id="jenisLabelEn"></label>
  <label>Bobot<input type="number" id="jenisBobot"></label>
</div>
<button id="tombolSimpanJenis">💾 Simpan</button>

<!-- Table (read/update/delete) -->
<table id="tabelJenis">
  <thead><tr><th>Urutan</th><th>Ikon</th><th>Kode</th><th>Label</th><th>English</th><th>Bobot</th><th>Aktif</th><th>Aksi</th></tr></thead>
  <tbody>...</tbody>
</table>
```
**Row features**: Inline inputs (label, urutan, bobot), icon upload (file input hidden in label), checkbox toggle (aktif), save/delete buttons.
**JS pattern**: Event delegation on table → handles save/put/delete/toggle/upload.

### Cache Busting Strategy
Static assets versioned via query string in templates:
```html
<link rel="stylesheet" href="/static/style.css?v=18">
<script src="/static/public.js?v=10"></script>
```
Bump on every CSS/JS change: `sed -i 's|style.css?v=17|style.css?v=18|' templates/public.html`

### Icon Handling (SVG + PNG Custom)
- Default: `/static/ikon/ikon_{kode}.svg` (filtered yellow via CSS)
- Custom PNG: Upload → save as `ikon/ikon_{kode}.png` → DB `pengaturan_app.ikon_{kode}`
- Render: `<img class="ikon-custom" src="...">` with `filter: none` (skip yellow filter)
- BW conversion: PIL luminance threshold 128 → white silhouette + transparent → black card BG
- Anti-cache: Rename file (e.g., `ikon_mobil_v2.png`) + update DB → forces fresh fetch