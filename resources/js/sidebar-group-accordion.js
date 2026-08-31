/**
 * Mobile sidebar nav groups are exclusive: only one Finances / Settings /
 * Integrations / Tools section stays expanded below the `lg` breakpoint.
 * Opening a group closes the previous. Desktop keeps Filament's independent
 * collapsed-group list.
 *
 * See docs/ui-mobile-nav.md.
 */
const MOBILE_MQ = window.matchMedia("(max-width: 1023px)");
const OPEN_GROUP_KEY = "tidoMobileOpenNavGroup";

/**
 * @returns {boolean}
 */
function isMobileAccordionViewport() {
    return MOBILE_MQ.matches;
}

/**
 * @returns {string[]}
 */
function groupLabels() {
    return Array.from(
        document.querySelectorAll(
            ".fi-main-sidebar .fi-sidebar-group[data-group-label]",
        ),
    )
        .map((group) => group.getAttribute("data-group-label"))
        .filter((label) => typeof label === "string" && label !== "");
}

/**
 * @param {string[]} labels
 * @param {boolean} preferActive
 * @returns {string}
 */
function resolveOpenGroup(labels, preferActive) {
    const active = document.querySelector(
        ".fi-main-sidebar .fi-sidebar-group.fi-active[data-group-label]",
    );
    const activeLabel = active?.getAttribute("data-group-label");

    if (preferActive && activeLabel && labels.includes(activeLabel)) {
        return activeLabel;
    }

    const stored = sessionStorage.getItem(OPEN_GROUP_KEY);

    if (stored === "") {
        return "";
    }

    if (stored && labels.includes(stored)) {
        return stored;
    }

    if (activeLabel && labels.includes(activeLabel)) {
        return activeLabel;
    }

    return labels[0] ?? "";
}

/**
 * @param {{ collapsedGroups?: unknown }} store
 * @param {string[]} labels
 * @param {string} openLabel
 */
function setExclusiveCollapsedGroups(store, labels, openLabel) {
    store.collapsedGroups =
        openLabel === ""
            ? labels.slice()
            : labels.filter((label) => label !== openLabel);
}

/**
 * @param {{ collapsedGroups?: unknown, toggleCollapsedGroup: Function }} store
 */
function wrapStore(store) {
    const originalToggle = store.toggleCollapsedGroup.bind(store);

    store.toggleCollapsedGroup = function toggleCollapsedGroup(group) {
        if (!isMobileAccordionViewport()) {
            originalToggle(group);

            return;
        }

        const labels = groupLabels();

        if (labels.length === 0) {
            originalToggle(group);

            return;
        }

        const collapsed = Array.isArray(store.collapsedGroups)
            ? store.collapsedGroups
            : [];
        const isOpening = collapsed.includes(group);

        if (isOpening) {
            sessionStorage.setItem(OPEN_GROUP_KEY, group);
            setExclusiveCollapsedGroups(store, labels, group);

            return;
        }

        sessionStorage.setItem(OPEN_GROUP_KEY, "");
        setExclusiveCollapsedGroups(store, labels, "");
    };
}

/**
 * @param {{ collapsedGroups?: unknown }} store
 * @param {{ preferActive?: boolean }} [options]
 */
function enforceMobileAccordion(store, options = {}) {
    if (!isMobileAccordionViewport()) {
        return;
    }

    const labels = groupLabels();

    if (labels.length === 0) {
        return;
    }

    const preferActive =
        options.preferActive === true ||
        sessionStorage.getItem(OPEN_GROUP_KEY) === null;
    const openLabel = resolveOpenGroup(labels, preferActive);

    sessionStorage.setItem(OPEN_GROUP_KEY, openLabel);
    setExclusiveCollapsedGroups(store, labels, openLabel);
}

/**
 * @param {{ preferActive?: boolean }} [options]
 * @returns {boolean}
 */
function patchSidebarStore(options = {}) {
    const store = window.Alpine?.store?.("sidebar");

    if (!store || typeof store.toggleCollapsedGroup !== "function") {
        return false;
    }

    if (!store.__tidoMobileAccordion) {
        store.__tidoMobileAccordion = true;
        wrapStore(store);
    }

    enforceMobileAccordion(store, options);

    return true;
}

/**
 * @param {{ preferActive?: boolean }} [options]
 */
function scheduleBoot(options = {}) {
    if (patchSidebarStore(options)) {
        return;
    }

    requestAnimationFrame(() => {
        patchSidebarStore(options);
    });
}

document.addEventListener("alpine:init", () => {
    queueMicrotask(() => scheduleBoot());
});
document.addEventListener("alpine:initialized", () => scheduleBoot());
document.addEventListener("livewire:navigated", () =>
    scheduleBoot({ preferActive: true }),
);

MOBILE_MQ.addEventListener("change", () => {
    if (MOBILE_MQ.matches) {
        scheduleBoot();
    }
});

if (window.Alpine?.store?.("sidebar")) {
    scheduleBoot();
}
