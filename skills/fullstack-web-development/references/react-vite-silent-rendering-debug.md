# React/Vite Silent Rendering Failure - Debug Session Log

**Date:** June 21, 2026  
**Issue:** Dashboard page completely blank, no JavaScript errors, no console output  
**Root Cause:** Multiple cascading issues with routing structure and auth store

## The Symptom

Page is completely white/blank after login. No React errors in console. Chat shows DashboardPage component exists but `<main>` tag in Layout is empty.

## Investigation Timeline

### 1. Initial Problem (Blank Dashboard)
**Error:** Chat showed `<main>` tag with no children

**Fixed by:** Creating missing child components
- DashboardStats.jsx
- EnergyChart.jsx  
- RecentAlerts.jsx

**Location:** `frontend/src/components/` (flat structure, not nested)

### 2. Second Problem (Still Blank)
**Issue:** Components created but still not rendering

**Root cause:** Route structure was wrong

**WRONG structure:**
```jsx
<Route path="/" element={<Layout />}>
  <Route index element={<Navigate to="/dashboard" />} />
  <Route path="dashboard" element={<DashboardPage />} />
</Route>
```

This renders **Layout at `/`** with NO children, and **DashboardPage at `/dashboard`** standalone. They're separate routes!

**CORRECT structure:**
```jsx
<Route path="/" element={<Layout />}>
  <Route index element={<Navigate to="/dashboard" replace />} />
  {/* All pages are children of Layout via children prop */}
  <Route path="dashboard" element={<DashboardPage />} />
  <Route path="map" element={<MapPage />} />
  // etc...
</Route>
```

And in Layout.jsx:
```jsx
<main className="flex-1 overflow-y-auto p-6">
  {children}  {/* ← Pages render here */}
</main>
```

Then either:
- **Option A**: All routes render as Layout children (above), or
- **Option B**: Explicit route `/dashboard` renders `<Layout><DashboardPage /></Layout>`

### 3. Third Problem (Auth Not Persisting)
**Issue:** Login successful, token returned by API, but user appears logged out on refresh

**Root cause:** Zustand store not rehydrating from localStorage on mount

**Wrong pattern (no rehydrate):**
```javascript
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

**Right pattern (with rehydrate function):**
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
```

**Usage in App.jsx:**
```javascript
useEffect(() => {
  useAuthStore.getState().rehydrate()
}, [])
```

### 4. Fourth Problem (Build Error)
**Error from build:**
```
"useAuthStore" is not exported by "src/store/authStore.js"
```

**File actually had `export default useAuthStore`** but Vite dev server cached old version.

**Fix:** Kill dev server completely and restart:
```bash
# Find process on port 5173
netstat -ano | findstr ":5173"

# Kill it
taskkill /PID <pid> /F

# Clean restart
npm run dev
```

## Final Debug Checklist Created

When dashboard is blank/empty with no console errors:

```markdown
1. **Check Route Structure**
   - Is Layout wrapping the page component OR are they separate routes?
   - Look for: `<main>{children}</main>` in Layout
   - If using nested routes, pages MUST be children of Layout

2. **Check Missing Components**
   - Run build and look for import errors
   - Check: `frontend/src/components/` has all imported files
   - Prefer flat structure over nested `components/Dashboard/`

3. **Check Auth Token**
   - After login: F12 → Application → Local Storage → `token` exists?
   - Console: `localStorage.getItem('token')` should return string
   - If null: login function not saving OR localStorage broken

4. **Check Zustand Store Rehydration**
   - Does store have a `rehydrate()` method?
   - Is it called in App.jsx or main.jsx?
   - Alternative: use Zustand `persist` middleware

5. **Check Vite Cache Issues**
   - Dev server running but edits not reflected?
   - Kill server: `netstat -ano | findstr ":5173"` → `taskkill /PID /F`
   - Restart: `npm run dev`
   - Verify timestamp in `src/main.jsx?t=...` changes

6. **Check API Response Shape**
   - Login API returns `{user, token}` or `{data: {user, token}}`?
   - Is code destructuring correctly?
   - Handle edge case: `res.data.user || res.data`

7. **Check Build Errors**
   - Run `npm run build` to see compilation errors
   - Vite dev server might hide import errors
   - Build shows exact line/column of failures
```

## Key Learnings

### Silent Failures vs loud failures
- **Loud**: "Component X is not defined" - easy to fix
- **Silent**: Blank page, no errors - requires systematic debugging of routes → components → state → API

### Most likely culprits (in order):
1. Route structure wrong (Layout renders but children undefined)
2. Missing child component imports
3. Auth state not rehydrating (Zustand/redux/mobx caching issues)
4. Vite/Webpack cache mismatch (rebuild fixes)

### Browser console is definitive
- If React DevTools shows component in tree → component is rendering
- If Network tab shows API returning data → API works
- If localStorage has token → login succeeded
- Only the gap between these tells you where the problem is

### State management needs initialization
- Zustand: call `rehydrate()` in App or use `persist` middleware
- Redux: `persistStore` with redux-persist
- MobX: `makePersistable`
- Plain Context: load from localStorage in useEffect

## Related Files

- `SKILL.md` section: "Component Import Structure" and "Zustand Store Pattern"
- `scripts/fullstack-troubleshoot.sh` - Automated diagnostics
- `references/dashboard-component-patterns.md` - Component structure best practices

## Copy-paste Fix Patterns

### Fix Zustand auth store
```diff
+ rehydrate: () => {
+   if (typeof window === 'undefined') return
+   const token = localStorage.getItem('token')
+   const userStr = localStorage.getItem('user')
+   if (token && userStr) {
+     set({ token, user: JSON.parse(userStr), isAuthenticated: true })
+   }
+ }
```

### Fix route structure
```diff
- <Route path="/dashboard" element={<DashboardPage />} />
+ <Route path="/dashboard" element={<Layout><DashboardPage /></Layout>} />
```

Or the better option with nested routes - ensure pages are rendered inside Layout's children slots.

### Fix login response destructuring
```diff
- login(res.data.user, res.data.token)
+ const user = res.data.user || res.data
+ const token = res.data?.token
+ login(user, token)
```

## Prevention

### Project template should include:
- ✅ Zustand store with rehydrate() already set up
- ✅ Route structure with proper Layout/children pattern
- ✅ Login page with defensive destructure
- ✅ Console.log in store login/logout for debugging
- ✅ Documented troubleshooting steps in README

---

**Session metadata:**
- Time spent debugging: ~3 hours
- Issues found: 4 (route structure, missing components, auth store, Vite cache)
- Lines of code changed: ~150 across 5 files
- Debugging approach tried: browser console → snapshot inspection → liveness checks → full rewrite of auth store

**Value for future sessions:** Next time a user says "page is blank but no errors", check route structure and Zustand rehydration FIRST before anything else.