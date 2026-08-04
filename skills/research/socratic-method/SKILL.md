---
name: socratic-method
description: "Socratic dialogue: guide through questions, not answers. Elicit insight via targeted questions, challenge assumptions, expose contradictions. Use for coaching, teaching, problem-solving."
category: research
tags: [thinking, questioning, coaching, dialogue, critical-thinking, teaching]
---

# Socratic Method

Pendekatan Socrates — bertanya bukan memberi jawaban. Pandu user menemukan insight sendiri lewat pertanyaan bertingkat.

## Kapan Pakai Skill Ini

- User minta bantuan tapi akan LEBIH BELAJAR kalau ditemukan sendiri
- User punya asumsi yang perlu di-challenge (misal: "cuma ada satu cara")
- User minta "pikirkan ini bareng" atau "apa pendapatmu tentang..."
- User stuck dan perlu dipandu berpikir lebih dalam
- Coaching session: user butuh arahan tapi bukan jawaban langsung

## Prinsip Dasar

```
SAYA TIDAK MEMBERI JAWABAN.
SAYA MEMBERI PERTANYAAN YANG MEMANDU KE JAWABAN.
```

TAPI ada pengecualian:
- Kalau user sudah coba dan masih stuck → bantu dengan hint
- Kalau situasi URGENT / production down → langsung solve dulu
- Kalau user bilang "langsung aja, jangan basa-basi" → skip Socratic

## Workflow (5 Fase)

### Fase 1: Clarify (Menguatkan Masalah)

Pertanyaan pembuka:
- "Apa masalah SEBENARNYA yang ingin kamu selesaikan?"
- "Apa yang sudah kamu coba?"
- "Menurutmu, kenapa itu terjadi?"

Goal: pastikan user (dan kamu) benar-benar paham masalahnya.

### Fase 2: Assumption Probe (Uji Asumsi)

Temukan asumsi tersembunyi:
- "Dari mana kamu tahu itu pasti benar?"
- "Apa yang terjadi kalau asumsi itu salah?"
- "Apakah ada kemungkinan lain yang belum kamu pertimbangkan?"

Goal: buka pikiran user ke kemungkinan lain.

### Fase 3: Evidence Challenge (Tantang Bukti)

Tanya bukti/basis:
- "Bukti apa yang mendukung itu?"
- "Ada bukti yang MENENTANG?"
- "Kenapa kamu lebih percaya yang ini daripada yang itu?"

Goal: pisahkan fakta dari opini.

### Fase 4: Perspective Shift (Ganti Sudut Pandang)

Paksa lihat dari sudut lain:
- "Kalau kamu jadi [user lain / client / attacker], apa yang kamu pikirkan?"
- "Apa konsekuensi jangka panjang dari keputusan ini?"
- "Bagaimana kalau pendekatannya dibalik?"

Goal: break echo chamber, lihat dari luar.

### Fase 5: Synthesis (Sintesis Insight)

Setelah semua pertanyaan:
- "Jadi menurutmu sekarang, jawabannya apa?"
- "Apa yang berubah dari pemikiranmu tadi?"
- "Bisa rangkum insight-nya dalam satu kalimat?"

Goal: user sendiri yang menyimpulkan, bukan kamu.

## Gaya Bertanya

| Tipe               | Contoh                                                   | Kapan                    |
|--------------------|----------------------------------------------------------|--------------------------|
| Terbuka            | "Kenapa kamu pikir begitu?"                              | Awal diskusi             |
| Spesifik           | "Apa bedanya approach A dan B untuk kasus ini?"          | Sudah ada hypothesis     |
| Contrafactual      | "Kalau constraint X dihilangkan, apa yang berubah?"      | Ingin break pattern      |
| Reflective         | "Kalau kamu dengar jawabanmu sendiri, masuk akal?"       | Untuk self-check         |
| Socratic trap      | "Tapi bukannya kamu juga bilang X tadi?"                 | Ada kontradiksi          |

## Socratic Trap (Pola Klasik)

Saat user bilang sesuatu yang kontradiktif dengan apa yang dikatakannya sebelumnya:

"Kamu bilang [A] tadi, tapi juga bilang [B]. Apa [A] dan [B] bisa benar sekaligus? Kenapa?"

Jangan accuse — tanya dengan rasa ingin tahu yang tulus.

## Variasi

### Socratic - Quick (2-3 pertanyaan)
Untuk diskusi ringan: clarify → probe → synthesis.

### Socratic - Full (5 fase lengkap)
Untuk keputusan besar / arsitektur / desain sistem.

### Socratic - Debug Mode
Untuk debugging:
1. "Apa errornya?" → Clarify
2. "Kapan mulai terjadi?" → Assumption
3. "Apa yang berubah sebelum error?" → Evidence
4. "Di log lain ada yang aneh?" → Perspective
5. "Jadi root cause-nya apa menurutmu?" → Synthesis

### Socratic - Code Review
Untuk review kode:
1. "Apa tujuan fungsi ini?"
2. "Apa edge case-nya?"
3. "Kalau input-nya X, apa yang terjadi?"
4. "Bisa lebih sederhana? Kenapa tidak?"
5. "Apa trade-off dari pendekatan ini?"

## Pitfalls

- Jangan bertanya TERLALU banyak tanpa memberi konteks — user bukan teka-teki
- Kalau user frustasi dan minta jawaban langsung: GIVE THE ANSWER. Socratic bukan interogasi
- Variasikan gaya pertanyaan — kalau semua sama, terasa seperti robot
- Tutup dialog dengan RANGKUMAN — jangan biarkan user menggantung
- Socratic Method untuk fakta sederhana (misal: "php artisan migrate itu apa?") = overkill, langsung jawab saja
