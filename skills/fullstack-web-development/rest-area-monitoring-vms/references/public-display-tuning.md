# Public Display Tuning — Iterative Visual Adjustments

**Context**: The public board (`/?lokasi=N&kiosk=1`, embedded in player via iframe) went through multiple visual adjustment rounds based on user feedback. Each round followed the pattern: user sees issue → requests specific change → CSS updated → cache-bust v=N → verify in 512×288 iframe.

## Adjustment Rounds (2026-08-03)

### Round 1: Label nama kendaraan (MOBIL/TRUK/BUS/MOTOR)
- **Issue**: Terlalu kecil, sulit dibaca di 512×288
- **Fix**: `.label-lokasi` 6.5px → **11px** (+69%), kiosk 2.2vh → **3.8vh**
- **Files**: `static/style.css` lines 854, 916

### Round 2: Angka slot (tersedia)
- **Issue**: Kurang besar
- **Fix**: `.angka` 24px → **34px**, kiosk 8.3vh → **11.8vh**
- **Files**: lines 870, 933

### Round 3: Sub-slot (X/Y terpakai/kapasitas) & Footer total
- **Issue**: Terlalu kecil
- **Fix**: `.sub-slot` 5px → **8px** (kiosk 1.7→2.6vh), `.footer-papan` 6.5px → **9px** (kiosk 2.2→3vh)
- **Files**: lines 872, 875, 936, 1029

### Round 4: Pill nama rest area (pindah kiri → kanan)
- **Issue**: User: "rata kekanan, jadi tulisan rest area nya ada di kanan"
- **Fix**: `.pill-lokasi` `left:30px` → `right:30px` (base), `left:10px` → `right:10px` (640px), `left:3vw` → `right:3vw` (kiosk)
- **Files**: lines 119, 843, 895

### Round 5: Judul "PARKIR TERSEDIA" benar tengah
- **Issue**: `justify-content:center` + flexbox space-between → tergeser karena logo kiri & tombol kanan
- **Fix**: `.bar-judul` → `position:absolute; left:50%; transform:translateX(-50%)` (true center, ignore siblings)
- **Applied to**: base, 640px media, kiosk
- **Files**: lines 81-85, 842, 894

### Round 6: Separator ikon | angka
- **Issue**: "tambah garis list sedikit, antara icon gambar dengan angka slot"
- **Fix**: `.area-data > :first-child` border `2px solid rgba(255,255,255,.08)` → `3px solid rgba(255,255,255,.25)`
- **Files**: line 180

### Round 7: Frame kartu (memperbesar kartu, mengurangi background biru)
- **Issue**: "sisa halaman background terlalu banyak, menyebabkan ukuran kartu menjadi kecil"
- **Fix**: Reduced padding/gap around header & grid:
  - Kiosk header: 1.2/4.2vh → 1/3vh; grid padding 6.2/1vh → 3/0.8vh; gap 1.4→1vh
  - 640px header: 4/16px → 3/10px; grid 18/3px → 8/2px; gap 4→3px
- **Result**: Kartu 220×120 → **244×113px** (+24px lebar), no overflow (scrollH=clientH=288)
- **Files**: lines 886, 899-902, 839-840, 850-852

## Cache-Bust Protocol
Every CSS/JS edit → bump `?v=N` in templates:
- `public.html` → `style.css?v=N` + `public.js?v=N`
- `admin.html` → `admin.js?v=N` + `i18n.js?v=N`
- `player.html` has no external CSS/JS (inline only)
- **Current baseline**: style.css v=28, public.js v=14

## Verification in 512×288 Iframe
```javascript
// Create test iframe
const f = document.createElement('iframe');
f.src = '/?lokasi=1&kiosk=1';
f.style.cssText = 'position:fixed;top:0;left:0;width:512px;height:288px;border:0;z-index:999';
document.body.appendChild(f);

// Check computed styles
const d = f.contentDocument;
getComputedStyle(d.querySelector('.label-lokasi')).fontSize  // expect "11px"
getComputedStyle(d.querySelector('.angka')).fontSize         // expect "34px" / "11.8vh"
d.body.scrollHeight === d.body.clientHeight === 288          // no overflow
d.querySelector('.pill-lokasi').getBoundingClientRect().right  // near viewport right edge
```

## Common Pitfall
**User alternates "perbesar" / "perkecil" requests** — 5 rounds of icon size: 19→40→56→60→120(2×)→ then TWO shrink rounds (72/44). After EACH change, verify `scrollHeight === clientHeight` (no scrollbar) AND visual check — don't just hit the requested size.