# Iframe Scaling: Design Resolution vs Display Resolution

## Problem
When embedding a responsive page in an iframe that matches a small display resolution (e.g., VMS 512×288), the iframe content triggers mobile CSS media queries (`max-width: 640px`), causing layout to break/cut off.

## Solution
Render iframe at a larger **"design resolution"** (e.g., 1200px wide, aspect-ratio preserved) then scale down via `transform: scale()` to fit the actual display viewport.

## Implementation
```javascript
// In player.html skala() function
const DESIGN_W = 1200;
const DESIGN_H = Math.round(1200 * displayHeight / displayWidth); // maintain aspect ratio

const sk = Math.min(1, window.innerWidth / DESIGN_W, window.innerHeight / DESIGN_H);
iframe.style.width = DESIGN_W + "px";
iframe.style.height = DESIGN_H + "px";
iframe.style.transform = `scale(${sk})`;
iframe.style.transformOrigin = "0 0";
```

## Key Points
- `transform-origin: 0 0` — matches C:\VMS reference implementation (top-left scaling)
- Never upscale by default (`Math.min(1, ...)`) — only downscale to fit
- Design width 1200px avoids common `max-width: 640px` / `max-width: 900px` breakpoints
- Aspect ratio calculated from VMS display dimensions (e.g., 512×288 → 1200×675)

## When to Use
- VMS/kiosk players embedding responsive dashboards
- Any iframe where target display < responsive breakpoint
- Digital signage with fixed hardware resolution

## Related Patterns
- Multi-area template system: per-location CSS variables via `--pt-*` vars
- Dynamic logo injection: `--pt-logo-bg` CSS variable + JS `setProperty`