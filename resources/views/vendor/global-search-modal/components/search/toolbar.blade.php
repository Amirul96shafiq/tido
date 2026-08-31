@php
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\Components\BadgeComponent;
    use Illuminate\View\ComponentAttributeBag;

    $activeFiltersCount = $this->getActiveFiltersCount();
    $hasTypeFilters = \App\Filament\GlobalSearch\GlobalSearchType::tryFromValue($this->type)->hasTypeFilters();
@endphp

<div
    class="fi-gsm-toolbar mt-2 flex w-full flex-wrap items-end gap-3 border-t border-gray-100 pt-3 dark:border-white/10"
    x-on:modal-closed.window="
        if ($event.detail?.id === 'global-search-modal::plugin') {
            $wire.resetModalState();
        }
    "
>
    <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-2">
        <div class="min-w-0">
            <label class="fi-gsm-toolbar-label mb-1 block text-xs font-semibold text-gray-500 dark:text-gray-400">
                Type
            </label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="type">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div class="min-w-0">
            <label class="fi-gsm-toolbar-label mb-1 block text-xs font-semibold text-gray-500 dark:text-gray-400">
                Sort
            </label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="sort">
                    @foreach ($this->sortOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    @if ($hasTypeFilters)
        <div
            class="fi-ta-filters-trigger-action-ctn ms-auto shrink-0"
            @if ($this->filtersOpen)
                x-on:click.outside="
                    if ($event.target.closest('.fi-dropdown-panel, .fi-fo-date-time-picker-panel')) {
                        return;
                    }

                    $wire.closeFilters();
                "
            @endif
        >
            <button
                type="button"
                class="fi-icon-btn fi-size-md fi-color fi-color-gray relative"
                wire:click="toggleFilters"
                wire:loading.attr="disabled"
                wire:target="toggleFilters, closeFilters, resetFilters, search, filters, type, sort"
                aria-label="Filters"
                aria-expanded="{{ $this->filtersOpen ? 'true' : 'false' }}"
                x-tooltip="{
                    content: 'Filters',
                    theme: $store.theme,
                    zIndex: 100000,
                }"
            >
                <x-filament::icon
                    :icon="Heroicon::Funnel"
                    class="fi-icon fi-size-md"
                    wire:loading.remove.delay.default
                    wire:target="toggleFilters, closeFilters, resetFilters, search, filters, type, sort"
                />

                <x-filament::loading-indicator
                    class="fi-icon fi-size-md"
                    wire:loading.delay.default
                    wire:target="toggleFilters, closeFilters, resetFilters, search, filters, type, sort"
                />

                @if ($activeFiltersCount > 0)
                    <span
                        {{
                            (new ComponentAttributeBag)->color(BadgeComponent::class, 'primary')->class([
                                'fi-badge fi-size-xs absolute -top-1 -end-1',
                            ])
                        }}
                        wire:loading.remove.delay.default
                        wire:target="toggleFilters, closeFilters, resetFilters, search, filters, type, sort"
                    >
                        {{ $activeFiltersCount }}
                    </span>
                @endif
            </button>

            @if ($this->filtersOpen)
                <div
                    wire:key="global-search-filters-panel-{{ $this->type }}"
                    class="fi-gsm-filters-panel"
                >
                    {{ $this->getSchema('filtersForm') }}

                    <div class="fi-ta-filters-actions-ctn">
                        <x-filament::button
                            color="danger"
                            icon="heroicon-o-arrow-path"
                            label-sr-only
                            tooltip="Reset filters"
                            tag="button"
                            size="sm"
                            wire:click="resetFilters"
                            wire:loading.attr="disabled"
                            wire:target="resetFilters"
                        >
                            Reset filters
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
