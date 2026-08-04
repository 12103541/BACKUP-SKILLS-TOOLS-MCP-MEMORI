# End-to-End Contract Workflow Testing (ME-CIPALI Pattern)

**Trigger**: User asks to "create end-to-end test for 1 contract from client creation → contract → 100% payment"

**Pattern**: Single PHP script that seeds complete data chain:
1. Klien (client) → 
2. Kontrak (contract) → 
3. KontrakTermin (5 termin: DP 10% + 4×22.5%) → 
4. RAB + RabKomponen (8 items) → 
5. Pekerjaan (5 phases) → 
6. Faktur (5 invoices, all lunas) → 
7. Pembayaran (5 payments, verified) → 
8. Update pekerjaan status → 
9. Recalculate progres → 
10. Assert 100% fisik & keuangan

**Key fixes applied in session**:
- `Pembayaran` model requires `nomor_pembayaran` (auto-generate via `Pembayaran::generateNomorPembayaran()`)
- `FakturItem` model uses table `faktur_items` (plural), fillable needs `no_urut`, `satuan`
- Clean existing test data with raw DB queries before seeding (unique constraints on nomor_kontrak, nomor_penawaran)
- Use `aset` enum value `VMS` (not `led_display`) for Pekerjaan
- `Kontrak::jenis` enum: use `projek` (added via migration 2026_07_29_191844)
- `Rab::hitungTotal()` recalculates total_rab from komponen

**Verification assertions**:
```php
$kontrak->progres_fisik === 100
$kontrak->progres_keuangan === 100
$kontrak->status === 'completed'
Pembayaran::where('kontrak_id', $id)->sum('jumlah') === $kontrak->nilai
```

**Test file created**: `test_me_cipali_full.php` at project root (re-runnable)