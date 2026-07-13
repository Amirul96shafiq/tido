<div
    wire:poll.10s="saveDraft"
    x-data="{ savedAt: null }"
    x-on:content-draft-saved.window="savedAt = new Date().toLocaleTimeString()"
    class="fi-content-draft-poller pointer-events-none fixed bottom-4 inset-e-4 z-40"
>
    <div
        x-show="savedAt"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="flex items-center gap-2.5 rounded-lg bg-white/90 px-3 py-2 text-xs text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900/90 dark:text-gray-400 dark:ring-white/10"
    >
        <span class="relative flex h-2 w-2 shrink-0">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
        </span>

        <span x-text="savedAt ? ('Draft saved at ' + savedAt) : ''"></span>
    </div>
</div>
