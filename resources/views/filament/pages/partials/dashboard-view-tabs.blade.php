@php
    $activeView = $activeView ?? \App\Filament\Pages\Dashboard::VIEW_FINANCES;
    $tabs = \App\Filament\Pages\Dashboard::viewTabs();
@endphp

<div class="tido-dashboard-view-tabs">
    <span class="tido-dashboard-view-tabs__label" aria-hidden="true">Focus:</span>

    <x-filament::tabs label="Dashboard views">
        @foreach ($tabs as $tab)
            @php
                $viewCall = new \Illuminate\Support\HtmlString(
                    'setDashboardView('.\Illuminate\Support\Js::from($tab['view']).')'
                );
            @endphp

            <x-filament::tabs.item
                wire:click="{{ $viewCall }}"
                :active="$activeView === $tab['view']"
                :aria-label="$tab['label']"
                x-tooltip="{
                    content: {{ \Illuminate\Support\Js::from($tab['label']) }},
                    theme: $store.theme,
                }"
            >
                <x-filament::icon
                    :icon="$tab['icon']"
                    class="fi-icon fi-size-md"
                    wire:loading.remove
                    wire:target="{{ $viewCall }}"
                />

                <x-filament::loading-indicator
                    class="fi-icon fi-size-md"
                    wire:loading
                    wire:target="{{ $viewCall }}"
                />
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>
</div>
