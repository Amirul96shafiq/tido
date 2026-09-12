{{-- SPA-safe scroll to URL hash after navigation (global search section links). --}}
<script data-navigate-once>
    (() => {
        if (window.__tidoHashScrollInstalled) {
            return;
        }

        window.__tidoHashScrollInstalled = true;

        const maxAttempts = 10;
        const retryDelayMs = 150;
        const expenseHighlightClass = 'tido-expense-item-search-highlight';
        const sectionHighlightClass = 'tido-section-search-highlight';

        const scrollBehavior = () => (
            typeof window.tidoPrefersReducedMotion === 'function' && window.tidoPrefersReducedMotion()
                ? 'auto'
                : 'smooth'
        );

        const pageScrollRoot = () => {
            const main = document.querySelector('.fi-main-ctn');

            if (!main) {
                return null;
            }

            const overflowY = getComputedStyle(main).overflowY;

            if (overflowY === 'auto' || overflowY === 'scroll') {
                return main;
            }

            return null;
        };

        const scrollElementIntoView = (scrollTarget) => {
            const behavior = scrollBehavior();
            const scroller = pageScrollRoot();

            if (!scroller) {
                if (behavior === 'auto') {
                    scrollTarget.scrollIntoView({
                        behavior: 'auto',
                        block: 'start',
                    });
                } else {
                    scrollTarget.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start',
                    });
                }

                return;
            }

            const elementRect = scrollTarget.getBoundingClientRect();
            const scrollerRect = scroller.getBoundingClientRect();
            const scrollMarginTop = parseFloat(getComputedStyle(scrollTarget).scrollMarginTop) || 0;
            const top = Math.max(
                0,
                scroller.scrollTop + (elementRect.top - scrollerRect.top) - scrollMarginTop,
            );

            scroller.scrollTop = top;
        };

        const clearSearchHighlights = () => {
            document.querySelectorAll(`.${expenseHighlightClass}, .${sectionHighlightClass}`)
                .forEach((element) => {
                    element.classList.remove(expenseHighlightClass);
                    element.classList.remove(sectionHighlightClass);
                });
        };

        window.tidoClearSearchHighlights = clearSearchHighlights;

        const sectionBorderTarget = (element) => {
            if (element.classList.contains('fi-section')) {
                return element;
            }

            if (element.classList.contains('fi-sc-section')) {
                return element.querySelector(':scope > .fi-section');
            }

            return null;
        };

        const applySearchHighlight = (target) => {
            document.documentElement.classList.remove('tido-suppress-search-highlight');
            clearSearchHighlights();

            const repeaterItem = target.closest('.fi-fo-repeater-item');

            if (repeaterItem) {
                repeaterItem.classList.add(expenseHighlightClass);

                return;
            }

            const section = sectionBorderTarget(target);

            if (section) {
                section.classList.add(sectionHighlightClass);
            }
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

            applySearchHighlight(target);

            requestAnimationFrame(() => {
                scrollElementIntoView(scrollTarget);
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
