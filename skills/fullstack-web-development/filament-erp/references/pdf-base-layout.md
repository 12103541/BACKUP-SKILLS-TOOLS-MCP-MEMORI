# PDF Base Layout Pattern

## Problem
4+ document-type PDFs (penawaran, faktur, RAB, invoice) each duplicated company header, logo, signature, footer, bank info, notes. Editing header/signature required touching every file.

## Solution
`resources/views/pdf/base.blade.php` — shared layout. Child extends it with content-only sections.

### Base handles:
- Company logo + profile (from `CompanySetting`)
- Document title area (`@yield('doc_title')`)
- Recipient (`@yield('kepada')`)
- Info table (`@yield('info')`)
- Main content table (`@yield('content')`)
- Terbilang (`@yield('terbilang')`)
- Default notes (per doc-type from `CompanySetting`)
- Bank info (hidden for RAB)
- Signature (director name + ttd image)
- Footer

### Doc-type-specific settings
Base auto-reads from `CompanySetting` using `$docType` prefix:
```php
$docPrefix = $docType ?? 'penawaran';
$showProfile = App\Models\CompanySetting::get($docPrefix . '_show_company_profile', 'true') === 'true';
$showSignature = App\Models\CompanySetting::get($docPrefix . '_show_signature', 'true') === 'true';
$defaultNotes = App\Models\CompanySetting::get($docPrefix . '_default_notes', '');
```

### Child example (penawaran)
```blade
@php $docType = 'penawaran'; $docNumber = $penawaran->nomor_penawaran; @endphp
@extends('pdf.base', ['docType' => $docType, 'docNumber' => $docNumber])

@section('doc_title', 'SURAT PENAWARAN')
@section('doc_subtitle', 'No. ' . $penawaran->nomor_penawaran)
@section('kepada', $penawaran->nama_klien)

@section('info')
<table class="info-table">
    <tr><td>Tanggal</td><td>{{ $penawaran->tanggal_penawaran->format('d F Y') }}</td></tr>
    <tr><td>Masa Berlaku</td><td>{{ $penawaran->masa_berlaku }} hari</td></tr>
</table>
@endsection

@section('content')
<table class="items">
    <thead><tr><th>No</th><th>Uraian</th><th>Qty</th><th>Harga</th><th>Jumlah</th></tr></thead>
    <tbody>@foreach($penawaran->items as $i => $item) ... @endforeach</tbody>
</table>
<table class="summary-table"><tr class="grand-line">...</tr></table>
@endsection
```

## Latest Fixes (2026-07-30)

### 1. Compact 1-Page Layout
Reduced margins, padding, font sizes to fit all content on single A4 page:

```css
@page { margin: 8mm 8mm 12mm 8mm; }
body { font-size: 9.5px; padding: 3px 12px; line-height: 1.25; }
.header-logo-cell { .company-logo { max-height: 75px; max-width: 140px; } }
.header-company-name { font-size: 15px; margin-bottom: 5px; }
.header-address, .header-contact { font-size: 9.5px; }
.info-table td { padding: 1px 4px; font-size: 8.5px; }
table.items th { padding: 3px 5px; font-size: 8.5px; }
table.items td { padding: 2px 5px; font-size: 8.5px; }
.signature-img { max-height: 70px; max-width: 160px; }
.footer { bottom: 8px; font-size: 7.5px; }
```

### 2. Grand Total - "Rp" + Value Merged (Not Separated)
Problem: Old layout put "GRAND TOTAL" in one cell and "Rp 100.000.000" in another → visual gap.

Solution: Use flex/inline-block in single cell:

**CSS (base.blade.php):**
```css
.grand-total { text-align: right; }
.grand-label { font-size: 11px; font-weight: bold; color: #333; display: inline-block; margin-right: 10px; }
.grand-value { font-size: 14px; font-weight: bold; color: #1a1a2e; white-space: nowrap; }
```

**Child template (penawaran/faktur/rab):**
```blade
<table class="summary-table">
    <tr><td class="grand-total">
        <span class="grand-label">GRAND TOTAL</span>
        <span class="grand-value">Rp {{ number_format($grandTotal, 0, ',', '.') }}</span>
    </td></tr>
</table>
```

### 3. Signature Image Larger + Company Name in Signature Block
```css
.signature-img { max-height: 70px; max-width: 160px; }
.signature-wrapper .company { font-size: 9px; color: #666; margin-bottom: 18px; }
.signature-wrapper .name { margin-top: 0; font-weight: bold; font-size: 9.5px; }
```

### 4. Header: Logo Left, Company Info Centered (per user template)
```html
<table class="header-table">
    <tr>
        <td class="header-logo-cell">
            <img src="..." class="company-logo">
        </td>
        <td class="header-info-cell">
            <div class="header-company-name">{{ $companyName }}</div>
            <div class="header-address">{{ $companyAddress }}</div>
            <div class="header-contact">Telp. {{ $companyPhone }}</div>
            <div class="header-contact">Email: {{ $companyEmail }}</div>
        </td>
    </tr>
</table>
<hr style="border: none; border-top: 1.5px solid #000; margin: 3px 0;">
```

### 5. Preview Button on Settings Page
Added "Preview PDF" button to `ProfilTemplatePage` that:
1. Generates PDF from **current form state** (not saved DB)
2. Saves to `storage/app/public/previews/`
3. Dispatches `openPreview` event with URL
4. Blade JS listener opens in new tab
