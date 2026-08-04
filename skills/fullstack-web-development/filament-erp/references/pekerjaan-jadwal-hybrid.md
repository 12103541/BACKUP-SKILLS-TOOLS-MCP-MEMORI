# Pekerjaan ↔ Jadwal — Hybrid Separation (July 2026)

## Problem
`Jadwal::createFromPekerjaan()` auto-created a Jadwal entry every time a Pekerjaan was assigned to a teknisi. This caused:
- **~70% data redundancy**: deadline, lokasi, teknisi, kontrak_id all duplicated across 2 tables
- **Desync**: changing Pekerjaan.tenggat_waktu did NOT update Jadwal.selesai
- **Confusion**: users couldn't distinguish "Pekerjaan" (job execution) from "Jadwal" (calendar event)

## Decision: Hybrid Model

### Pekerjaan (`🏗️ Proyek`)
| Aspect | Detail |
|--------|--------|
| **Purpose** | Task execution & field work management |
| **Actors** | Teknisi (R02) executes, Supervisor (R03) reviews |
| **Types** | Instalasi, perbaikan, perawatan, pemindahan, survei |
| **Workflow** | `draft → assigned → in_progress → submitted → approved` |
| **Key features** | GPS validation, 3-stage foto documentation (0%/50%/100%), sparepart request, laporan |
| **Link to kontrak** | Mandatory (via kontrak_id FK) |
| **Max teknisi** | 1 per pekerjaan |

### Jadwal (`🏗️ Proyek`, sort=3)
| Aspect | Detail |
|--------|--------|
| **Purpose** | Non-pekerjaan events on the calendar |
| **Types** | Maintenance, Meeting/Rapat, Deadline Administrasi, Lainnya |
| **Workflow** | None — simple event with start/end times |
| **Key features** | FullCalendar JS view, multi-teknisi assignment, kontrak link optional |
| **Link to kontrak** | Optional (collapsed section in form) |
| **Max teknisi** | Multiple (via JadwalTeknisi pivot table) |
| **CRUD** | Admin only (R00/R01/R03/R06); Teknisi read-only |

## Implementation

### 1. Remove auto-creation from Pekerjaan assign
In `PekerjaanResource.php` — `assign_teknisi` action:
```php
// BEFORE:
\App\Models\Jadwal::createFromPekerjaan($record);

// AFTER (comment out):
// HYBRID: jadwal untuk non-pekerjaan events saja
```

### 2. Deprecate Jadwal::createFromPekerjaan()
```php
/**
 * @deprecated HYBRID MODE: Pekerjaan dikelola di menu Pekerjaan.
 * Jadwal hanya untuk non-pekerjaan events (maintenance, meeting, deadline, lainnya).
 * Method ini dipertahankan untuk backward compatibility data lama.
 */
```

### 3. Jadwal form — remove 'pekerjaan' tipe
```php
Select::make('tipe')
    ->options([
        'maintenance' => 'Maintenance',
        'meeting' => 'Meeting / Rapat',
        'deadline' => 'Deadline Administrasi',
        'lainnya' => 'Lainnya',
    ])
    ->default('maintenance')
    ->helperText('Pekerjaan dikelola di menu Pekerjaan. Jadwal ini untuk kegiatan non-pekerjaan.');
```

### 4. Jadwal model — cleanup constants
```php
const TIPE = ['maintenance', 'meeting', 'deadline', 'lainnya'];
const WARNA = [
    'maintenance' => '#f59e0b',
    'meeting' => '#8b5cf6',
    'deadline' => '#ef4444',
    'lainnya' => '#6b7280',
];
```

### 5. Jadwal validation — kontrak_id not required
```php
protected function validationRules(): array
{
    return [
        'judul' => 'required|string|min:3',
        'mulai' => 'required|date',
        'selesai' => 'required|date|after_or_equal:mulai',
    ];
}
```

### 6. Sidebar — both under `🏗️ Proyek`
```
🏗️ Proyek
  ├─ Kontrak       (sort=1)
  ├─ Pekerjaan     (sort=2) — execution + workflow + dokumentasi
  ├─ Jadwal        (sort=3) — calendar events, non-pekerjaan
  ├─ Klien         (sort=4)
  ├─ Aset          (sort=5)
  └─ Laporan Pekerjaan (sort=7)
```

## Data Migration
Existing Jadwal records with `tipe = 'pekerjaan'` remain in DB — they are NOT deleted and NOT recreated. They serve as historical calendar entries.

## Pitfalls
- **Notification text** that mentions "Jadwal otomatis dibuat" must be removed from assign_teknisi action
- **Jadwal table badge colors** for 'pekerjaan' tipe (primary blue) must be removed from configuration
- **Filter options** in Jadwal table must exclude 'pekerjaan'
- **CalendarJadwal page** (FullCalendar) inherits the filtered query — it will show only non-pekerjaan events automatically
- Old `createFromPekerjaan()` method is kept for backward compatibility but should not be called from new code
