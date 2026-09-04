/**
 * Apply the looping `.tido-text-marquee-track` to Filament JS select labels.
 * Opt in with `.tido-select-value-marquee` (SelectValueMarquee::extraAttributes()).
 * Covers the closed selected value and dropdown option rows when they overflow.
 * See docs/ui-text-marquee.md.
 *
 * Overflow measure stays in JS; motion is CSS animation (same contract as
 * x-tido.text-marquee) so the main thread is not writing transform every frame.
 */
const ROOT_SELECTOR = ".tido-select-value-marquee";
const OPTION_SELECTOR = ".fi-select-input-option";
const SPEED = 40;

/**
 * @returns {boolean}
 */
function prefersReducedMotion() {
    if (typeof window.tidoPrefersReducedMotion === "function") {
        return window.tidoPrefersReducedMotion();
    }

    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

/** @type {WeakMap<HTMLElement, MarqueeState>} */
const states = new WeakMap();

/**
 * @typedef {{
 *   overflowing: boolean,
 *   scrollDistance: number,
 *   rafMeasure: number|null,
 * }} MarqueeState
 */

/**
 * @param {HTMLElement} clip
 * @returns {MarqueeState}
 */
function getState(clip) {
    let state = states.get(clip);

    if (!state) {
        state = {
            overflowing: false,
            scrollDistance: 0,
            rafMeasure: null,
        };
        states.set(clip, state);
    }

    return state;
}

/**
 * @param {HTMLElement} track
 * @returns {number}
 */
function readGap(track) {
    const styles = window.getComputedStyle(track);
    const parsed =
        Number.parseFloat(styles.columnGap) || Number.parseFloat(styles.gap);

    return Number.isFinite(parsed) ? parsed : 32;
}

/**
 * @param {HTMLElement} track
 * @param {boolean} shouldOverflow
 * @param {number} scrollDistance
 * @param {MarqueeState} state
 * @returns {void}
 */
function applyMotion(track, shouldOverflow, scrollDistance, state) {
    state.overflowing = shouldOverflow;
    state.scrollDistance = scrollDistance;
    track.classList.toggle(
        "is-overflowing",
        shouldOverflow && !prefersReducedMotion(),
    );

    if (shouldOverflow && !prefersReducedMotion() && scrollDistance > 0) {
        track.style.setProperty(
            "--tido-marquee-distance",
            `${scrollDistance}px`,
        );
        track.style.setProperty(
            "--tido-marquee-duration",
            `${(scrollDistance / SPEED).toFixed(2)}s`,
        );

        return;
    }

    track.style.removeProperty("--tido-marquee-distance");
    track.style.removeProperty("--tido-marquee-duration");
}

/**
 * @param {HTMLElement} clip
 * @param {HTMLElement} track
 * @param {HTMLElement} segment
 * @param {MarqueeState} state
 * @returns {void}
 */
function measure(clip, track, segment, state) {
    if (!segment.isConnected || !track.isConnected) {
        return;
    }

    const clipWidth = clip.clientWidth;
    const segmentWidth = segment.offsetWidth;

    if (clipWidth === 0 || segmentWidth === 0) {
        return;
    }

    const gap = readGap(track);
    const scrollDistance = segmentWidth + gap;
    const shouldOverflow = segmentWidth - clipWidth > 1;

    applyMotion(track, shouldOverflow, scrollDistance, state);
}

/**
 * @param {HTMLElement} track
 * @param {HTMLElement} label
 * @returns {void}
 */
function syncDuplicate(track, label) {
    let duplicate = track.querySelector(
        ':scope > .tido-text-marquee-segment[aria-hidden="true"]',
    );

    if (!duplicate) {
        duplicate = /** @type {HTMLElement} */ (label.cloneNode(true));
        duplicate.removeAttribute("id");
        duplicate.classList.remove("fi-select-input-value-label");
        duplicate.setAttribute("aria-hidden", "true");
        track.append(duplicate);

        return;
    }

    if (duplicate.innerHTML === label.innerHTML) {
        return;
    }

    duplicate.innerHTML = label.innerHTML;
}

/**
 * @param {HTMLElement} clip
 * @param {HTMLElement|null} label
 * @returns {{track: HTMLElement, segment: HTMLElement}|null}
 */
function ensureTrack(clip, label) {
    if (!(label instanceof HTMLElement)) {
        return null;
    }

    clip.querySelectorAll(":scope > .tido-text-marquee-track").forEach(
        (orphan) => {
            if (!orphan.contains(label)) {
                orphan.remove();
            }
        },
    );

    let track = label.closest(".tido-text-marquee-track");

    if (!(track instanceof HTMLElement) || track.parentElement !== clip) {
        track = document.createElement("span");
        track.className = "tido-text-marquee-track";
        label.classList.add(
            "tido-text-marquee-segment",
            "inline-block",
            "whitespace-nowrap",
        );
        label.replaceWith(track);
        track.append(label);
    } else {
        label.classList.add(
            "tido-text-marquee-segment",
            "inline-block",
            "whitespace-nowrap",
        );
    }

    syncDuplicate(track, label);

    return { track, segment: label };
}

/**
 * @param {HTMLElement} option
 * @returns {HTMLElement}
 */
function ensureOptionInnerClip(option) {
    let clip = option.querySelector(":scope > .tido-option-marquee-clip");

    if (clip instanceof HTMLElement) {
        return clip;
    }

    clip = document.createElement("span");
    clip.className =
        "tido-option-marquee-clip tido-text-marquee-clip min-w-0 overflow-hidden";

    while (option.firstChild) {
        clip.append(option.firstChild);
    }

    option.append(clip);

    return clip;
}

/**
 * @param {HTMLElement} option
 * @returns {HTMLElement|null}
 */
function findOptionLabel(option) {
    const clip =
        option.querySelector(":scope > .tido-option-marquee-clip") ?? option;

    const tracked = clip.querySelector(
        ':scope > .tido-text-marquee-track > .tido-text-marquee-segment:not([aria-hidden="true"])',
    );

    if (tracked instanceof HTMLElement) {
        return tracked;
    }

    const direct = clip.querySelector(":scope > span");

    return direct instanceof HTMLElement ? direct : null;
}

/**
 * @param {HTMLElement} clip
 * @param {() => HTMLElement|null} resolveLabel
 * @returns {void}
 */
function enhanceClip(clip, resolveLabel) {
    clip.classList.add("tido-text-marquee-clip", "min-w-0", "overflow-hidden");

    const label = resolveLabel();
    const wrapped = ensureTrack(clip, label);
    const state = getState(clip);

    if (!wrapped) {
        return;
    }

    const { track, segment } = wrapped;

    if (!clip.dataset.tidoMarqueeRo) {
        clip.dataset.tidoMarqueeRo = "1";
        new ResizeObserver(() => {
            if (state.rafMeasure) {
                cancelAnimationFrame(state.rafMeasure);
            }

            state.rafMeasure = requestAnimationFrame(() => {
                state.rafMeasure = null;
                const currentLabel = resolveLabel();
                const current = ensureTrack(clip, currentLabel);

                if (!current) {
                    return;
                }

                measure(clip, current.track, current.segment, state);
            });
        }).observe(clip);
    }

    measure(clip, track, segment, state);
}

/**
 * @param {HTMLElement} root
 * @returns {void}
 */
function enhanceSelectedValue(root) {
    const clip = root.querySelector(".fi-select-input-value-ctn");

    if (!(clip instanceof HTMLElement)) {
        return;
    }

    enhanceClip(clip, () => {
        const label = clip.querySelector(".fi-select-input-value-label");

        return label instanceof HTMLElement ? label : null;
    });
}

/**
 * @param {HTMLElement} root
 * @returns {void}
 */
function enhanceOptionLabels(root) {
    root.querySelectorAll(OPTION_SELECTOR).forEach((option) => {
        if (!(option instanceof HTMLElement)) {
            return;
        }

        const clip = ensureOptionInnerClip(option);

        enhanceClip(clip, () => findOptionLabel(option));
    });
}

/**
 * @param {HTMLElement} root
 * @returns {void}
 */
function enhanceRoot(root) {
    root.dataset.tidoMarqueeBusy = "1";
    enhanceSelectedValue(root);
    enhanceOptionLabels(root);
    delete root.dataset.tidoMarqueeBusy;
}

/**
 * @param {HTMLElement} root
 * @returns {void}
 */
function observeRoot(root) {
    enhanceSelectedValue(root);

    if (root.dataset.tidoMarqueeMo) {
        return;
    }

    root.dataset.tidoMarqueeMo = "1";
    let debounceId = 0;

    new MutationObserver(() => {
        if (root.dataset.tidoMarqueeBusy) {
            return;
        }

        window.clearTimeout(debounceId);
        debounceId = window.setTimeout(() => {
            root.dataset.tidoMarqueeBusy = "1";
            enhanceSelectedValue(root);
            enhanceOptionLabels(root);
            delete root.dataset.tidoMarqueeBusy;
        }, 50);
    }).observe(root, {
        childList: true,
        subtree: true,
        characterData: true,
    });
}

function bind() {
    document.querySelectorAll(ROOT_SELECTOR).forEach((root) => {
        if (root instanceof HTMLElement) {
            observeRoot(root);
        }
    });
}

function init() {
    bind();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}

document.addEventListener("livewire:navigated", init);

window.addEventListener("tido-reduce-motion-changed", () => {
    bind();
});
