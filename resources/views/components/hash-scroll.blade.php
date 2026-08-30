{{-- SPA-safe scroll to URL hash after navigation (global search section links). --}}
<script data-navigate-once>
    (() => {
        if (window.__tidoHashScrollInstalled) {
            return;
        }

        window.__tidoHashScrollInstalled = true;

        const maxAttempts = 10;
        const retryDelayMs = 150;

        const scrollBehavior = () => (
            typeof window.tidoPrefersReducedMotion === 'function' && window.tidoPrefersReducedMotion()
                ? 'auto'
                : 'smooth'
        );

        const scrollToHash = (attempt = 0) => {
            const hash = window.location.hash;

            if (!hash || hash.length < 2) {
                return;
            }

            const id = decodeURIComponent(hash.slice(1));
            const target = document.getElementById(id);

            if (!target) {
                if (attempt < maxAttempts) {
                    window.setTimeout(() => scrollToHash(attempt + 1), retryDelayMs);
                }

                return;
            }

            const sectionId = id.startsWith('expense-item-') ? 'line-items' : id;

            window.dispatchEvent(
                new CustomEvent('open-section', {
                    detail: { id: sectionId },
                }),
            );

            const repeaterItem = target.closest('.fi-fo-repeater-item');
            let scrollTarget = target;

            if (repeaterItem) {
                repeaterItem.dispatchEvent(new CustomEvent('expand'));

                if (repeaterItem.classList.contains('fi-collapsed') && attempt < maxAttempts) {
                    window.setTimeout(() => scrollToHash(attempt + 1), retryDelayMs);

                    return;
                }

                const headerLabel = repeaterItem.querySelector('.fi-fo-repeater-item-header-label');

                scrollTarget = headerLabel ?? repeaterItem;
            }

            requestAnimationFrame(() => {
                scrollTarget.scrollIntoView({
                    behavior: scrollBehavior(),
                    block: 'start',
                });
            });
        };

        const scheduleScroll = () => {
            window.setTimeout(() => scrollToHash(), retryDelayMs);
        };

        document.addEventListener('DOMContentLoaded', scheduleScroll);
        document.addEventListener('livewire:navigated', scheduleScroll);
        window.addEventListener('hashchange', () => scrollToHash());
    })();
</script>
