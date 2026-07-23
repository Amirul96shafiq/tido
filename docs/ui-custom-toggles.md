# UI custom toggles (Filament v5)

Prefer Filament’s native `Toggle::make()` whenever the value lives in form/`$wire` state **and** no sibling “current setting” pill is required. Use a **custom Blade toggle** when:

- the control must bind to Alpine / `localStorage` (or another client store) that Livewire cannot reliably own — e.g. Filament sidebar `$store.sidebar` on Edit Profile, or
- a live text indicator must sit beside the toggle (native `Toggle` cannot host a sibling pill cleanly).

**Canonical examples:**

- Sidebar (Alpine store): [`sidebar-mode-field.blade.php`](../resources/views/filament/schemas/components/sidebar-mode-field.blade.php)
- Stylized background (entangled form field + pill + preview): [`stylized-background-field.blade.php`](../resources/views/filament/schemas/components/stylized-background-field.blade.php)

## Prefer native first

```php
use Filament\Forms\Components\Toggle;

Toggle::make('notify_budget_alerts')
    ->label('Budget Alerts')
    ->live();
```

On Edit Profile Personalize, stylized background uses a custom field so a CheQQme-style status pill can sit next to the control; dehydrate via `Hidden::make('stylized_background_enabled')`.

On Edit Profile, `defaultForm()` sets `inlineLabel(! static::isSimple())`, so native toggles render **label left / control right** in the `fi-fo-field-has-inline-label` grid. Custom toggles on that page must mirror that markup (see Layout below).

## Current-setting pill

Personalize preference rows show a live primary pill beside the control (and keep the same copy on preview overlays where applicable):

| Control | Pill text |
|---------|-----------|
| Theme Mode | `Light` / `Dark` / `System` (`theme-changed` / `localStorage.theme`) |
| Stylized Background | `Enabled: Stylized Mode` / `Disabled: Focus Mode` |
| Sidebar Mode | `Collapsed style` / `Expanded style` |

Shared classes: `rounded-full bg-primary-500/90 px-2 py-1 text-xs font-medium text-primary-900`. Place the pill in a `flex w-full items-center justify-between gap-2` wrapper with the control inside `fi-fo-field-content-col` so the pill aligns to the end of the row.

## Required: Filament color classes

Do **not** style the on-state with only `fi-color-primary`. Filament’s toggle CSS paints via `.fi-color` + shade utilities (`fi-bg-color-*`) from the color map. Missing those leaves an “on” toggle looking gray/white.

Generate classes the same way native `Toggle` does:

```php
@php
    use Filament\Support\View\Components\ToggleComponent;
    use Illuminate\Support\Arr;

    use function Filament\Support\get_component_color_classes;

    $onClasses = Arr::toCssClasses([
        'fi-toggle',
        'fi-fo-toggle',
        'fi-toggle-on',
        ...get_component_color_classes(ToggleComponent::class, 'primary'),
    ]);

    $offClasses = Arr::toCssClasses([
        'fi-toggle',
        'fi-fo-toggle',
        'fi-toggle-off',
        ...get_component_color_classes(ToggleComponent::class, 'gray'),
    ]);
@endphp
```

Bind the full class string (include base `fi-toggle` / `fi-fo-toggle` in both states so Alpine does not drop them):

```blade
<button
    type="button"
    role="switch"
    x-bind:aria-checked="collapsed ? 'true' : 'false'"
    x-bind:class="collapsed ? @js($onClasses) : @js($offClasses)"
    x-on:click="toggle()"
    aria-label="Sidebar Mode"
>
    <div>
        <div aria-hidden="true"></div>
        <div aria-hidden="true"></div>
    </div>
</button>
```

`get_component_color_classes(ToggleComponent::class, 'primary')` yields classes such as `fi-color`, `fi-color-primary`, `fi-bg-color-600`, `fi-text-color-600`, `dark:fi-bg-color-500`. Gray off-state returns `[]` for `HasDefaultGrayColor` components — the base `fi-toggle` gray track still applies.

## Layout on inlineLabel forms (Edit Profile)

Match Filament’s field wrapper so the toggle lines up with other Personalize rows:

```blade
<div data-field-wrapper class="fi-fo-field fi-fo-field-has-inline-label">
    <div class="fi-fo-field-label-col fi-vertical-align-center">
        <div class="fi-fo-field-label-ctn">
            <label class="fi-fo-field-label">
                <span class="fi-fo-field-label-content">Sidebar Mode</span>
            </label>
        </div>
    </div>

    <div class="fi-fo-field-content-col">
        <div class="flex w-full items-center justify-between gap-2">
            {{-- toggle button (start) + current-setting pill (end) --}}
        </div>
    </div>
</div>
```

Do **not** use a full-width `justify-between` row for label + toggle on Profile — that pushes the control to the far right and no longer matches native `inlineLabel` toggles.

## Client-only state (Alpine / localStorage)

When the preference is Filament panel chrome (sidebar open/collapsed, theme), bind to the existing Alpine store instead of inventing a Livewire/`users` column:

| Concern | Source of truth |
|---------|-----------------|
| Sidebar collapse (desktop) | `$store.sidebar.isOpenDesktop` (`localStorage` key `isOpenDesktop`) |
| Sidebar open (mobile) | `$store.sidebar.isOpen` (`localStorage` key `isOpen`) |
| Mutate sidebar | `$store.sidebar.open()` / `$store.sidebar.close()` |
| Theme | Filament theme switcher → `theme-changed` / `localStorage.theme` |

Example getter used on Profile:

```js
get collapsed() {
    const isDesktop = window.innerWidth >= 1024;
    return isDesktop
        ? ! this.$store.sidebar.isOpenDesktop
        : ! this.$store.sidebar.isOpen;
},
```

Avoid `$wire.set('data.…')` for these chrome prefs: dehydrated/non-model keys often fail to stick across Livewire snapshots. Keep them client-side.

## Checklist for a new custom toggle

1. Confirm native `Toggle::make()` cannot own the state.
2. Build on/off class strings with `get_component_color_classes(ToggleComponent::class, …)` — never hand-roll only `fi-color-primary`.
3. Use `role="switch"`, `aria-checked`, and `aria-label` (or visible label).
4. On Edit Profile / `inlineLabel` forms, use `fi-fo-field-has-inline-label` markup.
5. Keep knob markup as Filament expects: button → single child `div` → two icon slots.
6. When a current-setting pill is required, wrap control + pill in `flex w-full items-center justify-between gap-2` (see Current-setting pill).
7. Prefer copy impersonal — see [ui-copy-style.md](ui-copy-style.md).

## Related

- Profile Personalize: [`EditProfile.php`](../app/Filament/Pages/Auth/EditProfile.php), [`sidebar-mode-field.blade.php`](../resources/views/filament/schemas/components/sidebar-mode-field.blade.php), [`stylized-background-field.blade.php`](../resources/views/filament/schemas/components/stylized-background-field.blade.php)
- Theme switcher embed: [`theme-mode-field.blade.php`](../resources/views/filament/schemas/components/theme-mode-field.blade.php)
- Dark theme / primary accents: [ui-dark-theme.md](ui-dark-theme.md)
- Filament conventions: `.cursor/rules/filament-conventions.mdc`
