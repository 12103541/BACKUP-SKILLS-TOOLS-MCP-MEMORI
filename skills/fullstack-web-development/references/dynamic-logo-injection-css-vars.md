# Dynamic Logo Injection: CSS Variable + JS Pattern

## Problem
Each rest area needs its own logo in the header (top-left). Logo is uploaded by admin per location and must appear instantly without rebuild.

## Solution
1. **Admin upload** → saves file to `static/ikon/logo_papan_{lokasi_id}.svg|png` + DB `pengaturan_app` key `logo_papan_{lid}`
2. **API** `/api/state?lokasi=N` returns `pengaturan.logo_papan` path
3. **CSS Variable**: `--pt-logo-bg` holds full `background` value
4. **JS on render**: Sets `--pt-logo-bg` from API response

## Implementation

### Admin Upload (main.py)
```python
if "logo_papan" in body and body["logo_papan"]:
    # Expect base64 data URL
    logo_data = body["logo_papan"]
    if logo_data.startswith("data:"):
        logo_data = logo_data.split(",", 1)[1]
    binary = base64.b64decode(logo_data)
    ext = ".svg" if binary[:4] == b"<svg" or b"<svg" in binary[:100] else ".png"
    logo_path = f"static/ikon/logo_papan_{lid}{ext}"
    with open(logo_path, "wb") as f:
        f.write(binary)
    db.execute(
        "INSERT OR REPLACE INTO pengaturan_app (kunci, nilai) VALUES (?, ?)",
        (f"logo_papan_{lid}", f"ikon/logo_papan_{lid}{ext}"),
    )
```

### CSS Variable (style.css)
```css
.header-tol .logo-papan {
  flex: 0 0 64px;
  height: 64px;
  background: var(--pt-logo-bg, #111 url('/static/ikon/ikon_restarea.svg') center/contain no-repeat);
  border-radius: 10px;
  border: 2px solid #333;
}
```

### JS Application (public.js)
```javascript
const logoDiv = document.getElementById("logoPapan");
const root = document.documentElement;
if (logoDiv && pengaturan.logo_papan) {
  const logoUrl = `url('/static/${pengaturan.logo_papan}')`;
  logoDiv.style.backgroundImage = logoUrl;
  root.style.setProperty("--pt-logo-bg", `#111 ${logoUrl} center/contain no-repeat`);
} else {
  root.style.removeProperty("--pt-logo-bg");
}
```

## Key Points
- **CSS variable approach**: Allows logo to be controlled via JS while keeping default in CSS
- **Background shorthand**: Sets color + image + position + repeat in one property
- **Fallback**: Default `ikon_restarea.svg` in `:root` if no logo uploaded
- **Per-location**: `logo_papan_{lid}` key in `pengaturan_app` table
- **Format support**: SVG (preferred) or PNG — auto-detected by magic bytes

## Admin UI (admin.html)
```html
<div class="grup-form">
  <label>Logo Papan Publik</label>
  <input type="file" id="setLogoPapan" accept="image/svg+xml,image/png,image/jpeg">
  <div style="margin-top:8px;">
    <img id="imgPreviewLogo" style="max-width:64px;max-height:64px;display:none;border:1px solid #ddd;border-radius:4px;">
    <span id="txtPreviewLogo" style="color:var(--abu);font-size:12px;">Belum ada logo</span>
  </div>
</div>
```

## Flow
```
Admin uploads logo → base64 → server saves file + DB → 
/state API returns logo path → JS sets --pt-logo-bg CSS var → 
logo appears in header (public page + player iframe)
```

## Benefits
- **No rebuild**: Instant visual change
- **Per-location**: Each rest area has unique logo
- **Graceful fallback**: Default icon if none uploaded
- **Format flexible**: SVG for sharp scaling, PNG for complex art
- **Cache friendly**: New filename per upload (optional version in filename)