# Terbilang Converter — Implementation Reference

PHP number-to-words helper for Indonesian Rupiah (`app/Helpers/Terbilang.php`).

## Architecture

Two-method pattern:
- `Terbilang::convert(int $number): string` — public entry: guards `<= 0`, appends `" Rupiah"`
- `Terbilang::terbilang(int $number): string` — protected recursive: pure digit-to-words

## Number Ranges & Output

| Range | Rule | Example |
|-------|------|---------|
| `<= 0` | Return `"Nol Rupiah"` | `0` → `"Nol Rupiah"` |
| `1-11` | Static word lookup | `1` → `"Satu"`, `11` → `"Sebelas"` |
| `12-19` | Digit + `" Belas"` | `12` → `"Dua Belas"` |
| `20-99` | Tens + `" Puluh"` + digit | `21` → `"Dua Puluh Satu"` |
| `100` | Exact: `"Seratus"` | `100` → `"Seratus"` |
| `101-199` | `"Seratus "` + terbilang(remainder) | `110` → `"Seratus Sepuluh"` |
| `200-999` | Digit + `" Ratus "` + terbilang(remainder) | `250` → `"Dua Ratus Lima Puluh"` |
| `1.000` | `"Seribu"` (atomic, not `"Se" . "Ribu"`) | `1000` → `"Seribu"` |
| `1.001-1.999` | `"Seribu "` + terbilang(remainder) | `1500` → `"Seribu Lima Ratus"` |
| `2.000+` | Segment loop: unit per 3 digits | `1.000.000` → `"Satu Juta"` |

## Complete Implementation

```php
<?php

namespace App\Helpers;

class Terbilang
{
    protected static array $words = [
        0 => '', 1 => 'Satu', 2 => 'Dua', 3 => 'Tiga', 4 => 'Empat',
        5 => 'Lima', 6 => 'Enam', 7 => 'Tujuh', 8 => 'Delapan', 9 => 'Sembilan',
        10 => 'Sepuluh', 11 => 'Sebelas',
    ];

    public static function convert($number): string
    {
        $number = (int) $number;
        if ($number <= 0) return 'Nol Rupiah';   // ← guard #5

        $result = self::terbilang($number);
        return trim($result) . ' Rupiah';          // ← always append
    }

    protected static function terbilang($number): string
    {
        if ($number < 12) return static::$words[$number] ?? '';  // ← fix #1

        if ($number < 20) {                                       // ← 12-19
            $digit = $number % 10;
            return static::$words[$digit] . ' Belas';
        }

        if ($number < 100) {                                      // ← 20-99
            $ten = floor($number / 10);
            $unit = $number % 10;
            $result = static::$words[$ten] . ' Puluh';
            if ($unit > 0) $result .= ' ' . static::$words[$unit];
            return $result;
        }

        if ($number == 100) return 'Seratus';                     // ← fix #2
        if ($number < 200) return 'Seratus ' . self::terbilang($number - 100);

        if ($number < 1000) {
            $hundred = floor($number / 100);
            $remainder = $number % 100;
            $result = static::$words[$hundred] . ' Ratus';
            if ($remainder > 0) $result .= ' ' . self::terbilang($remainder);
            return $result;
        }

        $units = ['', 'Ribu', 'Juta', 'Milyar', 'Triliun'];
        $result = '';

        foreach ($units as $i => $unit) {
            if ($i == 0) continue;
            $segment = floor($number / pow(1000, $i)) % 1000;
            if ($segment == 0) continue;

            $prefix = ($segment == 1 && $i == 1)
                ? 'Seribu'                                       // ← fix #4
                : self::terbilang($segment) . ' ' . $unit;

            $result = $prefix . ' ' . $result;
        }

        $remainder = $number % 1000;
        if ($remainder > 0) $result .= self::terbilang($remainder) . ' ';

        return trim($result);
    }
}
```

## Test Cases

```php
// Edge cases
assert(Terbilang::convert(0)         === 'Nol Rupiah');
assert(Terbilang::convert(1)         === 'Satu Rupiah');
assert(Terbilang::convert(10)        === 'Sepuluh Rupiah');
assert(Terbilang::convert(11)        === 'Sebelas Rupiah');
assert(Terbilang::convert(12)        === 'Dua Belas Rupiah');
assert(Terbilang::convert(19)        === 'Sembilan Belas Rupiah');
assert(Terbilang::convert(20)        === 'Dua Puluh Rupiah');
assert(Terbilang::convert(21)        === 'Dua Puluh Satu Rupiah');

// Hundreds
assert(Terbilang::convert(100)       === 'Seratus Rupiah');
assert(Terbilang::convert(110)       === 'Seratus Sepuluh Rupiah');
assert(Terbilang::convert(150)       === 'Seratus Lima Puluh Rupiah');
assert(Terbilang::convert(199)       === 'Seratus Sembilan Puluh Sembilan Rupiah');
assert(Terbilang::convert(200)       === 'Dua Ratus Rupiah');
assert(Terbilang::convert(999)       === 'Sembilan Ratus Sembilan Puluh Sembilan Rupiah');

// Thousands
assert(Terbilang::convert(1000)      === 'Seribu Rupiah');
assert(Terbilang::convert(1001)      === 'Seribu Satu Rupiah');
assert(Terbilang::convert(2000)      === 'Dua Ribu Rupiah');
assert(Terbilang::convert(11000)     === 'Sebelas Ribu Rupiah');
assert(Terbilang::convert(19000)     === 'Sembilan Belas Ribu Rupiah');
assert(Terbilang::convert(21000)     === 'Dua Puluh Satu Ribu Rupiah');
assert(Terbilang::convert(100000)    === 'Seratus Ribu Rupiah');
assert(Terbilang::convert(110000)    === 'Seratus Sepuluh Ribu Rupiah');

// Millions
assert(Terbilang::convert(1000000)   === 'Satu Juta Rupiah');
assert(Terbilang::convert(100000000) === 'Seratus Juta Rupiah');
assert(Terbilang::convert(100000001) === 'Seratus Juta Satu Rupiah');

// Max test
assert(Terbilang::convert(999999999) === 'Sembilan Ratus Sembilan Puluh Sembilan Juta Sembilan Ratus Sembilan Puluh Sembilan Ribu Sembilan Ratus Sembilan Puluh Sembilan Rupiah');
```

## Known Bug History

| Date | Bug | Fix |
|------|-----|-----|
| Jul 2026 | `Terbilang::convert(100000000)` → `"Seratus Nol Rupiah Juta Rupiah"` | 5 fixes: `<=0` guard, `<12` early return, `100` exact, `Seribu` atomic, clean 0 remainder skip |
