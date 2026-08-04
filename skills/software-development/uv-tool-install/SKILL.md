---
name: uv-tool-install
description: Install Python tools via `uv tool install` on Windows + git-bash (MSYS) without hitting the PowerShell-only PATH warning. Covers editable installs, persistent PATH, and the verification command.
---

# uv tool install on Windows + git-bash

`uv tool install` works fine on Windows, but its post-install PATH reminder assumes PowerShell. Under git-bash / MSYS the suggested command (`$env:PATH = ...`) does nothing in your shell. This skill is the working substitute.

Use this skill whenever the task is: install a Python CLI published as a tool (PyPI or editable local checkout) and the host is Windows + git-bash.

## Why the warning misleads

After a successful install, uv prints:

```
warning: `C:\Users\<you>\.local\bin` is not on your PATH. To use installed tools,
run `$env:PATH = "C:\Users\<you>\.local\bin;$env:PATH"` or `uv tool update-shell`.
```

`$env:PATH = ...` is PowerShell syntax. In git-bash this fails silently (or with a syntax error depending on quoting). `uv tool update-shell` *does* work — it appends the export to `~/.bashrc` — but only after the install is done, so you still hit the warning on the install that needs the PATH.

Fix: append the export yourself to all three of the standard login files. Idempotent — safe to re-run.

## Procedure

Run from the project root (or anywhere — last `cd` matters only for `-e .` editable installs):

```bash
# 1. Install globally (non-editable, from PyPI)
uv tool install <package-name>

# OR editable (for a project you've cloned)
uv tool install -e .

# 2. Persist PATH for all future git-bash sessions (idempotent)
PERSIST_LINE='export PATH="$HOME/.local/bin:$PATH"'
for f in "$HOME/.bashrc" "$HOME/.bash_profile" "$HOME/.profile"; do
  touch "$f"
  grep -qF '.local/bin' "$f" || echo "$PERSIST_LINE" >> "$f"
done

# 3. Verify in a NEW shell (current shell needs source ~/.bashrc, new terminal doesn't)
"$HOME/.local/bin/<tool>.exe" --version   # Windows → always ends in .exe
PATH="$HOME/.local/bin:$PATH" command -v <tool>
```

The `.exe` suffix on Windows is not optional — quoting `browser-harness.exe` vs `browser-harness` matters. `command -v` checks only the unqualified name on PATH.

## Editable install repos: prefer a stable path

For `-e .` (editable) installs, clone into a durable location like `~/Developer/<name>`, not `/tmp`. Otherwise the install breaks the next time `/tmp` is cleaned. `install.md` from browser-harness states this preference verbatim; treat it as the default rule for any editable uv-tool install.

## Installing into an existing venv that has no pip

Some bundled venvs ship without pip (Hermes's own venv at `<hermes-home>/hermes-agent/venv` is stripped for install size — `python -m pip` fails with `No module named pip`). To add packages there, activate the venv for uv via env vars — do NOT pass `--python`:

```bash
# WRONG — uv can't resolve the venv from a bare interpreter path:
uv pip install --python "/c/Users/<you>/AppData/Local/hermes/hermes-agent/venv/Scripts/python.exe" <pkg>
# → error: No virtual environment or system Python installation found for path ...

# RIGHT — export VIRTUAL_ENV + PATH so uv treats it as the active venv:
export VIRTUAL_ENV="/c/Users/<you>/AppData/Local/hermes/hermes-agent/venv"
export PATH="$VIRTUAL_ENV/Scripts:$PATH"
"/c/Users/<you>/AppData/Local/hermes/bin/uv" pip install <pkg>
```

Verify with the venv's own interpreter:
`"$VIRTUAL_ENV/Scripts/python" -c "import <pkg>; print(<pkg>.__version__)"`

Pitfall: `uv pip install --python <venv-python.exe>` looks for a venv *around* that interpreter path and fails for bundled venvs whose `pyvenv.cfg` points at a uv-managed Python home. The `VIRTUAL_ENV` export is the reliable trigger (worked for faster-whisper into Hermes' venv, 2026-07-31).

## Verification matrix

| Check                              | Command                                                   | Pass =                  |
| ---------------------------------- | --------------------------------------------------------- | ----------------------- |
| Binary present                     | `ls "$HOME/.local/bin/<tool>.exe"`                        | file exists             |
| Binary runs                        | `"$HOME/.local/bin/<tool>.exe" --version`                 | prints version          |
| PATH wired for current shell       | `PATH="$HOME/.local/bin:$PATH" command -v <tool>`         | resolved path           |
| PATH wired for future shells       | grep `.local/bin` `~/.bashrc`                             | one match               |

If 1–3 pass but 4 fails, you forgot step 2 — re-run it.

## Pitfalls

- **The warning is cosmetic on success.** Don't roll back the install because of it. Confirm the binary actually exists at `~/.local/bin/` first.
- **Don't trust `uv tool update-shell` blindly on Windows.** It writes to `~/.bashrc` for MSYS but may not detect Cygwin or other shells. The explicit `grep -qF` / `echo >>` loop above is portable.
- **An editable install can be "cleanly" cloned from a workdir under a path with no spaces or unicode.** Spaces in `$HOME` paths (uncommon but possible) have bitten `uv` resolver bug reports; if the install mysteriously fails to resolve, move the repo to `~/Developer/`.
- **`$env:FOO` references in install instructions are PowerShell, not bash.** When copying install steps from upstream READMEs that target Windows, mentally translate `$env:NAME` to `${NAME}` (or `"$NAME"`).

- **`uv tool upgrade` on an editable install does NOT bump the package version.** The version is pinned by the local clone's `pyproject.toml` (`uv tool install -e .`), so upgrade only refreshes dependencies — the tool stays at the old version while printing "update available" notices. Confirm with `grep editable "$HOME/AppData/Roaming/uv/tools/<tool>/uv-receipt.toml"`. If the clone is a personal fork diverged from upstream (`git pull --ff-only` fails), upgrading means merging the fork — decide deliberately; skipping is often correct (worked: browser-harness 0.1.0 fork, 2026-08-01).
- **Mid-upgrade, uv can silently remove the `~/.local/bin/<tool>.exe` shim** (upgrade half-succeeded, shim gone, tool still callable via full path). Restore: `ln -sf "$HOME/AppData/Roaming/uv/tools/<tool>/Scripts/<tool>.exe" "$HOME/.local/bin/<tool>.exe"` then `hash -r; <tool> --version`.

## When NOT to use this skill

- Pure POSIX (Linux/macOS): `uv tool update-shell` just works; no need for the manual loop.
- Pure PowerShell on Windows: use the `$env:PATH = ...` line uv prints; don't fight it.
- Installing into a project venv (not a global tool): use `uv add <pkg>` or `pip install` instead — `-e .` and `uv tool install -e .` are not the same thing. If that venv has no pip (stripped bundled venv), use the VIRTUAL_ENV technique in the venv section above.
