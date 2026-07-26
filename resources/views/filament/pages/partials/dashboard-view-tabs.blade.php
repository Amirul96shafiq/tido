@php
    $activeView = $activeView ?? \App\Filament\Pages\Dashboard::VIEW_FINANCES;
@endphp

<div class="tido-dashboard-view-tabs">
    <x-filament::tabs label="Dashboard views">
        <x-filament::tabs.item
            wire:click="setDashboardView('finances')"
            :active="$activeView === \App\Filament\Pages\Dashboard::VIEW_FINANCES"
            icon="heroicon-m-calculator"
            aria-label="Finances"
            x-tooltip="{
                content: {{ \Illuminate\Support\Js::from('Finances') }},
                theme: $store.theme,
            }"
        ></x-filament::tabs.item>

        <x-filament::tabs.item
            wire:click="setDashboardView('training')"
            :active="$activeView === \App\Filament\Pages\Dashboard::VIEW_TRAINING"
            icon="heroicon-m-bolt"
            aria-label="Training"
            x-tooltip="{
                content: {{ \Illuminate\Support\Js::from('Training') }},
                theme: $store.theme,
            }"
        ></x-filament::tabs.item>
    </x-filament::tabs>
</div>
