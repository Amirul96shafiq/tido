@php
    $enabledLightImage = asset('images/bg-enabled-l-v2.png');
    $enabledDarkImage = asset('images/bg-enabled-d-v2.png');
    $disabledLightImage = asset('images/bg-disabled-l-v2.png');
    $disabledDarkImage = asset('images/bg-disabled-d-v2.png');
    $initialLightImage = $enabled ? $enabledLightImage : $disabledLightImage;
    $initialDarkImage = $enabled ? $enabledDarkImage : $disabledDarkImage;
@endphp

<div
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
    }"
    class="flex flex-col gap-3"
>
    <div class="relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <img
            src="{{ $initialLightImage }}"
            x-bind:src="lightSource()"
            alt="Background preview"
            class="block w-full cursor-pointer transition-opacity duration-200 hover:opacity-80 dark:hidden"
            style="aspect-ratio: 1919 / 991; object-fit: contain;"
            x-on:click="window.open(lightSource(), '_blank', 'noopener,noreferrer')"
        />
        <img
            src="{{ $initialDarkImage }}"
            x-bind:src="darkSource()"
            alt="Background preview"
            class="hidden w-full cursor-pointer transition-opacity duration-200 hover:opacity-80 dark:block"
            style="aspect-ratio: 1919 / 991; object-fit: contain;"
            x-on:click="window.open(darkSource(), '_blank', 'noopener,noreferrer')"
        />

        <div class="absolute inset-block-start-2 inset-inline-start-2 rounded-full bg-primary-500/90 px-2 py-1 text-xs font-medium text-primary-900">
            <span x-text="enabled ? 'Enabled: Stylized Mode' : 'Disabled: Focus Mode'"></span>
        </div>
    </div>
</div>
