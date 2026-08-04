# Player Template: Per-Location Dynamic Theming

## Problem
Each VMS display (one per rest area) needs independent visual configuration:
- Header background color
- Title/yellow box color  
- Location pill background & text color
- Footer visibility
- Upscale behavior (fit vs fill)
- Font family
- Future: spacing, sizing, gap, font sizes per component

Changes must apply at runtime without rebuild.

## Solution
1. **DB Table**: `player_template` (id_lokasi PK, config_json TEXT)
2. **Logic Layer**: `get_player_template(lokasi_id)`, `set_player_template(config, lokasi_id)`
3. **API**: `GET /api/player-template`, `PUT /api/player-template` (admin)
4. **CSS Variables**: `:root` defaults + JS `setProperty('--pt-*', value)`
5. **Admin UI**: Color pickers, checkboxes, select → form submit
6. **Runtime**: Player page loads template on every render; public page polls every 5s

## Database Schema
```sql
CREATE TABLE IF NOT EXISTS player_template (
    id_lokasi     INTEGER PRIMARY KEY,
    config_json   TEXT NOT NULL DEFAULT '{}',
    diperbarui_pada TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (id_lokasi) REFERENCES lokasi(id)
);
```

## Default Config (logic.py)
```python
default = {
    "warna_header": "#000000",
    "warna_judul": "#ffd600",
    "warna_pill_bg": "#ffffff",
    "warna_pill_teks": "#0066ff",
    "show_footer": True,
    "upscale": False,
    "font_family": "Arial, sans-serif",
    "header_height_px": 140,
    "grid_gap_px": 26,
    "grid_padding_px": [56, 34, 30],
    "kartu_gap_px": 8,
    "label_padding_px": [8, 20],
    "area_data_gap_px": 14,
    "ikon_size_px": 72,
    "angka_font_px": 110,
    "caption_font_px": 17,
    "sub_slot_font_px": 13,
    "footer_font_px": 13,
}
```

## CSS Variables (style.css)
```css
:root {
  --pt-header-bg: #000000;
  --pt-judul: #ffd600;
  --pt-pill-bg: #ffffff;
  --pt-pill-teks: #0066ff;
  --pt-font: "Arial, sans-serif";
  --pt-upscale: 0;
  --pt-footer-hide: 0;
}

.header-tol { background: var(--pt-header-bg, #000); }
.header-tol .kotak-p { background: var(--pt-judul, #ffd600); }
.header-tol h1 { color: var(--pt-judul, #ffd600); font-family: var(--pt-font, "Arial, sans-serif"); }
.publik .header-tol .pill-lokasi { 
  background: var(--pt-pill-bg, #fff); 
  color: var(--pt-pill-teks, #0066ff); 
}

.footer-papan:has(var(--pt-footer-hide): 1) { display: none; }
```

## JS Application (public.js / player.html)
```javascript
// Apply template config to :root
function applyTemplateCSS() {
  const root = document.documentElement;
  root.style.setProperty("--pt-header-bg", cfg.warna_header || "#000000");
  root.style.setProperty("--pt-judul", cfg.warna_judul || "#ffd600");
  root.style.setProperty("--pt-pill-bg", cfg.warna_pill_bg || "#ffffff");
  root.style.setProperty("--pt-pill-teks", cfg.warna_pill_teks || "#0066ff");
  root.style.setProperty("--pt-font", cfg.font_family || "Arial, sans-serif");
  root.style.setProperty("--pt-upscale", cfg.upscale ? "1" : "0");
  if (!cfg.show_footer) {
    root.style.setProperty("--pt-footer-hide", "1");
  } else {
    root.style.removeProperty("--pt-footer-hide");
  }
}

// Scale with upscale toggle
function skala() {
  const allowUpscale = PLAYER_CFG.upscale === true;
  const sk = allowUpscale
    ? Math.min(window.innerWidth / DESIGN_W, window.innerHeight / DESIGN_H)
    : Math.min(1, window.innerWidth / DESIGN_W, window.innerHeight / DESIGN_H);
  // ...
}
```

## Admin Form (admin.html)
```html
<form id="formPlayerTemplate">
  <div class="grup-form">
    <label>Warna Header<input id="ptWarnaHeader" type="color" value="#000000"></label>
  </div>
  <div class="grup-form">
    <label>Warna Judul & Kotak P<input id="ptWarnaJudul" type="color" value="#ffd600"></label>
  </div>
  <div class="grup-form">
    <label>Warna Pill Background<input id="ptWarnaPill" type="color" value="#ffffff"></label>
  </div>
  <div class="grup-form">
    <label>Warna Pill Teks<input id="ptWarnaPillTeks" type="color" value="#0066ff"></label>
  </div>
  <div class="grup-form">
    <input type="checkbox" id="ptShowFooter" checked>
    <label for="ptShowFooter">Tampilkan Footer</label>
  </div>
  <div class="grup-form">
    <input type="checkbox" id="ptUpscale">
    <label for="ptUpscale">Izinkan Upscale (fullscreen fill)</label>
  </div>
  <div class="grup-form">
    <label>Font Family<select id="ptFontFamily">
      <option value="Arial, sans-serif">Arial</option>
      <option value="Roboto, sans-serif">Roboto</option>
      <option value="Inter, sans-serif">Inter</option>
    </select></label>
  </div>
  <button type="submit">💾 Simpan Template Player</button>
</form>
```

## Key Patterns
- **Per-location**: Each rest area (id_lokasi) has independent config
- **Runtime CSS vars**: No rebuild, instant preview
- **Upscale toggle**: Default `false` (never upscale, letterbox) — matches C:\VMS behavior
- **Footer show/hide**: Via `--pt-footer-hide` CSS var
- **Font selection**: Dropdown with web-safe fonts
- **Design resolution scaling**: See `references/iframe-scaling-design-resolution.md` for iframe size handling

## Extension Points (Ready for Future)
The config schema includes unused fields for granular control:
- `header_height_px`, `grid_gap_px`, `grid_padding_px`, `kartu_gap_px`
- `label_padding_px`, `area_data_gap_px`, `ikon_size_px`
- `angka_font_px`, `caption_font_px`, `sub_slot_font_px`, `footer_font_px`

These map to CSS variables when needed (add to `:root` and apply in CSS).