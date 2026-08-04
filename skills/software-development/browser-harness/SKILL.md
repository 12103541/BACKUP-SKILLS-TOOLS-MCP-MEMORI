---
name: browser-harness
description: Connect a Python driver (browser-harness from browser-use) to a running Chrome over CDP for LLM/agent browser control. Covers install, Chrome remote-debugging setup (Way 1 sticky checkbox vs Way 2 isolated profile), Cloud browser option, and when doctor output is misleading.
---

# browser-harness (`browser-use/browser-harness`)

Thin Python harness that gives an LLM/agent direct control over a real Chrome browser via the Chrome DevTools Protocol. It exposes a Python REPL you drive with `<<'PY' ... PY` heredocs — `page_info()`, `switch_tab()`, helpers for clicks/uploads, and an `agent-workspace/agent_helpers.py` the harness itself edits over time.

Use this skill when the task is: drive a browser from Python/agent code using the user's real Chrome (logins, cookies, bookmarks intact), or spin up an isolated profile for unattended jobs.

## When `--doctor` lies

Critical pitfall: on first run, `browser-harness --doctor` routinely prints:

```
[FAIL] daemon alive
[FAIL] active browser connections — 0
```

even when Chrome is healthy and the harness can attach. **These FAIL lines on a fresh install do NOT mean broken.** They mean "daemon has never started." Run a real probe before chasing them:

```bash
browser-harness <<'PY'
print(page_info())
PY
```

If that returns a dict with `url`, `title`, `w`, `h`, `sx`, `sy`, `pw`, `ph` — you're connected, daemon is alive, ignore the doctor FAILs. If it returns nothing or hangs, debug per `references/install-and-troubleshoot.md`.

## Two ways to connect Chrome — pick by use case

### Way 1 — real profile, sticky checkbox (recommended default)

- User opens `chrome://inspect/#remote-debugging` → ticks **"Allow remote debugging for this browser instance"** (one-time per profile, stays across restarts)
- On Chrome 144+ the first attach triggers an in-browser **"Allow remote debugging?"** popup — **the user must click Allow**
- Inherits the user's everyday Chrome: logins, extensions, history, bookmarks
- Right choice when the agent is helping with tasks in the user's actual browser

### Way 2 — isolated profile, no popups ever

- Launch Chrome yourself with `--remote-debugging-port=9222 --user-data-dir=<NON-default-path>`
- On Windows: `<NON-default-path>` MUST NOT be `%LOCALAPPDATA%\Google\Chrome\User Data`. Chrome 136+ silently ignores the port flag when it equals the platform default — even when passed explicitly.
- Set `BU_CDP_URL=http://127.0.0.1:9222` in the env before launching
- Profile is empty and clean. Bookmarks/extensions survive a copy; **cookies do not** (encrypted under the original directory's key). For real cookies, use Way 1.

### Cloud browsers (Browser Use cloud)

- Set `BROWSER_USE_API_KEY`, then `start_remote_daemon("work", ...)` from Python
- Free tier: 3 concurrent browsers, proxies, captcha solving
- Use for stealth, sub-agents, headless deployments, or when no local Chrome exists

## Day-to-day workflow

```bash
# Probe current page
browser-harness <<'PY'
print(page_info())
PY

# Multi-line script — NO leading f-string, no {{ / }} doubled braces
browser-harness <<'PY'
goto_url("https://example.com/login")
print(js("document.title"))
PY

# Update to latest version
browser-harness --update -y
```

For deeper usage patterns, read `~/Developer/browser-harness/SKILL.md` after install — it's the project's day-to-day reference and richer than this skill.

## What's actually in the runtime (auto-imported from `helpers.py`)

The harness imports a core API into every `<<'PY' ... PY` execution. There is **no `snapshot()` and no `click(ref)`.** Real primitives:

| Function | What it does |
|---|---|
| `page_info()` | `{url, title, w, h, sx, sy, pw, ph}` — or `{dialog: {...}}` if a native JS dialog is open (page is frozen in that case) |
| `goto_url(url)` | Navigate. With `BH_DOMAIN_SKILLS=1`, also returns matched domain-skill files |
| `js(expression)` | eval JS in the attached tab. Top-level `return` is auto-wrapped in an IIFE so both `js("document.title")` and `js("const x = 1; return x")` work |
| `cdp(method, **params)` | Raw CDP. e.g. `cdp("Network.deleteCookies", domain=..., name=...)`, `cdp("Page.captureScreenshot", format="png")` |
| `fill_input(selector, text, clear_first=True, timeout=0.0)` | Framework-friendly input fill — focuses, selects-all + Backspace, types char-by-char, dispatches synthetic `input`+`change` events |
| `type_text(text)` | Raw `Input.insertText`. **Bypasses framework listeners** and may leave submit buttons disabled — prefer `fill_input` for React/Vue/Alpine/Laravel |
| `press_key(key, modifiers=0)` | Modifier bitfield: 1=Alt, 2=Ctrl, 4=Meta, 8=Shift |
| `scroll(x, y, dy=-300, dx=0)` | Mousewheel scroll at coords |
| `click_at_xy(x, y, button="left", clicks=1)` | Mouse-event click at pixel coords |
| `list_tabs(include_chrome=True)`, `switch_tab(target)`, `new_tab(url)`, `close_tab(target)` | Tab management. `switch_tab` accepts dict or targetId string. Marks the active tab with a 🐴 prefix in `document.title`. |
| `wait(seconds)`, `wait_for_load(timeout=15)`, `wait_for_element(selector, timeout=10, visible=False)`, `wait_for_network_idle(timeout=10, idle_ms=500)` | Settling helpers. `wait_for_load` polls `document.readyState=='complete'` and **misses SPAs** — use `wait_for_element` after route changes. |
| `http_get(url, headers=None, timeout=20.0)` | Pure HTTP, no browser. For probing endpoints / APIs |
| `drain_events()` | Read buffered CDP events |

**Practical workflow for clicking real elements:** use `js()` to read the DOM, find the selector yourself, then `fill_input(selector, text)` or `click_at_xy(x, y)` against coords you compute from a `getBoundingClientRect()` call.

## Laravel & server-rendered forms: native setter + dispatch

`fill_input()` works for React/Vue/Alpine. Plain server-rendered forms (Laravel Blade with `_token` CSRF and `<button type="submit">`) sometimes reject its events. Reliable pattern that worked against a real Laravel app:

```python
js("""
(() => {
  const u = document.querySelector('input[name=username]');
  const p = document.querySelector('input[name=password]');
  if (!u || !p) return 'inputs missing';
  const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
  setter.call(u, 'admin');
  u.dispatchEvent(new Event('input', {bubbles:true}));
  u.dispatchEvent(new Event('change', {bubbles:true}));
  setter.call(p, 'the-password');
  p.dispatchEvent(new Event('input', {bubbles:true}));
  p.dispatchEvent(new Event('change', {bubbles:true}));
  document.querySelector('form').submit();
})()
""")
```

Diagnostic if it fails: page sticks on the login screen with `"The username field is required"` style errors. Verify the value landed with `js("document.querySelector('input[name=username]').value")` before re-submitting.

## Clearing the Laravel session on logout (HTTP-only cookies)

Laravel sets the session cookie (`<appname>_session`) as **HTTP-only**, so plain `document.cookie=''` in JS **does not clear it**. You need the CDP `Network.deleteCookies` call too:

```python
js("document.cookie.split(';').forEach(c=>{const n=c.split('=')[0].trim();if(n)document.cookie=n+'=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';})")
cdp("Network.deleteCookies", domain="your-app.test", name="yourapp_session")
cdp("Network.deleteCookies", domain="your-app.test", name="XSRF-TOKEN")
```

Verify with `cdp("Network.getAllCookies")` and grep for the domain.

## Pitfalls

- **`--doctor` FAIL on first install = noise, not signal.** Always confirm with a real `page_info()` call before debugging. Daemon only starts on first successful command.
- **"Port 9222 listening" does NOT mean DevTools API is there.** Chrome's `chrome://inspect/#remote-debugging` checkbox opens a relay listener on 9222 that serves only the inspect UI, NOT the DevTools HTTP/WebSocket. Verify with `curl -s http://127.0.0.1:9222/json/version` — if that returns 404, the DevTools API lives somewhere else and `browser-harness` will time out on CDP handshake ("CDP WS handshake failed: timed out during opening handshake"). Diagnostic: `netstat -ano | grep :9222 | grep LISTENING` to find the PID, then `curl /json/version` against every other plausible port (8081, 9333, etc.) before reaching for Way 2.
- **Chrome 144+ "Allow remote debugging?" popup is per-attach.** Conditions for re-appearance aren't fully characterized — Way 2 sidesteps.
- **Way 2 on Windows: do NOT point `--user-data-dir` at the platform default.** The `--remote-debugging-port` flag silently no-ops there on Chrome 136+.
- **Cookie copy on Way 2 fails.** If the user needs their actual logins, Way 1 is the only honest answer.
- **Editable install wraps a `git clone`** — keep the clone in a durable path. `/tmp` will eventually get cleaned and break the next `uv tool install -e .`.
- **Windows + git-bash: `uv tool install` PATH warning uses PowerShell syntax.** See the `uv-tool-install` skill for the workaround.
- **Generating harness scripts from Python — the `{{` doubling trap.** When you build a triple-quoted Python string that gets passed to `subprocess.run(['browser-harness'], input=...)`, **any `{{` you write as literal in Python source becomes a literal `{{` in the rendered output**. When browser-harness then parses that as Python, `{ ... }` is a dict literal and `{{ ... }}` is a syntax error. **Always write single `{` and `}` inside the triple-quoted template body.** Use `.replace()` for substitution, never `.format(...)` or f-strings (they double-escape the literal braces and break the rendered code). This bit me three times in one session — a real footgun.
- **`page_info()` returns `{dialog: ...}` instead of viewport dims if a native `alert/confirm/prompt/beforeunload` dialog is open.** The page's JS thread is frozen until handled. Detect and call `dispatch_key("Escape")` (or click the right button) before any other JS.
- **Spawned subprocess inherits no agent state.** Each `browser-harness <<'PY' ... PY` invocation is a fresh Python process. Auto-imports of `js`, `goto_url`, `fill_input` etc. come from `src/browser_harness/helpers.py` being loaded by `src/browser_harness/run.py`. You do NOT need to import them yourself. But anything YOU define in one invocation is gone in the next.

## See also

- `references/install-and-troubleshoot.md` — exact install steps, doctor table, off-line copy of upstream `install.md`.
- `references/first-run-output.md` — concrete transcript from a fresh install + probe on Windows 10 git-bash.
- `references/using-the-api.md` — **runtime API reference** distilled from `src/browser_harness/helpers.py` (what's imported, common recipes, Laravel/server-render form dance, full-page probe pattern).
- Sibling skill: `uv-tool-install` — cross-platform Python-tool install workflow, including the git-bash PATH pitfall.
