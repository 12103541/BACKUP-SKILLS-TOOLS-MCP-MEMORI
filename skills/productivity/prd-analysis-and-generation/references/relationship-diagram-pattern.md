# PRD Relationship Diagram Pattern

After generating a v2.0 PRD document, create a "Diagram Alur Hubungan Antar Dokumen" HTML file showing the full document ecosystem.

## Structure

```
Central Hub: PRD v2.0 (violet)
  4 Domain Boundaries (dashed boxes):
    1. Domain Teknis (emerald) — kiri atas
    2. Domain Bisnis & Monetisasi (amber) — kiri bawah
    3. Domain Compliance & Legal (rose) — kanan atas
    4. Domain User & Operations (cyan) — kanan bawah
```

## Color Mapping

| Domain | Fill | Stroke |
|--------|------|--------|
| PRD v2.0 (Central) | `rgba(76, 29, 149, 0.5)` | `#a78bfa` (violet) |
| Teknis | `rgba(6, 78, 59, 0.08)` border + `rgba(6, 78, 59, 0.4)` boxes | `#34d399` (emerald) |
| Bisnis | `rgba(120, 53, 15, 0.08)` border + `rgba(120, 53, 15, 0.3)` boxes | `#fbbf24` (amber) |
| Compliance | `rgba(136, 19, 55, 0.08)` border + `rgba(136, 19, 55, 0.4)` boxes | `#fb7185` (rose) |
| User/Ops | `rgba(8, 51, 68, 0.08)` border + `rgba(8, 51, 68, 0.4)` boxes | `#22d3ee` (cyan) |

## Layout Coordinates (1100x800 viewBox)

- Domain boundaries: x=20/760, y=20/400, width=320, height=350/370
- PRD Central: x=390, y=310, width=320, height=150
- Each domain: 4 boxes (280x65 each, 40px from top, 85px apart)

## Arrows

Use `<path>` with quadratic bezier curves (`Q`) to route around corners:
- PRD → Teknis: Exit left, curve up
- PRD → Bisnis: Exit left, curve down  
- PRD → Compliance: Exit right, curve up
- PRD → User/Ops: Exit right, curve down

Each arrow has an inline text label rotated 90 degrees showing the PRD section reference (e.g., "PRD §7 → Detail Teknis").

## Cross-Domain Connections

Use dashed gray arrows (`stroke-dasharray="4,4"`, `stroke="#94a3b8"`, `stroke-width="0.8"`):
- Billing ↔ Compliance (tax data)
- Testing ↔ Security (pen test)
- Architecture ↔ Compliance (encryption)
- UX/UI ↔ Mobile (design transfer)

## 6 Info Cards Below Diagram

| Card | Content |
|------|---------|
| 1 (violet) | PRD v2.0 — Central document, 17 sections, 24+ features |
| 2 (emerald) | Teknis — 4 derivative docs with PRD § references |
| 3 (amber) | Bisnis — BMC, GTM, competitor, billing |
| 4 (rose) | Compliance — KYC, GDPR, regulatory matrix, audit |
| 5 (cyan) | User/Ops — onboarding, UX/UI, mobile, SLA |
| 6 (orange) | Cross-domain connections listing |

## Template

Use the `architecture-diagram` skill template as base, then customize:
- Grid background: `#1e293b` at 40px
- Font: JetBrains Mono
- No JavaScript, pure CSS + SVG
- Legend in bottom-right corner