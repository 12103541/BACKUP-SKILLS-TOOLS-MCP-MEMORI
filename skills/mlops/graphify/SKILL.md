---
name: graphify
description: "Codebase→knowledge graph via graphify. Query, path, explain."
---

# graphify

Turn any folder of code, docs, papers, images into a navigable knowledge graph with community detection, interactive HTML, GraphRAG-ready JSON, and plain-language GRAPH_REPORT.md.

## Prerequisites

Install once: `pip install graphifyy`

Binary: `/c/Users/62897/AppData/Local/Python/pythoncore-3.14-64/Scripts/graphify`

## Fast Path — Existing Graph

If `graphify-out/graph.json` exists AND user's question is about the codebase (not a rebuild command):

```bash
graphify query "<question>"
graphify path "<A>" "<B>"
graphify explain "<concept>"
```

## Full Pipeline

```bash
graphify extract <path>              # full AST extraction
graphify extract <path> --code-only  # AST only, no LLM needed
graphify extract <path> --mode deep  # aggressive INFERRED edges
graphify extract <path> --backend gemini  # semantic via LLM
graphify update .                    # incremental re-extract
graphify add <url>                   # fetch URL, update graph
```

## Subcommands

| Subcommand | Purpose |
|-----------|---------|
| `query "<q>" [--dfs] [--budget N]` | BFS/DFS traversal |
| `path "A" "B"` | Shortest path between nodes |
| `explain "X"` | Node explanation |
| `god-nodes [--top N]` | Hub nodes |
| `affected "X"` | Impact analysis |
| `tree` | D3 hierarchical tree HTML |
| `export callflow-html` | Mermaid architecture HTML |
| `hook install` | Post-commit hook |

## Output

```
graphify-out/
├── graph.html          # interactive D3 graph
├── graph.json          # persistent queryable graph
├── GRAPH_REPORT.md     # god nodes, connections
├── obsidian/           # obsidian vault (--obsidian)
├── wiki/               # agent wiki (--wiki)
└── cache/
```

## References

Detailed docs in `graphify/skills/agents/references/`:
- `extraction-spec.md` — extraction subagent prompt
- `query.md` — query/path/explain flow
- `update.md` — incremental update
- `github-and-merge.md` — clone + merge
- `add-watch.md` — add URL / watch
- `exports.md` — SVG/GraphML/Neo4j
- `hooks.md` — git hooks
