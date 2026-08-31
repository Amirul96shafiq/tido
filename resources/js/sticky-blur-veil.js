/**
 * Sticky pin + blur veil — toggles `.tido-sticky-stuck` while pinned.
 * See docs/ui-sticky-blur.md.
 */
const PIN_SELECTOR =
    '.tido-sticky-scope > .fi-sc > .fi-grid-col:has(.tido-sticky-marker)';
const STUCK_CLASS = 'tido-sticky-stuck';
const SCROLLING_CLASS = 'tido-is-scrolling';
const SCROLL_IDLE_MS = 150;

/**
 * @typedef {{ isBottom: boolean, expectedOffset: number }} PinMetrics
 */

/** @type {Set<Element>} */
const tracked = new Set();

/** @type {WeakMap<Element, PinMetrics>} */
const metricsByPin = new WeakMap();

/** @type {WeakMap<Element, boolean>} */
const stuckByPin = new WeakMap();

let rafId = null;
let bindRafId = null;
let scrollIdleTimer = null;
let listening = false;

function findPins() {
    return Array.from(document.querySelectorAll(PIN_SELECTOR));
}

function isBottomPin(pinEl) {
    return Boolean(pinEl.querySelector('.tido-sticky-marker--bottom'));
}

function readMetrics(pinEl) {
    const isBottom = isBottomPin(pinEl);
    const style = getComputedStyle(pinEl);
    const expectedOffset = isBottom
        ? parseFloat(style.bottom) || 0
        : parseFloat(style.top) || 0;
    const metrics = { isBottom, expectedOffset };

    metricsByPin.set(pinEl, metrics);

    return metrics;
}

function metricsFor(pinEl) {
    return metricsByPin.get(pinEl) ?? readMetrics(pinEl);
}

function invalidateMetrics() {
    for (const pin of tracked) {
        metricsByPin.delete(pin);
    }
}

function isStuck(pinEl) {
    const metrics = metricsFor(pinEl);
    const rect = pinEl.getBoundingClientRect();

    if (metrics.isBottom) {
        return Math.abs(window.innerHeight - rect.bottom - metrics.expectedOffset) < 2;
    }

    return Math.abs(rect.top - metrics.expectedOffset) < 2;
}

function forgetPin(pin) {
    tracked.delete(pin);
    metricsByPin.delete(pin);
    stuckByPin.delete(pin);
}

function updateStuck() {
    rafId = null;

    for (const pin of [...tracked]) {
        if (! document.contains(pin)) {
            forgetPin(pin);
            continue;
        }

        const stuck = isStuck(pin);
        const previous = stuckByPin.get(pin);
        const hasClass = pin.classList.contains(STUCK_CLASS);

        if (previous === stuck && hasClass === stuck) {
            continue;
        }

        stuckByPin.set(pin, stuck);
        pin.classList.toggle(STUCK_CLASS, stuck);
    }
}

function onScrollOrResize() {
    if (rafId !== null) {
        return;
    }

    rafId = requestAnimationFrame(updateStuck);
}

function clearScrolling() {
    if (scrollIdleTimer !== null) {
        window.clearTimeout(scrollIdleTimer);
        scrollIdleTimer = null;
    }

    document.documentElement.classList.remove(SCROLLING_CLASS);
}

function markScrolling() {
    document.documentElement.classList.add(SCROLLING_CLASS);

    if (scrollIdleTimer !== null) {
        window.clearTimeout(scrollIdleTimer);
    }

    scrollIdleTimer = window.setTimeout(() => {
        document.documentElement.classList.remove(SCROLLING_CLASS);
        scrollIdleTimer = null;
    }, SCROLL_IDLE_MS);
}

function isPageScrollTarget(target) {
    if (
        target === document
        || target === document.documentElement
        || target === document.body
        || target === window
    ) {
        return true;
    }

    if (
        document.documentElement.classList.contains('tido-mobilenav')
        && target instanceof Element
        && target.classList.contains('fi-main-ctn')
    ) {
        return true;
    }

    return false;
}

/**
 * Nested scrollers (widget lists) move content under the fixed veil without
 * firing a window scroll, so listen in the capture phase and stand the veil
 * blur down for those too. Pin stuck-state updates still run only for page scroll.
 */
function onScrollCapture(event) {
    markScrolling();

    if (! isPageScrollTarget(event.target)) {
        return;
    }

    onScrollOrResize();
}

function onResize() {
    invalidateMetrics();
    onScrollOrResize();
}

function bind() {
    for (const pin of [...tracked]) {
        if (! document.contains(pin)) {
            forgetPin(pin);
        }
    }

    for (const pin of findPins()) {
        tracked.add(pin);
    }

    if (tracked.size === 0) {
        return;
    }

    if (! listening) {
        document.addEventListener('scroll', onScrollCapture, {
            passive: true,
            capture: true,
        });
        window.addEventListener('resize', onResize, { passive: true });
        listening = true;
    }

    updateStuck();
}

function init() {
    clearScrolling();
    bind();
}

/**
 * Livewire morph (e.g. dashboard Focus tabs) replaces sticky pins without
 * firing livewire:navigated — re-bind after the component finishes morphing.
 */
function scheduleBindAfterMorph() {
    if (bindRafId !== null) {
        return;
    }

    bindRafId = requestAnimationFrame(() => {
        bindRafId = null;
        init();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

document.addEventListener('livewire:navigated', init);

document.addEventListener('livewire:init', () => {
    Livewire.hook('morphed', scheduleBindAfterMorph);
});
