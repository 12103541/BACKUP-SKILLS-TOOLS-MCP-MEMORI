# Wrapper Scripts — Triple-Platform Pattern for Python CLI Tools

When shipping a Python CLI app to Windows users who may have multiple Python installations on PATH (Hermes Agent venv, Laragon Python, system Python/Conda), the user's `python` command often resolves to the WRONG interpreter — one without your project's dependencies. Symptom: `ModuleNotFoundError: No module named 'ccxt'` even after `uv sync` succeeded.

The fix: ship a wrapper script (`.sh`, `.bat`, `.ps1`) that hardcodes the project's venv path. Run these instead of `python main.py ...`.

## When to use

- Distributing a Python CLI to Windows users
- User has another tool that clobbers `python` on PATH (e.g., Hermes Agent's venv, Laragon Python, Conda)
- `python main.py ...` fails with `ModuleNotFoundError` but the project's own venv has the package installed
- You want a specific Python interpreter to be used, never the system one

## Triple-wrapper files

### 1. `run-bot.sh` (git-bash, MSYS, WSL, Linux, macOS)

```bash
#!/usr/bin/env bash
# run-bot.sh — bash wrapper (git-bash on Windows, Linux, macOS).
set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Forward-slash works in git-bash on Windows.
VENV_PY="$SCRIPT_DIR/.venv/Scripts/python.exe"
# On native Linux/macOS the venv inside an `uv add ...` resolution looks like:
# VENV_PY="$SCRIPT_DIR/.venv/bin/python"

if [ ! -f "$VENV_PY" ]; then
  echo "[ERROR] venv missing: $VENV_PY"
  echo "        Run: uv sync"
  exit 1
fi

if [ $# -eq 0 ]; then
  echo "Usage: ./run-bot.sh main.py [args]"
  echo "Examples:"
  echo "  ./run-bot.sh main.py backtest"
  echo "  ./run-bot.sh main.py --verbose run --mode=mock"
  echo "  ./run-bot.sh main.py --verbose run --mode=live --once"
  exit 0
fi

"$VENV_PY" "$@"
```

### 2. `run-bot.bat` (Windows CMD)

```bat
@echo off
REM run-bot.bat
if "%~1"=="" (
    echo Usage: run-bot.bat main.py [args]
    echo Examples:
    echo   run-bot.bat main.py backtest
    echo   run-bot.bat main.py --verbose run --mode=mock
    echo   run-bot.bat main.py --verbose run --mode=live --once
    exit /b 0
)

set VENV_PY=.venv\Scripts\python.exe
if not exist %VENV_PY% (
    echo [ERROR] venv missing: %VENV_PY%
    echo         Run: uv sync
    exit /b 1
)

%VENV_PY% %*
exit /b %errorlevel%
```

### 3. `run-bot.ps1` (PowerShell — friendly with colored output)

```powershell
# run-bot.ps1
param([Parameter(ValueFromRemainingArguments=$true)]$Args)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$VenvPython = Join-Path $ScriptDir ".venv\Scripts\python.exe"

if (-not (Test-Path $VenvPython)) {
    Write-Host "[ERROR] venv missing: $VenvPython" -ForegroundColor Red
    Write-Host "        Run: uv sync" -ForegroundColor Yellow
    exit 1
}

if ($Args.Count -eq 0) {
    Write-Host "Trading Bot — Available commands:" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  .\run-bot.ps1 main.py backtest" -ForegroundColor White
    Write-Host "  .\run-bot.ps1 main.py --verbose run --mode=mock" -ForegroundColor White
    Write-Host "  .\run-bot.ps1 main.py --verbose run --mode=live --once" -ForegroundColor White
    Write-Host "  .\run-bot.ps1 main.py status" -ForegroundColor White
    exit 0
}

& $VenvPython @Args
exit $LASTEXITCODE
```

## Discovery: figure out what `python` actually points to

Before suggesting wrappers, run this to see the user's reality:

```bash
which python           # Linux/git-bash/macOS
# or
where.exe python       # Windows CMD
# or
Get-Command python     # PowerShell
```

If output on Windows looks like any of these, wrapper scripts are warranted:

- `/c/Users/somebody/AppData/Local/hermes/hermes-agent/venv/Scripts/python` (Hermes Agent venv)
- `/c/laragon/bin/python/python` (Laragon Python)
- `/c/tools/miniconda3/python.exe` (Conda)

Even one shadowed `python` is enough for the user to hit `ModuleNotFoundError` with their project's venv installation.

## Mistakes to avoid

- Pointing the wrapper at `python` or `py` — those still resolve via PATH. Hardcode the absolute venv path only.
- Forgetting `chmod +x run-bot.sh` after creation (won't work via direct invocation on *nix or git-bash without execute bit).
- Using `.venv/bin/python` path inside a Windows-targeted `.sh` script. git-bash on Windows accepts `./.venv/Scripts/python.exe` (forward-slash works), but `.venv/bin/python` will fail.
- Building only ONE wrapper — Windows users will be split between CMD, PowerShell, and git-bash. Ship all three so no user is left out.
- Verifying wrapper with `python main.py` instead of `./run-bot.sh main.py`. The test must use the wrapper itself, otherwise the original problem is untested.

## Sign-off test

In the project root after writing the wrappers:

```bash
chmod +x run-bot.sh
./run-bot.sh main.py --help
```

Expected: full Click help text rendered, NOT `ModuleNotFoundError`. If you see the module error, the wrapper venv path is wrong.

For cross-shell coverage:

```bash
cmd.exe /c "run-bot.bat main.py --help"        # CMD
powershell -File run-bot.ps1 main.py --help    # PowerShell
```

## Embedding a usage hint in your README

Add this snippet so users discover wrappers first, before wrestling with bare `python`:

```markdown
## ⚠️ If you have multiple Python tools on PATH

Hermes Agent, Laragon, Conda, etc. may shadow your `python`. Use the bundled wrapper:

- **git-bash on Windows / Linux / macOS**: `./run-bot.sh main.py ...`
- **Windows CMD**: `run-bot.bat main.py ...`
- **PowerShell**: `.\run-bot.ps1 main.py ...`

Wrappers hardcode `.venv/Scripts/python.exe` and avoid wrong-interpreter `ModuleNotFoundError` issues.
```

## When NOT to use

- The user's `python` already resolves to your project's venv (e.g., they run commands from inside an activated venv). Wrappers add no value here.
- You're shipping to a Linux server where only one Python is present. A `bash` shell launcher is still nice for ergonomic help text but not strictly required.
- The project is one-off and won't be redistributed — favor simplicity.
