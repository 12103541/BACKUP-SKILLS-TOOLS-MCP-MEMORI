import subprocess, sys, threading, os

# Bridge for C binaries that emit CRLF on Windows stdio MCP servers.
# The mcp Python SDK splits stdout on \n only (trailing \r breaks JSON parse)
# and its pipe reader blocks until buffer-full/EOF. This bridge:
#   - forwards stdin to the child in a thread (direct stdin=sys.stdin.buffer
#     drops data under the SDK on Windows)
#   - pumps child stdout line-wise (binary readline blocks until \n, matching
#     newline-terminated JSON-RPC responses)
#   - strips CRLF so the SDK parses cleanly
# Usage: register the bridge as the MCP command, NOT the binary:
#   hermes mcp add <name> --command python --args "C:\path\mcp-stdio-bridge.py"

EXE = r"C:\path\to\server.exe"  # EDIT: absolute path to the MCP server binary

p = subprocess.Popen([EXE] + sys.argv[1:], stdin=subprocess.PIPE,
                     stdout=subprocess.PIPE, stderr=sys.stderr)


def pump():
    for line in p.stdout:  # binary readline: blocks until \n
        if line.endswith(b"\r\n"):
            line = line[:-2]
        elif line.endswith(b"\n"):
            line = line[:-1]
        os.write(1, line + b"\n")


threading.Thread(target=pump, daemon=True).start()

while True:
    data = os.read(0, 4096)
    if not data:
        break
    p.stdin.write(data)
    p.stdin.flush()
p.stdin.close()
p.wait()
