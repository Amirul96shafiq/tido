@props([
    'id',
    'heading',
    'icon',
    'description',
])

<x-filament::section :id="$id">
    <x-slot name="heading">{{ $heading }}</x-slot>

    <div class="flex min-h-72 w-full flex-col items-center justify-center rounded-xl bg-white px-4 py-6 dark:bg-slate-800">
        <div class="relative mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-gray-500/10 dark:bg-slate-500/10">
            <x-filament::icon :icon="$icon" class="relative h-10 w-10 text-gray-400 dark:text-gray-500" />
        </div>

        <h3 class="text-center text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Coming soon</h3>

        <p class="mt-4 max-w-sm text-center text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $description }}</p>
    </div>
</x-filament::section>
