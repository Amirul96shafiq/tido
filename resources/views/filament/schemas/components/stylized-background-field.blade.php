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

    $initialEnabled = (bool) ($enabled ?? false);
    $lightBackground = asset('images/bg-l.png');
    $darkBackground = asset('images/bg-d.png');
@endphp

<div
    class="flex flex-col gap-3"
    x-data="{
        enabled: $wire.entangle('data.stylized_background_enabled').live,
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

    <div
        class="tido-stylized-preview relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
        style="aspect-ratio: 1919 / 1079;"
    >
        <x-tido.panel-preview-chrome class="h-full">
            <x-slot:background>
                <div
                    class="absolute inset-0 bg-white transition-opacity duration-200 dark:bg-slate-900"
                    x-bind:class="enabled ? 'opacity-0' : 'opacity-100'"
                    aria-hidden="true"
                ></div>

                <div
                    class="absolute inset-0 bg-cover bg-bottom bg-no-repeat transition-opacity duration-200 dark:hidden"
                    style="background-image: url('{{ $lightBackground }}');"
                    x-bind:class="enabled ? 'opacity-100' : 'opacity-0'"
                    aria-hidden="true"
                ></div>

                <div
                    class="absolute inset-0 hidden bg-cover bg-bottom bg-no-repeat transition-opacity duration-200 dark:block"
                    style="background-image: url('{{ $darkBackground }}');"
                    x-bind:class="enabled ? 'opacity-100' : 'opacity-0'"
                    aria-hidden="true"
                ></div>
            </x-slot:background>

            <div class="h-3 w-1/3 rounded-full bg-gray-200/90 dark:bg-gray-700/90"></div>
            <div class="h-16 w-full rounded-md bg-white/80 ring-1 ring-gray-200/80 dark:bg-slate-800/80 dark:ring-white/10"></div>
            <div class="flex gap-2">
                <div class="h-10 flex-1 rounded-md bg-white/80 ring-1 ring-gray-200/80 dark:bg-slate-800/80 dark:ring-white/10"></div>
                <div class="h-10 flex-1 rounded-md bg-white/80 ring-1 ring-gray-200/80 dark:bg-slate-800/80 dark:ring-white/10"></div>
            </div>
        </x-tido.panel-preview-chrome>

        <div class="absolute inset-block-start-2 inset-inline-start-2 z-20 rounded-full bg-primary-500/90 px-2 py-1 text-xs font-medium text-primary-900">
            <span x-text="enabled ? 'Enabled: Stylized Mode' : 'Disabled: Focus Mode'">
                {{ $initialEnabled ? 'Enabled: Stylized Mode' : 'Disabled: Focus Mode' }}
            </span>
        </div>
    </div>
</div>
