# Livewire + Filament Page Property Pitfalls

Concrete error→fix mappings discovered across sessions. These are the Livewire landmines when building custom Filament Pages with `HasForms`.

## Error Catalog

### 1. "Property [$x] not found on component"
**Cause**: Form field `SomeField::make('x')` exists but the Page class has no `public $x`.
**Fix**: Add `public $x = null;` (or appropriate default) to the Page class.

### 2. "Property type not supported in Livewire for property: [null]"
**Cause A**: `public ?string $rab_id = null;` — Livewire can't synthesize nullable types.
**Fix A**: `public string $rab_id = '';` — use non-nullable with empty-string default.

**Cause B**: Page implements `HasForms` but is missing `use \Filament\Forms\Concerns\InteractsWithForms;` trait. Without this trait, Livewire cannot bind form state at all — every form interaction triggers "Property type not supported" because Livewire tries to serialize unbound form state. Error surfaces specifically when FileUpload or complex components trigger a state update.
**Fix B**: Add the trait + declare the statePath target property:
```php
class ImportRab extends Page implements HasForms
{
    use \Filament\Forms\Concerns\InteractsWithForms;  // REQUIRED
    public ?array $formData = [];  // matches form()->statePath('formData')
    // ...
}
```

### 3. "No synthesizer found for key: \"\""
**Cause A**: `public ?array $allSheets = null;` — same nullable issue, different error.
**Fix A**: `public array $allSheets = [];` — use non-nullable with array default.

**Cause B**: `public string $file = ''` on a FileUpload property. Livewire's file upload stores a `TemporaryUploadedFile` object (not a string), and the string type-hint prevents serialization.
**Fix B**: `public $file = null;` — remove type hint entirely. Livewire needs freedom to store objects.

### 4. FileUpload callback receives TemporaryUploadedFile, not path string
**Cause**: `afterStateUpdated(fn ($state) => ...)` — `$state` is a Livewire TemporaryUploadedFile object.
**Fix**: Detect and store explicitly:
```php
->afterStateUpdated(function ($state) {
    if ($state) {
        $path = is_object($state) && method_exists($state, 'getPath')
            ? $state->store('target/directory', 'public')
            : (is_string($state) ? $state : null);
        if ($path) {
            $this->filePath = $path;
        }
    }
}),
```

### 5. "Non-static method Filament\Notifications\Notification::danger() cannot be called statically"
**Cause**: Using `Notification::danger('...')` or `Notification::success('...')` directly — these are instance methods in Filament v3, not static.
**Fix**: Use the factory pattern: `Notification::make()->danger('...')->send()`. Every notification call must go through `make()`:
```php
// WRONG — throws Error in Filament v3
Notification::danger('Error occurred');
Notification::success('Saved!');

// RIGHT — factory pattern
Notification::make()->danger('Error occurred')->send();
Notification::make()->success('Saved!')->send();
Notification::make()->warning('Check this')->send();
```
**Scope**: This affects EVERY Livewire component that uses Filament Notifications — custom Pages, Resources, Widgets. The error fires at runtime when the notification method is called, not at class load time, so it only surfaces during user interaction.

### 6. "Cannot assign null to property ... of type string"

**Cause**: `public string $manualNama = '';` — Livewire sets null on form-bound properties before the user interacts. When a property is typed as `string` (non-nullable), assigning null causes a TypeError.

**Fix**: Use nullable type hint with a non-null default:
```php
// ERROR — Livewire assigns null before form fill
public string $manualNama = '';

// CORRECT — nullable type + empty-string default
public ?string $manualNama = '';

// ALSO CORRECT for FileUpload
public $manualFile = null;  // no type hint
```

**Important distinction**: The banned pattern is `?string $x = null` (nullable WITH null default — triggers "Property type not supported"). The working pattern is `?string $x = ''` (nullable WITH empty-string default — allows null assignment on form reset while being a string normally). Livewire can set and read null on these without triggering the synthesizer error.

## Universal Rule

ALL public properties on a Filament Livewire Page that will be bound to form fields must follow:

| Field Type | Property Declaration | Why |
|-----------|---------------------|-----|
| TextInput, Select, etc. | `public string $x = '';` | Livewire needs concrete type |
| Checkbox, Toggle | `public bool $x = false;` | Livewire needs concrete type |
| Array state | `public array $x = [];` | Never `?array` |
| FileUpload | `public $x = null;` | No type hint — TemporaryUploadedFile object |

Internal-only properties (not bound to form fields) can use nullable types since they're never serialized by Livewire's form system — but to be safe, use concrete defaults everywhere.

## Testing Pattern

After adding a new Page with forms, always:
1. Load the page in browser — check for 500 errors
2. Interact with each form field — check for Livewire update errors
3. For FileUpload: upload a test file, verify `afterStateUpdated` fires
4. Check `storage/logs/laravel.log` for "No synthesizer" or "Property type not supported"
