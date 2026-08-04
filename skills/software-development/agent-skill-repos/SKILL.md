---
name: agent-skill-repos
description: Analyze a repo's agent skills, install into Hermes.
---

# Agent Skill Repos — analyze & install

Three repo archetypes exist; identify which BEFORE deep-diving:

| Archetype | Example | Shape |
|---|---|---|
| Pure markdown skills | OpenMontage | 50-100 SKILL.md + references/, multi-target dirs, PROVENANCE.md for vendored skills |
| Executable node definitions | Flowise | 0 SKILL.md; "skills" = typed TS node classes (INode: label/name/type/category/inputs) |
| Runtime skill system | DeerFlow | skills/ dir + loader, tests, contracts, evals — skills are runtime packages, not static docs |

## Analysis workflow

1. Shallow clone: `git clone --depth 1 <url>` (big skill repos carry assets — OpenMontage was 2023 files / 54MB).
2. Locate skill dirs — check ALL agent conventions:
   `.agents/skills`, `.claude/skills`, `.cursor/rules`, `.codex/prompts`, `.github/prompts`, root `skills/`, `pipeline_defs/`.
3. Read AGENTS.md / CLAUDE.md FIRST — it reveals orchestration (routing layers, mandatory guides, Rule Zero) that markdown-only inspection misses. CLAUDE.md is often just `@AGENTS.md` import.
4. Inventory: SKILL.md count, `wc -l */SKILL.md` (deepest = richest), du -sh, dirs with scripts/.
5. Frontmatter validity check:
   ```bash
   for d in */; do head -3 "$d/SKILL.md" | grep -q "^name:" && head -6 "$d/SKILL.md" | grep -q "^description:" || echo "BAD: $d"; done
   ```
6. Runtime dependency scan — grep SKILL.md for `allowed-tools`, `slash_command`, `review_skill_package`, MCP refs, API-key providers. Skills bound to the host runtime (DeerFlow's review_skill_package, OpenMontage's pipeline_defs) are only partially useful standalone.
7. Multi-target duplication: `.agents/` and `.claude/` often hold copies — confirm with `diff -q a b && echo identical`. Note it as maintenance smell.
8. Vendor provenance: PROVENANCE.md files record upstream source + commit + date — good practice worth copying for your own vendored skills.

## Install into Hermes

```bash
DEST="$HOME/AppData/Local/hermes/skills/<category>"
mkdir -p "$DEST" && cp -r <repo>/skills/public/* "$DEST/"
```

- Verify every SKILL.md has `name:` + `description:` frontmatter (step 5) — Hermes parser needs both.
- Confirm one install with `skill_view(name='<category>/<skill>')` — checks linked_files, required env, readiness.
- Active after `/reload-skills` or new session.
- Report per-skill standalone status: ready / needs API key / bound to source runtime.

## Pitfalls

- **search_files fails on MSYS /tmp paths** ("IO error ... cannot find the path"): ripgrep is a native Windows binary and can't resolve `/tmp/...`. Convert first: `cygpath -w /tmp/foo` and pass the Windows path. Terminal (bash) accepts both forms fine.
- Skill dir named differently from frontmatter `name:` (e.g. `vercel-deploy-claimable` → name `vercel-deploy`) — use frontmatter name for skill_view.
- `hermes skills install` is for hub/URL installs; bulk repo installs go via direct copy to the skills dir, then `/reload-skills`.
- Duplicate skill names across categories cause `skill_view` "Ambiguous skill name" errors — pass full `category/name` path.

## Reuse ideas worth stealing (not installing)

- One SKILL.md + N references/ per variant (chart-visualization: 26 generate_*.md) — good shape for skill families.
- Meta-skills: skill-creator (draft→test→eval→rewrite loop), skill-reviewer (read-only audit, allowed-tools frontmatter).
- Onboarding skill producing SOUL.md (DeerFlow bootstrap) — mirrors Hermes user-profile concept.
