---
name: live-preview
description: |
  Live browser preview workflow — open dev server, refresh after code changes,
  take screenshots so the user sees visual results in real-time.
  Works with any local dev server (Laragon, Vite, etc.).
version: 1.0.0
platforms: [windows, macos, linux]
metadata:
  hermes:
    tags: [preview, browser, live, visual, laravel, laragon]
    category: software-development
    related_skills: [browser-harness, computer-use, fullstack-web-development, filament-erp]
---

# Live Browser Preview

Give the user visual feedback by keeping a browser open on the local dev server
and refreshing after every significant code change.

## When to use

- User says "preview", "lihat hasilnya", "show me", "cek di browser", or similar
- Building/editing UI: Filament pages, Blade views, dashboard widgets, forms
- After patches that affect visual output (CSS, layouts, components)
- User wants to verify appearance before moving on

## Quick start

```
1. Navigate browser to local dev URL
2. Make code changes
3. Refresh page
4. Take screenshot via browser_vision
5. Report what changed
```

## Step-by-step workflow

### Step 1 — Find the dev URL

Check `.env` for `APP_URL`:
```bash
grep APP_URL "/c/laragon/www/PT.EXFERIA PUTRA INOVASI/.env"
```

Known working URL: `http://localhost` (Filament login at `/admin/login`)

### Step 2 — Open browser

```
browser_navigate(url="http://localhost")
```

Filament will redirect to `/admin/login` if not authenticated. Login with
user credentials. Known credentials (if seeded):
```
Email:    superadmin@example.com
Password: password123
```

### Step 3 — After code changes, refresh and capture

```
browser_navigate(url="https://erp.test/some-page")
```
Or use browser_refresh if available. Then:

```
browser_vision(question="Describe the current page layout, any errors, and visual elements")
```

### Step 4 — Report to user

Describe what changed visually. Focus on:
- Layout changes
- New elements appearing
- Error messages or blank screens
- Color/styling differences from before

## Filament-specific patterns

### Navigate to specific pages
```
Dashboard:     /admin/dashboard
Resources:     /admin/{resource-name}
Create form:   /admin/{resource-name}/create
Edit form:     /admin/{resource-name}/{id}/edit
Custom pages:  /admin/{page-slug}
```

### Common visual checks after code changes
1. Page loads without 500 error
2. Form fields render correctly
3. Tables show data
4. Dashboard widgets display numbers
5. Navigation sidebar is correct for the logged-in role

## After login — stay logged in

Browser session cookies persist across `browser_navigate` calls within the same
Hermes session. No need to re-login unless cookies are cleared.

## Pitfalls

- **Session timeout**: If the browser has been idle, the Laravel session may
  expire. Re-navigate to the login page if you see a redirect.
- **CSRF mismatch after code change**: Clear browser cache or hard-refresh
  if forms submit but return CSRF errors.
- **Filament SPA mode**: Pages may cache. Full page reload (navigate to URL
  directly) is more reliable than clicking links for fresh content.
- **Mixed content**: If APP_URL is https but assets load via http, the page
  may look broken. Check Laragon SSL config.
