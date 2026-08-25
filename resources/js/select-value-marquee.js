/**
 * Apply the looping `.tido-text-marquee-track` to Filament JS select selected labels.
 * Opt in with `.tido-select-value-marquee` (SelectValueMarquee::extraAttributes()).
 * See docs/ui-text-marquee.md.
 *
 * Overflow measure stays in JS; motion is CSS animation (same contract as
 * x-tido.text-marquee) so the main thread is not writing transform every frame.
 */
const ROOT_SELECTOR = ".tido-select-value-marquee";
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
 * @returns {{track: HTMLElement, segment: HTMLElement}|null}
 */
function ensureTrack(clip) {
    const label = clip.querySelector(".fi-select-input-value-label");

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
 * @param {HTMLElement} root
 * @returns {void}
 */
function enhanceRoot(root) {
    const clip = root.querySelector(".fi-select-input-value-ctn");

    if (!(clip instanceof HTMLElement)) {
        return;
    }

    clip.classList.add("tido-text-marquee-clip", "min-w-0", "overflow-hidden");
    root.dataset.tidoMarqueeBusy = "1";

    const wrapped = ensureTrack(clip);
    const state = getState(clip);

    if (!wrapped) {
        delete root.dataset.tidoMarqueeBusy;

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
                const current = ensureTrack(clip);

                if (!current) {
                    return;
                }

                measure(clip, current.track, current.segment, state);
            });
        }).observe(clip);
    }

    measure(clip, track, segment, state);
    delete root.dataset.tidoMarqueeBusy;
}

/**
 * @param {HTMLElement} root
 * @returns {void}
 */
function observeRoot(root) {
    enhanceRoot(root);

    if (root.dataset.tidoMarqueeMo) {
        return;
    }

    root.dataset.tidoMarqueeMo = "1";
    new MutationObserver(() => {
        if (root.dataset.tidoMarqueeBusy) {
            return;
        }

        enhanceRoot(root);
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
