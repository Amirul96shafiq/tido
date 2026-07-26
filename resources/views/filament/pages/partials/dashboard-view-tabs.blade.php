@php
    $activeView = $activeView ?? \App\Filament\Pages\Dashboard::VIEW_FINANCES;
@endphp

<div class="tido-dashboard-view-tabs">
    <x-filament::tabs label="Dashboard views">
        <x-filament::tabs.item
            wire:click="setDashboardView('finances')"
            :active="$activeView === \App\Filament\Pages\Dashboard::VIEW_FINANCES"
        >
            Finances
        </x-filament::tabs.item>

        <x-filament::tabs.item
            wire:click="setDashboardView('training')"
            :active="$activeView === \App\Filament\Pages\Dashboard::VIEW_TRAINING"
        >
            Training
        </x-filament::tabs.item>
    </x-filament::tabs>
</div>
