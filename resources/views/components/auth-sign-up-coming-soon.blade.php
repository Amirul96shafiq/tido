<div {{ $attributes->class(['tido-auth-sign-up-coming-soon']) }}>
    <div class="flex w-full flex-col items-center justify-center rounded-xl bg-white px-4 py-8 dark:bg-slate-800">
        <div class="relative mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-500/10 dark:bg-slate-500/10">
            <x-filament::icon
                icon="heroicon-o-user-plus"
                class="relative h-7 w-7 text-gray-400 dark:text-gray-500"
            />
        </div>

        <h3 class="text-center text-lg font-bold tracking-tight text-gray-950 dark:text-white">
            Coming Soon
        </h3>

        <p class="mt-2 max-w-xs text-center text-sm leading-6 text-gray-500 dark:text-gray-400">
            Account registration is not available yet. Check back later to create a new household.
        </p>
    </div>
</div>
