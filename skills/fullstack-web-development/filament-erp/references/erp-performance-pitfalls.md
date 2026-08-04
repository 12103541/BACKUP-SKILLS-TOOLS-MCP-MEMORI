# ERP Performance Pitfalls (Session Notes)

## 1. Section visible() Hides Form Fields from State
**File pattern:** `ImportRab.php`  
**Symptom:** After preview, `nomor_rab` & `nama_proyek` are null → import fails with "Isi Nomor RAB dan Nama Proyek"

**Root cause:** `->visible(fn () => $this->import_mode === 'new' && !$this->previewData)` — when `previewData` is set, section + its fields REMOVED from DOM. `$form->getState()` returns null for those fields.

**Fix:** Remove `!$this->previewData` from visible condition, or store critical values in component properties before hiding.

## 2. HelperText with DB Queries in Filament Repeater = N+1
**Symptom:** Edit RAB page very slow, 80+ queries per render.

**Root cause:** `helperText` on `harga_satuan` calls `HargaReferensi::cariHarga($uraian)` — 4 queries per row (FULLTEXT + BOOLEAN + LIKE loop + fallback). Each Repeater item triggers on render.

**Fix:** Remove DB-dependent helperText from inline Repeater items. Provide analysis via dedicated action button instead.

## 3. ->live(onBlur: true) vs ->live()
**Symptom:** Numeric fields trigger Livewire request on every keystroke.

**Fix:** Use `->live(onBlur: true)` for volume, harga_satuan, and other numeric/select fields. Only fires when user leaves the field.

## 4. Batch INSERT vs Foreach ::create()
**Symptom:** `RabMaterialPlanService::generateFromRab()` — N INSERT queries for N items.

**Fix:** Collect data array, use `Model::insert($batch)` — 1 query.

## 5. In-Memory Matching vs N×DB LIKE Queries
**Symptom:** Sparepart matching on RAB → Gudang: 250+ LIKE scans for 50 komponen.

**Fix:** `Sparepart::all()` once, loop in-memory with keyword scoring. Same logic, 0 DB queries.

## 6. saveAll() Redundant Before BOM Generation
**Symptom:** `kirimKeGudang()` calls `saveAll()` which does DELETE + UPDATE/CREATE per item + SUM + reload + reapplyAnalisa.

**Fix:** Replace with single `updateQuietly(['markup_persen' => ...])` — komponen already stored in DB.

## 7. Undefined Constants Block Workflow
**Symptom:** `self::STATUS_COMPLETED` / `self::STATUS_ACTIVE` in Kontrak model not defined → PHP Fatal Error when faktur reaches lunas.

**Fix:** Define `const STATUS_ACTIVE = 'active'`, `const STATUS_COMPLETED = 'completed'` in the model.

## 8. PPN Hardcode vs Config
**Symptom:** CreateFaktur/EditFaktur use `0.11` while config says 12%. Create overrides form display.

**Fix:** `config('pajak.tarif_ppn_keluaran', 12) / 100` instead of hardcoded `0.11`.
