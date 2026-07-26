<div class="tido-dashboard-view-tabs">
    <x-filament::tabs label="Dashboard views">
        <x-filament::tabs.item active>
            Finances
        </x-filament::tabs.item>

        <span
            class="inline-flex"
            x-tooltip="{
                content: @js('Coming soon'),
                theme: $store.theme,
            }"
        >
            <x-filament::tabs.item
                disabled
                aria-label="Training (coming soon)"
            >
                Training
            </x-filament::tabs.item>
        </span>
    </x-filament::tabs>
</div>
