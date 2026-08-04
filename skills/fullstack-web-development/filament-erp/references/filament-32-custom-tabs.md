# Filament 3.2 Custom Tab Navigation (Alpine.js)

## Problem
Filament v3.2 does NOT have `x-filament::tabs.tab` or `x-filament-tabs` blade components for custom Pages.
Using either causes: `InvalidArgumentException: Unable to locate a class or view for component [filament::tabs.tab]`

## Solution
Use Alpine.js (already bundled with Filament) for custom page tabs.

### Basic Pattern
```html
<div x-data="{ tab: 'manual' }">
    <nav class="flex gap-6 border-b border-gray-200 mb-6">
        <button @click="tab='manual'"
            :class="tab==='manual' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500'"
            class="pb-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>Tab Label
        </button>
    </nav>
    <div x-show="tab==='manual'" x-cloak> ... content ... </div>
    <div x-show="tab==='riwayat'" x-cloak> ... content ... </div>
</div>
```

### Key Points
- `x-cloak` prevents flash-of-content on initial load
- `wire:model` on radio/checkbox works fine inside `x-show` tabs
- Each tab can contain full Filament components (sections, tables, buttons, icon-buttons)
- Icons: use heroicon SVG or `heroicon-o-*` via `<x-filament::icon>` component
- Badge counts on tabs: `<span class="ml-1 text-xs bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full">{{ count }}</span>`
- Status-colored tabs: change `border-primary-500` to `border-red-500` for danger tabs

### Header Stats Cards (Gradient)
Common pattern for page headers:
```html
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white">
        <div class="text-sm opacity-80">Label</div>
        <div class="text-2xl font-bold">{{ $count }}</div>
    </div>
</div>
```

## Reference
Added 2026-07-27 after discovering this limitation while building backup page with 5 tabs.
Filament docs incorrectly suggest `x-filament::tabs` syntax that doesn't work for custom Pages.
