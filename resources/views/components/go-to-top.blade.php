<div
    x-data="{
        visible: false,
        threshold: 50,
        rafId: null,
        update() {
            const scrollTop = window.scrollY || document.documentElement.scrollTop;

            this.visible = scrollTop > this.threshold;
        },
        scheduleUpdate() {
            if (this.rafId !== null) {
                return;
            }

            this.rafId = window.requestAnimationFrame(() => {
                this.rafId = null;
                this.update();
            });
        },
        goToTop() {
            const behavior = typeof window.tidoPrefersReducedMotion === 'function' && window.tidoPrefersReducedMotion()
                ? 'auto'
                : 'smooth';

            window.scrollTo({ top: 0, behavior });
        },
    }"
    x-init="
        update();
        window.addEventListener('scroll', () => scheduleUpdate(), { passive: true });
        window.addEventListener('resize', () => scheduleUpdate(), { passive: true });
        document.addEventListener('livewire:navigated', () => update());
    "
    x-cloak
    x-show="visible"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="tido-go-to-top"
>
    <button
        type="button"
        class="tido-go-to-top-btn"
        aria-label="Go to top"
        x-tooltip="{
            content: @js('Go to top'),
            theme: $store.theme,
        }"
        @click="goToTop()"
    >
        <x-heroicon-o-arrow-up class="h-5 w-5" />
    </button>
</div>
