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

    $enabledLightImage = asset('images/bg-enabled-l-v2.png');
    $enabledDarkImage = asset('images/bg-enabled-d-v2.png');
    $disabledLightImage = asset('images/bg-disabled-l-v2.png');
    $disabledDarkImage = asset('images/bg-disabled-d-v2.png');
    $initialEnabled = (bool) ($enabled ?? false);
    $initialLightImage = $initialEnabled ? $enabledLightImage : $disabledLightImage;
    $initialDarkImage = $initialEnabled ? $enabledDarkImage : $disabledDarkImage;
@endphp

<div
    class="flex flex-col gap-3"
    x-data="{
        enabled: $wire.entangle('data.stylized_background_enabled').live,
        enabledLight: @js($enabledLightImage),
        enabledDark: @js($enabledDarkImage),
        disabledLight: @js($disabledLightImage),
        disabledDark: @js($disabledDarkImage),
        lightSource() {
            return this.enabled ? this.enabledLight : this.disabledLight;
        },
        darkSource() {
            return this.enabled ? this.enabledDark : this.disabledDark;
        },
        toggle() {
            this.enabled = ! this.enabled;
        },
    }"
>
    <div data-field-wrapper class="fi-fo-field fi-fo-field-has-inline-label">
        <div class="fi-fo-field-label-col fi-vertical-align-center">
            <div class="fi-fo-field-label-ctn">
                <label class="fi-fo-field-label">
                    <span class="fi-fo-field-label-content">Stylized Background</span>
                </label>
            </div>
        </div>

        <div class="fi-fo-field-content-col">
            <div class="flex w-full items-center justify-between gap-2">
                <button
                    type="button"
                    role="switch"
                    x-bind:aria-checked="enabled ? 'true' : 'false'"
                    x-bind:class="enabled ? @js($onClasses) : @js($offClasses)"
                    x-on:click="toggle()"
                    aria-label="Stylized Background"
                >
                    <div>
                        <div aria-hidden="true"></div>
                        <div aria-hidden="true"></div>
                    </div>
                </button>

                <span class="rounded-full bg-primary-500/90 px-2 py-1 text-xs font-medium text-primary-900">
                    <span x-text="enabled ? 'Enabled: Stylized Mode' : 'Disabled: Focus Mode'">
                        {{ $initialEnabled ? 'Enabled: Stylized Mode' : 'Disabled: Focus Mode' }}
                    </span>
                </span>
            </div>
        </div>
    </div>

    <div class="relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <img
            src="{{ $initialLightImage }}"
            x-bind:src="lightSource()"
            alt="Background preview"
            class="block h-full w-full cursor-pointer object-cover transition-opacity duration-200 hover:opacity-80 dark:hidden"
            style="aspect-ratio: 1919 / 1079;"
            x-on:click="window.open(lightSource(), '_blank', 'noopener,noreferrer')"
        />
        <img
            src="{{ $initialDarkImage }}"
            x-bind:src="darkSource()"
            alt="Background preview"
            class="hidden h-full w-full cursor-pointer object-cover transition-opacity duration-200 hover:opacity-80 dark:block"
            style="aspect-ratio: 1919 / 1079;"
            x-on:click="window.open(darkSource(), '_blank', 'noopener,noreferrer')"
        />

        <div class="absolute inset-block-start-2 inset-inline-start-2 rounded-full bg-primary-500/90 px-2 py-1 text-xs font-medium text-primary-900">
            <span x-text="enabled ? 'Enabled: Stylized Mode' : 'Disabled: Focus Mode'">
                {{ $initialEnabled ? 'Enabled: Stylized Mode' : 'Disabled: Focus Mode' }}
            </span>
        </div>
    </div>
</div>
