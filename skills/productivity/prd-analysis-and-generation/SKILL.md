---
name: prd-analysis-and-generation
description: "Analyze existing PRD / product requirement documents, identify gaps, restructure into comprehensive sections, and generate v2.0 documents with integrated recommendations, business models, and relationship diagrams."
version: 1.0.0
author: Hermes Agent
tags: [prd, product-requirements, document-analysis, product-management, document-generation]
---

# PRD Analysis & Generation Skill

Analyze a user's existing PRD document, identify gaps and missing sections, and generate an enhanced v2.0 document.

## When to Use

Trigger when a user asks to:
- "Analisa PRD ini" / "Review my PRD"
- "Tambah rekomendasi fitur" / "Add feature recommendations"
- "Buatkan versi revisi" / "Generate revised version"
- "Analisa dokumen [nama] dan beri saran perbaikan"
- "Buat diagram alur hubungan antar dokumen"

## Workflow

### Phase 1: Read & Analyze

1. **Read the document** with `read_file`. If encoding fails (e.g. `utf-8` codec error), check with `file` command first, then read with the correct encoding.

   ```bash
   file <path>                    # Check actual encoding
   wc -l <path>                   # Check length
   ```

2. **Identify structural issues**:
   - Corrupted characters (due to wrong encoding)
   - Inconsistent formatting, typos, missing punctuation
   - Duplicate sections
   - Missing critical sections (business model, compliance, onboarding, testing)

3. **Identify conceptual gaps**:

   | Gap Area | What to Check |
   |----------|---------------|
   | **Business Model** | No pricing, no monetization strategy |
   | **Target Users** | Vague personas, no segmentation |
   | **Competitive Landscape** | No competitor analysis |
   | **Technical Architecture** | No diagram, no tech stack justification |
   | **Testing Strategy** | No test pyramid, no chaos/QA plan |
   | **Compliance** | No KYC/AML, GDPR, regulatory coverage |
   | **Risk** | No circuit breaker, kill switch, no risk matrix with scores |
   | **Onboarding** | No user journey, no education plan |
   | **Metrics** | Only vanity metrics, no business KPIs |
   | **Milestones** | No explicit go-live criteria per phase |

### Phase 2: Generate Recommendations

Categorize recommendations into thematic buckets (example from trading bot domain):

1. **Intelligence & AI** — market regime detection, recommender, sentiment, anomaly detection
2. **Risk Management Enhancement** — circuit breaker, kill switch, correlation analyzer, slippage predictor
3. **Analytics & Reporting** — advanced ratios (Sharpe, Sortino, Calmar), tax reports, trade journal
4. **Notifications & Alerting** — smart alert system, multi-channel orchestration, webhooks
5. **Strategy Builder Enhancement** — marketplace, versioning, multi-timeframe, templates
6. **User Experience** — paper trading, onboarding wizard, mobile app, TradingView integration
7. **Security & Compliance** — hardware wallet, withdrawal whitelist, session management, audit log
8. **Platform & Scalability** — multi-tenant, self-hosted, latency optimization, disaster recovery
9. **Testing & Reliability** — chaos engineering, regression suite, sandbox exchange
10. **Business & Monetization** — tiered subscription, performance fee, revenue sharing
11. **Education & Community** — built-in academy, community forum, risk profile quiz

### Phase 3: Generate v2.0 Document

Use `execute_code` or `write_file` with append (`"a"`) mode to build the full document in parts to avoid truncation:

```python
from hermes_tools import write_file, terminal

# Part 1: Sections 1-5
write_file(path, part1_content)

# Part 2: Sections 6-10 (append)
with open(path, "a", encoding="utf-8") as f:
    f.write(part2_content)
```

**Recommended document structure** (adapt to domain):

```
1. Overview & Vision (purpose, goals, key differentiators)
2. Target Users & Personas (segments + detailed personas)
3. Market Analysis & Competitive Landscape
4. Business Model & Monetization
5. Core Features
6. AI & Intelligence Layer (if applicable)
7. Technical Architecture (diagram + tech stack + data flow)
8. Data Model (entities, schema)
9. Non-Functional Requirements
10. Testing Strategy (pyramid + chaos + sandbox)
11. Compliance & Legal (KYC, regulatory coverage, privacy)
12. Onboarding & Education (quiz, paper trading, academy, gamification)
13. Milestones & Release Plan (sprints, go-live criteria)
14. Risks & Mitigations (scored matrix)
15. Success Metrics & KPIs (business + product + technical)
16. Appendix (terminology, references, assumptions)
17. Document Changelog
```

**Key integrations per section**:
- §4 (Business Model): Always include pricing tiers, revenue projection, and at least one alternative monetization
- §5 (Features): Categorize into pre-built vs custom for builder-type products
- §7 (Architecture): Include an ASCII/HTML architecture diagram
- §10 (Testing): Always include a pyramid diagram and chaos scenarios
- §12 (Onboarding): Always include gamification + academy table
- §14 (Risks): Always use scored matrix (Impact × Probability)

### Phase 4: Generate Relationship Diagram (Optional)

After the v2.0 document, create a **diagram alur hubungan antar dokumen** showing:
- PRD v2.0 as the central hub
- 4 domain boundaries: Technical (emerald), Business (amber), Compliance (rose), User/Ops (cyan)
- Each domain has 4 sub-documents with references to PRD sections
- Cross-domain connections as dashed lines

Use the `architecture-diagram` skill template for the HTML/SVG diagram.
Load `skill_view(name="prd-analysis-and-generation", file_path="references/relationship-diagram-pattern.md")` for exact layout coordinates and color mappings.

## Pitfalls to Avoid

- **ISO-8859 encoding**: If the original file has encoding issues, convert to UTF-8 explicitly. Corrupted characters (`?`, `??`, `�`) must be fixed.
- **Truncation from write_file**: Large PRDs exceed single `write_file` size limits. Use append mode with `"a"` in a Python script.
- **Duplicate sections**: Patch the file afterward to remove duplicates.
- **Non-standard timeframes**: In trading PRDs, standardize to industry timeframes (1m, 5m, 15m, 1H, 4H, 1D, 1W, 1M) — not "3 hours" or "15 hours".
- **Missing changelog**: Always add a changelog entry for v2.0 documenting what changed.
- **Over-recommending**: Prioritize recommendations into MVP/P1/P2/P3 buckets. Not everything should be v1.0.
- **Business model must exist**: A PRD without monetization strategy is incomplete for investor/startup contexts.
- **Stream response into multiple writes to avoid network truncation on long outputs**: When generating 50K+ char documents, break into 3-4 logical chunks via separate `write_file` (mode='w' → 'a') calls rather than one giant response. Long single responses risk network mid-stream drops, leaving the document incomplete.
- **`patch` tool can introduce indent-level corruption**: When the agent's source code being patched already has multi-line decorators / nested function bodies (Click commands, SQLAlchemy models), the fuzzy `patch` matcher may shift indentation by one level. Symptom: `IndentationError: unexpected indent`. Fix: prefer `execute_code` with `content.replace()` for surgical fixes, OR read the whole file then `write_file` the correct whole section.

## Verification

After generating:
1. Count all sections — should match target structure (e.g. 17 sections)
2. Check all 24+ features integrated via regex search
3. Verify no corrupt characters remaining
4. Verify the document is UTF-8 clean
5. Verify total file size matches expectations