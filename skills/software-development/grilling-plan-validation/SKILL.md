---
name: grilling-plan-validation
description: Stress-test plans with grilling interview before coding.
---

# Grilling Plan Validation

**Purpose**: Stress-test a plan with hard questions — find gaps, edge cases, wrong assumptions, missing requirements — before any code is written. Like a senior engineer grilling a junior's design doc.

## When to Use
- After writing a plan (via `plan` skill or manually)
- Before starting implementation (spike or full build)
- When plan feels "too clean" — real systems have edge cases
- When handing off to another agent — ensure plan survives scrutiny

## The Grilling Process

### Phase 1: Assumption Audit
Ask for every explicit/implicit assumption:
- "What happens if X is null/empty/missing?"
- "What if the API returns 500 / times out / returns partial data?"
- "What if two users do this simultaneously?"
- "What if the DB has 10M rows, not 10?"
- "What if the user has no permission / wrong role / deleted account?"
- "What if config/env var is missing / wrong type?"
- "What if the external service changes schema tomorrow?"

### Phase 2: Edge Case Enumeration
Force-list at least 5 edge cases per feature:
- Empty state / zero / null / undefined
- Maximum bounds (max length, max file size, max recursion)
- Concurrency (race conditions, lost updates, double-submit)
- Partial failure (network hiccup mid-transaction, power loss)
- Malformed input (SQL injection, XSS, oversized payload, wrong encoding)
- Time zones / DST / leap seconds
- Idempotency (retry safety, duplicate webhook delivery)

### Phase 3: Requirements Traceability
Map each plan item → requirement source:
- User story / ticket / PRD line / verbal request / "vibe"
- If no traceable source → **cut it** (YAGNI)
- If source contradicts plan → **flag it**

### Phase 4: Implementation Reality Check
- "Which existing file/module does this touch?" (grep to verify)
- "Does this need migration? Seed data? Config change?"
- "What breaks if we deploy this halfway?"
- "Rollback plan? Feature flag? Canary?"
- "Observability: logs, metrics, traces — what tells us it's broken?"

### Phase 5: The "Kill Shot" Questions
- "What's the simplest thing that could possibly work? Why aren't we doing that?"
- "What would make you rewrite this in 3 months?"
- "If you had 1 hour, what would you cut?"
- "What's the one bug that will definitely ship?"

## Output Format
Produce a **Grilling Report** (markdown):
```markdown
# Grilling Report: [Plan Name]
Date: [ISO]
Plan: [link or path]

## Assumptions Challenged
| Assumption | Risk | Mitigation / Decision |
|------------|------|----------------------|

## Edge Cases Found
| Feature | Edge Case | Severity (P0-P3) | Handling |
|---------|-----------|------------------|----------|

## Untraceable Items (YAGNI Candidates)
| Plan Item | Reason to Cut |
|-----------|---------------|

## Implementation Risks
| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|

## Kill Shot Answers
- Simplest thing: ...
- 3-month rewrite trigger: ...
- 1-hour cut: ...
- Guaranteed bug: ...

## Verdict
[ ] PASS — Plan survives grilling, ready for spike/impl
[ ] CONDITIONAL — Fix items above first
[ ] FAIL — Fundamental flaw, rewrite plan
```

## Usage
```bash
# After creating a plan
hermes plan "Build user import from CSV"
# Then grill it
# (run this skill's validation manually or via agent)
```

## Integration with Other Skills
- **plan** — writes the plan, this validates it
- **spike** — run a spike on risky areas identified here
- **test-driven-development** — write tests for edge cases found here
- **systematic-debugging** — use Phase 4 risks as debugging hypotheses

## Ponytail Notes
- Deliberate simplification: no automated tooling — grilling is a thinking process, not a script
- Upgrade path: add LLM-assisted assumption extraction from plan text
- Ceiling: human judgment required for "kill shot" answers; AI can suggest but not decide