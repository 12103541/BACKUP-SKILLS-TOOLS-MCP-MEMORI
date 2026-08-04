---
name: erp-docs
description: Auto-load ERP documentation for PT EXFERIA PUTRA INOVASI project. Architecture, Design, PRD, Rules, and Schema files. Use when working on the ERP system — Laravel 11 + PHP 8.2 + Filament v3.2 + MySQL 8.0 at C:\laragon\www\PT.EXFERIA PUTRA INOVASI\ (see DUAL-PATH PITFALL below)
tags: [erp, laravel, filament, documentation, architecture, schema, rules]
---

# ERP Documentation Hub

## Project Location (DUAL-PATH PITFALL — CRITICAL)

There are TWO copies of the project. Apache serves from (A), user works from (B):

```
(A) C:\laragon\www\PT.EXFERIA PUTRA INOVASI\    ← Apache DocumentRoot (LIVE — this is what the browser sees)
(B) C:\Users\62897\OneDrive\Desktop\laragon\www\PT.EXFERIA PUTRA INOVASI\  ← OneDrive copy (user's working dir)
```

**Rule**: ALWAYS edit files in path (A) — that's where Apache serves from. Then sync to (B) if needed:
```bash
cp "/c/laragon/www/PT.EXFERIA PUTRA INOVASI/path/to/file" "/c/Users/62897/OneDrive/Desktop/laragon/www/PT.EXFERIA PUTRA INOVASI/path/to/file"
```

**Why this matters**: Path (B) has NO Filament vendor/, NO Filament Resources, NO Livewire components. Searching (B) for Filament files returns nothing — causing false "file not found" conclusions. After editing, clear view cache: `php artisan view:clear`

## Documentation Files

All docs are in the project root. Load the relevant one based on the task:

| File | When to Load | Content |
|------|-------------|---------|
| `ARCHITECTURE.md` | System design, module structure, deployment | Full system context, module list, API architecture |
| `DESIGN.md` | UI/UX, dashboard layout, form patterns, charts | KPI cards, role-based dashboards, form/table patterns |
| `PRD.md` | Feature requirements, business rules, specs | 19 modules, acceptance criteria, NFRs |
| `RULES.md` | Coding conventions, security, anti-patterns | DB rules, PHP/Laravel rules, Filament rules, naming |
| `SCHEMA.md` | Database changes, models, relationships | 66+ migrations, all table schemas, ER relationships |

## References

- `references/rab-module-views.md` — RAB module route map, view purposes, and mystery URL notes

## Quick Reference

### ERPs Key Facts
- **Stack**: Laravel 11 + PHP 8.2 + Filament v3.2 + MySQL 8.0
- **Roles**: R00 (Super Admin), R01-R06 (functional roles)
- **Auto-numbers**: KON/YYYYMM/XXXX, PNW/YYYYMM/XXXX, RAB/YYYYMM/XXXX, INV/YYYYMM/XXXX
- **PPN rate**: 12% from config/pajak.php
- **Permission flow**: R00 bypass → user_permissions → role_permissions → deny
- **API dual interface**: Filament (session) + REST API (Sanctum token)
- **LAN access**: http://192.168.0.6 (Apache vhost)
- **Database safety**: NEVER migrate:fresh — ALWAYS migrate only

### Existing Docs in Project
- `ALUR_PROSES_SIRKULER_DIVISI.md` — Business process flow per division
- `ALUR_SINKRONISASI_DIVISI.md` — Data sync between divisions
- `PANDUAN_DATABASE.md` — Database safety guide
- `README.md` — Setup & API overview
- `requirements.md` — Original requirements (v1)

## Loading Strategy

### For new feature development:
1. Read `ARCHITECTURE.md` → understand where it fits
2. Read `SCHEMA.md` → check existing tables
3. Read `PRD.md` → check if feature exists
4. Read `RULES.md` → follow conventions

### For bug fixing:
1. Read `RULES.md` → check anti-patterns
2. Read `SCHEMA.md` → understand table structure

### For UI/dashboard work:
1. Read `DESIGN.md` → follow design patterns
2. Read `ARCHITECTURE.md` → module context

### For database changes:
1. Read `SCHEMA.md` → current schema
2. Read `RULES.md` → migration safety rules

## Critical Rules Summary

1. **NEVER** `migrate:fresh` or `migrate:reset`
2. **ALWAYS** implement `canAccess()` on Filament Resources
3. **ALWAYS** use `hasPermission()` not hardcoded role checks
4. **Property names** must match form field names (Livewire)
5. **File downloads**: use `->url()->openUrlInNewTab()` not action stream
6. **Custom Pages**: use `->options(fn()` not `->relationship()`
7. **Currency input**: text + inputmode numeric, not native number
