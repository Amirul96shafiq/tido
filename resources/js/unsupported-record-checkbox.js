/**
 * Keep unsupported expense selection controls visible without making them selectable.
 * Filament omits the native checkbox when isRecordSelectable() returns false, so
 * this adds a disabled visual control to the marked expense rows after each render.
 */
const UNSUPPORTED_ROW_SELECTOR = '.tido-ta-record-unsupported';
const CHECKBOX_SELECTOR = '[data-tido-unsupported-checkbox]';

function createDisabledCheckbox(row) {
    const checkbox = document.createElement('input');
    const recordKey = row.getAttribute('wire:key')?.split('.records.').pop() ?? '';

    checkbox.type = 'checkbox';
    checkbox.disabled = true;
    checkbox.tabIndex = -1;
    checkbox.className = 'fi-ta-record-checkbox fi-checkbox-input';
    checkbox.setAttribute(
        'aria-label',
        recordKey ? `Select record ${recordKey}` : 'Select record',
    );
    checkbox.dataset.tidoUnsupportedCheckbox = 'true';

    return checkbox;
}

function ensureDisabledCheckbox(row) {
    if (row.querySelector(CHECKBOX_SELECTOR)) {
        return;
    }

    const selectionCell = row.querySelector(':scope > .fi-ta-selection-cell');

    if (selectionCell) {
        selectionCell.append(createDisabledCheckbox(row));

        return;
    }

    const contentContainer = row.querySelector(
        ':scope > .fi-ta-record-content-ctn',
    );

    if (contentContainer) {
        row.insertBefore(createDisabledCheckbox(row), contentContainer);
    }
}

function scan(root = document) {
    if (!root || typeof root.querySelectorAll !== 'function') {
        return;
    }

    if (root.matches?.(UNSUPPORTED_ROW_SELECTOR)) {
        ensureDisabledCheckbox(root);
    }

    root.querySelectorAll(UNSUPPORTED_ROW_SELECTOR).forEach(
        ensureDisabledCheckbox,
    );
}

const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) {
                scan(node);
            }
        });
    });
});

function boot() {
    scan();
    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });
    document.addEventListener('livewire:navigated', () => scan());
    document.addEventListener('livewire:init', () => {
        scan();

        Livewire.hook('morphed', () => requestAnimationFrame(() => scan()));
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
