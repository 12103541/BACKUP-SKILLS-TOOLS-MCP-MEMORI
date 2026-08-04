# Role-Permission Matrix — PT EXFERIA PUTRA INOVASI

## Overview
- `role_permissions` table: one row per (role, permission) = granted
- User-level overrides in `user_permissions` (granted/revoke per user)
- R00 (Super Admin) bypasses all checks via `isSuperAdmin()` returning true
- Without `filament.access`, user CANNOT enter Filament admin panel at all
- Sync query pattern: DELETE then INSERT (not upsert) for clean state

## SQL: Sync Permissions for a Role

```sql
-- Clear existing
DELETE FROM role_permissions WHERE role = 'R02';

-- Insert needed permissions
INSERT INTO role_permissions (role, permission_id, created_at, updated_at)
SELECT 'R02', id, NOW(), NOW() FROM permissions WHERE kode IN (
    'filament.access', 'dashboard.view', ...
);
```

## SQL: Check Role Permission Count

```sql
SELECT role, COUNT(*) as cnt FROM role_permissions GROUP BY role ORDER BY role;
```

## SQL: Find Missing Permissions for a Role

```sql
SELECT p.kode, p.nama, p.modul
FROM permissions p
LEFT JOIN role_permissions rp ON rp.permission_id = p.id AND rp.role = 'R02'
WHERE rp.id IS NULL
ORDER BY p.modul;
```

## Programmatic Matrix Verification (test ALL roles at once)

Run via `php artisan tinker --execute` to print a Y/- matrix of every role × every resource:

```php
$roles = ['R00'=>'SuperAdmin','R01'=>'AdminProyek','R02'=>'Teknisi','R03'=>'Supervisor','R04'=>'Gudang','R05'=>'Keuangan','R06'=>'Manajer','R07'=>'HRD'];
$resources = [
    'kontrak.view'=>'Kontrak','pekerjaan.view'=>'Pekerjaan','gudang.view'=>'Sparepart',
    'pengeluaran.view'=>'Pengeluaran','faktur.view'=>'Faktur','pajak.view'=>'Pajak',
    'penawaran.view'=>'Penawaran','klien.view'=>'Klien','sdm.karyawan'=>'Karyawan',
    'aset.view'=>'Aset','petty_cash.view'=>'PettyCash','harga_referensi.view'=>'HrgRef',
    'rab.view'=>'RAB','dokumen.view'=>'Dokumen','admin.users'=>'Users',
    'admin.settings'=>'Pengaturan','dashboard.view'=>'Dashboard','workflow_proyek.view'=>'Workflow',
];
echo str_pad('Role',15); foreach($resources as $l) echo str_pad(substr($l,0,8),9); echo PHP_EOL;
echo str_repeat('-',15+9*count($resources)).PHP_EOL;
foreach($roles as $code=>$name){
    echo str_pad($code.' '.substr($name,0,8),15);
    foreach($resources as $kode=>$label){
        $has = \App\Models\RolePermission::where('role',$code)->whereHas('permission',fn($q)=>$q->where('kode',$kode))->exists();
        echo str_pad($has?'Y':'-',9);
    }
    echo PHP_EOL;
}
```

## R00 — Super Admin
Auto-granted all permissions via `isSuperAdmin()` check. No role_permissions entries needed, but usually present for consistency.

## R01 — Admin Proyek
```
filament.access, dashboard.view, dashboard.export, report.view,
kontrak.view, kontrak.create, kontrak.edit, kontrak.progress,
pekerjaan.view, pekerjaan.create, pekerjaan.approve,
rab.view, penawaran.view, penawaran.create,
klien.view, klien.create, klien.edit, klien.delete,
harga_referensi.view,
workflow_proyek.view, command_center.view,
calendar.view, calendar.create, calendar.edit, calendar.assign,
dokumen.view, dokumen.upload,
approval.view, approval.process,
sdm.karyawan, sdm.departemen,
petty_cash.view, gudang.view, transaksi_keluar.view,
permintaan_pembelian.view, user.view
```

## R02 — Teknisi Lapangan
```
filament.access, dashboard.view,
pekerjaan.view, pekerjaan.create,
approval.view,
calendar.view,
pengajuan-sparepart.view, pengajuan-sparepart.create, pengajuan-sparepart.edit,
pemakaian_sparepart.view, pemakaian_sparepart.create
```
**NOT included**: kontrak, faktur, pajak, pengeluaran, admin, sdm, klien, penawaran, rab, aset, gudang (sparepart/supplier/stock_opname), smart pricing, analisa AI, Harga Referensi

## R03 — Supervisor Teknik
All R02-unique permissions PLUS:
```
pekerjaan.approve,
pengajuan-sparepart.approve,
permintaan_pembelian.view, permintaan_pembelian.manage,
calendar.create, calendar.assign,
command_center.view, report.view, dashboard.export,
dokumen.view, dokumen.upload,
penawaran.view, gudang.view, sdm.karyawan,
filament.access, workflow_proyek.view
```

## R04 — Staff Gudang
```
filament.access, dashboard.view,
gudang.view, gudang.create, gudang.transaksi, gudang.stock_opname,
pengajuan-sparepart.view, pengajuan-sparepart.create, pengajuan-sparepart.edit,
aset.view, aset.create, aset.edit,
approval.view, sdm.karyawan, workflow_proyek.view
```

## R05 — Keuangan
```
filament.access, dashboard.view, dashboard.export, report.view, command_center.view,
faktur.view, faktur.create, faktur.edit, faktur.delete,
pajak.view, pajak.create, pajak.export,
pengeluaran.view, pengeluaran.create, pengeluaran.edit, pengeluaran.delete,
pengeluaran.kategori, pengeluaran.tipe,
petty_cash.view, petty_cash.topup, petty_cash.edit, petty_cash.delete,
penawaran.view, permintaan_pembelian.view,
transaksi_keluar.view, sdm.karyawan, approval.view, workflow_proyek.view
```

## R06 — Manajer
Like R00 — all permissions. Dashboard shows all sections. Includes:
```
admin.users, admin.settings, admin.audit_trail,
approval.manage_workflow, calendar.create, calendar.view,
kalender.view, kalender.create,
aset.*, dokumen.*, faktur.*, gudang.view, harga_referensi.view,
klien.*, kontrak.view, pajak.*, pekerjaan.*, penawaran.*,
pengajuan-sparepart.view, pengeluaran.view, permintaan_pembelian.view,
petty_cash.view, rab.view, sdm.*, smart_pricing.view, transaksi_keluar.view,
user.view, workflow_proyek.view, filament.access, dashboard.*
```

## R07 — HRD (Custom)
```
filament.access, dashboard.view,
sdm.karyawan, sdm.absensi, sdm.cuti, sdm.kinerja, sdm.departemen,
approval.view, workflow_proyek.view, dokumen.view
```

**Actual INSERT executed 2026-07-25** (was missing from DB — R07 had ZERO role_permissions):
```sql
INSERT INTO role_permissions (role, permission_id, created_at, updated_at) VALUES
('R07', 61, NOW(), NOW()),  -- filament.access
('R07', 28, NOW(), NOW()),  -- dashboard.view
('R07', 33, NOW(), NOW()),  -- sdm.karyawan
('R07', 34, NOW(), NOW()),  -- sdm.absensi
('R07', 35, NOW(), NOW()),  -- sdm.cuti
('R07', 36, NOW(), NOW()),  -- sdm.departemen
('R07', 59, NOW(), NOW()),  -- sdm.kinerja
('R07', 93, NOW(), NOW()),  -- workflow_proyek.view
('R07', 37, NOW(), NOW()),  -- approval.view
('R07', 40, NOW(), NOW());  -- dokumen.view
```

**Note**: `calendar.view` NOT included — orphaned permission (no Filament resource references it). Same for `kalender.*` permissions.

## Orphaned Permissions (in DB but unused in code)
- `kalender.view` (43), `kalender.create` (44) — old naming
- `calendar.view` (80), `calendar.create` (81), `calendar.edit` (82), `calendar.delete` (83), `calendar.assign` (84) — newer naming
- No Filament Resource, Page, or middleware references any of these. They exist in `role_permissions` for some roles but have zero effect.

## Verification via Tinker

```bash
php artisan tinker --execute="
\$u = App\Models\User::where('email','teknisi1@example.com')->first();
echo 'Role: '.\$u->role.PHP_EOL;
echo 'filament.access: '.(\$u->hasPermission('filament.access')?'YA':'TIDAK').PHP_EOL;
echo 'pekerjaan.view: '.(\$u->hasPermission('pekerjaan.view')?'YA':'TIDAK').PHP_EOL;
echo 'faktur.view (should be TIDAK): '.(\$u->hasPermission('faktur.view')?'YA':'TIDAK').PHP_EOL;
echo 'harga_referensi.view (should be TIDAK): '.(\$u->hasPermission('harga_referensi.view')?'YA':'TIDAK').PHP_EOL;
"
```

## Browser-Based Role Testing Workflow

After fixing permissions, verify each role via browser:

1. Navigate to `http://localhost/admin/login`
2. Login as user of each role (password: `password123`)
3. Check sidebar — only expected menus should appear
4. Logout via JS: `document.querySelector('button[type="submit"]').click()`
5. Repeat for next role

**Expected sidebar per role:**
| Role | Expected Sidebar Items |
|------|----------------------|
| R00 SuperAdmin | ALL menus |
| R01 AdminProyek | Kontrak, Pekerjaan, Klien, Penawaran, RAB, Harga Ref, Dokumen, SDM, Workflow |
| R02 Teknisi | Pekerjaan, Pengajuan Sparepart, Pemakaian Sparepart |
| R03 Supervisor | Pekerjaan, Penawaran, Dokumen, SDM, Gudang, Workflow |
| R04 Gudang | Aset, Sparepart, Supplier, Stock Opname, Pengajuan Sparepart, SDM, Workflow |
| R05 Keuangan | Faktur, Pajak, Pengeluaran, Petty Cash, Transaksi Keluar, SDM, Workflow |
| R06 Manajer | ALL menus (like R00) |
| R07 HRD | SDM (Karyawan, Absensi, Cuti, Kinerja, Departemen), Dokumen, Workflow |

## Common Mistake: User Can't See Any Menu
Symptom: Login succeeds, sidebar empty, no resources visible.
Diagnosis: `SELECT COUNT(*) FROM role_permissions WHERE role='RXX'` returns 0.
Fix: Sync permissions using the INSERT pattern above. At minimum, `filament.access` is required.

## Common Mistake: Resource Visible to ALL Users
Symptom: A resource shows in sidebar for every user regardless of role.
Diagnosis: Resource has no `canAccess()` method.
Fix: Add `public static function canAccess(): bool { return Auth::user()->hasPermission('module.view'); }`
Audit: `grep -rL 'canAccess' app/Filament/Resources/` to find missing ones.
