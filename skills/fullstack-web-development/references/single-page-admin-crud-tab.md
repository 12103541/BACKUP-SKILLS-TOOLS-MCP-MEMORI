# Single-Page Admin CRUD Tab Pattern

## Problem
Admin needs full CRUD for an entity (e.g., Vehicle Types) in one tab/panel without page navigation. Create, Read, Update, Delete all in one view.

## Solution
Single tab with:
1. **Create Form** at top (inline)
2. **Data Table** below with inline editing
3. **Event delegation** on table for all actions

## HTML Structure (admin.html)
```html
<section id="tab-jenis-kendaraan" class="tab-panel" hidden>
  <h2>🚙 Jenis Kendaraan</h2>
  
  <!-- CREATE FORM -->
  <form id="formJenis" class="form-grid-2">
    <div class="grup-form">
      <label>Kode <input type="text" id="jenisKode" placeholder="mobil" required></label>
    </div>
    <div class="grup-form">
      <label>Label <input type="text" id="jenisLabel" placeholder="Mobil" required></label>
    </div>
    <div class="grup-form">
      <label>English <input type="text" id="jenisLabelEn" placeholder="Car"></label>
    </div>
    <div class="grup-form">
      <label>Bobot <input type="number" id="jenisBobot" value="10" min="1" max="100"></label>
    </div>
    <button type="submit" class="tombol">💾 Tambah</button>
  </form>

  <!-- READ/UPDATE/DELETE TABLE -->
  <table id="tabelJenis" class="tabel-admin">
    <thead>
      <tr>
        <th>Urutan</th>
        <th>Ikon</th>
        <th>Kode</th>
        <th>Label</th>
        <th>English</th>
        <th>Bobot</th>
        <th>Aktif</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</section>
```

## Table Row Template (admin.js)
```javascript
function renderJenisRow(k) {
  const ikonHtml = k.ikon
    ? `<img src="/static/${esc(k.ikon)}" style="width:44px;height:44px;object-fit:contain;">`
    : `<span style="font-size:32px;">${esc(k.label[0] || k.kode[0])}</span>`;
  
  return `<tr data-jenis="${esc(k.kode)}">
    <td><input type="number" class="inp-urutan" value="${k.urutan}" min="0" style="width:60px;"></td>
    <td>
      ${ikonHtml}
      <label class="ikon-upload-label">
        <input type="file" accept="image/*" class="inp-ikon" data-jenis="${esc(k.kode)}" style="display:none;">
        <span class="tombol kecil">🖼️ Ganti</span>
      </label>
    </td>
    <td><code>${esc(k.kode)}</code></td>
    <td><input type="text" class="inp-label" value="${esc(k.label)}"></td>
    <td><input type="text" class="inp-label-en" value="${esc(k.label_en)}"></td>
    <td><input type="number" class="inp-bobot" value="${k.bobot}" min="1" max="100" style="width:70px;"></td>
    <td>
      <label class="switch">
        <input type="checkbox" class="sw-jenis" data-jenis="${esc(k.kode)}" ${k.aktif ? "checked" : ""}>
        <span class="slider"></span>
      </label>
    </td>
    <td>
      <button class="tombol kecil btn-simpan-jenis" data-jenis="${esc(k.kode)}">💾</button>
      <button class="tombol kecil btn-hapus-jenis" data-jenis="${esc(k.kode)}">🗑️</button>
    </td>
  </tr>`;
}
```

## Event Delegation (admin.js)
```javascript
// Single listener on table body
$("#tabelJenis tbody").addEventListener("click", async (e) => {
  const row = e.target.closest("tr");
  if (!row) return;
  const jenis = row.dataset.jenis;
  
  // SAVE (inline edit)
  if (e.target.matches(".btn-simpan-jenis")) {
    const body = {
      urutan: Number(row.querySelector(".inp-urutan").value),
      label: row.querySelector(".inp-label").value,
      label_en: row.querySelector(".inp-label-en").value,
      bobot: Number(row.querySelector(".inp-bobot").value),
    };
    await api(`/api/jenis/${jenis}`, { method: "PUT", body: JSON.stringify(body) });
    notifikasi(`${jenis} diperbarui`);
    muatJenis();
    return;
  }
  
  // DELETE
  if (e.target.matches(".btn-hapus-jenis")) {
    if (!confirm(`Hapus jenis "${jenis}"?`)) return;
    await api(`/api/jenis/${jenis}`, { method: "DELETE" });
    notifikasi(`${jenis} dihapus`);
    muatJenis();
    return;
  }
});

// Active toggle (change event)
$("#tabelJenis tbody").addEventListener("change", async (e) => {
  if (e.target.matches(".sw-jenis")) {
    const jenis = e.target.dataset.jenis;
    await api(`/api/kapasitas/${jenis}/aktif`, { 
      method: "PUT", 
      body: JSON.stringify({ aktif: e.target.checked }) 
    });
    notifikasi(e.target.checked ? `${jenis} diaktifkan` : `${jenis} dinonaktifkan`);
    muatJenis();
  }
});

// File upload (icon)
document.addEventListener("change", async (e) => {
  const inp = e.target.closest(".inp-ikon");
  if (inp && inp.files?.[0]) {
    const jenis = inp.dataset.jenis;
    const fd = new FormData();
    fd.append("file", inp.files[0]);
    await api(`/api/pengaturan/ikon/${jenis}`, { method: "POST", body: fd });
    notifikasi(`Ikon ${jenis} diperbarui`);
    muatJenis();
    inp.value = "";
  }
});
```

## Create Form Handler
```javascript
$("#formJenis").addEventListener("submit", async (e) => {
  e.preventDefault();
  const body = {
    kode: $("#jenisKode").value.toLowerCase(),
    label: $("#jenisLabel").value,
    label_en: $("#jenisLabelEn").value || "",
    bobot: Number($("#jenisBobot").value) || 10,
  };
  await api("/api/jenis", { method: "POST", body: JSON.stringify(body) });
  notifikasi("Jenis kendaraan ditambahkan");
  $("#formJenis").reset();
  muatJenis();
});
```

## Load Function
```javascript
async function muatJenis() {
  try {
    const list = await api("/api/jenis");
    const tbody = $("#tabelJenis tbody");
    tbody.innerHTML = list.map(k => renderJenisRow(k)).join("");
  } catch (e) {
    // Tab might not be active
  }
}
```

## API Endpoints Required
```python
# main.py
@app.get("/api/jenis")           # List all
@app.post("/api/jenis")          # Create
@app.put("/api/jenis/{kode}")    # Update (label, urutan, bobot, label_en)
@app.delete("/api/jenis/{kode}") # Delete
@app.put("/api/kapasitas/{jenis}/aktif")  # Toggle active
@app.post("/api/pengaturan/ikon/{jenis}") # Upload icon
```

## Key Features
- **Single tab**: No navigation, all actions inline
- **Event delegation**: One listener handles all table actions
- **Inline editing**: Inputs directly in table cells
- **Icon upload**: Hidden file input triggered by button
- **Active toggle**: Checkbox switch per row
- **Optimistic UI**: Reloads table after each action
- **Validation**: Required fields, number ranges

## When to Use
- Entity with 5-15 records (manageable in one table)
- Admin needs quick CRUD without modal dialogs
- Inline editing preferred over separate forms
- Icon/image upload per record