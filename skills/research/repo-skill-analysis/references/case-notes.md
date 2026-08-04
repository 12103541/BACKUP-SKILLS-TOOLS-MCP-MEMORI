# Repo skill-analysis case notes (2026-07-31)

## OpenMontage (github.com/calesthio/OpenMontage) — skill collection archetype
- 82 SKILL.md, 19,407 lines, 54MB clone; AGPLv3, HeyGen-affiliated.
- 3-layer routing: AGENTS.md → AGENT_GUIDE.md (Rule Zero: all production through pipelines) → 13 pipeline YAMLs (pipeline_defs/) → stage-director skills (skills/pipelines/) → tool skills (.agents/skills/).
- Multi-target copies, byte-identical (verified diff -q): .agents/skills (82, fullest) ⊃ .claude/skills (47) ; .cursor/rules/openmontage.mdc ; .codex/prompts + .claude/commands + .github/prompts (3 prompts each: animated-drawing, backlot, ink-art).
- HyperFrames family: 12 skills vendored from github.com/heygen-com/hyperframes v0.7.17 commit 3351fb1a — PROVENANCE.md per family (source/commit/tag/date). hyperframes-animation = 39MB assets in git (clone bloat).
- Other families: threejs (10), gsap (8), remotion (3), manim (3), AI video-gen (comfyui, flux, seedance-2-0, kling-official, ltx2, heygen, faceswap), media (ffmpeg, video-*, tts/stt: elevenlabs/doubao/azure, lyria), unique (pose-library-design, character-rigging, synthetic-screen-recording, canvas-procedural-animation, media-use).
- media-use pattern worth stealing: one verb (resolve) → frozen local file + ledger manifest; search noise stays on disk.
- Overlap with user's Hermes install: hyperframes* + media-use already present there.

## Flowise (github.com/FlowiseAI/Flowise) — zero-skill platform archetype
- 0 SKILL.md, 0 AGENTS.md/CLAUDE.md/.cursorrules. Not a skill repo — gate check must catch this before analysis framing.
- Closest analog to "skills": 278 typed node definitions in packages/components/nodes/ (26 categories: 41 tools, 40 documentloaders, 30 chatmodels, 25 vectorstores, 16 retrievers, 16 agentflow, 12 sequentialagents, 12 chains, 9 agents) + 113 credentials + 8 agent nodes (ToolAgent, ReActAgentChat/LLM, XMLAgent, OpenAIAssistant, ...) + multiagents Supervisor/Worker.
- Node = TypeScript class implementing INode: label, name, version, description, type, category, icon, baseClasses, inputs: INodeParams[] (label/name/type/list/options). Code-as-skill, opposite of markdown instructions.
- packages/agentflow: embeddable React visualizer, DDD layout atoms/features/core/infrastructure.
- Lesson: for these, analyze the node/tool catalog + schema pattern, not skill quality.

## browser-use (github.com/browser-use/browser-use) — code platform + wrapper skills
- 7 SKILL.md = 6 unique skills (skills/: browser-use, cloud, open-source, qa, remote-browser, x402) + 1 byte-identical duplicate bundled at browser_use/skills/browser-use/ (ships inside the pip package — runtime gets its skill automatically).
- AGENTS.md solid: uv-only, Pydantic v2 for action schemas, structured ActionResult returns, "never replace model names", no example-file rule. references/ dirs under cloud, open-source, qa.
- Most skills ecosystem-bound: cloud/qa/remote-browser need BROWSER_USE_API_KEY + Browser Use Cloud; browser-use skill is a wrapper over the browser-harness runtime (CDP, BH_* env vars). x402 (pay-per-request via USDC wallet, no signup) is the only novel standalone pattern.
- Verdict case: DO NOT install into Hermes — user already has the underlying runtime + equivalent skill. Lesson: before declaring ecosystem-coupled skills "skip", check whether the runtime is already installed locally (uv tool list, which <cli>) and probe it once — browser-harness was present and functional, so the repo's browser-use skill was pure overlap.
- Naming trap: repo skill says CLI is `browser-use`; installed tool is `browser-harness`. CLI names differ from repo skill names — verify with uv tool list, don't trust the SKILL.md.
