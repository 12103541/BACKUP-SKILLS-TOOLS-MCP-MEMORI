---
name: ai-feature-integration
description: "Add/remove LLM features in web apps safely."
---

# AI Feature Integration (LLM in existing web apps)

Use when: adding, evaluating, or removing AI/LLM features in an existing app (esp. Laravel + Filament ERP). Covers the decision framework, integration pitfalls, and clean teardown.

## Deterministic-first principle

~90% of "AI agent" requests are solvable with SQL/rules — LLM is worse at them (hallucination, latency, cost).
- Process monitoring / stuck-workflow detection = queries, NOT LLM
- Search / retrieval = DB + index; LLM only translates natural language → filter params
- LLM is only for: summarization, drafting, comparison narratives

Layered architecture (present to user as 3 layers):
- L1: deterministic monitors (cron, zero LLM) — build first, delivers most value, zero risk
- L2: async read-only LLM (queue worker), never in request path
- L3: chat assistant — only after L1+L2 proven

Iron rules:
- AI never writes DB — read-only + human confirmation. Auto-action = audit risk.
- AI never in request path (page load / save / validation).
- Results respect RBAC — don't leak cross-department data into AI responses.

## Pitfalls when wiring LLM into Laravel/Filament

1. **Availability ≠ validity**: `llmAvailable()` (key-presence check) lies when key is 401 or response unparseable — code silently falls back to template while UI claims AI success. Use a post-parse success flag: `lastUsedLlm = true` ONLY after JSON parse yields items. Toast/badge read the flag, not the presence check.
2. **max_tokens truncation**: long JSON output gets cut → `json_decode` fails → silent fallback. Always send `max_tokens` (e.g. 3000) on chat/completions calls.
3. **Honest provenance**: mark source as 'ai' only when LLM actually produced items; template fallback = 'template' badge. Mislabeling poisons trust + list filters.
4. **Livewire modal action state handoff**: separate `->action()` closures inside ONE modal run in SEPARATE Livewire requests — a service instance created in the "generate" action does not exist in the "apply" action. Share state via form fields (`Hidden::make` + `Set`/`Get`), never instance properties.
5. **Structured output parsing**: prompt "output ONLY valid JSON, no code fence"; regex-extract `{...}` (handle markdown fences); normalize numbers (ID decimal `37,5` vs thousand-separator `1,000`); normalize satuan aliases (`pcs→buah`, `bln→bulan`, `m²→m2`); validate volume > 0 (drop zero rows); per-field fallback to local template values.
6. **Timeout**: Laravel default Http 30s hangs modals. Set `->timeout(15)->connectTimeout(5)`; fallback must be fast.
7. **Chat-mode UX**: when user wants "type a command, AI does everything", make jenis/volume OPTIONAL (required only when prompt empty) and make the LLM prompt adaptive — no koefisien injection, "TENTUKAN dari instruksi user" when fields are blank.

## Removing an AI feature cleanly

1. grep all references: `grep -rn "ServiceName" app/ routes/ resources/`
2. Delete dead service files only after zero refs remain
3. Delete DB rows: `CompanySetting::where('key','like','llm%')->orWhere('key','like','%api_key')...->delete()`; check leftover `group='ai'` rows
4. Prune sensitive-key lists (e.g. `SENSITIVE_KEYS` const)
5. `php -l` every touched file
6. Browser-verify affected pages (create form, settings page); `php artisan schedule:list` if scheduling touched

## Windows + PHP editing pitfall (CRLF / escaping)

- patch tool can double-escape `\n` in PHP strings (renders `\\n` = literal backslash-n) → parse error. `php -l` after EVERY patch; grep the line to check byte-level.
- Project files are CRLF: Python `str.index()` on LF-normalized assumptions fails. Pattern: read `.replace('\r\n','\n')` → edit with whitespace-tolerant regex → write with `newline='\n'`.
- After Python block-deletion, check for orphaned structural lines (stray `}`, removed `$field = match(` header) — deletions often eat structural lines too.

## References

- `references/deterministic-workflow-monitor.md` — L1 monitor pattern: SQL checks, notification dedup, hourly cron, verification recipe.
