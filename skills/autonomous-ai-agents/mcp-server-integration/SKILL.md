---
name: mcp-server-integration
description: Connect or debug MCP servers; fix Windows stdio hangs.
---

# MCP Server Integration

Connecting external MCP servers to Hermes Agent's native MCP client, and debugging stdio transport failures — especially C binaries on Windows.

## Add to Hermes

```bash
hermes mcp add <name> --command <cmd> --args <arg...>   # stdio
hermes mcp add <name> --url <endpoint>                  # HTTP
hermes mcp list
hermes mcp test <name>
```

- Config saved to `~/AppData/Local/hermes/config.yaml` under `mcp_servers:` (`command`/`args`/`env`, or `url`/`headers`).
- Tools register as `mcp_<server>_<tool>`; require new session (`/reset` or restart) to appear.
- Prereqs: `mcp` Python package in the Hermes venv (`pip install mcp`); npx/uvx for those servers.
- Non-interactive shells: `hermes mcp add` prompts "Enable all N tools? [Y/n/select]" — pipe `printf 'Y\n' |` into it.
- Installer scripts may also offer `--skip-config` — use it so the server doesn't overwrite configs for OTHER agents (Claude/Codex/etc).

## Debugging ladder (stdio hang)

Order matters — prove the server raw, then isolate with a known-good server:

1. **Raw JSON-RPC handshake** — proves the server itself works, no SDK:
```bash
printf '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"t","version":"1"}}}\n' | timeout 30 <server-cmd> 2>/dev/null | head -c 300
```
Any result (protocolVersion echoed back) = server OK. Test each protocolVersion (2024-10-07, 2025-03-26, 2025-06-18, 2025-11-25) — server may negotiate differently per version.

2. **SDK test** — minimal ClientSession init:
```python
import asyncio
from mcp import ClientSession, StdioServerParameters
from mcp.client.stdio import stdio_client
async def main():
    async with stdio_client(StdioServerParameters(command=..., args=...)) as (r, w):
        async with ClientSession(r, w) as s:
            init = await asyncio.wait_for(s.initialize(), timeout=15)
            print(init.serverInfo)
asyncio.run(main())
```

3. **Isolate** — raw works but SDK hangs:
   - Run a known-good Python stdio server (write the mini server from references/mini-server.md). SDK connects → problem is the C binary + Windows pipe interaction, not the SDK or server logic.
   - Pitfall: uvx servers can fail for unrelated reasons (dependency version mismatch inside uv cache, e.g. `cannot import name 'McpError'`). Don't let that mislead the diagnosis — always use a self-written mini server as the control.
   - Also test the mini server emitting CRLF: if it connects, CRLF alone is NOT the culprit — the pipe blocking is primary.

## Windows stdio pitfalls (C binaries)

mcp SDK 1.28.x + C binary on Windows pipes:

- **`p.stdout.read(4096)` blocks until buffer full or EOF** — a short response (~177 bytes) sits in the pipe until the child exits, then arrives too late. The SDK's internal reader shares this failure mode.
- **CRLF vs LF**: SDK splits on `\n` only; trailing `\r` corrupts JSON parse → message silently dropped → hang.
- **Direct `stdin=sys.stdin.buffer` drops data** under the SDK — stdin must be forwarded in a thread.

Fix: wrapper bridge (scripts/mcp-stdio-bridge.py) — thread-forwards stdin, pumps stdout line-wise (`for line in p.stdout` blocks until `\n`, matching newline-terminated JSON-RPC), strips CRLF. Register the bridge as the MCP command:

```bash
hermes mcp add <name> --command python --args "C:\path\mcp-stdio-bridge.py"
```

Edit the `EXE` constant in the bridge to point at the server binary.

## Installer fallback

PowerShell installers (install.ps1) can fail with HttpClient errors even when the network is fine. Fallback: manual curl + checksum verify:
```bash
curl -sL -o pkg.zip <release-url>/<archive>
curl -sL -o checksums.txt <release-url>/checksums.txt
grep "<archive>" checksums.txt && sha256sum pkg.zip   # digests must match
unzip -o pkg.zip -d <install-dir>
```

## Verification

After registering: `hermes mcp list` shows the server; `hermes mcp test <name>`; new session exposes `mcp_<name>_*` tools.

## Support files

- `scripts/mcp-stdio-bridge.py` — bridge wrapper for CRLF C binaries on Windows stdio.
- `references/mini-server.md` — minimal Python MCP stdio server used as isolation-test control.
