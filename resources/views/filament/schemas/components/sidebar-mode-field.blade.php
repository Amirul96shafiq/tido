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

<div
    class="flex flex-col gap-3"
    x-data="{
        enabled: $wire.entangle('data.stylized_background_enabled').live,
        isDesktop: window.innerWidth >= 1024,
        init() {
            this.updateViewport();
            window.addEventListener('resize', () => this.updateViewport());
        },
        updateViewport() {
            this.isDesktop = window.innerWidth >= 1024;
        },
        get isRestricted() {
            return ! this.isDesktop;
        },
        get collapsed() {
            return this.isDesktop
                ? ! this.$store.sidebar.isOpenDesktop
                : ! this.$store.sidebar.isOpen;
        },
        toggle() {
            if (this.isRestricted) {
                return;
            }

            if (this.collapsed) {
                this.$store.sidebar.open();
            } else {
                this.$store.sidebar.close();
            }
        },
    }"
>
    {{-- Match Filament EditProfile inlineLabel field grid --}}
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
                <button
                    type="button"
                    role="switch"
                    x-bind:aria-checked="collapsed ? 'true' : 'false'"
                    x-bind:aria-disabled="isRestricted ? 'true' : 'false'"
                    x-bind:disabled="isRestricted"
                    x-bind:class="collapsed ? @js($onClasses) : @js($offClasses)"
                    x-on:click="toggle()"
                    aria-label="Sidebar Mode"
                >
                    <div>
                        <div aria-hidden="true"></div>
                        <div aria-hidden="true"></div>
                    </div>
                </button>

                <span class="rounded-full bg-primary-500/90 px-2 py-1 text-xs font-medium text-primary-900">
                    <span x-text="collapsed ? 'Collapsed style' : 'Expanded style'">Expanded style</span>
                </span>
            </div>

            <p class="fi-sc-text">
                Restricted to larger responsive users.
            </p>
        </div>
    </div>

    <div
        class="tido-sidebar-preview relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
        style="aspect-ratio: 32 / 9;"
    >
        <div class="bg-white dark:bg-slate-900" style="height: 200%;">
            <x-tido.panel-preview-chrome>
                <div class="h-3 w-1/3 rounded-full bg-gray-200/90 dark:bg-gray-700/90"></div>
                <div class="h-16 w-full rounded-md bg-white/80 ring-1 ring-gray-200/80 dark:bg-slate-800/80 dark:ring-white/10"></div>
                <div class="flex gap-2">
                    <div class="h-10 flex-1 rounded-md bg-white/80 ring-1 ring-gray-200/80 dark:bg-slate-800/80 dark:ring-white/10"></div>
                    <div class="h-10 flex-1 rounded-md bg-white/80 ring-1 ring-gray-200/80 dark:bg-slate-800/80 dark:ring-white/10"></div>
                </div>
            </x-tido.panel-preview-chrome>
        </div>

        <div class="absolute inset-block-start-2 inset-inline-start-2 rounded-full bg-primary-500/90 px-2 py-1 text-xs font-medium text-primary-900">
            <span x-text="collapsed ? 'Collapsed style' : 'Expanded style'">Expanded style</span>
        </div>
    </div>
</div>
