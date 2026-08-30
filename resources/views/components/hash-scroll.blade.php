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

        const clearExpenseItemHighlights = () => {
            document.querySelectorAll('.tido-expense-item-search-highlight')
                .forEach((element) => element.classList.remove('tido-expense-item-search-highlight'));
        };

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

            if (!id.startsWith('expense-item-')) {
                clearExpenseItemHighlights();
            }

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

                clearExpenseItemHighlights();
                repeaterItem.classList.add('tido-expense-item-search-highlight');
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
