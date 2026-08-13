/**
 * Apply the looping `.tido-text-marquee-track` to Filament JS select selected labels.
 * Opt in with `.tido-select-value-marquee` (SelectValueMarquee::extraAttributes()).
 * See docs/ui-text-marquee.md.
 */
const ROOT_SELECTOR = '.tido-select-value-marquee';
const SPEED = 40;

/** @type {WeakMap<HTMLElement, MarqueeState>} */
const states = new WeakMap();

/**
 * @typedef {{
 *   offset: number,
 *   overflowing: boolean,
 *   scrollDistance: number,
 *   rafId: number|null,
 *   lastTime: number|null,
 *   reducedMotion: boolean,
 *   rafMeasure: number|null,
 * }} MarqueeState
 */

/**
 * @param {HTMLElement} clip
 * @returns {MarqueeState}
 */
function getState(clip) {
    let state = states.get(clip);

    if (! state) {
        state = {
            offset: 0,
            overflowing: false,
            scrollDistance: 0,
            rafId: null,
            lastTime: null,
            reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
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
    const parsed = Number.parseFloat(styles.columnGap) || Number.parseFloat(styles.gap);

    return Number.isFinite(parsed) ? parsed : 32;
}

/**
 * @param {MarqueeState} state
 * @param {HTMLElement} track
 * @returns {void}
 */
function stopTicker(state, track) {
    state.offset = 0;
    state.lastTime = null;
    track.style.transform = '';

    if (state.rafId !== null) {
        cancelAnimationFrame(state.rafId);
        state.rafId = null;
    }
}

/**
 * @param {HTMLElement} clip
 * @param {HTMLElement} track
 * @param {HTMLElement} segment
 * @param {MarqueeState} state
 * @returns {void}
 */
function measure(clip, track, segment, state) {
    if (! segment.isConnected || ! track.isConnected) {
        return;
    }

    const clipWidth = clip.clientWidth;
    const segmentWidth = segment.offsetWidth;

    if (clipWidth === 0 || segmentWidth === 0) {
        return;
    }

    const gap = readGap(track);
    const scrollDistance = segmentWidth + gap;
    const shouldOverflow = (segmentWidth - clipWidth) > 1;

    if (Math.abs(state.scrollDistance - scrollDistance) > 1) {
        if (state.scrollDistance > 0 && state.offset > 0) {
            state.offset = state.offset % scrollDistance;
        } else {
            state.offset = 0;
        }

        state.scrollDistance = scrollDistance;
    }

    state.overflowing = shouldOverflow;
    track.classList.toggle('is-overflowing', shouldOverflow);

    if (shouldOverflow && ! state.reducedMotion) {
        if (state.rafId === null) {
            state.rafId = requestAnimationFrame((time) => tick(clip, track, segment, state, time));
        }

        return;
    }

    stopTicker(state, track);
}

/**
 * @param {HTMLElement} clip
 * @param {HTMLElement} track
 * @param {HTMLElement} segment
 * @param {MarqueeState} state
 * @param {number} time
 * @returns {void}
 */
function tick(clip, track, segment, state, time) {
    if (state.lastTime === null) {
        state.lastTime = time;
    }

    const delta = (time - state.lastTime) / 1000;
    state.lastTime = time;

    if (! state.overflowing || state.reducedMotion || state.scrollDistance <= 0 || ! track.isConnected) {
        state.rafId = null;
        state.lastTime = null;

        return;
    }

    state.offset += SPEED * delta;

    if (state.offset >= state.scrollDistance) {
        state.offset -= state.scrollDistance;
    }

    track.style.transform = `translate3d(${-state.offset}px, 0, 0)`;
    state.rafId = requestAnimationFrame((nextTime) => tick(clip, track, segment, state, nextTime));
}

/**
 * @param {HTMLElement} track
 * @param {HTMLElement} label
 * @returns {void}
 */
function syncDuplicate(track, label) {
    let duplicate = track.querySelector(':scope > .tido-text-marquee-segment[aria-hidden="true"]');

    if (! duplicate) {
        duplicate = /** @type {HTMLElement} */ (label.cloneNode(true));
        duplicate.removeAttribute('id');
        duplicate.classList.remove('fi-select-input-value-label');
        duplicate.setAttribute('aria-hidden', 'true');
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
    const label = clip.querySelector('.fi-select-input-value-label');

    if (! (label instanceof HTMLElement)) {
        return null;
    }

    clip.querySelectorAll(':scope > .tido-text-marquee-track').forEach((orphan) => {
        if (! orphan.contains(label)) {
            orphan.remove();
        }
    });

    let track = label.closest('.tido-text-marquee-track');

    if (! (track instanceof HTMLElement) || track.parentElement !== clip) {
        track = document.createElement('span');
        track.className = 'tido-text-marquee-track';
        label.classList.add('tido-text-marquee-segment', 'inline-block', 'whitespace-nowrap');
        label.replaceWith(track);
        track.append(label);
    } else {
        label.classList.add('tido-text-marquee-segment', 'inline-block', 'whitespace-nowrap');
    }

    syncDuplicate(track, label);

    return { track, segment: label };
}

/**
 * @param {HTMLElement} root
 * @returns {void}
 */
function enhanceRoot(root) {
    const clip = root.querySelector('.fi-select-input-value-ctn');

    if (! (clip instanceof HTMLElement)) {
        return;
    }

    clip.classList.add('tido-text-marquee-clip', 'min-w-0', 'overflow-hidden');
    root.dataset.tidoMarqueeBusy = '1';

    const wrapped = ensureTrack(clip);
    const state = getState(clip);

    if (! wrapped) {
        delete root.dataset.tidoMarqueeBusy;

        return;
    }

    const { track, segment } = wrapped;

    if (! clip.dataset.tidoMarqueeRo) {
        clip.dataset.tidoMarqueeRo = '1';
        new ResizeObserver(() => {
            if (state.rafMeasure) {
                cancelAnimationFrame(state.rafMeasure);
            }

            state.rafMeasure = requestAnimationFrame(() => {
                state.rafMeasure = null;
                const current = ensureTrack(clip);

                if (! current) {
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

    root.dataset.tidoMarqueeMo = '1';
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

document.addEventListener('livewire:navigated', init);
