# VMS Player Scaling — Three Corrections Pattern

**Context**: Player page at `/player/{token}` embeds the public board via iframe. The iframe is sized to the VMS display dimensions (e.g., 512×288) and scaled via CSS transform.

## Three corrections in one session (2026-08-02)

### Round 1: Centered letterbox → Top-left anchor
**User**: "kenapa tampilan player untuk materinya tidak sesuai koordinat / koordinat ukuran lebar 512 tinggi 288 x:0 y:0"
**My mistake**: `transform-origin: center center` (letterbox center)
**Fix**: `transform-origin: 0 0` — matches C:\VMS `applyScaleToFit()`

### Round 2: Never-upscale cap removed → Full-viewport upscale
**User**: "tampilan masih terpotong" (screenshot showed mini board top-left of big monitor)
**My interpretation**: The `Math.min(1, vw/w, vh/h)` cap prevented upscale → 512×288 board rendered tiny at 1× on large monitor
**My fix**: Removed the `1` cap → `scale = Math.min(vw/w, vh/h)` → full viewport fill (scale 2.17, iframe 1111×625 on 1264×625 viewport)
**User feedback**: "tampilan frame over" — still wrong in their eyes

### Round 3: USER REVERT — Explicit rejection of upscale
**User**: "kembalikan seperti ukuran semual player vms ukura lebar 512 tinggi 288"
**Final rule**: `scale = Math.min(1, vw/w, vh/h)` — NEVER upscale, physical 512×288 at top-left, black monitor void around is CORRECT
**Key insight**: The C:\VMS reference app never upscales because it targets real LED panels sized to their native pixel dimensions. The browser-based player runs on arbitrary monitors — but the user wants the board to display at its PHYSICAL size (512×288 pixels = physical VMS resolution). Upscaling to fill the browser window defeats the purpose of simulating the actual VMS display.

## Final code (templates/player.html)

```javascript
function skala() {
  const f = document.querySelector("iframe.konten");
  if (!f) return;
  const w = parseFloat(f.dataset.w), h = parseFloat(f.dataset.h);
  if (!w || !h) return;
  const sk = Math.min(1, window.innerWidth / w, window.innerHeight / h);
  f.style.width = w + "px";
  f.style.height = h + "px";
  f.style.transform = `scale(${sk})`;
}
```

## Verification checklist
- `browser_console: getComputedStyle(iframe).transform === 'matrix(1, 0, 0, 1, 0, 0)'`
- `iframe.getBoundingClientRect() === {width: 512, height: 288}` (on any viewport ≥512×288)
- Re-apply on resize (rAF debounce)
- Status screens (nonaktif/maintenance) stay full-screen

## Trajectory lesson
- User uses "terpotong" (cut off) to mean BOTH: content overflowing the frame AND content rendered too small with huge black void
- "Terpotong" = layout/sizing issue, but direction can be either way
- When in doubt, ASK: "apakah konten terpotong ke tepi (overflow) ATAU terlalu mini dengan banyak ruang hitam?" before picking one
- The C:\VMS reference app behavior is the SPEC — not a bug to fix