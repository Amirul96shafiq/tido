@php
    $activeView = $activeView ?? \App\Filament\Pages\Dashboard::VIEW_FINANCES;
    $tabs = \App\Filament\Pages\Dashboard::viewTabs();
@endphp

<div class="tido-dashboard-view-tabs">
    <x-filament::tabs label="Dashboard views">
        @foreach ($tabs as $tab)
            <x-filament::tabs.item
                wire:click="setDashboardView({{ \Illuminate\Support\Js::from($tab['view']) }})"
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
                    wire:target="setDashboardView({{ \Illuminate\Support\Js::from($tab['view']) }})"
                />

                <x-filament::loading-indicator
                    class="fi-icon fi-size-md"
                    wire:loading
                    wire:target="setDashboardView({{ \Illuminate\Support\Js::from($tab['view']) }})"
                />
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>
</div>
