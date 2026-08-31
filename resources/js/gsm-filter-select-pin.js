/**
 * Pin Filament Select option panels to position:fixed inside global-search
 * filter dropdowns. The modal is fi-absolute-positioning-context, so Filament
 * keeps selects absolute and they get clipped by .fi-ta-filters-body overflow.
 */
const GSM_MODAL_SELECTOR = '[id="global-search-modal::plugin"]';
const GSM_FILTER_PANEL_SELECTOR = ".fi-gsm-filters-dropdown-panel";
const SELECT_PANEL_SELECTOR =
    ".fi-select-input-ctn > .fi-dropdown-panel.fi-scrollable";
const PINNED_PANEL_SELECTOR = `${SELECT_PANEL_SELECTOR}[data-tido-gsm-select-fixed-pinned="1"]`;

/**
 * @param {HTMLElement} panel
 */
function clearFixedPin(panel) {
    panel.style.removeProperty("position");
    panel.style.removeProperty("left");
    panel.style.removeProperty("top");
    panel.style.removeProperty("width");
    panel.style.removeProperty("z-index");
    panel.dataset.tidoGsmSelectFixedPinned = "0";
}

/**
 * @param {HTMLElement} panel
 */
function isGsmFilterSelectPanel(panel) {
    return (
        panel.matches(SELECT_PANEL_SELECTOR) &&
        panel.closest(GSM_FILTER_PANEL_SELECTOR) instanceof HTMLElement &&
        panel.closest(GSM_MODAL_SELECTOR) instanceof HTMLElement
    );
}

/**
 * @param {DOMRect} triggerRect
 * @param {HTMLElement} panel
 * @param {number} gap
 * @returns {{ left: number, top: number }}
 */
function getFixedCoordsForTrigger(triggerRect, panel, gap) {
    const estimatedHeight = panel.offsetHeight || 240;
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

/**
 * @param {HTMLElement} panel
 */
function pinSelectPanelFixed(panel) {
    if (!isGsmFilterSelectPanel(panel)) {
        return;
    }

    if (getComputedStyle(panel).display === "none") {
        if (panel.dataset.tidoGsmSelectFixedPinned === "1") {
            clearFixedPin(panel);
        }

        return;
    }

    const trigger = panel
        .closest(".fi-select-input-ctn")
        ?.querySelector(".fi-select-input-btn");

    if (!(trigger instanceof HTMLElement)) {
        return;
    }

    const triggerRect = trigger.getBoundingClientRect();
    const { left, top } = getFixedCoordsForTrigger(triggerRect, panel, 4);

    panel.dataset.tidoGsmSelectPinning = "1";
    panel.style.setProperty("position", "fixed", "important");
    panel.style.setProperty("left", `${Math.round(left)}px`, "important");
    panel.style.setProperty("top", `${Math.round(top)}px`, "important");
    panel.style.setProperty(
        "width",
        `${Math.round(triggerRect.width)}px`,
        "important",
    );
    panel.style.setProperty("z-index", "100002", "important");
    panel.dataset.tidoGsmSelectFixedPinned = "1";
    delete panel.dataset.tidoGsmSelectPinning;
}

/**
 * @param {HTMLElement} panel
 */
function watchSelectPanelPin(panel) {
    if (
        !(panel instanceof HTMLElement) ||
        panel.dataset.tidoGsmSelectWatch === "1"
    ) {
        return;
    }

    panel.dataset.tidoGsmSelectWatch = "1";

    let quiet = false;

    const schedulePin = () => {
        if (quiet || panel.dataset.tidoGsmSelectPinning === "1") {
            return;
        }

        if (getComputedStyle(panel).display === "none") {
            return;
        }

        quiet = true;
        pinSelectPanelFixed(panel);
        quiet = false;
    };

    new MutationObserver(schedulePin).observe(panel, {
        attributes: true,
        attributeFilter: ["style", "class"],
    });

    schedulePin();
}

/**
 * @param {ParentNode} root
 */
function scanSelectPanelPins(root = document) {
    if (!root || typeof root.querySelectorAll !== "function") {
        return;
    }

    root.querySelectorAll(SELECT_PANEL_SELECTOR).forEach((panel) => {
        if (panel instanceof HTMLElement) {
            watchSelectPanelPin(panel);
        }
    });
}

const selectPinObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) {
                scanSelectPanelPins(node);
            }
        });
    });
});

function repinOpenGsmFilterSelectPanels() {
    document.querySelectorAll(PINNED_PANEL_SELECTOR).forEach((panel) => {
        if (panel instanceof HTMLElement) {
            pinSelectPanelFixed(panel);
        }
    });
}

function bootGsmFilterSelectPins() {
    scanSelectPanelPins(document);
    selectPinObserver.observe(document.documentElement, {
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
                    `${GSM_MODAL_SELECTOR} ${GSM_FILTER_PANEL_SELECTOR} .fi-ta-filters-body, ${GSM_MODAL_SELECTOR} .fi-gsm-toolbar-filters`,
                )
            ) {
                return;
            }

            repinOpenGsmFilterSelectPanels();
        },
        true,
    );

    window.addEventListener("resize", repinOpenGsmFilterSelectPanels);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootGsmFilterSelectPins);
} else {
    bootGsmFilterSelectPins();
}
