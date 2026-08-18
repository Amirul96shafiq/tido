/**
 * Stop Filament searchable Selects from focusing the dropdown search input
 * when the panel opens. Clicking Search or typing a printable character still
 * focuses it. See docs/vite-assets.md.
 */
const SEARCH_SELECTOR = '.fi-dropdown-panel input.fi-input[aria-label="Search"]';
const BUTTON_SELECTOR = '.fi-select-input-btn';

let allowSearchFocus = false;

/**
 * @param {EventTarget | null} target
 * @returns {target is HTMLInputElement}
 */
function isSelectSearchInput(target) {
    return target instanceof HTMLInputElement && target.matches(SEARCH_SELECTOR);
}

/**
 * @returns {void}
 */
function allowNextSearchFocus() {
    allowSearchFocus = true;
}

/**
 * @returns {void}
 */
function clearAllowSearchFocus() {
    allowSearchFocus = false;
}

document.addEventListener('pointerdown', (event) => {
    const target = event.target;

    if (! (target instanceof Element)) {
        return;
    }

    if (target.closest(SEARCH_SELECTOR)) {
        allowNextSearchFocus();

        return;
    }

    if (target.closest(BUTTON_SELECTOR)) {
        clearAllowSearchFocus();
    }
}, true);

document.addEventListener('keydown', (event) => {
    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    if (typeof event.key !== 'string' || event.key.length !== 1 || event.key === ' ') {
        return;
    }

    const target = event.target;

    if (! (target instanceof Element) || ! target.closest(BUTTON_SELECTOR)) {
        return;
    }

    allowNextSearchFocus();
}, true);

document.addEventListener('focusin', (event) => {
    const input = event.target;

    if (! isSelectSearchInput(input)) {
        return;
    }

    if (allowSearchFocus) {
        clearAllowSearchFocus();

        return;
    }

    const related = event.relatedTarget;
    const fromButton = related instanceof Element && related.closest(BUTTON_SELECTOR) !== null;
    const fromOutside = related === null || related === document.body;

    if (! fromButton && ! fromOutside) {
        return;
    }

    input.blur();

    const button = input.closest('.fi-select-input-ctn')?.querySelector(BUTTON_SELECTOR);

    if (button instanceof HTMLElement) {
        button.focus({ preventScroll: true });
    }
});
