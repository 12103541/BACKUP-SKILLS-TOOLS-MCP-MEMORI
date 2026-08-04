# First-run transcripts (Windows 10, git-bash)

Real outputs captured during a fresh `browser-harness` install on Windows 10 with Chrome already running. Use these to recognize what "working despite FAIL" looks like.

## `tasklist` (Chrome present)

```
Image Name                     PID Session Name        Session#    Mem Usage
========================= ======== ================ =========== ============
chrome.exe                    7028 Console                    1    215.088 K
chrome.exe                    5972 Console                    1      9.548 K
... 15 more chrome.exe processes (renderer/sandbox children) ...
```

Single Chrome process tree verified by walking parent PIDs.

## `netstat -ano` (port 9222 listening)

```
TCP    127.0.0.1:9222         0.0.0.0:0              LISTENING       7028
TCP    127.0.0.1:9222         127.0.0.1:60763        ESTABLISHED     7028
TCP    127.0.0.1:60763        127.0.0.1:9222         ESTABLISHED     13108
... more ESTABLISHED harness↔Chrome pairs ...
```

PID 7028 = Chrome parent with remote debugging. PID 13108 = harness client. ESTABLISHED pairs are normal mid-session traffic, not pollution.

## `browser-harness --doctor` (misleading on first run)

```
browser-harness doctor
  platform          Windows 10
  python            3.11.15
  version           0.1.0 (git)
  latest release    (could not reach github)
  [ok  ] chrome running
  [FAIL] daemon alive — see install.md
  [FAIL] active browser connections — 0
  [FAIL] profile-use installed — optional: ...
  [FAIL] BROWSER_USE_API_KEY set — optional: needed only for cloud browsers / profile sync
```

Key observation: `chrome running` is `ok`, `daemon alive` is `FAIL`, yet a real call works (next section). Don't assume FAIL = broken.

## Real probe (proves attachment worked)

```bash
$ browser-harness <<'PY'
print(page_info())
PY
{'url': 'http://aplikasi-kantor.test:8080/sparepart/transaksi',
 'title': 'Riwayat Transaksi Stok — PT Exferia Putra Inovasi',
 'w': 1920, 'h': 1031, 'sx': 0, 'sy': 0, 'pw': 1920, 'ph': 1031}
```

Healthy response shape: dict with `url` (string), `title` (string), `w`/`h` (viewport), `sx`/`sy` (scroll), `pw`/`ph` (page). If you get a dict → ignore doctor FAILs.

## Why no "Allow remote debugging?" popup appeared

The user's Chrome had previously accepted at least one attach session (ESTABLISHED connection visible in netstat before the new probe). Chrome's per-attach popup only triggers under specific conditions (time elapsed, daemon restart, browser restart, new CDP session) that aren't fully characterized upstream. If the user reports "I expected a popup but didn't get one," check `netstat` for prior ESTABLISHED before assuming the setting was forgotten.
