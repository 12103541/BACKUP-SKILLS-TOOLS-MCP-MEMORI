# Filament Page Form Fixes (2026-07-31)

## Missing `implements HasForms` Bug
**Symptoms:**
- Form fields render empty in browser (DOM has no values)
- Server state populated (visible via `wire:snapshot` attribute)
- `$this->form->getState()` returns data but `$this->data` is empty
- save() method not triggered on button click

**Root Cause:**
Page extends `Filament\Pages\Page` and uses `InteractsWithForms` trait but does NOT implement `Filament\Forms\Contracts\HasForms`. Without the contract:
- Form state binding fails silently
- `$this->form` not properly initialized
- Livewire doesn't sync form state to DOM

**Fix:**
```php
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class ProfilPerusahaanPage extends Page implements HasForms
{
    use InteractsWithForms;
    // ...
}
```

## Form Fill Wrap Bug
**Code (broken):**
```php
$this->form->fill(['data' => $values]);
```
**Form schema expects flat state (statePath = none), so fill must be flat:**
```php
$this->form->fill($values);
```

## Save StatePath Mismatch
**Code (broken):**
```php
$state = $this->form->getState()['data'] ?? [];
```
**Actual state is flat (no 'data' key):**
```php
$state = $this->form->getState();
$state = $state['data'] ?? $state;  // handle both
```

## Files Fixed
- `app/Filament/Pages/Settings/ProfilPerusahaanPage.php` — added `implements HasForms`, fixed fill/save