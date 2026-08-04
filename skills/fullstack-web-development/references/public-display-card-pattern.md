# Public Display Card Pattern (2×2 Grid, 2-Part Split)

## Problem
Display parking availability on large VMS/signage screens. Each location shows 4 cards (one per vehicle type) in a 2×2 grid. Each card splits vertically 50/50: icon on left, number + slot info on right.

## Layout Structure
```html
<main class="grid-papan">
  <!-- 4 cards, one per vehicle type -->
  <section class="kartu-tol tenang">      <!-- status class: tenang/waspada/penuh -->
    <div class="label-lokasi">Mobil</div>  <!-- yellow pill label -->
    <div class="area-data">                <!-- flex row: 2 equal parts -->
      <div class="bagian-ikon">            <!-- 50% - icon centered -->
        <img src="/static/ikon/ikon_mobil.png" class="ikon-custom">
      </div>
      <div class="bagian-slot">            <!-- 50% - data column -->
        <div class="angka">38</div>        <!-- big yellow number -->
        <div class="caption-slot">SLOT</div>
        <div class="sub-slot">38 / 100</div>  <!-- available / max -->
      </div>
    </div>
  </section>
</main>
```

## CSS (Key Rules)
```css
/* Grid: 2 columns × 2 rows, equal height */
.publik .grid-papan {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  grid-template-rows: 1fr 1fr;
  gap: 26px;
  padding: 56px 34px 30px;
  max-width: 1200px;
  margin: 0 auto;
}

/* Card fills grid cell, vertical flex */
.kartu-tol {
  display: flex;
  flex-direction: column;
  gap: 8px;
  height: 100%;
}

/* Label: yellow pill, top-left */
.kartu-tol .label-lokasi {
  background: #ffd600;
  color: #111;
  font-weight: 800;
  text-transform: uppercase;
  padding: 8px 20px;
  border-radius: 10px;
  display: inline-block;
  align-self: flex-start;
}

/* Area data: black background, horizontal flex, 2 equal parts */
.kartu-tol .area-data {
  background: #0a0a0a;
  border-radius: 14px;
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

/* Thin separator between icon/slot */
.kartu-tol .area-data > :first-child { 
  border-right: 2px solid rgba(255, 255, 255, .08); 
}

/* Icon part: 50%, centered */
.kartu-tol .bagian-ikon {
  flex: 1 1 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
}
.kartu-tol .bagian-ikon img.ikon-custom { 
  filter: none;  /* show original colors */
  width: clamp(44px, 5vw, 84px);
  height: clamp(44px, 5vw, 84px);
}

/* Slot part: 50%, vertical centered column */
.kartu-tol .bagian-slot {
  flex: 1 1 50%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 12px;
}

/* Big number */
.kartu-tol .angka {
  color: #ffd600;
  font-size: clamp(52px, 6vw, 110px);
  font-weight: 900;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  text-shadow: 0 0 26px rgba(255, 214, 0, .35);
}

/* Caption */
.kartu-tol .caption-slot {
  color: #fff;
  font-size: clamp(10px, 1vw, 17px);
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
}

/* Sub-slot: available / max */
.kartu-tol .sub-slot {
  color: #888;
  font-size: clamp(11px, 1vw, 15px);
  font-weight: 500;
}
```

## Media Query for VMS (512×288)
```css
@media (max-width: 640px) {
  .header-tol { padding: 4px 10px 16px; }
  .header-tol .kotak-p { font-size: 11px; padding: 2px 5px; }
  .header-tol h1 { font-size: 13px; }
  .publik .header-tol .pill-lokasi { 
    left: 10px; bottom: -9px; 
    font-size: 6.5px; padding: 2px 8px; 
  }
  .publik .grid-papan { 
    gap: 4px; 
    padding: 18px 6px 3px; 
    max-width: none; 
  }
  .kartu-tol { gap: 2px; }
  .kartu-tol .label-lokasi { 
    font-size: 6.5px; padding: 1px 5px; 
  }
  .kartu-tol .area-data { 
    padding: 2px 4px; gap: 3px; 
  }
  .kartu-tol .bagian-ikon img { width: 44px; height: 44px; }
  .kartu-tol .angka { font-size: 24px; }
  .kartu-tol .caption-slot { font-size: 5px; }
  .kartu-tol .sub-slot { font-size: 5px; }
}
```

## JS Render (public.js)
```javascript
function render(data) {
  const kapasitas = (data.kapasitas || []).filter(k => k.aktif !== false);
  const grid = document.getElementById("gridPapan");
  grid.innerHTML = "";

  for (const k of kapasitas) {
    const tersedia = Math.max(0, k.kapasitas_maks - (k.masuk - k.keluar));
    const status = tersedia <= 0 ? "penuh" : 
                   tersedia / k.kapasitas_maks * 100 <= 20 ? "waspada" : "tenang";

    const card = document.createElement("section");
    card.className = `kartu-tol ${status}`;
    card.innerHTML = `
      <div class="label-lokasi">${esc(labelJenis(k))}</div>
      <div class="area-data">
        <div class="bagian-ikon">
          <img src="/static/${esc(k.ikon || `ikon/ikon_${k.jenis}.svg`)}" 
               class="ikon-custom" alt="" loading="lazy">
        </div>
        <div class="bagian-slot">
          <div class="angka">${tersedia > 0 ? tersedia : "—"}</div>
          <div class="caption-slot">SLOT</div>
          <div class="sub-slot">${tersedia} / ${k.kapasitas_maks}</div>
        </div>
      </div>
    `;
    grid.appendChild(card);
  }
}
```

## Key Features
- **2×2 grid**: Always 4 cards (or fewer if some types inactive)
- **Vertical split**: 50/50 icon | data using flexbox
- **Status colors**: `tenang` (blue border), `waspada` (yellow), `penuh` (red + pulse)
- **Responsive**: `clamp()` for fonts, media query for 512×288 VMS
- **Sub-slot**: Shows "available / max" for complete info
- **Icon handling**: Custom PNG (`ikon-custom` class = no yellow filter) or default SVG

## When to Use
- Toll/rest area VMS displays
- Digital signage with grid layouts
- Any dashboard with icon + metric cards
- Fixed-screen kiosk displays