@props([
    'compact' => false,
    'currentPageOptionProperty' => 'tableRecordsPerPage',
    'pageOptions' => [],
])

@php
    $selectOptions = collect($pageOptions)
        ->map(function (int|string $option): array {
            return [
                'label' => $option === 'all'
                    ? __('filament::components/pagination.fields.records_per_page.options.all')
                    : (string) $option,
                'value' => (string) $option,
            ];
        })
        ->values()
        ->all();

    $currentState = data_get($this, $currentPageOptionProperty);
    $currentStateForSelect = filled($currentState) ? (string) $currentState : null;

    $initialOptionLabel = collect($selectOptions)
        ->firstWhere('value', $currentStateForSelect)['label'] ?? $currentStateForSelect;

    $variant = $compact ? 'compact' : 'full';
    $prefix = $compact
        ? null
        : __('filament::components/pagination.fields.records_per_page.label');
@endphp

<label
    @class([
        'fi-pagination-records-per-page-select',
        'fi-compact' => $compact,
    ])
>
    <x-filament::input.wrapper :prefix="$prefix">
        <div
            x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('select', 'filament/forms') }}"
            x-data="selectFormComponent({
                canOptionLabelsWrap: true,
                canSelectPlaceholder: false,
                getOptionLabelUsing: async () => null,
                getOptionLabelsUsing: async () => [],
                getOptionsUsing: async () => @js($selectOptions),
                getSearchResultsUsing: async () => [],
                hasDynamicOptions: false,
                hasDynamicSearchResults: false,
                hasInitialNoOptionsMessage: false,
                initialOptionLabel: @js($initialOptionLabel),
                initialOptionLabels: [],
                initialState: @js($currentStateForSelect),
                isAutofocused: false,
                isDisabled: false,
                isHtmlAllowed: false,
                isMultiple: false,
                isReorderable: false,
                isSearchable: true,
                livewireId: @js($this->getId()),
                loadingMessage: @js(__('filament-forms::components.select.loading_message')),
                maxItems: null,
                maxItemsMessage: @js(__('filament-forms::components.select.max_items_message')),
                noOptionsMessage: @js(__('filament-forms::components.select.no_options_message')),
                noSearchResultsMessage: @js(__('filament-forms::components.select.no_search_results_message')),
                options: @js($selectOptions),
                optionsLimit: null,
                placeholder: null,
                position: null,
                searchDebounce: 0,
                searchingMessage: @js(__('filament-forms::components.select.searching_message')),
                searchPrompt: @js(__('filament-forms::components.select.search_prompt')),
                searchableOptionFields: ['label'],
                state: $wire.$entangle(@js($currentPageOptionProperty)).live,
                statePath: @js($currentPageOptionProperty),
            })"
            wire:ignore
            wire:key="{{ $this->getId() }}.pagination.records-per-page.{{ $variant }}"
            x-on:keydown.esc="select?.isOpen && $event.stopPropagation()"
            class="fi-select-input"
        >
            <div x-ref="select"></div>
        </div>
    </x-filament::input.wrapper>

    @if ($compact)
        <span class="fi-sr-only">
            {{ __('filament::components/pagination.fields.records_per_page.label') }}
        </span>
    @endif
</label>
