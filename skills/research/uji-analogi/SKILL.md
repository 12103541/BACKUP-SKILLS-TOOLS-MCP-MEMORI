---
name: uji-analogi
description: "Analogy Testing Framework: validate analogies for accuracy, boundaries, and misleading mappings. Use to check if an analogy teaches or misleads."
category: research
tags: [analogy, validation, teaching, thinking, critical-thinking]
---

# Uji Analogi

Kerangka untuk menguji apakah suatu analogi AKURAT atau MENYESATKAN.

## Kapan Pakai Skill Ini

- Kamu (atau user) menggunakan analogi untuk menjelaskan sesuatu — yakin analoginya tepat?
- User menantang analogi yang kamu beri — validasi dengan framework
- Sebelum mengajar konsep baru dengan analogi — pastikan tidak membentuk mental model yang salah
- Code review / architecture discussion — analogi "ini seperti..." perlu diuji
- Peer review: teman/kolega pakai analogi yang "terdengar" benar — cek

## Framework Uji (5 Dimensi)

### 1. Mapping Test — Apakah mappingnya 1:1?

Cocokkan setiap elemen analogi dengan elemen konsep asli:

| Analogi (Source) | Konsep (Target) | Cocok? | Catatan |
|------------------|-----------------|--------|---------|
| ...              | ...             | ✓/✗/~ | ...     |

**Red flag**: Ada elemen source yang tidak punya mapping → analogi berlebih (overextension)
**Red flag**: Ada elemen target yang tidak punya mapping → analogi kurang (underextension)

### 2. Boundary Test — Sampai mana analogi ini berlaku?

- Skenario normal: analogi masih masuk akal?
- Edge case: kalau input ekstrem, analogi masih jalan?
- Kalau ada ERROR / exception, analogi tetap relevan?

Contoh: "Database itu seperti lemari arsip"
- Normal ✓: masukin file, ambil file, urutkan
- Edge ✗: lemari arsip tidak punya indexing B-tree, ACID transaction, replication

### 3. Misleading Test — Apakah analogi MENYESATKAN?

Pertanyaan kunci:
- "Kalau user percaya analogi ini SEPENUHNYA, kesimpulan apa yang mereka tarik?"
- "Apakah ada properti source yang TIDAK ADA di target?"
- "Apakah user akan membuat keputusan SALAH karena analogi ini?"

Contoh: "Cloud itu seperti langit — data 'mengambang' di internet"
- Misleading: Data tidak mengambang — ada server fisik di lokasi tertentu
- User mungkin pikir data "tidak ada di mana-mana" padahal compliance memerlukan lokasi geografis

### 4. Simplicity Test — Apakah analogi lebih sederhana dari konsep asli?

- Analogi harus LEBIH MUDAH dipahami daripada konsep asli
- Kalau analogi sama kompleksnya → analogi gagal
- Kalau perlu analogi untuk menjelaskan analogi → overcomplicated

**Rule of thumb**: Analogi yang baik = 1 paragraf, 1 ide, 0 jargon baru.

### 5. Robustness Test — Apakah analogi tahan banting?

- Coba 3 pertanyaan probing:
  1. "Kalau skenario X terjadi, apa yang terjadi di analogi?"
  2. "Apa perbedaan terbesar antara analogi dan konsep asli?"
  3. "Kalau user hanya ingat analogi ini 1 minggu lagi, mental model apa yang tersisa?"

## Skor Kelayakan Analogi

| Skor | Status | Tindakan |
|------|--------|----------|
| ✅ 5/5 | Strong | Bisa dipakai langsung |
| ✅ 4/5 | Good | Catat caveat, beri disclaimer |
| ⚠️ 3/5 | Moderate | Bisa dipakai KALAU tambah disclaimer EXPLICIT |
| ❌ 2/5 | Weak | Jangan dipakai — ganti analogi lain |
| ❌ 1/5 | Dangerous | Berbahaya — bisa bentuk mental model salah |

## Format Output

```
═══ UJI ANALOGI ═══

Analogi: [analogi]
Konsep:  [konsep asli]

1. Mapping:     [✓/✗/~] — [catatan mapping lemah]
2. Boundary:    [✓/✗/~] — [kelemahan boundary]
3. Misleading:  [✓/✗]   — [potensi misinterpretasi]
4. Simplicity:  [✓/✗]   — [apakah lebih sederhana]
5. Robustness:  [✓/✗]   — [tahan probing?]

Skor: [N/5] — [Strong/Good/Moderate/Weak/Dangerous]

── Rekomendasi ──
[Paka/Ganti/Tambah caveat: ...]

── Alternatif Analogy ──
[Kalau perlu ganti]
```

## Contoh Lengkap

```
═══ UJI ANALOGI ═══

Analogi: "Migrations database itu seperti meng-upgrade resep masakan"
Konsep:  "Schema migration (ELoquent)"

1. Mapping:
   - Menambah bahan  = Menambah kolom       ✓
   - Mengganti teknik = Mengubah tipe data   ✓
   - Resep gagal      = Migration rollback   ✓
   - Masak lagi       = Migrate:fresh        ~ (tidak semua migration bisa rollback)

2. Boundary:
   - Resep tidak punya konsep FOREIGN KEY constraint — mapping lemah ✗
   - Migrations production bisa protract — resep tidak ✗

3. Misleading:
   - User mungkin pikir migration selalu aman di-rollback — tidak selalu (no down method) ✗

4. Simplicity:
   - "Ubah resep" lebih simpel dari "DDL statements — ALTER TABLE" ✓

5. Robustness:
   - 1 minggu lagi user ingat "migration = ubah resep" — cukup akurat untuk konsep dasar ✓

Skor: 3/5 — MODERATE

── Rekomendasi ──
Pakai dengan caveat: "tapi migration bisa gagal total tanpa rollback kalau no down method"

── Alternatif ──
"Migrations seperti git commit untuk database schema — ada history, bisa revert, bisa conflict"
```

## Variasi

### Uji Cepat (Mapping + Misleading)
Kalau lagi diskusi cepat: hanya cek 2 dimensi paling kritis.

### Uji Lengkap (5 dimensi)
Untuk materi ajar / dokumentasi / sebelum dipublikasi.

### Peer Review Analogi
Minta user menguji analogi yang kamu buat — biar mereka kritis juga.

## Pitfalls

- Jangan over-kill — uji hanya untuk analogi yang AKAN DIGUNAKAN untuk mengajar, bukan setiap perbandingan ringan
- Skor 3/5 boleh dipakai asal ada caveat — tidak semua analogi harus sempurna
- Tujuan uji analogi BUKAN membunuh analogi, tapi memastikan user tidak salah paham
- Kalau analogi gagal, SARANKAN alternatif — jangan cuma bilang "salah"
- Perhatikan budaya — analogi yang masuk akal di satu budaya bisa aneh di budaya lain
