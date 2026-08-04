# Public Display Polling Pattern (5s Interval)

## Endpoint
```
GET /api/state?lokasi=N
```

## Response Shape
```json
{
  "lokasi_aktif": 4,
  "lokasi_list": [...],
  "lokasi": {"id": 4, "nama": "Rest Area KM42A...", "kode": "KM42A", "urutan": 4},
  "pengaturan": {
    "id": 4,
    "id_lokasi": 4,
    "nama_lokasi": "Rest Area KM42A - Tol Tangerang Merak",
    "reset_jam": "00:00",
    "batas_hampir_penuh_pct": 20,
    "kapasitas_total": 0,
    "logo_papan": "ikon/logo_papan_4.svg"
  },
  "kapasitas": [
    {
      "jenis": "mobil",
      "label": "Mobil",
      "label_en": "Car",
      "kapasitas_maks": 100,
      "masuk": 218,
      "keluar": 119,
      "terpakai": 99,
      "tersedia": 1,
      "status": "hampir_penuh",
      "aktif": true,
      "ikon": "ikon/ikon_mobil.png"
    }
  ],
  "kamera": [...],
  "waktu": "2026-08-02 22:17:18",
  "deteksi": {...},
  "alert": [...]
}
```

## Client: static/public.js
```javascript
const paramsURL = new URLSearchParams(location.search);
const pinLokasi = paramsURL.get("lokasi") ? Number(paramsURL.get("lokasi")) : null;
const qState = pinLokasi ? `?lokasi=${pinLokasi}` : "";
const kiosk = paramsURL.get("kiosk") === "1";

async function muat() {
  try {
    const data = await fetch(`/api/state${qState}`).then(r => r.json());
    render(data);
  } catch (e) { /* silent */ }
}

// Poll every 5 seconds
setInterval(muat, 5000);
muat();

function render(data) {
  const kapasitas = (data.kapasitas || []).filter(k => k.aktif !== false);
  const pengaturan = data.pengaturan || {};

  // Pill header: nama lokasi
  document.getElementById("lokasi").textContent =
    (pengaturan.nama_lokasi || "Rest Area").toUpperCase();

  // Logo papan (from pengaturan.logo_papan)
  const logoDiv = document.getElementById("logoPapan");
  if (logoDiv && pengaturan.logo_papan) {
    logoDiv.style.backgroundImage = `url('/static/${pengaturan.logo_papan}')`;
  }

  // Grid: 1 kartu per jenis aktif
  const grid = document.getElementById("gridPapan");
  grid.innerHTML = "";

  const totalSlot = Number(pengaturan.kapasitas_total) > 0
    ? Number(pengaturan.kapasitas_total)
    : kapasitas.reduce((s, k) => s + k.kapasitas_maks, 0);
  let totalTerpakai = 0, totalTersedia = 0;

  for (const k of kapasitas) {
    const tersedia = k.tersedia;
    totalTerpakai += k.terpakai;
    totalTersedia += tersedia;

    const kartu = document.createElement("section");
    kartu.className = "kartu-tol " + (tersedia <= 0 ? "penuh" :
      tersedia <= (totalSlot || 1) * 0.2 ? "waspada" : "tenang");
    kartu.innerHTML = `
      <div class="label-lokasi">${esc(labelJenis(k))}</div>
      <div class="area-data">
        <div class="bagian-ikon">${ikonJenis(k)}</div>
        <div class="bagian-slot">
          <div class="angka">${tersedia <= 0 ? "-" : tersedia}</div>
          <div class="caption-slot">SLOT</div>
          ${k.kapasitas_maks > 0 ? `<div class="sub-slot">${tersedia} / ${k.kapasitas_maks}</div>` : ""}
        </div>
      </div>`;
    grid.appendChild(kartu);
  }

  // Kartu cadangan (total tersedia semua jenis)
  const kartuCad = document.createElement("section");
  kartuCad.className = "kartu-tol " + (totalTersedia <= 0 ? "penuh" : "tenang");
  kartuCad.innerHTML = `
    <div class="label-lokasi">${t("cadangan")}</div>
    <div class="area-data">
      <div class="bagian-ikon"><svg>...</svg></div>
      <div class="bagian-slot">
        <div class="angka">${totalTersedia <= 0 ? "-" : totalTersedia}</div>
        <div class="caption-slot">SLOT</div>
      </div>
    </div>`;
  grid.appendChild(kartuCad);

  // Banner alert (histeresis from server)
  // ...
}
```

## Template: templates/public.html
```html
<header class="header-tol">
  <div class="bar-branding">
    <div class="logo-papan" id="logoPapan" title="Logo Rest Area"></div>
    <div class="bar-judul">
      <span class="kotak-p">P</span>
      <h1>PARKIR TERSEDIA</h1>
    </div>
    <div class="bar-aksi-papan">
      <button id="tombolPilihLokasi">📍 Pilih lokasi</button>
      <button data-i18n-toggle>EN</button>
    </div>
  </div>
  <div class="pill-lokasi" id="lokasi">REST AREA</div>
</header>

<main class="grid-papan" id="gridPapan"></main>

<footer class="footer-papan">...</footer>
```

## CSS Media Query for VMS 512×288
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

## Key Points
- Polling interval: 5000ms (5 seconds)
- `?lokasi=N` pins to specific location; without param follows global active location
- `?kiosk=1` hides location picker and language toggle (for VMS player iframe)
- `tersedia` computed client-side: `kapasitas_maks - (masuk - keluar)` — server also sends it
- Kartu status classes: `tenang` (blue), `waspada` (yellow ≤20%), `penuh` (red 0)
- Sub-slot shows `tersedia / kapasitas_maks` when `kapasitas_maks > 0`
- Cadangan kartu = sum of all `tersedia` across active jenis