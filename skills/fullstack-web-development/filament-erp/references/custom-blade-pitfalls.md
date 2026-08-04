# Custom Filament Page — Blade Pitfalls (2026-07-27)

## Problem Summary
Building a custom Filament Page (extends `Page`, implements `HasForms`) with a custom Blade view using Alpine.js tabs. Multiple runtime errors encountered and resolved.

## Pitfall 1: Missing `$view` Property
**Symptom:** Page renders but Livewire doesn't mount (0 `wire:id` elements in DOM). The page appears as static HTML.
**Fix:** Custom Filament Pages MUST have:
```php
protected static ?string $view = 'filament.pages.your-page';
```
Without this, Filament cannot render the Livewire component.

## Pitfall 2: `$this->table` Without HasTable
**Symptom:** `PropertyNotFoundException: Property [$table] not found on component`
**Fix:** If using `InteractsWithTable` trait + `HasTable` interface, `$this->table` works. If removed HasTable (e.g., using Alpine.js tabs instead), the `{{ $this->table }}` call in Blade fails.
**Rule:** Don't mix `HasTable` with custom Alpine.js table rendering. Pick one approach.

## Pitfall 3: `x-filament-section` vs `x-filament::section`
**Symptom:** `InvalidArgumentException: Unable to locate a class or view for component [filament-section].`
**Fix:** In Filament 3.2 Blade, the correct component syntax is `x-filament::section` (with double colon `::`).
The `x-filament-section` format does NOT exist.

## Pitfall 4: Avoid Filament Blade Components in Custom Pages
**Symptom:** Various component not found errors.
**Rule:** For custom Filament Page Blade views, prefer PLAIN HTML + Tailwind CSS classes over Filament Blade components.
Filament component names are inconsistent across versions and error-prone.
```blade
<!-- ✅ Safe: Plain HTML -->
<div class="bg-white rounded-xl border shadow-sm">...</div>

<!-- ⚠️ Risky in custom pages -->
<x-filament::section heading="Title">...</x-filament::section>
<x-filament::input wire:model="field" />
```
Exception: `<x-filament-panels::page>` wrapper is REQUIRED.

## Pitfall 5: Alpine.js Tabs (No Filament Tabs Component)
**Symptom:** `x-filament::tabs` doesn't exist in Filament 3.2.
**Fix:** Use Alpine.js:
```html
<div x-data="{ tab: 'manual' }">
    <nav class="flex gap-2 border-b border-gray-200">
        <button @click="tab = 'manual'" :class="...">Tab 1</button>
        <button @click="tab = 'other'" :class="...">Tab 2</button>
    </nav>
    <div x-show="tab === 'manual'">Content 1</div>
    <div x-show="tab === 'other'" x-cloak>Content 2</div>
</div>
```

## Pitfall 6: NavigationGroup Deduplication
**Symptom:** Sidebar shows two groups with same name because one uses emoji `'⚙️ Pengaturan'` and another uses `'Pengaturan'` (different strings = different groups).
**Fix:** Ensure ALL Resources and Pages that should be in the same group use EXACTLY the same string, including emoji/unicode. PHP `\u2699` is NOT a unicode escape in single-quoted strings — it renders as literal text.

## Recommended Custom Page Structure
```php
<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class BackupPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'Backup & Restore';
    protected static ?string $slug = 'backup-restore';
    protected static ?string $view = 'filament.pages.backup-page'; // REQUIRED

    // ... public properties for wire:model ...
    public string $backupType = 'full';
    // ... methods for wire:click ...
    public function createManualBackup(): void { ... }
}
```
