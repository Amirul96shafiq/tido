@php
    use Filament\Support\Enums\Width;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Support\View\Components\BadgeComponent;

    $activeFiltersCount = $this->getActiveFiltersCount();
    $hasTypeFilters = $this->hasTypeFilters();
    $currentTypeLabel = $this->typeTooltipLabel();
    $currentSortLabel = $this->sortOptions[$this->sort] ?? 'Default';
    $typeTooltipLabel = 'Type: '.$currentTypeLabel;
    $sortTooltipLabel = 'Sort: '.$currentSortLabel;
    $filtersTooltipLabel = $this->filtersTooltipLabel();

    $dropdownPlacements = [
        ['class' => 'hidden lg:block', 'placement' => 'right-start', 'tooltipPlacement' => 'right'],
        ['class' => 'lg:hidden', 'placement' => 'bottom-end', 'tooltipPlacement' => 'bottom'],
    ];
    $dropdownOffset = 12;
@endphp

<div
    class="fi-gsm-toolbar"
    x-on:modal-closed.window="
        if ($event.detail?.id === 'global-search-modal::plugin') {
            $wire.resetModalState();
        }
    "
>
    @foreach ($dropdownPlacements as $placementConfig)
        @php
            $tooltipPlacement = $placementConfig['tooltipPlacement'];
        @endphp
        <x-filament::dropdown
            :placement="$placementConfig['placement']"
            shift
            :flip="false"
            :offset="$dropdownOffset"
            :wire:key="$this->getId().'.gsm.type.'.$placementConfig['placement']"
            @class(['fi-gsm-toolbar-control fi-gsm-toolbar-type fi-gsm-toolbar-menu', $placementConfig['class']])
        >
            <x-slot name="trigger">
                <button
                    type="button"
                    data-gsm-tooltip-trigger
                    aria-label="Type"
                    class="fi-icon-btn fi-color-gray fi-size-md fi-gsm-toolbar-trigger fi-version-icon-btn"
                    x-tooltip="{
                        content: @js($typeTooltipLabel),
                        theme: $store.theme,
                        placement: '{{ $tooltipPlacement }}',
                        appendTo: () => document.body,
                        zIndex: 100000,
                    }"
                >
                    <x-filament::icon
                        :icon="Heroicon::Squares2x2"
                        class="fi-icon fi-size-md"
                    />
                </button>
            </x-slot>

            <x-filament::dropdown.list>
                @foreach ($this->typeOptions as $value => $label)
                    <x-filament::dropdown.list.item
                        wire:click="toggleType('{{ $value }}')"
                        x-on:click.stop
                        wire:key="gsm-type-option-{{ $value }}-{{ $placementConfig['placement'] }}"
                        :color="$this->isTypeSelected($value) ? 'primary' : 'gray'"
                        :aria-pressed="$this->isTypeSelected($value) ? 'true' : 'false'"
                        @class(['fi-active' => $this->isTypeSelected($value)])
                    >
                        {{ $label }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endforeach

    @foreach ($dropdownPlacements as $placementConfig)
        @php
            $tooltipPlacement = $placementConfig['tooltipPlacement'];
        @endphp
        <x-filament::dropdown
            :placement="$placementConfig['placement']"
            shift
            :flip="false"
            :offset="$dropdownOffset"
            :wire:key="$this->getId().'.gsm.sort.'.$placementConfig['placement']"
            @class(['fi-gsm-toolbar-control fi-gsm-toolbar-sort fi-gsm-toolbar-menu', $placementConfig['class']])
        >
            <x-slot name="trigger">
                <button
                    type="button"
                    data-gsm-tooltip-trigger
                    aria-label="Sort"
                    class="fi-icon-btn fi-color-gray fi-size-md fi-gsm-toolbar-trigger fi-version-icon-btn"
                    x-tooltip="{
                        content: @js($sortTooltipLabel),
                        theme: $store.theme,
                        placement: '{{ $tooltipPlacement }}',
                        appendTo: () => document.body,
                        zIndex: 100000,
                    }"
                >
                    <x-filament::icon
                        :icon="Heroicon::ArrowsUpDown"
                        class="fi-icon fi-size-md"
                    />
                </button>
            </x-slot>

            <x-filament::dropdown.list>
                @foreach ($this->sortOptions as $value => $label)
                    <x-filament::dropdown.list.item
                        wire:click="$set('sort', '{{ $value }}')"
                        wire:key="gsm-sort-option-{{ $value }}-{{ $placementConfig['placement'] }}"
                        :color="$this->sort === $value ? 'primary' : 'gray'"
                        @class(['fi-active' => $this->sort === $value])
                    >
                        {{ $label }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endforeach

    @foreach ($dropdownPlacements as $placementConfig)
        @php
            $tooltipPlacement = $placementConfig['tooltipPlacement'];
        @endphp
        <x-filament::dropdown
            :placement="$placementConfig['placement']"
            shift
            :flip="false"
            :offset="$dropdownOffset"
            max-height="min(70vh, 28rem)"
            :width="Width::ExtraSmall"
            :wire:key="$this->getId().'.gsm.filters.'.$placementConfig['placement']"
            @class([
                'fi-gsm-toolbar-control fi-gsm-toolbar-filters',
                $placementConfig['class'],
                'fi-gsm-toolbar-filters-unavailable' => ! $hasTypeFilters,
            ])
        >
                <x-slot name="trigger">
                    <button
                        type="button"
                        data-gsm-tooltip-trigger
                        aria-label="{{ $filtersTooltipLabel }}"
                        class="fi-icon-btn fi-color-gray fi-size-md fi-gsm-toolbar-trigger fi-version-icon-btn"
                        x-tooltip="{
                            content: @js($filtersTooltipLabel),
                            theme: $store.theme,
                            placement: '{{ $tooltipPlacement }}',
                            appendTo: () => document.body,
                            zIndex: 100000,
                        }"
                    >
                        <x-filament::icon
                            :icon="Heroicon::Funnel"
                            class="fi-icon fi-size-md"
                        />

                        @if ($activeFiltersCount > 0)
                            <div class="fi-icon-btn-badge-ctn">
                                <span
                                    {{
                                        (new FilamentComponentAttributeBag)->color(BadgeComponent::class, 'primary')->class([
                                            'fi-badge fi-size-xs',
                                        ])
                                    }}
                                >
                                    {{ $activeFiltersCount }}
                                </span>
                            </div>
                        @endif
                    </button>
                </x-slot>

                <div class="fi-gsm-filters-dropdown-panel fi-fixed-positioning-context">
                    {{ $this->getSchema('filtersForm') }}

                    <div class="fi-ta-filters-actions-ctn">
                        <x-filament::button
                            color="primary"
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
            </x-filament::dropdown>
    @endforeach
</div>
