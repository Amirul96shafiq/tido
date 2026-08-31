/**
 * Replace Filament date-picker native month <select> with a Filament-styled
 * dropdown list so the open panel matches other Selects in the admin panel.
 */
const SELECT_SELECTOR = ".fi-fo-date-time-picker-month-select";

/**
 * @param {HTMLSelectElement} select
 * @returns {boolean}
 */
function enhance(select) {
    if (select.dataset.tidoMonthEnhanced === "1") {
        return true;
    }

    if (!window.Alpine || typeof window.Alpine.$data !== "function") {
        return false;
    }

    let data;

    try {
        data = window.Alpine.$data(select);
    } catch {
        return false;
    }

    if (!data || !Array.isArray(data.months)) {
        return false;
    }

    select.dataset.tidoMonthEnhanced = "1";
    select.classList.add("tido-date-picker-month-select-native");
    select.setAttribute("aria-hidden", "true");
    select.tabIndex = -1;

    const wrapper = document.createElement("div");
    wrapper.className = "tido-date-picker-month";
    wrapper.dataset.tidoDatePickerMonth = "1";

    select.parentNode?.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "tido-date-picker-month-trigger";
    trigger.setAttribute("aria-haspopup", "listbox");
    trigger.setAttribute("aria-expanded", "false");

    const triggerLabel = document.createElement("span");
    triggerLabel.className = "tido-date-picker-month-trigger-label";
    trigger.appendChild(triggerLabel);

    const triggerIcon = document.createElement("span");
    triggerIcon.className = "tido-date-picker-month-trigger-icon";
    triggerIcon.setAttribute("aria-hidden", "true");
    triggerIcon.innerHTML =
        '<svg viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>';
    trigger.appendChild(triggerIcon);

    const panel = document.createElement("div");
    panel.className =
        "tido-date-picker-month-panel fi-dropdown-panel fi-scrollable";
    panel.setAttribute("role", "listbox");
    panel.hidden = true;

    const list = document.createElement("ul");
    list.className = "fi-dropdown-list";
    panel.appendChild(list);

    wrapper.appendChild(trigger);
    wrapper.appendChild(panel);

    const syncLabel = () => {
        const index = Number(data.focusedMonth);
        triggerLabel.textContent =
            data.months?.[index] ?? data.months?.[0] ?? "";
    };

    const renderOptions = () => {
        list.replaceChildren();

        data.months.forEach((month, index) => {
            const item = document.createElement("li");
            const option = document.createElement("button");
            option.type = "button";
            option.className = "fi-dropdown-list-item fi-select-input-option";
            option.setAttribute("role", "option");
            option.setAttribute("data-month-index", String(index));
            option.setAttribute(
                "aria-selected",
                Number(data.focusedMonth) === index ? "true" : "false",
            );

            if (Number(data.focusedMonth) === index) {
                option.classList.add("fi-selected");
            }

            const label = document.createElement("span");
            label.className = "fi-dropdown-list-item-label";
            label.textContent = month;
            option.appendChild(label);

            option.addEventListener("click", (event) => {
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
        trigger.setAttribute("aria-expanded", "false");
        wrapper.classList.remove("tido-date-picker-month-open");
    };

    const open = () => {
        renderOptions();
        panel.hidden = false;
        trigger.setAttribute("aria-expanded", "true");
        wrapper.classList.add("tido-date-picker-month-open");
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

    trigger.addEventListener("click", toggle);

    const onDocumentClick = (event) => {
        if (!wrapper.contains(event.target)) {
            close();
        }
    };

    const onKeydown = (event) => {
        if (event.key === "Escape" && !panel.hidden) {
            event.stopPropagation();
            close();
            trigger.focus();
        }
    };

    document.addEventListener("click", onDocumentClick);
    wrapper.addEventListener("keydown", onKeydown);

    syncLabel();
    renderOptions();

    if (typeof window.Alpine.effect === "function") {
        window.Alpine.effect(() => {
            // Re-read so Alpine tracks focusedMonth / months.
            void data.focusedMonth;
            void data.months;
            syncLabel();

            if (!panel.hidden) {
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
    if (!root || typeof root.querySelectorAll !== "function") {
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

    document.addEventListener("alpine:initialized", () => scan());
    document.addEventListener("livewire:navigated", () => scan());
    document.addEventListener("livewire:init", () => {
        scan();
        Livewire.hook("morphed", () => requestAnimationFrame(() => scan()));
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}

/**
 * Pin JS date-picker calendars to position:fixed inside filter overflow contexts
 * (same idea as Select + .fi-fixed-positioning-context). Keeps filter overflow
 * hidden so later fields stay inside the white card.
 *
 * Anchor to the trigger button — never re-measure the panel's own rect after a
 * prior fixed pin (that double-counts Alpine left/top as absolute offsets).
 */
function clearFixedPin(panel) {
    panel.style.removeProperty("position");
    panel.style.removeProperty("left");
    panel.style.removeProperty("top");
    panel.style.removeProperty("width");
    panel.style.removeProperty("z-index");
    panel.dataset.tidoFixedPinned = "0";
}

/**
 * @param {DOMRect} triggerRect
 * @param {HTMLElement} panel
 * @param {number} gap
 * @returns {{ left: number, top: number }}
 */
function getFixedCoordsForTrigger(triggerRect, panel, gap) {
    const estimatedHeight = panel.offsetHeight || 280;
    const viewportBottom = window.innerHeight - 8;
    let viewportTop = triggerRect.bottom + gap;

    if (viewportTop + estimatedHeight > viewportBottom) {
        viewportTop = Math.max(8, triggerRect.top - gap - estimatedHeight);
    }

    const containingBlock = panel.closest(".fi-gsm-toolbar");

    if (containingBlock instanceof HTMLElement) {
        const containingBlockRect = containingBlock.getBoundingClientRect();

        return {
            left: triggerRect.left - containingBlockRect.left,
            top: viewportTop - containingBlockRect.top,
        };
    }

    return {
        left: triggerRect.left,
        top: viewportTop,
    };
}

function pinDatePickerPanelFixed(panel) {
    if (!(panel instanceof HTMLElement)) {
        return;
    }

    if (!panel.closest(".fi-fixed-positioning-context")) {
        return;
    }

    if (getComputedStyle(panel).display === "none") {
        if (panel.dataset.tidoFixedPinned === "1") {
            clearFixedPin(panel);
        }

        return;
    }

    const trigger = panel
        .closest(".fi-fo-date-time-picker")
        ?.querySelector(".fi-fo-date-time-picker-trigger");

    if (!(trigger instanceof HTMLElement)) {
        return;
    }

    const triggerRect = trigger.getBoundingClientRect();
    const { left, top } = getFixedCoordsForTrigger(triggerRect, panel, 8);
    const isGsmFilterPanel =
        panel.closest(
            '[id="global-search-modal::plugin"] .fi-gsm-filters-dropdown-panel',
        ) instanceof HTMLElement;

    panel.dataset.tidoDatePickerPinning = "1";
    panel.style.setProperty("position", "fixed", "important");
    panel.style.setProperty("left", `${Math.round(left)}px`, "important");
    panel.style.setProperty("top", `${Math.round(top)}px`, "important");

    if (isGsmFilterPanel) {
        panel.style.setProperty("z-index", "100002", "important");
    }

    panel.dataset.tidoFixedPinned = "1";
    delete panel.dataset.tidoDatePickerPinning;
}

function watchDatePickerPanelPin(panel) {
    if (
        !(panel instanceof HTMLElement) ||
        panel.dataset.tidoFixedWatch === "1"
    ) {
        return;
    }

    panel.dataset.tidoFixedWatch = "1";

    let quiet = false;

    const schedulePin = () => {
        if (quiet || panel.dataset.tidoDatePickerPinning === "1") {
            return;
        }

        const isGsmFilterPanel =
            panel.closest(
                '[id="global-search-modal::plugin"] .fi-gsm-filters-dropdown-panel',
            ) instanceof HTMLElement;

        if (isGsmFilterPanel) {
            if (getComputedStyle(panel).display === "none") {
                return;
            }

            quiet = true;
            pinDatePickerPanelFixed(panel);
            quiet = false;

            return;
        }

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                quiet = true;
                pinDatePickerPanelFixed(panel);
                requestAnimationFrame(() => {
                    quiet = false;
                });
            });
        });
    };

    new MutationObserver(schedulePin).observe(panel, {
        attributes: true,
        attributeFilter: ["style", "class"],
    });

    schedulePin();
}

function scanDatePickerPanelPins(root = document) {
    if (!root || typeof root.querySelectorAll !== "function") {
        return;
    }

    root.querySelectorAll(".fi-fo-date-time-picker-panel").forEach((panel) => {
        watchDatePickerPanelPin(panel);
    });
}

const datePickerPinObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) {
                scanDatePickerPanelPins(node);
            }
        });
    });
});

function bootDatePickerPanelPins() {
    scanDatePickerPanelPins(document);
    datePickerPinObserver.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });
    document.addEventListener(
        "scroll",
        (event) => {
            const target = event.target;

            if (
                !(target instanceof Element) ||
                !target.closest?.(
                    '.fi-ta-filters-body, .fi-ta-filters-dropdown, [id="global-search-modal::plugin"] .fi-gsm-filters-dropdown-panel .fi-ta-filters-body',
                )
            ) {
                return;
            }

            document
                .querySelectorAll(
                    '.fi-fixed-positioning-context .fi-fo-date-time-picker-panel[data-tido-fixed-pinned="1"]',
                )
                .forEach((panel) => pinDatePickerPanelFixed(panel));
        },
        true,
    );
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootDatePickerPanelPins);
} else {
    bootDatePickerPanelPins();
}
