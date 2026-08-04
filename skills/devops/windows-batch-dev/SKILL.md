---
name: windows-batch-dev
description: Use when authoring or testing Windows .bat from git-bash.
---

# Windows Batch (.bat) Development from git-bash

Author/test Windows batch scripts when the terminal tool runs bash (git-bash/MSYS). cmd.exe is a different parser — errors look cryptic.

## Running a .bat from MSYS bash

```bash
export PATH="/c/Windows/System32:$PATH"      # netstat/findstr/taskkill must resolve INSIDE cmd
MSYS_NO_PATHCONV=1 cmd /c stop.bat < /dev/null
```

- `MSYS_NO_PATHCONV=1` stops MSYS rewriting `/c/...` args into `C:\...` (which makes cmd enter interactive mode and hang).
- Append `< /dev/null` so `pause` doesn't block forever; `printf '\n' |` also works to auto-answer prompts.
- Background server test: `terminal(background=true)` running `cmd /c start.bat`, then health-check the port, then kill the process session (python child dies with it).

## Authoring rules

- **Write .bat with write_file, never printf/heredoc** — bash `printf` mangles `\n` inside the file (path `C:\Windows\...` becomes two lines). write_file writes clean ASCII that cmd accepts.
- **Parentheses break parsing**: `echo [INFO] PID %%p (port 8090)...` INSIDE a `for /f ... do ( ... )` block → `... was unexpected at this time.` The nested `( )` confuses the block parser. Use `- port 8090` or quote instead.
- **`%errorlevel%` inside a for-block is expanded at parse time** (empty → `if ==0 ( ... )` syntax error). Fix: `setlocal enabledelayedexpansion` at top + `!errorlevel!` inside blocks. Outside blocks plain `%errorlevel%` is fine.
- **taskkill** uses single slash: `taskkill /PID 1234 /F`. `//PID` (MSYS double-slash) fails with "Invalid argument/option".
- `%%p` in .bat becomes `%p` at runtime — use `%%` when writing literal `for /f` vars in the file.

## Template

See `templates/start-port-service.bat` + `templates/stop-port-service.bat` — proven port-based start/stop pair (netstat find PID → taskkill /F → verify), includes the paren-bug fix and delayed-expansion pattern.

## Pitfalls

- `cmd /c` from MSYS without System32 in PATH: `for /f ... in ('netstat ...')` fails with "The system cannot find the file netstat" even though `netstat` works in bash — cmd's PATH is inherited but for /f child may lose it. Export PATH first.
- Testing one-liner for /f via printf is unreliable; write a temp .bat with write_file, run, delete.
- Error messages land AFTER visible output; grep with `head` on the tail (`... was unexpected` appears after echo banners).
- Batch parse errors kill the WHOLE file at parse time: banners print, then the
  error — no per-line traceback. Isolate the offending line by bisection:
  `sed 's/<suspect-construct>/<clean>/' stop.bat > _t.bat && cmd /c _t.bat` —
  strip one suspect construct per run (parenthesized echo text, nested if,
  `!var!` without delayed expansion) until the file runs; then apply the real
  fix and delete `_t.bat`.
- start.bat runs foreground: closing the window stops the server (python child
  dies with the cmd session). For a hidden background server use `start /min`.
