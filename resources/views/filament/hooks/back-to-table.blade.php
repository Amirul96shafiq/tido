@php
    use Filament\Resources\Pages\CreateRecord;
    use Filament\Resources\Pages\EditRecord;
    use Livewire\Livewire;

    $livewire = Livewire::current();
    $showBackToTable = $livewire instanceof CreateRecord || $livewire instanceof EditRecord;
    $url = $showBackToTable ? $livewire::getResource()::getUrl('index') : null;
@endphp

@if ($showBackToTable && filled($url))
    <a
        href="{{ $url }}"
        wire:navigate
        class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 transition hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400"
    >
        <x-heroicon-o-arrow-left class="size-4 shrink-0" />
        Go back to table
    </a>
@endif
