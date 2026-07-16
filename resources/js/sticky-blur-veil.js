/**
 * Sticky pin + blur veil — toggles `.tido-sticky-stuck` while pinned.
 * See docs/ui-sticky-blur.md.
 */
const PIN_SELECTOR =
    '.tido-sticky-scope > .fi-sc > .fi-grid-col:has(.tido-sticky-marker)';
const STUCK_CLASS = 'tido-sticky-stuck';

/** @type {Set<Element>} */
const tracked = new Set();
let rafId = null;
let listening = false;

function findPins() {
    return Array.from(document.querySelectorAll(PIN_SELECTOR));
}

function isBottomPin(pinEl) {
    return Boolean(pinEl.querySelector('.tido-sticky-marker--bottom'));
}

function isStuck(pinEl) {
    const style = getComputedStyle(pinEl);
    const rect = pinEl.getBoundingClientRect();

    if (isBottomPin(pinEl)) {
        const expectedBottom = parseFloat(style.bottom) || 0;

        return Math.abs(window.innerHeight - rect.bottom - expectedBottom) < 2;
    }

    const expectedTop = parseFloat(style.top) || 0;

    return Math.abs(rect.top - expectedTop) < 2;
}

function updateStuck() {
    rafId = null;

    for (const pin of [...tracked]) {
        if (! document.contains(pin)) {
            tracked.delete(pin);
            continue;
        }

        pin.classList.toggle(STUCK_CLASS, isStuck(pin));
    }
}

function onScrollOrResize() {
    if (rafId !== null) {
        return;
    }

    rafId = requestAnimationFrame(updateStuck);
}

function bind() {
    for (const pin of findPins()) {
        tracked.add(pin);
    }

    if (tracked.size === 0) {
        return;
    }

    if (! listening) {
        window.addEventListener('scroll', onScrollOrResize, { passive: true });
        window.addEventListener('resize', onScrollOrResize, { passive: true });
        listening = true;
    }

    updateStuck();
}

function init() {
    bind();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

document.addEventListener('livewire:navigated', init);
