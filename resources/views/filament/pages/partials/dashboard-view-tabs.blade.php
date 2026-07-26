@php
    $activeView = $activeView ?? 'finances';
    $financesUrl = $financesUrl ?? \App\Filament\Pages\Dashboard::getUrl();
    $trainingUrl = $trainingUrl ?? \App\Filament\Pages\TrainingDashboard::getUrl();
@endphp

<div class="tido-dashboard-view-tabs">
    <x-filament::tabs label="Dashboard views">
        <x-filament::tabs.item
            tag="a"
            :href="$financesUrl"
            :active="$activeView === 'finances'"
        >
            Finances
        </x-filament::tabs.item>

        <x-filament::tabs.item
            tag="a"
            :href="$trainingUrl"
            :active="$activeView === 'training'"
        >
            Training
        </x-filament::tabs.item>
    </x-filament::tabs>
</div>
