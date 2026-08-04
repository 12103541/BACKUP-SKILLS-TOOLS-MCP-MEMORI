# Role Access & Permission Counts — Verified 2026-08-01

Source: full 4-layer role test (panel access, hasPermission counts, module matrix, user overrides) run via standalone bootstrap script during E2E verification.

## Panel Access — all 12 users PASS `canAccessPanel(admin)`

Users span R00, R01, R02 (x2), R03, R04 (x2), R05 (x2), R06, R07 (x2).

## Permission Counts (total 86 permissions in DB)

| Role | Count | Notes |
|------|-------|-------|
| R00 SuperAdmin | 86/86 | bypass |
| R01 AdminProyek | 40/86 | kontrak, penawaran, rab, pekerjaan, faktur, klien, aset, kalender |
| R02 Teknisi | 14/86 | pekerjaan(view+create), gudang(view), dokumen, calendar |
| R03 Supervisor | 28/86 | pekerjaan(approve), approval, dashboard, calendar(assign) |
| R04 Gudang | 20/86 | gudang(CRUD+stock), aset, permintaan_pembelian |
| R05 Keuangan | 32/86 | pengeluaran(CRUD), pajak, faktur(CRUD), petty_cash, report |
| R06 Manajer | 48/86 | dashboard(export), audit_trail, admin, read-only |
| R07 HRD | 10/86 | sdm.karyawan, approval(view), sdm.departemen |

⚠️ erp-rules SKILL.md still shows July-2026 numbers (81 total; R01 58/81, R03 24/81, R04 19/81, R06 44+/81) — STALE. erp-rules is user-owned; adopt it (`hermes curator adopt erp-rules`) to let curation refresh the table.

## Module Matrix Highlights (27 modules)

- `filament` + `approval` = ALL roles ✓ (everyone can enter + approve)
- `admin` / `user` / `kontrak` / `laporan` / `rab` read-only = R00 + R01 + R06 only
- `gudang` = R00, R02 (view), R03, R04, R06
- `pengajuan-sparepart` = everyone except R01, R07
- `workflow_proyek` = everyone except R01

## User Overrides (user_permissions)

- "Test Auto Workflow" (R04): GRANT faktur.create/view, pajak.create/export/view; REVOKE admin.settings/users, aset.create/edit/view, kontrak.create/delete/edit, penawaran.create/view
- "Staff HRD 2" (R07): REVOKE admin.settings/users, aset.create/edit/view

## Login Note

Login form field = **Username** (not email). Superadmin: `superadmin` / `password123` (all users reset 2026-07-31).
