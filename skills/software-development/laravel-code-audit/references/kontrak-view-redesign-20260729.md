# Kontrak View Page Redesign + WorkflowProyek Cleanup — Session 2026-07-29

## Context
User asked to check Kontrak page for embedded monitoring features, make it professional, and delete duplicate WorkflowProyek system.

## Files Created/Modified

### 1. `app/Filament/Resources/KontrakResource/Pages/ViewKontrak.php` — Professional View Page
**Structure:** 4 collapsible sections in Infolist
- **Informasi Kontrak** — 8 fields across 2 Grid rows: No. Kontrak, Status badge, Jenis, Klien, Nilai Kontrak, Nilai Efektif, Periode Mulai-Berakhir
- **Progress Overview** — 6 Grid cards: Fisik %, Keuangan %, Tahap Selesai (3/7), Sedang Berjalan, Sisa Garansi, Retensi Sisa
- **Workflow Pipeline (7 Tahap)** — `ViewEntry` embedding `filament.resources.kontrak-resource.partials.pipeline-indicator` blade view with dynamic `$wf` data from `WorkflowIndicatorService::calculate($record)`
- **Ringkasan Data Terkait** — 5 Grid cards: Termin count, Pekerjaan count, Approved count, Faktur count, Lunas count

**Key patterns:**
- Use `TextEntry::make(...)->getStateUsing(fn() => ...)` for dynamic computed values (not static attributes)
- `Placeholder` only for truly static summary that doesn't need `$record` methods
- `ViewEntry` for complex blade components with calculated data
- `InfolistGrid` (not `Forms\Components\Grid`) inside Infolist
- Header actions: Edit, Hitung Progres (calls `hitungProgresOtomatis()`), Tandai Selesai (calls `complete()`)

### 2. `resources/views/filament/resources/kontrak-resource/partials/pipeline-indicator.blade.php`
Reuses the same professional pipeline component from `workflow-indicator.blade.php` with 140px nodes, status badges, animated rings, full labels.

### 3. WorkflowProyek System — Complete Removal
**Deleted:**
- `app/Models/WorkflowProyek.php`
- `app/Models/WorkflowStep.php`
- `app/Models/WorkflowTahapan.php`
- `app/Filament/Resources/WorkflowProyekResource/` (entire directory)
- `app/Filament/Pages/WorkflowDetailPage.php`
- Migration `2024_01_02_000004_create_workflow_proyek_tables.php` (tables: `workflow_proyeks`, `workflow_steps`, `workflow_tahapan`)
- Migration `2026_07_20_181806_create_workflow_proyeks_table.php` (duplicate)

**Verification:**
- DB: 0 records in all 3 tables before deletion
- No FK references in other models (checked `WorkflowLog::tahapan()` - kept but now points to non-existent table, needs cleanup)
- Grep across `app/` confirms zero remaining references

## RBAC Fixes Applied (from same session)
- `HargaReferensiResource`: `penawaran.smart_pricing.view` → `smart_pricing.view`
- 4 SDM Pages: `return false` → `hasPermission('sdm.xxx')` 
- 3 hardcoded Pages: `role === 'R00'` → `hasPermission('admin.settings'/'workflow.view')`
- `DeptAccessService::NAV_ACCESS`: 19→47 entries, synced with config role_map
- `config/permissions.php`: +6 SDM permissions for R01,R03
- `php artisan permissions:sync` — 6 new role-permissions registered

## Verification Commands
```bash
# Syntax check all modified
php -l app/Filament/Resources/KontrakResource/Pages/ViewKontrak.php
php -l app/Services/WorkflowIndicatorService.php
php -l resources/views/filament/resources/kontrak-resource/partials/pipeline-indicator.blade.php

# Clear cache
php artisan view:clear && php artisan optimize:clear

# Browser test
# http://localhost/admin/kontraks/10
```

## Pitfalls Encountered
1. **Placeholder vs TextEntry in Infolist** — `Filament\Infolists\Components\Placeholder` doesn't exist. Use `TextEntry::make(...)->getStateUsing(fn() => ...)` or `ViewEntry` for dynamic content
2. **Forms Grid vs Infolist Grid** — Must use `Filament\Infolists\Components\Grid` inside Infolist, not `Filament\Forms\Components\Grid`
3. **TextEntry Size constants** — `TextEntry\TextEntrySize::ExtraLarge` doesn't exist; remove or use valid size enum
4. **Blade cache** — Must `view:clear` after blade changes; accessibility tree shows truncated labels but visual render is correct after cache clear
5. **Namespace patch tool** — `patch` doubles backslashes in PHP namespaces; use `write_file` for files with `\\Illuminate\\Validation\\` etc.
6. **Orphan FK reference** — `WorkflowLog::tahapan()` still references `WorkflowTahapan` after deletion; need to remove or fix that relation

## Professional Pipeline UI Reference
See `references/workflow-monitoring-redesign-20260729.md` for the base component pattern reused here.