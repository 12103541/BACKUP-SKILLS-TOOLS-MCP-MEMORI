# Minimal Python MCP stdio server (isolation-test control)

Proves the mcp SDK can connect to a Python stdio server on the host — the
control in the stdio-hang debugging ladder. Copy to a `.py` file and run via:

```python
StdioServerParameters(command='python', args=['C:/path/mini_mcp.py'])
```

If this connects while the target C binary hangs, the problem is the
C-binary/Windows-pipe interaction, not the SDK or the server's logic.

```python
import sys, json
for line in sys.stdin:
    line = line.strip()
    if not line:
        continue
    msg = json.loads(line)
    if msg.get("method") == "initialize":
        sys.stdout.write(json.dumps({"jsonrpc": "2.0", "id": msg["id"], "result": {
            "protocolVersion": "2025-11-25",
            "serverInfo": {"name": "mini", "version": "1"},
            "capabilities": {"tools": {"listChanged": False}}
        }}) + "\n")
        sys.stdout.flush()
    elif msg.get("method") == "tools/list":
        sys.stdout.write(json.dumps({"jsonrpc": "2.0", "id": msg["id"], "result": {
            "tools": [{"name": "ping", "description": "p",
                       "inputSchema": {"type": "object", "properties": {}}}]
        }}) + "\n")
        sys.stdout.flush()
    elif msg.get("method") == "notifications/initialized":
        pass
```

Variant: change the two `+ "\n"` to `+ "\r\n"` to test the CRLF hypothesis.
If the CRLF variant ALSO connects, CRLF alone is not the culprit — the
Windows pipe read-blocking is primary (see SKILL.md pitfalls).
