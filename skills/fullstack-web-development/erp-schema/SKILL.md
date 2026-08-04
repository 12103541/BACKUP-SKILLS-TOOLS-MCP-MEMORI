---
name: erp-schema
description: Database schema reference for ERP system. Use when adding/changing tables, writing migrations, or working with Eloquent models for PT EXFERIA PUTRA INOVASI.
tags: [erp, database, schema, migration, mysql, eloquent]
---

# ERP Schema Reference

## Project Location
```
C:\Users\62897\OneDrive\Desktop\laragon\www\PT.EXFERIA PUTRA INOVASI\
```

## Full Schema
Read `SCHEMA.md` in the project root for complete table definitions.

## Migration Safety Rules
- ❌ NEVER: `migrate:fresh`, `migrate:reset`, `db:wipe`
- ✅ ALWAYS: `php artisan migrate` (only new migrations)
- ✅ ALWAYS: backup before schema changes
- ✅ ALWAYS: new migration for changes (never edit old ones)
- ✅ ALWAYS: use nullable() for new columns on existing tables

## Core Table Quick Reference

| Table | PK | Key Fields | Status |
|-------|-----|-----------|--------|
| users | id | username, role(R00-R06), supervisor_id | auth |
| kontrak | id | nomor_kontrak, nilai, tgl_mulai/akhir, progres_* | business |
| pekerjaan | id | kontrak_id, user_id, status(draft→approved) | business |
| spareparts | id | sku, stok, safety_stock, harga | inventory |
| transaksi_masuk | id | sparepart_id, quantity, no_invoice | inventory |
| transaksi_keluar | id | sparepart_id, pekerjaan_id, teknisi_id | inventory |
| pengeluaran | id | kategori, jumlah_biaya, kontrak_id(nullable) | finance |
| pajak | id | jenis(ppn/pph23), dpp, tarif, nominal, verified | finance |
| faktur | id | nomor_faktur, subtotal, ppn, total_tagihan, status | finance |
| rab | id | nomor_rab, nama_proyek, versi, parent_id(self-ref) | planning |
| penawaran | id | nomor_penawaran, nama_klien, status | planning |
| departemen | id | kode, nama, kepala_id | HR |
| karyawan | id | user_id, nik, jabatan, departemen_id | HR |

## Key Relationships
- kontrak 1:N pekerjaan, pengeluaran, faktur, rab, penawaran
- pekerjaan N:M spareparts (via pekerjaan_spareparts)
- transaksi_keluar → pekerjaan + teknisi (who used what where)
- pengeluaran.kontrak_id nullable (operasional kantor)
- rab.parent_id self-referencing (version history)

## Auto-Generated Numbers
- KON-YYYYMM-XXXX (kontrak, reset monthly)
- PNW-YYYYMM-XXXX (penawaran)
- RAB-YYYYMM-XXXX (RAB)
- INV-YYYYMM-XXXX (faktur)

## Model Patterns
```php
// Money fields
protected $casts = ['nilai' => 'decimal:2', 'tgl_mulai' => 'date'];

// Relationships
public function kontrak() { return $this->belongsTo(Kontrak::class); }
public function pekerjaan() { return $this->hasMany(Pekerjaan::class); }

// Auto-numbering
use App\Traits\GeneratesCodeNumber;
```
