# Install + troubleshoot cheat sheet

Condensed from upstream `install.md` (https://github.com/browser-use/browser-harness/blob/main/install.md). Kept offline so this skill is self-contained.

## Install (stable, editable, PATH-safe)

```bash
mkdir -p ~/Developer && cd ~/Developer
[ -d browser-harness ] || git clone https://github.com/browser-use/browser-harness
cd browser-harness
uv tool install -e .
PERSIST_LINE='export PATH="$HOME/.local/bin:$PATH"'
for f in "$HOME/.bashrc" "$HOME/.bash_profile" "$HOME/.profile"; do
  touch "$f"
  grep -qF '.local/bin' "$f" || echo "$PERSIST_LINE" >> "$f"
done
"$HOME/.local/bin/browser-harness.exe" --version   # expect: 0.1.0 (or newer)
```

Skip `mkdir` if `~/Developer/` already exists. The `for` loop is idempotent.

Optional: symlink the SKILL.md to Codex/Claude Code locations so other clients pick up browser-harness automatically (not needed for Hermes):

```bash
# Codex
mkdir -p "${CODEX_HOME:-$HOME/.codex}/skills/browser-harness"
ln -sf "$PWD/SKILL.md" "${CODEX_HOME:-$HOME/.codex}/skills/browser-harness/SKILL.md"
```

## First connection — Way 1 (default if Chrome already running)

1. Run `browser-harness <<'PY'\nprint(page_info())\nPY`
2. If returns a dict → done.
3. If returns nothing: run `browser-harness --doctor`. Look at `chrome running` and `daemon alive`.
4. No Chrome detected → ask user to open their target Chrome (Way 1) OR launch isolated Chrome yourself (Way 2).
5. Chrome OK, daemon FAIL → user must tick `chrome://inspect/#remote-debugging` → "Allow remote debugging", then click Allow on the in-browser popup (Chrome 144+).

## First connection — Way 2 (no popups, isolated profile)

Only valid when the path is **not** the platform default. On Windows, that means NOT `%LOCALAPPDATA%\Google\Chrome\User Data`.

```bash
# Pick a non-default path
PROFILE_DIR="$HOME/.browser-harness-chrome"
mkdir -p "$PROFILE_DIR"

# Launch Chrome (user must close any existing Chrome first, or this opens a second window via the new profile)
# PowerShell users: use Start-Process; from MSYS bash this works:
"/c/Program Files/Google/Chrome/Application/chrome.exe" \
  --remote-debugging-port=9222 \
  --user-data-dir="$(cygpath -w "$PROFILE_DIR")" &

export BU_CDP_URL=http://127.0.0.1:9222
browser-harness <<'PY'
print(page_info())
PY
```

Bookmark/profile copy loot: bookmarks + extensions survive a copy from the default profile dir; cookies do not.

## Stale daemon

```bash
browser-harness <<'PY'
restart_daemon()
PY
```

If hangs: kill Chrome + daemon processes, remove Unix socket/pid file if any. On Windows there's no `/tmp/bu-default.sock` (TCP loopback instead), but stale state can still wedge — `--doctor` will say `daemon alive FAIL`. On macOS/Linux:

```bash
rm -f /tmp/bu-default.sock /tmp/bu-default.pid
```

## Update

```bash
browser-harness --update -y
```

Editable clone → `git pull --ff-only`. PyPI install → `uv tool upgrade browser-harness`. Refuses to run with uncommitted changes; tell the user and let them resolve the dirty worktree.

## Architecture (one-liner)

`Chrome → CDP WS → browser_harness.daemon → IPC → browser_harness.run`

- IPC: Unix socket `/tmp/bu-$NAME.sock` (POSIX) or TCP loopback with port file (Windows)
- `BU_NAME` namespaces IPC, pid, log
- `BU_CDP_WS` overrides Chrome discovery for remote browser
- `BU_CDP_URL` overrides Chrome discovery with a specific DevTools HTTP endpoint (Way 2)
