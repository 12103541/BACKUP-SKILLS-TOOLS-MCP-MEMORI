# Role-Permission Matrix — PT EXFERIA PUTRA INOVASI
## Full Permission Kode per Role

### R00 (Super Admin) — ALL 81 permissions GRANTED (bypass)

### R01 (Admin Proyek) — 58 granted, 23 denied
**GRANTED:** kontrak.view/create/edit/delete/progress, pekerjaan.view/create/approve, gudang.view, pengeluaran.view/create, pajak.view, penawaran.view/create, faktur.view/create/edit, aset.view/create/edit, petty_cash.view, dashboard.view/export, sdm.karyawan/departemen, approval.view/process/manage_workflow, dokumen.view/upload, report.view, kalender.view/create, klien.view/create/edit/delete, smart_pricing.view, rab_comparison.view, command_center.view, filament.access, calendar.view/create/edit/delete/assign, pengajuan-sparepart.view/create/edit, rab.view, harga_referensi.view, transaksi_keluar.view, permintaan_pembelian.view, workflow_proyek.view, user.view, analisa_ai.view, smart_pricing_ops, pemakaian_sparepart.view

**DENIED:** gudang.create/transaksi, pengeluaran.edit/delete, pajak.create/export, petty_cash.topup/edit/delete, admin.users/settings/audit_trail, sdm.absensi/cuti/kinerja, gudang.stock_opname, pengeluaran.kategori/tipe, faktur.delete, audit_trail.view, permintaan_pembelian.manage, pengajuan-sparepart.approve, pemakaian_sparepart.create

### R02 (Teknisi Lapangan) — 14 granted, 67 denied
**GRANTED:** pekerjaan.view/create, gudang.view, approval.view, dokumen.view/upload, kalender.view, filament.access, calendar.view, pengajuan-sparepart.view/create/edit, pemakaian_sparepart.view/create

### R03 (Supervisor Teknik) — 24 granted
**GRANTED:** pekerjaan.view/approve, gudang.view, penawaran.view, dashboard.view/export, sdm.karyawan, approval.view/process, dokumen.view/upload, report.view, kalender.view/create, command_center.view, filament.access, calendar.view/create/assign, pengajuan-sparepart.view/approve, permintaan_pembelian.view/manage, workflow_proyek.view

### R04 (Staff Gudang) — 19 granted
**GRANTED:** gudang.view/create/transaksi/stock_opname, aset.view/create/edit, dashboard.view, sdm.karyawan, approval.view, filament.access, pengajuan-sparepart.view/create/edit, permintaan_pembelian.view/manage, workflow_proyek.view, pemakaian_sparepart.view/create

### R05 (Keuangan) — 32 granted
**GRANTED:** pengeluaran.view/create/edit/delete/kategori/tipe, pajak.view/create/export, penawaran.view, faktur.view/create/edit/delete, petty_cash.view/topup/edit/delete, dashboard.view/export, sdm.karyawan, approval.view/process, report.view, klien.view/edit, command_center.view, filament.access, workflow_proyek.view, permintaan_pembelian.view

### R06 (Manajer) — 44+ granted (read-heavy)
**GRANTED:** kontrak.view, pekerjaan.view, gudang.view, dashboard.view/export, approval.view/process, penawaran.view, faktur.view, pengeluaran.view, pajak.view, admin.users/settings/audit_trail, sdm.karyawan, dokumen.view/upload, command_center.view, report.view, user.view, analisa_ai.view, smart_pricing_ops, audit_trail.view, workflow_proyek.view, calendar.view/create, calendar.view, permintaan_pembelian.view, pengajuan-sparepart.view

### R07 (HRD) — 10 granted
**GRANTED:** filament.access, dashboard.view, sdm.karyawan, sdm.departemen, approval.view, workflow_proyek.view, user.view, calendar.view

## Module Access Summary
| Module         | R00 | R01 | R02 | R03 | R04 | R05 | R06 | R07 |
|----------------|-----|-----|-----|-----|-----|-----|-----|-----|
| admin          |  ✓  |  ✗  |  ✗  |  ✗  |  ✗  |  ✗  |  ✓  |  ✗  |
| approval       |  ✓  |  ✓  |  ✓  |  ✓  |  ✓  |  ✓  |  ✓  |  ✓  |
| aset           |  ✓  |  ✓  |  ✗  |  ✗  |  ✓  |  ✗  |  ✓  |  ✗  |
| calendar       |  ✓  |  ✓  |  ✓  |  ✓  |  ✗  |  ✗  |  ✓  |  ✓  |
| dashboard      |  ✓  |  ✓  |  ✗  |  ✓  |  ✓  |  ✓  |  ✓  |  ✓  |
| dokumen        |  ✓  |  ✓  |  ✓  |  ✓  |  ✗  |  ✗  |  ✓  |  ✗  |
| faktur         |  ✓  |  ✓  |  ✗  |  ✗  |  ✗  |  ✓  |  ✓  |  ✗  |
| gudang         |  ✓  |  ✓  |  ✓  |  ✓  |  ✓  |  ✗  |  ✓  |  ✗  |
| kontrak        |  ✓  |  ✓  |  ✗  |  ✗  |  ✗  |  ✗  |  ✓  |  ✗  |
| pajak          |  ✓  |  ✓  |  ✗  |  ✗  |  ✗  |  ✓  |  ✓  |  ✗  |
| pekerjaan      |  ✓  |  ✓  |  ✓  |  ✓  |  ✗  |  ✗  |  ✓  |  ✗  |
| penawaran      |  ✓  |  ✓  |  ✗  |  ✓  |  ✗  |  ✓  |  ✓  |  ✗  |
| pengeluaran    |  ✓  |  ✓  |  ✗  |  ✗  |  ✗  |  ✓  |  ✓  |  ✗  |
| petty_cash     |  ✓  |  ✓  |  ✗  |  ✗  |  ✗  |  ✓  |  ✓  |  ✗  |
| rab            |  ✓  |  ✓  |  ✗  |  ✗  |  ✗  |  ✗  |  ✓  |  ✗  |
| sdm            |  ✓  |  ✓  |  ✗  |  ✓  |  ✓  |  ✓  |  ✓  |  ✓  |
| user           |  ✓  |  ✓  |  ✗  |  ✗  |  ✗  |  ✗  |  ✓  |  ✓  |
| workflow_proyek|  ✓  |  ✓  |  ✗  |  ✓  |  ✓  |  ✓  |  ✓  |  ✓  |
