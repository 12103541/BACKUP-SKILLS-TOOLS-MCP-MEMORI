# Filament Multi-Tab Hybrid Page (HasForms + HasTable)

A page implementing BOTH HasForms and HasTable with wire:model="activeTab" tabs, where each tab is a completely different UI zone: custom form, Filament Table, schedule cards, or confirmation dialogs.

## Architecture

```php
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class BackupPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $view = 'filament.pages.backup-page';

    // Tab state — required Livewire property
    public ?array $activeTab = ['manual'];

    // All tabs form state is on the Page class
    public ?string $backupType = 'full';
    public ?array $selectedTables = [];
    public ?string $schedName = null;
}
```

## Key Rules

### 1. `wire:model="activeTab"` uses array, not string
The `activeTab` property on the Page class MUST be `?array` (not `?string`). Blade checks `in_array('tabName', $activeTab)`.

### 2. Each tab is independent — rendered lazily
Each tab wrapped in `@if(in_array('tab', $activeTab))`. Non-active tabs don't render DOM or trigger Livewire hydration.

### 3. Page implements HasTable — `{{ $this->table }}` renders inline
The Filament Table renders directly in the Blade. The `table()` method on the Page class handles all column/action/filter definitions. Actions, bulk actions, and sorting work natively.

### 4. All state lives on Page class
Every form field and collection used in any tab is a public property on the Page with concrete defaults (never nullable on Livewire properties).

### 5. `wire:click` works on non-form elements
Buttons with `wire:click` fire full Livewire actions without triggering form CSRF issues.

### 6. No `wire:model.live` on interactive selects
Use `wire:change` or non-live bindings to avoid excessive AJAX. Radio buttons with `wire:model` (not `.live`) are fine.
