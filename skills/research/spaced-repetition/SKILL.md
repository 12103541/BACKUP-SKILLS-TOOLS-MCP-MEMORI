---
name: spaced-repetition
description: "Spaced Repetition System (SRS): optimize learning with increasing-interval reviews. Use for long-term retention, studying documentation, mastering APIs, onboarding."
category: research
tags: [learning, memory, retention, srs, studying, spaced-repetition]
---

# Spaced Repetition

Sistem pengulangan dengan interval yang semakin panjang — pindahkan pengetahuan dari short-term ke long-term memory secara efisien.

## Kapan Pakai Skill Ini

- Kamu/User sedang belajar framework/library baru dan ingin retensi jangka panjang
- User sering lupa syntax / API / command yang jarang dipakai
- User MENGERTI sekarang tapi besok LUPA — butuh sistem review terjadwal
- Onboarding tech stack baru — jadwal review terstruktur
- Persiapan interview / sertifikasi

## Prinsip Dasar

```
Interval ideal:
Review 1: 1 hari setelah belajar
Review 2: 3 hari setelah review 1
Review 3: 7 hari setelah review 2
Review 4: 14 hari setelah review 3
Review 5: 1 bulan setelah review 4

Kalau MASIH INGAT → gandakan interval
Kalau SUDAH LUPA → balik ke interval 1 hari
```

## Workflow

### 1. Capture — Catat Apa yang Dipelajari

Format capture minimal:
```
Konsep: [nama singkat]
Penjelasan: [1-2 kalimat]
Konteks: [kenapa ini penting]
Sumber: [link / file / chapter]
```

Jangan terlalu panjang — maksimal 3 baris per kartu. 
Kalau konsep kompleks, pecah jadi beberapa kartu.

### 2. Schedule — Jadwalkan Review

Hari ke-1 (hari belajar):
- Capture kartu
- Review pertama HARI ITU JUGA (setelah 15-30 menit)

Review schedule:
```
T+1d  →  1 hari setelah capture
T+3d  →  3 hari
T+7d  →  1 minggu
T+14d →  2 minggu
T+30d →  1 bulan
T+90d →  3 bulan
T+180d→  6 bulan (final review — pindah ke long-term memory)
```

### 3. Execute — Proses Review

Setiap review:
1. LIHAT nama konsep (jangan lihat penjelasan dulu)
2. Coba INGAT penjelasannya sendiri — seolah ngajar ke orang lain
3. BANDINGKAN dengan capture asli
4. Nilai: MASIH INGAT / SAMAR / LUPA

Setelah nilai:
| Nilai | Tindakan | Interval berikutnya |
|-------|----------|---------------------|
| MASIH INGAT | Lanjut interval normal | 2x interval |
| SAMAR | Tetap interval yang sama | interval sama |
| LUPA | Reset ke T+1d | 1 hari lagi |

### 4. Consolidate — Setelah Review

Setiap review:
- Kalau LUPA → update kartu: tambah analogi, contoh, atau catatan kenapa lupa
- Kalau SAMAR → baca ulang, coba jelaskan dengan suara sendiri (Feynman Hack!)
- Kalau INGAT → selamat! 1 langkah lebih dekat ke long-term memory

## Format Kartu SRS

```
─── SRS CARD ───
Card: [judul]
Kategori: [php / laravel / js / docker / etc]

[Side A - Konsep]
...

[Side B - Penjelasan (yang harus diingat)]
...

[Analogy / Mental Model]
...

[Last Reviewed: T+x]
[Status: INGAT / SAMAR / LUPA]
[Next Review: T+y]
```

## Contoh SRS Cards

```
─── SRS CARD ───
Card: dd() vs dump()
Kategori: laravel

Side A:
Apa beda dd() dan dump()?

Side B:
dd() = dump + die — stop eksekusi
dump() = dump AJA — lanjut eksekusi
Pakai dd() untuk debugging final, dump() untuk inspeksi sementara

Analogy: dd() = emergency exit, dump() = jendela buat lihat keluar
```

```
─── SRS CARD ───
Card: Eloquent Eager Loading
Kategori: laravel

Side A:
Kapan harus pakai with() vs load()?

Side B:
with() = eager load SEBELUM query — 1 join / 1+n query
load() = lazy load SETELAH query — untuk loaded model
with() = siapin dari awal, load() = ambil tambahan nanti

Analogy: with() = siapin tas sebelum pergi, load() = balik ambil tas
```

## Integrasi dengan Skill Lain

- Dengan **Feynman Hack**: setelah belajar konsep & Feynman-kan, buat SRS card
- Dengan **Dekonstruksi**: setelah dekonstruksi sistem, buat SRS card untuk tiap layer
- Dengan **Socratic Method**: minta user capture insight sebagai SRS card

## Manajemen Deck

### Per Kategori
Buat deck terpisah per kategori (PHP deck, Laravel deck, Docker deck, JS deck).

### Daily Review Limit
Maksimal 10-15 kartu per hari — jangan overload. Kualitas > kuantitas.

### Archiving
3x MASIH INGAT berturut-turut → arsipkan (pindah ke long-term memory, review 6 bulan sekali).

## Variasi

### SRS Quick
Untuk belajar cepat: capture → review T+1d → review T+7d → selesai (3 langkah).

### SRS Full
Untuk sertifikasi / skill kritis: 7 interval penuh (T+1d → T+180d).

### SRS Pair (Feynman + SRS)
1. Belajar konsep
2. Feynman Hack → jelaskan dengan analogi
3. Capture SRS card
4. Review sesuai jadwal
5. Kalau lupa: Feynman lagi dengan analogi baru

### SRS Debug
Aktifkan saat debug error yang jarang — capture root cause + fix sebagai SRS card.
"Error ini muncul kalau [X]. Fix: [Y]. Bedanya dari error mirip [Z]."

## Pitfalls

- JANGAN buat terlalu banyak kartu di hari yang sama — burnout SRS nyata
- Review HARIAN itu kunci — skip 1 hari = skip 3 hari = rusak jadwal
- Kartu yang terlalu panjang (>3 baris) = kartu buruk — pecah
- Jangan hanya baca — harus AKTIF recall (coba ingat dulu, baru lihat jawaban)
- SRS untuk FAKTA (syntax, command, API). Jangan untuk proses kreatif / problem-solving
- Integrasi prefer: Anki, Notion DB, atau Obsidian Spaced Repetition plugin
