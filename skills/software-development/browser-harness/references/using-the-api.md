# Using the browser-harness runtime API

Distilled from `src/browser_harness/helpers.py` (503 lines, the file `run.py` auto-loads on every `<<'PY' ... PY` invocation). This is what you can actually call — there is no `snapshot()`, no `click(ref)`, no auto element handle.

## Core primitives (auto-imported, no `import` needed)

```python
page_info()                       # {url, title, w, h, sx, sy, pw, ph} OR {dialog: {...}}
goto_url("https://...")           # Page.navigate; returns CDP result
js("document.title")              # Runtime.evaluate; top-level `return` auto-IIFE-wrapped
cdp("Network.deleteCookies", domain="x.test", name="session")   # raw CDP call
cdp("Network.getAllCookies")      # returns {"cookies": [{name, domain, path, ...}]}
drain_events()                    # buffered CDP events
http_get("https://api.example.com")  # pure HTTP, no browser
```

## Input / interaction

```python
fill_input("input[name=email]", "me@x.com", clear_first=True, timeout=0.0)
# focuses, Ctrl/Cmd+A + Backspace to clear, types char by char, fires input+change events
type_text("raw insertText bypassing framework listeners - usually wrong")
press_key("Enter", modifiers=0)   # 1=Alt, 2=Ctrl, 4=Meta, 8=Shift
scroll(x, y, dy=-300, dx=0)       # mousewheel at coords
click_at_xy(x, y, button="left", clicks=1)
upload_file("input[type=file]", "/tmp/file.pdf")  # DOM.setFileInputFiles
dispatch_key("input[name=email]", "Enter", "keypress")  # synthetic KeyboardEvent
```

## Tabs

```python
list_tabs(include_chrome=True)    # skip chrome:// / devtools:// etc with include_chrome=False
switch_tab(dict_or_targetId)      # accepts dict from current_tab()/list_tabs()
new_tab("about:blank")            # don't pass URL — race with attach; navigate after
close_tab()                       # closes currently attached tab
current_tab()                     # {targetId, url, title}
ensure_real_tab()                 # switch to first non-chrome tab; useful after daemon reconnect
```

Active tab gets a 🐴 prefix in `document.title` so the user can see which tab is "owned".

## Settling / waits

```python
wait(0.5)                                  # plain sleep
wait_for_load(timeout=15.0)                # polls readyState=='complete' — misses SPAs
wait_for_element(".modal", timeout=10, visible=True)   # also checks visibility
wait_for_network_idle(timeout=10, idle_ms=500)         # no Network.* events for idle_ms ms
```

`wait_for_load` is not enough after SPA route changes. Use `wait_for_element(selector, timeout=10)` after `click_at_xy` or any navigation you trigger.

## Recipes

### Probe a page

```python
print(page_info())                          # viewport + URL + title
bodyText = js("(document.body.innerText||'').replace(/\\s+/g,' ').trim().slice(0, 500)")
errors = js("(() => { const j=[]; document.querySelectorAll('.error, .alert-danger, [role=alert]').forEach(e=>{const t=(e.innerText||'').trim();if(t)j.push(t);}); return JSON.stringify(j); })()")
print(errors)
```

### Get all in-app links (cookie-bounded, sidebar-friendly)

```python
links = js("""
(() => {
  const out = []; const seen = new Set();
  document.querySelectorAll('a[href]').forEach(a => {
    const h = a.getAttribute('href');
    if (!h || h.startsWith('#') || h.startsWith('javascript:')) return;
    try { if (new URL(h, location.href).origin !== location.origin) return; } catch(e) { return; }
    const text = (a.innerText || '').trim().replace(/\\s+/g,' ').slice(0, 40);
    const key = h + '|' + text;
    if (seen.has(key)) return;
    seen.add(key);
    out.push([h, text]);
  });
  return JSON.stringify(out);
})()
""")
```

### Laravel form login (when `fill_input` rejects the events)

```python
js("""
(() => {
  const u = document.querySelector('input[name=username]');
  const p = document.querySelector('input[name=password]');
  if (!u || !p) return 'inputs not found';
  const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
  setter.call(u, 'admin');
  u.dispatchEvent(new Event('input', {bubbles:true}));
  u.dispatchEvent(new Event('change', {bubbles:true}));
  setter.call(p, 'the-password');
  p.dispatchEvent(new Event('input', {bubbles:true}));
  p.dispatchEvent(new Event('change', {bubbles:true}));
  document.querySelector('form').submit();
  return 'submitted';
})()
""")
wait_for_network_idle(8)
```

### Logout (kill Laravel HTTP-only session cookie via CDP, not just JS)

```python
js("document.cookie.split(';').forEach(c=>{const n=c.split('=')[0].trim();if(n)document.cookie=n+'=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';})")
cdp("Network.deleteCookies", domain="your-app.test", name="yourapp_session")
cdp("Network.deleteCookies", domain="your-app.test", name="XSRF-TOKEN")
goto_url("your-app.test/login")
```

### Generate harness scripts from Python — avoid the `{{` doubling trap

When you build a triple-quoted Python string that you later pass to `subprocess.run(['browser-harness'], input=...)`, **never write `{{` or `}}` as literal characters inside the template body**. The browser-harness subprocess parses it as Python, where `{...}` is a dict literal and `{{...}}` is a syntax error.

```python
# WRONG — `json.dumps({{ ... }})` becomes literal `json.dumps({{ ... }})` in the rendered string
SCRIPT = '''
print("CHECKT|" + json.dumps({{
    "path": "/foo",
    "ok": True,
}}))
'''

# RIGHT — single braces throughout
SCRIPT = '''
print("CHECKT|" + json.dumps({
    "path": "/foo",
    "ok": True,
}))
'''

# Substitute with .replace(), NOT .format() and NOT f-strings
script = SCRIPT.replace("/foo", actual_path)
subprocess.run(['browser-harness'], input=script, capture_output=True, text=True, timeout=60)
```

### Long-running sweep with `subprocess.run` from Python

```python
import subprocess, json, time, sys
ROUTES = ["/", "/kontrak", "/pekerjaan", ...]   # collected from sidebar sweep

SCRIPT = '''
import time, json
goto_url("BASEURL" + "PATH")
wait_for_network_idle(20)
time.sleep(1.5)
print("CHECKT|" + json.dumps({
    "path": "PATH",
    "title": js("document.title"),
    "url": js("location.href"),
    "bodyLen": js("document.body.innerText.length"),
    "snippet": js("(document.body.innerText||'').replace(/\\s+/g,' ').trim().slice(0, 280)"),
}))
'''

results = []
for path in ROUTES:
    script = SCRIPT.replace("BASEURL", BASE).replace("PATH", path)
    p = subprocess.run(['browser-harness'], input=script, capture_output=True, text=True, timeout=60, cwd=r'C:\path\to\browser-harness\repo')
    line = next((l for l in p.stdout.splitlines() if l.startswith("CHECKT|")), None)
    if not line:
        results.append({"path": path, "error": "no parse", "stderr": p.stderr[-200:]})
        continue
    results.append(json.loads(line[len("CHECKT|"):]))
```

Expect ~3-5s per route on a local dev server. Run in background if you have many routes:

```python
import subprocess
proc = subprocess.Popen(['python', 'sweep.py'], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
# poll for completion or stream output
```

Or use realtime MCP-style progress via `terminal(background=true, notify_on_complete=true)` from the Hermes runtime.

## Failure modes you'll actually see

| Symptom | Cause | Fix |
|---|---|---|
| "CDP WS handshake failed: timed out during opening handshake" | Port 9222 is Chrome Relay, not DevTools API. Or Chrome 144 popup wasn't Allow'd. | `curl http://127.0.0.1:9222/json/version` — if 404, find the real DevTools port (Way 2 launch). Or re-tick `chrome://inspect/#remote-debugging` and re-click Allow. |
| `[FAIL] daemon alive` from `--doctor` even though `page_info()` works | Normal on a daemon that just started. Ignore — see main skill's "When --doctor lies". |
| `js()` raises `JavaScript evaluation failed: <expr>` | Runtime exception in page context. The error message includes the snippet and a line/column. |
| A form stays on the screen saying "field is required" after `fill_input` | Framework rejected synthetic events. Try the native setter + dispatch recipe above. |
| `wait_for_load()` returns immediately but the page is blank | It's an SPA. Use `wait_for_element('body h1, body .content', timeout=10)`. |
| `page_info()` returns `{dialog: {type, message}}` instead of dims | Native JS alert/confirm/prompt is open. The page is frozen. `dispatch_key('body', 'Escape', 'keypress')` or click OK to close it. |
