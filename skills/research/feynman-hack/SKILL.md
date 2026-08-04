---
name: feynman-hack
description: "Feynman Technique: explain concepts in ultra-simple terms, find knowledge gaps, iterate until mastery. Use for learning, teaching, debugging understanding."
category: research
tags: [learning, teaching, thinking, simplification, knowledge-gap]
---

# Feynman Hack

Teknik belajar Feynman — menjelaskan konsep dengan bahasa paling sederhana sampai benar-benar paham.

## Kapan Pakai Skill Ini

- User bertanya konsep kompleks dan perlu benar-benar MEMAHAMI (bukan sekadar tahu)
- User stuck debug karena tidak paham dasar dari konsep yang dipakai
- User minta "jelaskan seolah saya anak kecil"
- User ingin mengajar / menjelaskan sesuatu ke orang lain
- Setelah belajar sesuatu, ingin verify pemahaman

## Workflow (4 Langkah)

### 1. Pilih Konsep

Apa konsep yang ingin dipahami?
→ Namakan dengan jelas. Contoh: "HTTP Request-Response Cycle"

### 2. Jelaskan dengan Bahasa Anak 5 Tahun

Gunakan aturan:
- NO jargon teknis tanpa analogi
- Analogi dari kehidupan sehari-hari
- Kalimat pendek, satu ide per kalimat
- Gunakan "bayangkan...", "seperti...", "artinya..."
- Boleh pakai gambar ASCII / diagram sederhana

Contoh output:

```
Bayangkan kamu mau kirim surat ke temen.

1. Kamu tulis suratnya         → REQUEST (kamu minta data)
2. Kamu kasih ke pos           → KIRIM ke SERVER
3. Pos baca alamatnya          → SERVER proses
4. Pos balas surat ke kamu     → RESPONSE (data dikirim balik)

Kalau alamatnya salah? → 404 Not Found
Kalau temennya ga ada? → 503 Service Unavailable
Kalau suratnya terlalu panjang? → 413 Payload Too Large
```

### 3. Identifikasi Gap

Setelah menjelaskan, tanya diri sendiri:

Mana bagian yang MASIH BERBELIT BELUM JELAS?
→ Jika ada: kembali ke sumber belajar, pelajari bagian itu
→ Jika tidak ada: lanjut ke langkah 4

Kadang user tidak langsung tahu gap-nya. Triknya:
- "Bisa jelaskan KENAPA X bekerja begitu?" — kalau mulai gagap = gap
- "Apa yang terjadi kalau X tidak ada?" — tes pemahaman
- "Apa bedanya X dan Y?" — kalau jawabannya ambigu = gap

### 4. Simplifikasi & Ulang

- Buat versi yang LEBIH sederhana dari versi sebelumnya
- Tambah analogi baru untuk bagian yang masih berat
- Ulangi sampai tidak ada jargon tersisa yang belum bisa dijelaskan

## Variasi

### Feynman Hack - Quick (1 langkah)
Untuk pertanyaan cepat: langsung jawab dengan analogi sederhana, skip formal workflow.

### Feynman Hack - Deep (4 langkah penuh)
Untuk konsep kompleks: jalani semua 4 langkah, minta user konfirmasi setiap langkah.

### Feynman Hack - Debug Mode
Untuk debug: jelaskan KENAPA error terjadi (bukan cuma cara fix) dengan analogi sederhana.

## Pitfalls

- Jangan tergoda pakai jargon "supaya terdengar pintar" — itu tanda belum paham
- Analogi harus ACCURATE, bukan cuma catchy — kalau analogi punya lubang, ganti
- Kalau tidak bisa dijelaskan sederhana = belum paham, balik ke langkah 1
- Feynman Hack untuk konsep SANGAT luas (misal "AI"): pecah dulu jadi sub-konsep kecil
