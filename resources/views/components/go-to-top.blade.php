<div
    x-data="{
        visible: false,
        atPageBottom: false,
        threshold: 50,
        update() {
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const viewportHeight = window.innerHeight;
            const scrollHeight = document.documentElement.scrollHeight;

            this.visible = scrollTop > this.threshold;
            this.atPageBottom = scrollTop + viewportHeight >= scrollHeight - this.threshold;
        },
        goToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    }"
    x-init="
        update();
        window.addEventListener('scroll', () => update(), { passive: true });
        window.addEventListener('resize', () => update(), { passive: true });
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
    :class="{ 'tido-go-to-top--page-bottom': atPageBottom }"
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
