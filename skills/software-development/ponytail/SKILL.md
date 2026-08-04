---
name: ponytail
description: "YAGNI enforcer — the best code is the code you never wrote. Prevent over-engineering, premature abstraction, and feature bloat. Cut before you add."
tags: [yagni, kiss, simplicity, code-review, refactoring, minimalism]
triggers:
  - adding new files, classes, or abstractions
  - creating "just in case" or "future-proof" code
  - building generic/reusable before concrete usage exists
  - writing helper functions with zero callers
  - adding config options no one asked for
  - refactoring that adds more code than it removes
---

# Ponytail — YAGNI Enforcer

> "The best code is the code you never wrote."

## Core Principles

1. **Build only what is requested NOW** — not what might be needed someday
2. **Delete > Simplify > Add** — always prefer removing code
3. **One caller = inline it** — no abstraction until 2nd concrete use appears
4. **No speculative generics** — Y, not X<Y> until X<Z> actually exists
5. **No config for things that don't change** — hardcode the one value, extract later

## Decision Gate (run mentally before ANY code change)

Ask these 3 questions before writing code:

```
Q1: Does the user/product SPECIFICALLY need this right now?
    YES → proceed
    NO  → STOP. You're building for a ghost future.

Q2: Can I solve this with LESS code?
    YES → do the smaller version
    NO  → proceed, but justify why.

Q3: Am I creating something with ZERO callers/users today?
    YES → STOP. Inline it. Delete it. It's dead weight.
    NO  → proceed.
```

If any answer is STOP, don't write the code. Ship what works.

## Anti-Patterns to Watch For

| Anti-Pattern | What It Looks Like | What To Do |
|---|---|---|
| Premature abstraction | `HelperService`, `BaseRepository`, `AbstractFactory` | Use the concrete thing directly |
| Speculative features | "What if we add X later?" | Don't. Add it when "later" becomes "now" |
| Config bloat | `config('app.something_enabled', false)` | Hardcode `false`. Change the line when needed |
| Dead utilities | `Str::slugify()` with zero callers | Delete. Git remembers if you need it back |
| Wrapper soup | Wrapping a wrapper wrapping Laravel | Call Laravel directly |
| Premature DRY | Extracting a 3-line repeated block into a class | Leave it. 3 lines repeated is fine |
| Over-engineered tests | Testing private internals, mocking everything | Test behavior, not implementation |
| Feature flags for features | `if (Feature::isEnabled('new_thing'))` | Build it or don't. Don't half-build it |

## Practical Rules

### In Laravel/Filament ERP context:

- **Model method**: Only add scopes/relationships when there's a real query using them
- **Resource fields**: Only add columns that appear on the actual table/form
- **Observer**: Only observe events that have real side effects now
- **Migration**: Only add columns the code actually reads/writes today
- **Config**: Only extract to config if the value is genuinely different per environment
- **Service class**: Only create when logic exceeds ~20 lines in Controller/Model
- **Enum**: Only create when a field has 3+ fixed values that actually exist in DB

### In general code:

- **Helper function**: Only extract when the same logic appears in 3+ places
- **Interface/contract**: Only when there are 2+ concrete implementations
- **Event/listener**: Only when the decoupling has a real consumer
- **Middleware**: Only when the logic is reused across routes
- **Trait**: Only when 3+ classes share the exact same behavior
- **Package/dependency**: Only when built-in solution doesn't exist

## Review Checklist

Before approving any code, verify:

- [ ] Every new file has at least 1 caller today (not "will have someday")
- [ ] No new config values without a real need to switch
- [ ] No new abstract/base classes without 2+ implementations
- [ ] Lines added < lines removed (for refactors)
- [ ] No "just in case" comments or dead code paths
- [ ] No new dependency that duplicates existing functionality

## The Ponytail Test

> "If I delete this code, does ANYTHING break today?"
>
> YES → keep it
> NO  → delete it. Git is your backup. Rebuild when needed.

## When YAGNI Does NOT Apply

YAGNI is about **features and abstraction**, not about:

- **Security** — always add proper auth, validation, escaping
- **Error handling** — always handle known failure modes
- **Database constraints** — foreign keys, unique indexes are never YAGNI
- **Logging** — essential for debugging in production
- **Input validation** — validate at system boundaries always

The rule: YAGNI applies to **optional complexity**, not **essential correctness**.
