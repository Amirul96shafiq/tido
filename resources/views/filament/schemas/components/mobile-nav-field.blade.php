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

    $initialMobileNavEnabled = (bool) ($mobileNavEnabled ?? false);
@endphp

<div
    class="flex flex-col gap-3"
    x-data="{
        mobileNav: $wire.entangle('data.mobile_nav_enabled').live,
        enabled: $wire.entangle('data.stylized_background_enabled').live,
        toggle() {
            this.mobileNav = ! this.mobileNav;
        },
    }"
>
    <div data-field-wrapper class="fi-fo-field fi-fo-field-has-inline-label">
        <div class="fi-fo-field-label-col fi-vertical-align-center">
            <div class="fi-fo-field-label-ctn">
                <label class="fi-fo-field-label">
                    <span class="fi-fo-field-label-content">Mobile Navigation Menu</span>
                </label>
            </div>
        </div>

        <div class="fi-fo-field-content-col">
            <div class="flex w-full items-center justify-between gap-2">
                <button
                    type="button"
                    role="switch"
                    x-bind:aria-checked="mobileNav ? 'true' : 'false'"
                    x-bind:class="mobileNav ? @js($onClasses) : @js($offClasses)"
                    x-on:click="toggle()"
                    aria-label="Mobile Navigation Menu"
                >
                    <div>
                        <div aria-hidden="true"></div>
                        <div aria-hidden="true"></div>
                    </div>
                </button>

                <span class="bg-primary-500/90 text-primary-900 rounded-full px-2 py-1 text-xs font-medium">
                    <span x-text="mobileNav ? 'Enabled: Bottom Bar' : 'Disabled: Top Bar'">
                        {{ $initialMobileNavEnabled ? 'Enabled: Bottom Bar' : 'Disabled: Top Bar' }}
                    </span>
                </span>
            </div>

            <p class="fi-sc-text">Save changes needed to take effect.</p>
        </div>
    </div>

    <div class="tido-mobilenav-preview tido-preview-static relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="tido-mobilenav-preview-frame">
            <x-tido.mobile-preview-chrome class="h-full w-full">
                <div class="h-2 w-2/3 rounded-full bg-gray-200/90 dark:bg-gray-700/90"></div>
                <div class="h-10 w-full rounded-md bg-white/80 ring-1 ring-gray-200/80 dark:bg-slate-800/80 dark:ring-white/10"></div>
                <div class="flex flex-col gap-1.5">
                    <div class="h-6 w-full rounded-md bg-white/80 ring-1 ring-gray-200/80 dark:bg-slate-800/80 dark:ring-white/10"></div>
                    <div class="h-6 w-full rounded-md bg-white/80 ring-1 ring-gray-200/80 dark:bg-slate-800/80 dark:ring-white/10"></div>
                </div>
            </x-tido.mobile-preview-chrome>
        </div>
    </div>
</div>
