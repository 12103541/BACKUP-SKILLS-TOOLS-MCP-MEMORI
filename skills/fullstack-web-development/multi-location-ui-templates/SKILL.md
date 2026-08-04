---
name: multi-location-ui-templates
description: Per-location UI theming via DB, API, admin, CSS.
trigger: Multi-tenant displays needing per-location visual config managed via admin.
---

# Multi-Location UI Template System

## Overview
Pattern for systems where each location (rest area, branch, site) has its own public display (VMS, digital signage, dashboard) with configurable theming: header colors, pill colors, fonts, logo, footer visibility, scale behavior. Configuration stored per-location in DB, managed via admin UI, exposed via API, consumed by public pages via CSS variables.

## Architecture

### Database
```sql
CREATE TABLE player_template (
    id_lokasi     INTEGER PRIMARY KEY,
    config_json   TEXT NOT NULL DEFAULT '{}',
    diperbarui_pada TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (id_lokasi) REFERENCES lokasi(id)
);
```
- `id_lokasi` = FK to locations table (PK here, one config per location)
- `config_json` = flexible JSON, extensible without migrations
- Sensible defaults in code (never null in API response)

### Backend (FastAPI/Python)
- `GET /api/player-template` → returns merged config (defaults + DB override) for active/queried location
- `PUT /api/player-template` → saves config (admin only), accepts `lokasi_id` override for cross-location editing
- `get_player_template(lokasi_id)` helper merges defaults with stored JSON
- `get_pengaturan()` includes `player_template` for public page consumption

### Admin UI
- Single form with: color pickers (header, title, pill bg/text), font selector, checkboxes (footer, upscale)
- Live preview via CSS variable injection on `:root`
- Loads on tab activate, saves via PUT, shows toast notification

### Public/Display Pages
- CSS defines `--pt-*` variables with defaults
- `public.js` / `player.html` fetch template on load/render
- Apply to `:root.style.setProperty('--pt-xxx', value)`
- Components use `var(--pt-xxx, fallback)` in CSS
- Scale logic respects `upscale` config (default: never upscale, transform-origin 0 0)

### Key Config Fields
| Field | CSS Var | Default | Purpose |
|-------|---------|---------|---------|
| `warna_header` | `--pt-header-bg` | `#000000` | Header bar background |
| `warna_judul` | `--pt-judul` | `#ffd600` | Title text + P box |
| `warna_pill_bg` | `--pt-pill-bg` | `#ffffff` | Location pill background |
| `warna_pill_teks` | `--pt-pill-teks` | `#0066ff` | Location pill text |
| `font_family` | `--pt-font` | `Arial, sans-serif` | Global font |
| `show_footer` | `--pt-footer-hide` | `true` | Footer visibility |
| `upscale` | `--pt-upscale` | `false` | Allow upscaling (VMS) |

### Extensible Fields (in defaults, not yet in UI)
- `header_height_px`, `grid_gap_px`, `grid_padding_px`
- `kartu_gap_px`, `label_padding_px`, `area_data_gap_px`
- `ikon_size_px`, `angka_font_px`, `caption_font_px`, `sub_slot_font_px`, `footer_font_px`

## Implementation Checklist
- [ ] DB migration adds `player_template` table
- [ ] Logic: `get_player_template()` + `set_player_template()`
- [ ] API: GET/PUT `/api/player-template` with auth
- [ ] Admin: form with color pickers, font, toggles
- [ ] CSS: `:root` variables + component usage
- [ ] Public JS: fetch + apply to `:root` on render
- [ ] Player HTML: fetch on load + apply + scale logic
- [ ] Defaults in code match CSS defaults

## Pitfalls
1. **Module caching**: After logic.py changes, server restart required (Python imports are cached). Use background process with notify_on_complete for restarts.
2. **Default merging**: Always merge user config INTO defaults (not replace) so new fields get defaults automatically.
3. **Lokasi scope**: API uses active location by default; admin can override via `lokasi_id` in body. Public pages must pass `?lokasi=N` or use active.
4. **CSS var naming**: Use consistent `--pt-` prefix. Apply on `:root` (not body) for iframe inheritance in player.
5. **Scale logic**: Player must respect `upscale` config. Default: `scale = min(1, vw/w, vh/h)` (never upscale). When `upscale=true`: `scale = min(vw/w, vh/h)`.
6. **Logo handling**: Logo is separate (stored in `pengaturan_app` as `logo_papan_{lid}`), not in template JSON. Public page applies both.

## References
- `references/implementation-log.md` — session log of this pattern's implementation
- `references/schema.sql` — SQL for player_template table
- `references/api-contract.md` — API request/response shapes
- `references/css-variables.md` — CSS variable definitions and usage