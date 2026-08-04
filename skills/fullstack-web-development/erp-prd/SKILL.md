---
name: erp-prd
description: Product Requirements Document for ERP system. Feature specs, acceptance criteria, business rules, module status for PT EXFERIA PUTRA INOVASI.
tags: [erp, prd, requirements, features, business-rules]
---

# ERP Product Requirements

## Project Location
```
C:\Users\62897\OneDrive\Desktop\laragon\www\PT.EXFERIA PUTRA INOVASI\
```

## Full PRD
Read `PRD.md` in the project root for complete requirements.

## Module Status Quick Reference

### ✅ DONE (Core)
| Module | Key Feature |
|--------|------------|
| Auth | Login, role, permission, lockout 5x |
| Kontrak | CRUD, progress, sisa pagu, adendum, termin |
| Pekerjaan | Laporan + approval + sparepart usage |
| Gudang | Sparepart CRUD, stok, transaksi masuk/keluar, stok kritis |
| Pengeluaran | 8 kategori, rekap per proyek, bukti struk |
| Pajak | PPN/PPh23, verifikasi, CSV export |
| Faktur | Auto nomor, PPN 12%, PDF, status tracking |
| Dashboard | KPI cards, charts, export CSV |

### ✅ DONE (Extended)
| Module | Key Feature |
|--------|------------|
| Penawaran | Auto nomor, PDF, status, convert to kontrak |
| RAB | Auto total, PDF, revisi, import, markup, smart pricing |
| SDM | Karyawan, departemen, struktur org, jadwal teknisi |
| Petty Cash | Top up, running balance, minimum alert |
| Aset | VMS/PJU/videotron lifecycle, QR code, riwayat |
| DMS | Document management system |

### 🔨 PLANNED
| Module | Feature |
|--------|---------|
| Absensi | Employee attendance |
| Cuti | Leave management |
| Project Command Center | Centralized view |
| AI Analysis | Dashboard AI insights |
| E-Faktur | Electronic invoicing integration |

## Key Business Rules

### Auto-Numbering
KON-YYYYMM-XXXX, PNW-YYYYMM-XXXX, RAB-YYYYMM-XXXX, INV-YYYYMM-XXXX

### PPN
- Rate: 12% (config/pajak.php)
- Applied to subtotal
- Rounding: nearest integer

### Notification Schedule
- Kontrak: H-30, H-14, H-7
- Stok kritis: real-time
- Faktur overdue: H+1
- Penawaran expired: real-time

### Validation Rules
- kontrak: tgl_mulai < tgl_akhir, nilai 0.01-999B
- pekerjaan: only active kontrak, foto 1-10 (5MB each)
- pengeluaran: jumlah 1-999B, kontrak active
- sparepart: SKU unique, stok >= 0

### NFR Targets
- Dashboard load: < 5 sec
- PDF render: < 30 sec (RAB), < 10 sec (Faktur)
- API response: < 2 sec
- Token TTL: 60 min
