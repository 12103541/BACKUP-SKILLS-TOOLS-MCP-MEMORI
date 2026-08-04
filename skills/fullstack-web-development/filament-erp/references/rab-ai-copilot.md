# RAB AI Copilot — Phase 1-2 & Sumber Tracking (2026-07-31)

## Phase 1: Generator Draft (RabCopilotService)
- `app/Services/RabCopilotService.php` — generator draft RAB: template (pemasangan_pju, perawatan_pju) + volume per titik/bulan, harga berjenjang sparepart→HargaReferensi→riwayat→estimasi.
- Tombol header "✨ Buat RAB dengan AI" di CreateRab via `getHeaderActions()` → Action modal `->form([...])` (BUKAN schema — schema hanya utk Page actions, form utk Action modal). Repeater draft + checkbox Pilih → action Terapkan mengisi `$this->data['komponen']` + `$this->form->fill()`.
- Pitfall: `formatStateUsing` (number_format titik ribuan) pada TextInput numeric BREAKS programmatic fill → "field required" validasi. Jangan pakai formatStateUsing di input numerik yang diisi via code.
- Pitfall: Toggle di Repeater modal = Livewire entangle error "property cannot be found"; Checkbox aman.
- Pitfall: setelah action modal Terapkan → Livewire re-render → ref browser berubah; type ulang field form utama.
- HargaReferensi: seed via script (historis dari RabKomponen + supplier dari Sparepart.harga_jual). 36 rows. cariHarga() pakai bersihkanKeyword (stopwords m/mm/cm/w/v...).
- AiAnalysisService::analyzeRab — 9/10 matched utk PJU. AI Price dashboard = /admin/rab/ai-price, RAB list dropdown.

## Phase 2: Sumber Tracking & List View Badge
- Migration: `add_sumber_field_to_rab_table` — kolom `sumber` (string, default 'manual', indexed). Values: `manual` | `ai` | `import` | `template`.
- Model `Rab.php` — `sumber` added to `$fillable`.
- **CreateRab (AI)** — action `ai_copilot` sets `'sumber' => 'ai'` when applying draft to form.
- **ImportRab** — `confirmImportNew()` creates RAB with `'sumber' => 'import'`.
- **RabResource Table** — new column `sumber` with badge + icon + color:
  - `ai` → 🤖 AI, `warning` (orange), `heroicon-o-sparkles`
  - `import` → 📁 Import, `info` (blue), `heroicon-o-arrow-down-tray`
  - `template` → 📋 Template, `success` (green), `heroicon-o-document-duplicate`
  - `manual` → ✏️ Manual, `gray`, `heroicon-o-pencil`
  - Uses `Tables\Columns\TextColumn::make('sumber')->badge()->color(fn...)->icon(fn...)->formatStateUsing(fn...)`
- **Filter** — `Tables\Filters\SelectFilter::make('sumber')` with same label options.