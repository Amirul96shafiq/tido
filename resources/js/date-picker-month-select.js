/**
 * Replace Filament date-picker native month <select> with a Filament-styled
 * dropdown list so the open panel matches other Selects in the admin panel.
 */
const SELECT_SELECTOR = '.fi-fo-date-time-picker-month-select';

/**
 * @param {HTMLSelectElement} select
 * @returns {boolean}
 */
function enhance(select) {
    if (select.dataset.tidoMonthEnhanced === '1') {
        return true;
    }

    if (! window.Alpine || typeof window.Alpine.$data !== 'function') {
        return false;
    }

    let data;

    try {
        data = window.Alpine.$data(select);
    } catch {
        return false;
    }

    if (! data || ! Array.isArray(data.months)) {
        return false;
    }

    select.dataset.tidoMonthEnhanced = '1';
    select.classList.add('tido-date-picker-month-select-native');
    select.setAttribute('aria-hidden', 'true');
    select.tabIndex = -1;

    const wrapper = document.createElement('div');
    wrapper.className = 'tido-date-picker-month';
    wrapper.dataset.tidoDatePickerMonth = '1';

    select.parentNode?.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'tido-date-picker-month-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const triggerLabel = document.createElement('span');
    triggerLabel.className = 'tido-date-picker-month-trigger-label';
    trigger.appendChild(triggerLabel);

    const triggerIcon = document.createElement('span');
    triggerIcon.className = 'tido-date-picker-month-trigger-icon';
    triggerIcon.setAttribute('aria-hidden', 'true');
    triggerIcon.innerHTML =
        '<svg viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>';
    trigger.appendChild(triggerIcon);

    const panel = document.createElement('div');
    panel.className =
        'tido-date-picker-month-panel fi-dropdown-panel fi-scrollable';
    panel.setAttribute('role', 'listbox');
    panel.hidden = true;

    const list = document.createElement('ul');
    list.className = 'fi-dropdown-list';
    panel.appendChild(list);

    wrapper.appendChild(trigger);
    wrapper.appendChild(panel);

    const syncLabel = () => {
        const index = Number(data.focusedMonth);
        triggerLabel.textContent =
            data.months?.[index] ?? data.months?.[0] ?? '';
    };

    const renderOptions = () => {
        list.replaceChildren();

        data.months.forEach((month, index) => {
            const item = document.createElement('li');
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'fi-dropdown-list-item fi-select-input-option';
            option.setAttribute('role', 'option');
            option.setAttribute('data-month-index', String(index));
            option.setAttribute(
                'aria-selected',
                Number(data.focusedMonth) === index ? 'true' : 'false',
            );

            if (Number(data.focusedMonth) === index) {
                option.classList.add('fi-selected');
            }

            const label = document.createElement('span');
            label.className = 'fi-dropdown-list-item-label';
            label.textContent = month;
            option.appendChild(label);

            option.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                data.focusedMonth = index;
                syncLabel();
                renderOptions();
                close();
            });

            item.appendChild(option);
            list.appendChild(item);
        });
    };

    const close = () => {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        wrapper.classList.remove('tido-date-picker-month-open');
    };

    const open = () => {
        renderOptions();
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        wrapper.classList.add('tido-date-picker-month-open');
    };

    const toggle = (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (panel.hidden) {
            open();
        } else {
            close();
        }
    };

    trigger.addEventListener('click', toggle);

    const onDocumentClick = (event) => {
        if (! wrapper.contains(event.target)) {
            close();
        }
    };

    const onKeydown = (event) => {
        if (event.key === 'Escape' && ! panel.hidden) {
            event.stopPropagation();
            close();
            trigger.focus();
        }
    };

    document.addEventListener('click', onDocumentClick);
    wrapper.addEventListener('keydown', onKeydown);

    syncLabel();
    renderOptions();

    if (typeof window.Alpine.effect === 'function') {
        window.Alpine.effect(() => {
            // Re-read so Alpine tracks focusedMonth / months.
            void data.focusedMonth;
            void data.months;
            syncLabel();

            if (! panel.hidden) {
                renderOptions();
            }
        });
    }

    return true;
}

/**
 * @param {ParentNode} [root]
 */
function scan(root = document) {
    if (! root || typeof root.querySelectorAll !== 'function') {
        return;
    }

    root.querySelectorAll(SELECT_SELECTOR).forEach((node) => {
        if (node instanceof HTMLSelectElement) {
            enhance(node);
        }
    });
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

    document.addEventListener('alpine:initialized', () => scan());
    document.addEventListener('livewire:navigated', () => scan());
    document.addEventListener('livewire:init', () => {
        scan();
        Livewire.hook('morphed', () =>
            requestAnimationFrame(() => scan()),
        );
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
