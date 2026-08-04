# React + Vite Blank Page Debug Guide

**Session**: SMART PJU Dashboard Build (June 2026)  
**Duration**: 30+ minutes  
**Root Cause**: Vite module cache + nested routing structure

## Problem

Dashboard page showed completely blank/white screen after login. No console errors, no runtime errors.

## Symptoms

- ✅ Login successful (token returned from API)
- ✅ localStorage had token after login
- ✅ Backend API working (could curl endpoints)
- ❌ Dashboard page blank/white after navigation
- ❌ No console errors
- ❌ No runtime exceptions
- ❌ Browser DevTools showed correct URL (`/dashboard`)
- ❌ Layout sidebar showing, header showing, but `<main>` empty

## Root Cause Discovery

Ran `npm run build` and found compilation error:

```
error during build:
src/components/Layout.jsx (6:9): "useAuthStore" is not exported by "src/store/authStore.js"
```

**But the export WAS correct in the file!** This is a **Vite dev server cache issue** - HMR (Hot Module Replacement) didn't propagate the changes correctly.

## Solution

### Immediate Fix

```bash
# Windows - kill ALL Node processes
taskkill /F /IM node.exe

# Verify port 5173 is free
netstat -ano | findstr ":5173"
# Should return nothing

# Restart dev server
npm run dev

# Wait for "VITE ready in XXX ms"
```

**Result**: Dashboard rendered immediately after clean restart.

## Complete Debug Checklist

When facing **blank/white page in React + Vite**:

```
□ 1. Run `npm run build` to see ACTUAL compilation errors (not runtime)
□ 2. Check import/export statements match (VSCode might not show errors)
□ 3. Kill ALL Node processes: taskkill /F /IM node.exe
□ 4. Verify port is free: netstat -ano | findstr ":5173"
□ 5. Restart dev server: npm run dev
□ 6. Hard refresh browser: Ctrl+Shift+R
□ 7. Check localStorage for auth tokens
□ 8. Check route structure - are components passed as children?
□ 9. Check browser console with "error" filter
□ 10. Check Network tab for 401/500 responses
```

## Prevention

1. **Build before commit**: Always run `npm run build` to catch errors early
2. **Clean restart after store changes**: Zustand/Vuex/Redux changes require full restart
3. **Trust build output over dev server**: HMR can hide issues
4. **Test each component immediately**: Verify render after creation

---

**Status**: ✅ Resolved  
**Tech Stack**: React 18 + Vite 5 + Zustand + React Router v6