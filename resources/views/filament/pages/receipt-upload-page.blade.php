<x-filament-panels::page>
    <form wire:submit="save">
        <x-filament::section>
            <x-slot name="heading">
                Upload Receipts
            </x-slot>
            <x-slot name="description">
                Select or drop receipt images to be parsed by the AI.
            </x-slot>

            {{ $this->form }}
            
            <div style="margin-top: 1.5rem;" class="flex justify-start">
                <x-filament::button type="submit" size="lg">
                    Upload and Start AI Extraction
                </x-filament::button>
            </div>
        </x-filament::section>
    </form>

    <div class="mt-4">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
