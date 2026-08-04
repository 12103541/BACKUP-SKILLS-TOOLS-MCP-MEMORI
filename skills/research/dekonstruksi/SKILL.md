---
name: dekonstruksi
description: "Deconstruction: break complex concepts into fundamental components. Peel layers until you reach first principles. Use for reverse-engineering, problem-solving, architecture analysis."
category: research
tags: [deconstruction, first-principles, analysis, reverse-engineering, thinking]
---

# Dekonstruksi

Memecah konsep kompleks sampai ke komponen paling dasar — first principles thinking versi praktis.

## Kapan Pakai Skill Ini

- Menghadapi sistem/kode yang terlalu kompleks dan perlu dipahami dari dasar
- Mau reverse-engineer sesuatu (API, algoritma, arsitektur, business process)
- User bilang "ini rumit banget" — bantu bongkar
- Mau bedah kenapa sesuatu bekerja (atau tidak bekerja)
- Inovasi: setelah dekonstruksi, rekonstruksi ulang dengan cara baru

## Workflow (5 Langkah)

### 1. Surface Layer — Apa yang KELIHATAN?

Identifikasi apa yang tampak di permukaan:
- Output / behaviour apa yang dihasilkan?
- Input apa yang masuk?
- Interface / API apa yang diekspos?
- User experience-nya seperti apa?

Goal: dokumentasikan apa yang TAMPAK tanpa interpretasi.

### 2. Functional Layer — Apa yang DILAKUKAN?

Break down fungsi-fungsi:
- Langkah demi langkah prosesnya
- Komponen apa saja yang terlibat
- Data flow: input → transformasi → output
- Dependency: siapa bergantung pada siapa

Goal: pahami FUNGSI setiap bagian, bukan cara kerjanya.

### 3. Structural Layer — Bagaimana STRUKTURNYA?

Break down hubungan:
- Arsitektur / hirarki komponen
- Interface antar komponen
- State management
- Data model / schema

Goal: peta hubungan antar bagian.

### 4. Principle Layer — Apa PRINSIP DASARNYA?

Ini tahap first principles:
- Hukum/fakta APA yang membuat ini bekerja?
- Matematika / fisika / logika dasar di belakangnya?
- Kenapa pakai pendekatan ini dan bukan yang lain?
- Apa yang TIDAK BISA diubah (constraint fundamental)?

Goal: temukan bedrock — pengetahuan yang pasti benar.

### 5. Rekonstruksi — Kalau BIKIN ULANG, gimana?

Setelah semua layer terbongkar:
- Mana bagian yang bisa disederhanakan?
- Mana bagian yang bisa dieliminasi?
- Kalau mulai dari nol, apa yang akan KAMU buat?

Goal: insight untuk inovasi / simplifikasi / improvement.

## Format Output

```
═══ DEKONSTRUKSI ═══

[Konsep]: [nama]

Layer 1 — Surface:
   Input: ...
   Output: ...
   Interface: ...

Layer 2 — Functional:
   1. ...
   2. ...

Layer 3 — Structural:
   - Komponen A ↔ B
   - Dependency: A → B → C

Layer 4 — First Principles:
   🔹 Prinsip fundamental: ...

Layer 5 — Rekonstruksi:
   💡 Ide improvement: ...
```

## Contoh Penerapan

### Dekonstruksi Error
```
[Konsep]: "Fatal error: Call to a member function on null"

Layer 1: Error muncul saat method dipanggil pada variable null
Layer 2: Variable tidak diinisialisasi / return null dari function
Layer 3: Chain panggilan method tanpa null check
Layer 4: Objects perlu instantiation sebelum method dipanggil — immutable law
Layer 5: Pakai null object pattern / optional chaining
```

### Dekonstruksi Business Process
```
[Konsep]: "Operasional Kantor — petty cash"

Layer 1: Karyawan minta uang → beli ATK → laporan
Layer 2: Request → Approval → Pencairan → Pembelian → Pelaporan → Reimbursement
Layer 3: Karyawan → Manager → Finance → Karyawan → Finance
Layer 4: Setiap transaksi uang perlu 2 pihak (approver + executor) dan bukti
Layer 5: Bisa pakai virtual card instead of cash — eliminate reconciliation
```

### Dekonstruksi Architecture
```
[Konsep]: "Laravel Service Container"

Layer 1: Class bisa di-resolve tanpa new, dependencies otomatis masuk
Layer 2: Bind → Resolve → Inject
Layer 3: Container holds binding map → Reflection resolver → Dependency chain
Layer 4: IoC principle — high-level modules jangan depend ke low-level modules, both depend ke abstraction
Layer 5: Auto-wiring bisa diputer manual untuk performance — cached container
```

## Variasi

### Quick Dekonstruksi (3 layer)
Untuk masalah cepat: Surface → Function → Principle. Skip Structural & Rekonstruksi.

### Full Dekonstruksi (5 layer)
Untuk sistem kompleks / arsitektur / bisnis.

### Dekonstruksi Debug
Layer 1: Error message
Layer 2: Call stack (langkah-langkah)
Layer 3: Dependency & state
Layer 4: Root cause fundamental
Layer 5: Fix struktural vs temporary

## Pitfalls

- Jangan loncat ke Layer 5 (rekonstruksi) sebelum Layer 1-4 selesai — bias solusi
- First principles BUKAN "pendapat orang pintar" — cari yang benar-benar fundamental
- Analogi boleh, tapi tandai sebagai analogi — bukan fakta
- Kalau mentok di Layer 4, berarti belum cukup paham domainnya — research dulu
- Dekonstruksi BUKAN kritik — ini analisis netral
