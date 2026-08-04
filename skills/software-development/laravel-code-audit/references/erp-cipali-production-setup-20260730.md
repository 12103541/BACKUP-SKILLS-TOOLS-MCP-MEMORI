# CIPALI Production Setup — RAB→BOM Auto-Sparepart (2026-07-30)

## Context

RAB ME-CIPALI (ID 19, 518 items, Rp 1.713.883.998) was an orphan — no linked kontrak. Only 5 sparepart existed in DB. Full production workflow needed.

## Steps Executed

### 1. Create Kontrak + Link RAB
- Kontrak: `KTR-CIPALI-20260730` (ID 18, projek, PT Jasa Marga Tbk)
- RAB 19 updated with `kontrak_id = 18`
- ENUM values for kontrak: `status` = active/completed/terminated; `jenis` = pengadaan_langsung/perawatan/lelang/swakelola/projek

### 2. Generate BOM (518 items)
Via `RabMaterialPlanService::generateFromRab($rab)`:
- BOM: `BOM/202607/0004` (ID 5)
- 81 items auto-mapped to existing 5 sparepart (fuzzy keyword match — many incorrect)
- 25 items auto-detected as `jasa` → skipped
- 412 items unmapped

### 3. Auto-Create Sparepart from Unmapped Items
Batch process: group BOM items by `uraian_item` (162 unique names), create Sparepart for each, update all BOM items' `sparepart_id`.

```php
$unmapped = RabMaterialPlanItem::where('plan_id', 5)
    ->whereNull('sparepart_id')->where('tipe_item', '!=', 'jasa')->get();
$uniqueItems = $unmapped->groupBy('uraian_item');
// 162 unique → 162 new Sparepart + update 412 BOM items
```

Result: 162 new sparepart created (kategori='CIPALI', stok=1000, harga from RAB data), 167 total sparepart. 493/518 BOM items mapped.

### 4. Activate BOM
Via `RabMaterialPlanService::activate($plan)`:
- Status: draft → active
- Approved by: Eka Gudang (R04)

## Key Findings

| Issue | Detail | Fix |
|-------|--------|-----|
| RAB orphan | kontrak_id = NULL | Update to link existing kontrak |
| Only 5 sparepart | Auto-match wrong (MCB 1P 4A → MCB 1P 10A, Sekring → MCB) | Auto-create sparepart from RAB item names |
| `kategori` required | DB has no default → SQL 1364 | Always pass `'kategori' => '...'` |
| TransaksiKeluar auto-decrement | booted() decrements stok on create | Jangan double-decrement manual |
| `brick/math` deprecation | Float to BigNumber warnings | Harmless, filter with grep -v |

## Related Skills
- `laravel-code-audit` — "Workflow Chain Test Order" section for detailed code patterns
- `erp-schema` — table structure reference

## Error Traces

### SQL 1364 — kategori field
```
SQLSTATE[HY000]: General error: 1364 Field 'kategori' doesn't have a default value
```
Cause: `Sparepart::create([...])` without `kategori`. Fix: always include `'kategori' => 'CIPALI'` (or appropriate project name).

### Stok double-decrement
```
Exception: Stok sparepart tidak mencukupi. Stok tersedia: 0, diminta: 3.00.
```
Cause: Manual `$sp->decrement('stok', ...)` after `TransaksiKeluar::create()` which already decrements via booted. Fix: remove manual decrement.
