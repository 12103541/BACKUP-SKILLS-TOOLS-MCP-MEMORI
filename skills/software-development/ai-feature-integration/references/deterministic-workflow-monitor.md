# Deterministic workflow monitor (L1 pattern)

Built 2026-08-01 for PT EXFERIA PUTRA INOVASI ERP (Laravel 11 + Filament, Windows/Laragon). Pattern: cron command → service of pure-SQL checks → InAppNotification. Zero LLM.

## Service shape (WorkflowMonitorService)

- `run()` calls each check method, returns `['count' => N, 'judul' => '...']` per check.
- Each check: query models for stuck states → loop → `notify($role, ...)`.
- Checks used (workflow KLIEN→KONTRAK→RAB→PENAWARAN→PEKERJAAN→APPROVAL→FAKTUR→LUNAS):
  1. kontrak active/draft tanpa RAB → R01 + R06
  2. RAB orphan (`kontrak_id` null, `is_active` true) → R01
  3. RAB draft > 14 hari belum final (final = `is_active` false, konvensi sistem) → R01 + R06
  4. pekerjaan `submitted` > 3 hari tanpa approval → R02 + R01
  5. faktur `jatuh_tempo` < now → R05 + R01 (danger)
  6. kontrak active lewat `tgl_akhir` → R01 + R06
  7. penawaran expired (`DATE_ADD(tanggal_penawaran, INTERVAL masa_berlaku DAY) < CURDATE()`, status != disetujui) → R01

## Dedup (critical for hourly cron)

```php
private function notify(string $role, string $judul, string $pesan, string $tipe, string $link): void
{
    $already = InAppNotification::where('link', $link)
        ->where('is_read', false)
        ->where('created_at', '>', now()->subHours(20))
        ->exists();
    if ($already) return;
    InAppNotification::sendToRole($role, $judul, $pesan, $tipe, $link);
}
```

Dedup key = unread notification with same `link` within 20h. Without this, hourly cron spams the bell.

## Registration

`routes/console.php`:

```php
Artisan::command('workflow:monitor', function () {
    $result = app(\App\Services\WorkflowMonitorService::class)->run();
    // print per-check counts, summary
})->purpose('...');

Schedule::command('workflow:monitor')->hourly();
```

## Verification recipe

1. `php artisan workflow:monitor` → expect findings + notification rows created
2. Run AGAIN → notification count for the links must stay flat (dedup works)
3. `php artisan schedule:list` → hourly entry present
4. Browser: login (superadmin/password123 on this project), check bell + affected pages

## Notes

- Role codes: R00 superadmin, R01 admin proyek, R02 supervisor, R04 gudang, R05 keuangan, R06 manajer/direktur. `InAppNotification::sendToRole($role, $judul, $pesan, $tipe, $link)` handles routing; `UserNotificationPreference` can suppress per-type.
- This project has duplicate schedule registrations (same commands in `bootstrap/app.php` AND `routes/console.php`) — `schedule:list` shows each twice; harmless, don't "fix" blindly.
- Real run caught an orphan RAB immediately ("Test AI RAB" with no kontrak) — good demo that stuck data exists and monitor works.
