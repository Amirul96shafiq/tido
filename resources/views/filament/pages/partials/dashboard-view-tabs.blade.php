@php
    $activeView = $activeView ?? \App\Filament\Pages\Dashboard::VIEW_FINANCES;
@endphp

<div class="tido-dashboard-view-tabs">
    <x-filament::tabs label="Dashboard views">
        <x-filament::tabs.item
            wire:click="setDashboardView('finances')"
            :active="$activeView === \App\Filament\Pages\Dashboard::VIEW_FINANCES"
            aria-label="Finances"
            x-tooltip="{
                content: {{ \Illuminate\Support\Js::from('Finances') }},
                theme: $store.theme,
            }"
        >
            <x-filament::icon
                icon="heroicon-m-calculator"
                class="fi-icon fi-size-md"
                wire:loading.remove
                wire:target="setDashboardView('finances')"
            />

            <x-filament::loading-indicator
                class="fi-icon fi-size-md"
                wire:loading
                wire:target="setDashboardView('finances')"
            />
        </x-filament::tabs.item>

        <x-filament::tabs.item
            wire:click="setDashboardView('training')"
            :active="$activeView === \App\Filament\Pages\Dashboard::VIEW_TRAINING"
            aria-label="Training"
            x-tooltip="{
                content: {{ \Illuminate\Support\Js::from('Training') }},
                theme: $store.theme,
            }"
        >
            <x-filament::icon
                icon="heroicon-m-bolt"
                class="fi-icon fi-size-md"
                wire:loading.remove
                wire:target="setDashboardView('training')"
            />

            <x-filament::loading-indicator
                class="fi-icon fi-size-md"
                wire:loading
                wire:target="setDashboardView('training')"
            />
        </x-filament::tabs.item>
    </x-filament::tabs>
</div>
