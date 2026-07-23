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
        get collapsed() {
            const isDesktop = window.innerWidth >= 1024;
            return isDesktop
                ? ! this.$store.sidebar.isOpenDesktop
                : ! this.$store.sidebar.isOpen;
        },
        toggle() {
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
        </div>
    </div>

    <div
        class="tido-sidebar-preview relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
        style="aspect-ratio: 32 / 9;"
    >
        <div class="bg-white dark:bg-slate-900" style="height: 200%;">
            <x-tido.panel-preview-chrome collapsible>
                <div class="h-3 w-1/3 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-16 w-full rounded-md bg-gray-100 dark:bg-slate-800"></div>
                <div class="flex gap-2">
                    <div class="h-10 flex-1 rounded-md bg-gray-100 dark:bg-slate-800"></div>
                    <div class="h-10 flex-1 rounded-md bg-gray-100 dark:bg-slate-800"></div>
                </div>
            </x-tido.panel-preview-chrome>
        </div>

        <div class="absolute inset-block-start-2 inset-inline-start-2 rounded-full bg-primary-500/90 px-2 py-1 text-xs font-medium text-primary-900">
            <span x-text="collapsed ? 'Collapsed style' : 'Expanded style'">Expanded style</span>
        </div>
    </div>
</div>
