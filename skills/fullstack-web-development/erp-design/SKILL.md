---
name: erp-design
description: UI/UX design system for ERP — dashboard layouts, KPI cards, form patterns, chart styling, PDF templates for PT EXFERIA PUTRA INOVASI.
tags: [erp, design, ui, ux, dashboard, filament, charts]
---

# ERP Design System

## Project Location
```
C:\Users\62897\OneDrive\Desktop\laragon\www\PT.EXFERIA PUTRA INOVASI\
```

## Full Design Doc
Read `DESIGN.md` in the project root for complete design system.

## Quick Reference

### Design Principles
1. **Modern Professional** — Gradient KPI cards, donut charts, line charts with fill
2. **Role-Based Sections** — Dashboard terpisah per divisi (bukan filter dropdown)
3. **Minimal Forms** — Hanya field esensial, auto-set user fields
4. **Real-time Feedback** — Loading states, success/error toasts
5. **Mobile-First** — Responsive untuk teknisi di lapangan

### KPI Card Gradients
- Kontrak Aktif: blue→indigo
- Stok Kritis: red→orange
- Pengeluaran: green→teal
- Faktur Outstanding: yellow→orange

### Status Colors
- Draft: gray (#6B7280)
- Submitted: blue (#3B82F6)
- Approved: green (#22C55E)
- Rejected: red (#EF4444)

### Dashboard per Role
- **R01 (Admin Proyek)**: KPI + Pie Chart + Progress Trend + Kontrak table
- **R04 (Staff Gudang)**: Stok Kritis list (progress bar) + Quick actions
- **R06 (Manajer)**: 4 KPI cards + Donut + Bar + Top 5 + Line chart 12 bulan

### Chart Library
- Chart.js for all charts
- Transparent bg, gray-200 gridlines
- Line fill: 0.1 opacity
- Border radius 4px on bars

### Currency Input Pattern
```php
TextInput::make('harga')
    ->prefix('Rp')
    ->inputMode('numeric')
```

### Form Layout (Filament)
```php
// Layout wraps grid, use flex for horizontal
<div class="grid gap-6">
  <div class="flex gap-6"> <!-- horizontal KPI cards -->
    // KPI cards here
  </div>
</div>
```

### PDF Templates
- Penawaran: Company header + item table + total + validity
- RAB: Company header + komponen biaya + total
- Faktur: Company header + item tagihan + PPN + total + jatuh tempo

### Responsive Breakpoints
- Mobile: < 640px (teknisi lapangan)
- Tablet: 640-1024px (supervisor)
- Desktop: > 1024px (office staff)
