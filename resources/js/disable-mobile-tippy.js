/**
 * Disable Filament Tippy (x-tooltip) below the Tailwind `sm` breakpoint.
 * Chart.js widget tooltips are unaffected (not Tippy).
 * See docs/ui-tooltips.md.
 */
const MOBILE_MQ = window.matchMedia('(max-width: 639px)');
const patched = new WeakSet();

function isMobile() {
    return MOBILE_MQ.matches;
}

/**
 * @param {{ setProps: Function, hide: Function, state?: { isVisible?: boolean } } | undefined | null} tip
 */
function patchInstance(tip) {
    if (!tip || patched.has(tip)) {
        return;
    }

    patched.add(tip);

    tip.setProps({
        touch: isMobile() ? false : true,
        onShow() {
            return !isMobile();
        },
    });

    if (isMobile()) {
        tip.hide();
    }
}

/**
 * @param {Element} el
 */
function patchElement(el) {
    if (el.__x_tippy) {
        patchInstance(el.__x_tippy);
    }

    if (el._tippy) {
        patchInstance(el._tippy);
    }
}

/**
 * @param {ParentNode | null | undefined} root
 */
function scan(root = document) {
    if (!root) {
        return;
    }

    const scope = root instanceof Element || root instanceof Document ? root : document;

    if (scope instanceof Element) {
        patchElement(scope);
    }

    scope
        .querySelectorAll(
            '[x-tooltip], [x-tooltip\\.html], [x-tooltip\\.raw], [data-tippy-root]',
        )
        .forEach((el) => patchElement(el));
}

function hideVisibleTippies() {
    document.querySelectorAll('[data-tippy-root]').forEach((root) => {
        const tip = root._tippy;

        if (tip?.state.isVisible) {
            tip.hide();
        }
    });
}

function syncForViewport() {
    scan(document);

    document
        .querySelectorAll(
            '[x-tooltip], [x-tooltip\\.html], [x-tooltip\\.raw], [data-tippy-root]',
        )
        .forEach((el) => {
            const tip = el.__x_tippy ?? el._tippy;

            if (!tip) {
                return;
            }

            tip.setProps({
                touch: isMobile() ? false : true,
            });

            if (isMobile()) {
                tip.hide();
            }
        });

    if (isMobile()) {
        hideVisibleTippies();
    }
}

/**
 * @param {Event} event
 */
function onPointerOrHover(event) {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    let current = target;

    while (current) {
        patchElement(current);
        current = current.parentElement;
    }
}

const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
            if (node instanceof Element) {
                scan(node);
            }
        }
    }
});

function boot() {
    scan(document);
    syncForViewport();

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });

    MOBILE_MQ.addEventListener('change', syncForViewport);

    document.addEventListener('pointerdown', onPointerOrHover, true);
    document.addEventListener('mouseenter', onPointerOrHover, true);
    document.addEventListener('livewire:navigated', () => {
        scan(document);
        syncForViewport();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
