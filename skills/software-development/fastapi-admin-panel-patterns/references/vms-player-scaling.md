# VMS Player Scaling Formula (Transform-Origin 0 0)

## Reference: C:\VMS\www\resources\views\player\display.blade.php (lines 925-975)

## Formula
```javascript
// Display resolution (from VMS device config)
const displayW = 512;  // VMS.display_width
const displayH = 288;  // VMS.display_height

// Viewport size
const vw = window.innerWidth;
const vh = window.innerHeight;

// Scale factor - NEVER UPSCALE (cap at 1)
const scale = Math.min(1, vw / displayW, vh / displayH);

// Apply to iframe
iframe.style.transformOrigin = '0 0';  // Top-left anchor
iframe.style.transform = `translate(0, 0) scale(${scale})`;
// iframe rendered size = displayW * scale  x  displayH * scale
```

## Key Rules
1. **transform-origin: 0 0** — Anchor top-left (not center)
2. **translate(0, 0)** — Explicit x:0 y:0 (not center)
3. **Math.min(1, ...)** — Never upscale; content shows at native 512×288 on large screens
4. **Aspect ratio preserved** — min(vw/w, vh/h) ensures no distortion

## Implementation in templates/player.html
```javascript
function skala() {
  const f = document.querySelector("iframe");
  if (!f) return;
  const w = 512, h = 288; // from VMS display_width/height
  const scale = Math.min(1, window.innerWidth / w, window.innerHeight / h);
  f.style.transformOrigin = '0 0';
  f.style.transform = `translate(0, 0) scale(${scale})`;
}
window.addEventListener('resize', skala);
skala();
```

## CSS for 512×288 Viewport (VMS Player)
```css
@media (max-width: 640px) {
  body.publik { overflow: hidden; }
  .header-tol { padding: 4px 10px 16px; }
  .header-tol .kotak-p { font-size: 11px; padding: 2px 6px; }
  .header-tol h1 { font-size: 15px; }
  .pill-lokasi { font-size: 7.5px; padding: 2px 8px; bottom: -11px; }
  .grid-papan { gap: 4px; padding: 18px 6px 3px; }
  .kartu-tol .label-lokasi { font-size: 6.5px; padding: 1px 5px; }
  .kartu-tol .bagian-ikon { padding: 0; }
  .kartu-tol .bagian-ikon img,
  .kartu-tol .bagian-ikon svg { width: 44px; height: 44px; }
  .kartu-tol .bagian-ikon img.ikon-custom { width: 72px; height: 72px; filter: none; }
  .kartu-tol .bagian-slot { padding: 0 3px; gap: 0; }
  .kartu-tol .angka { font-size: 24px; }
  .kartu-tol .caption-slot { font-size: 5px; }
  .kartu-tol .sub-slot { font-size: 5px; margin-top: 1px; }
}
```

## Common Mistakes
| Wrong | Right |
|-------|-------|
| `transform-origin: center` | `transform-origin: 0 0` |
| `scale = Math.max(1, ...)` | `scale = Math.min(1, ...)` |
| `translate(-50%, -50%)` | `translate(0, 0)` |
| Upscale on large screens | Cap at 1 — show native size |