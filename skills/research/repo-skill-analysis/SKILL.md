---
name: repo-skill-analysis
description: Audit a repo's agent skills ('analisa skills <url>').
---

# Repo Skill Collection Analysis

Analyze any GitHub repo for agent skills ("coba analisa skills ini <url>"). Answer: does it have skills, what kind, how good, what's worth taking.

## Steps

1. Shallow clone: `cd /tmp && rm -rf <name> && git clone --depth 1 <url> <name> 2>&1 | tail -1`
2. **Gate check FIRST** — count skills before framing the analysis:
   - `find . -name "SKILL.md" -not -path "*/node_modules/*" | wc -l`
   - `find . -maxdepth 2 -name "AGENTS.md" -o -name "CLAUDE.md" -o -name ".cursorrules"`
   - Zero SKILL.md + zero AGENTS.md → NOT a skill collection (e.g. Flowise = 278 typed node definitions in TS, 0 skills). Adapt: analyze the node/tool-definition catalog instead, and state plainly "repo has no skills, closest analog is X". Never force a skill framing.
3. Inventory agent dirs: `.agents/skills`, `.claude/skills`, `.cursor/rules`, `.codex/prompts`, `.claude/commands`, `.github/prompts`. List each.
4. Metrics: skill count, `wc -l */SKILL.md | sort -rn | head`, `du -sh .` total, `du -sh */` per-skill (anomalies = vendored media assets, e.g. 39MB hyperframes-animation bloats clone).
5. Multi-target overlap: `ls .agents/skills > /tmp/a.txt; ls .claude/skills > /tmp/c.txt; comm -12` (needs sorted input), `comm -23` for only-in-primary, `diff -q <a>/SKILL.md <b>/SKILL.md` to test identical copies vs symlinks. Duplicated dirs = maintenance debt worth flagging.
6. Provenance: look for PROVENANCE.md (vendored from upstream: source URL, commit, tag, date) — strong quality marker; audit trail.
7. Quality signals: frontmatter consistency (name/description/license/metadata tags/compatibility), description length & self-contained trigger, routing tables, capability maps, scripts/ dirs (count: `find . -maxdepth 2 -type d -name scripts | wc -l`).
8. Taxonomy: group skills into domain families (animation/rendering, AI video-gen, media pipeline, TTS/STT, unique/original). Identify ecosystem-coupled vs standalone.
9. Relevance verdict for Hermes: standalone skills = importable; pipeline_defs/backlot/project-specific = coupled, skip. Name what's worth installing. Before declaring an ecosystem-coupled skill "skip, overlap", verify the runtime locally: `uv tool list`, `which <cli>`, and one real probe — the underlying tool may already be installed (browser-use repo's skill overlapped an already-working browser-harness install). Trust `uv tool list`, not the repo SKILL.md, for the actual CLI name (repo said `browser-use`, installed tool was `browser-harness`).
10. Output ultra-terse (user style: telegraphic, exact commands, no filler). Close with an offer: deep-dive one skill, or install a subset.

## Pitfalls

- Exclude node_modules in every find: `-not -path "*/node_modules/*"`.
- git clone progress spam on Windows — pipe to `tail -1`.
- `comm` requires sorted lists; `ls` output is already sorted.
- Check for skill-like dirs OUTSIDE the expected names too: `skills/meta`, `skills/pipelines`, `pipeline_defs/` (OpenMontage layered-routing pattern).
- A repo can be a skill collection AND a code platform (OpenMontage: 82 skills + Python backlot + ink-theater). Report both.
- A repo can be an MCP server / agent platform instead of a skill collection (0 SKILL.md, has a `hermes mcp add`-able binary, e.g. DeusData/codebase-memory-mcp). Verdict: not a skill collection; offer install via the mcp-server-integration skill (Windows C-binary stdio may need the CRLF/pipe bridge).
- CRLF line endings don't break `diff -q` on identical files.

## Support files

- `scripts/inventory.sh` — re-runnable inventory of a cloned repo (counts, sizes, frontmatter stats, dir overlap).
- `references/case-notes.md` — worked examples: OpenMontage (skill collection) vs Flowise (no skills, node platform).
