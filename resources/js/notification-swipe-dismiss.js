/**
 * Swipe/drag right to dismiss Filament flash toasts.
 * Pointer Events — touch + mouse on small and large screens.
 */
const TOAST_SELECTOR = '.fi-no-notification:not(.fi-inline)';
const SWIPING_CLASS = 'tido-no-swiping';
const SETTLING_CLASS = 'tido-no-settling';
const INTERACTIVE_SELECTOR = 'button, a, [role="button"], input, textarea, select';
const SLOP_PX = 8;
const MIN_DISMISS_PX = 72;
const DISMISS_RATIO = 0.35;
const VELOCITY_DISMISS = 0.6;
const SETTLE_MS = 220;

/** @type {{
 *   toast: HTMLElement,
 *   pointerId: number,
 *   startX: number,
 *   startY: number,
 *   startTime: number,
 *   lastX: number,
 *   lastTime: number,
 *   dx: number,
 *   axisLocked: null | 'x' | 'y',
 * } | null} */
let gesture = null;

/**
 * @param {EventTarget | null} target
 * @returns {HTMLElement | null}
 */
function findToast(target) {
    if (! (target instanceof Element)) {
        return null;
    }

    const toast = target.closest(TOAST_SELECTOR);

    return toast instanceof HTMLElement ? toast : null;
}

/**
 * @param {EventTarget | null} target
 * @param {HTMLElement} toast
 */
function startsOnInteractive(target, toast) {
    if (! (target instanceof Element)) {
        return false;
    }

    const interactive = target.closest(INTERACTIVE_SELECTOR);

    return interactive instanceof Element && toast.contains(interactive);
}

/**
 * @param {HTMLElement} toast
 * @param {number} dx
 */
function applyDrag(toast, dx) {
    const width = Math.max(toast.offsetWidth, 1);
    const progress = Math.min(dx / width, 1);

    toast.style.transform = `translateX(${dx}px)`;
    toast.style.opacity = String(Math.max(1 - progress * 0.85, 0.15));
}

/**
 * @param {HTMLElement} toast
 */
function clearDragStyles(toast) {
    toast.style.transform = '';
    toast.style.opacity = '';
    toast.style.transition = '';
    toast.classList.remove(SWIPING_CLASS);
    toast.classList.remove(SETTLING_CLASS);
}

/**
 * @param {HTMLElement} toast
 * @param {() => void} onDone
 */
function afterSettle(toast, onDone) {
    let finished = false;

    const finish = () => {
        if (finished) {
            return;
        }

        finished = true;
        toast.removeEventListener('transitionend', onTransitionEnd);
        window.clearTimeout(fallbackId);
        onDone();
    };

    /**
     * @param {TransitionEvent} event
     */
    const onTransitionEnd = (event) => {
        if (event.target !== toast || event.propertyName !== 'transform') {
            return;
        }

        finish();
    };

    toast.addEventListener('transitionend', onTransitionEnd);
    const fallbackId = window.setTimeout(finish, SETTLE_MS + 80);
}

/**
 * @param {HTMLElement} toast
 */
function closeToastImmediate(toast) {
    const alpine = window.Alpine;

    if (alpine && typeof alpine.$data === 'function') {
        const data = alpine.$data(toast);

        if (data && typeof data.close === 'function') {
            data.close(true);

            return;
        }
    }

    toast.querySelector('.fi-no-notification-close-btn')?.click();
}

/**
 * Continue from current drag offset off-screen, then remove without Alpine leave jump.
 *
 * @param {HTMLElement} toast
 * @param {number} fromDx
 */
function dismissToast(toast, fromDx) {
    const rect = toast.getBoundingClientRect();
    const offscreen = Math.ceil(window.innerWidth - rect.left + 24);
    const startDx = Math.max(fromDx, 0);
    const targetDx = Math.max(offscreen, startDx + 64);

    toast.classList.add(SWIPING_CLASS);
    toast.style.transform = `translateX(${startDx}px)`;
    toast.offsetWidth;
    toast.classList.remove(SWIPING_CLASS);
    toast.classList.add(SETTLING_CLASS);
    toast.style.transform = `translateX(${targetDx}px)`;
    toast.style.opacity = '0';

    afterSettle(toast, () => {
        clearDragStyles(toast);
        closeToastImmediate(toast);
    });
}

/**
 * @param {HTMLElement} toast
 */
function snapBack(toast) {
    toast.classList.remove(SWIPING_CLASS);
    toast.classList.add(SETTLING_CLASS);
    toast.offsetWidth;
    toast.style.transform = 'translateX(0px)';
    toast.style.opacity = '1';

    afterSettle(toast, () => {
        clearDragStyles(toast);
    });
}

/**
 * @param {PointerEvent} event
 */
function onPointerDown(event) {
    if (gesture !== null) {
        return;
    }

    if (event.button !== 0 && event.pointerType === 'mouse') {
        return;
    }

    const toast = findToast(event.target);

    if (! toast || startsOnInteractive(event.target, toast)) {
        return;
    }

    const now = performance.now();

    gesture = {
        toast,
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        startTime: now,
        lastX: event.clientX,
        lastTime: now,
        dx: 0,
        axisLocked: null,
    };

    try {
        toast.setPointerCapture(event.pointerId);
    } catch {
        // Capture is best-effort; move/up still work on the element.
    }
}

/**
 * @param {PointerEvent} event
 */
function onPointerMove(event) {
    if (! gesture || event.pointerId !== gesture.pointerId) {
        return;
    }

    if (! document.contains(gesture.toast)) {
        endGesture(false);

        return;
    }

    const rawDx = event.clientX - gesture.startX;
    const dy = event.clientY - gesture.startY;
    const absDx = Math.abs(rawDx);
    const absDy = Math.abs(dy);

    if (gesture.axisLocked === null) {
        if (absDx < SLOP_PX && absDy < SLOP_PX) {
            return;
        }

        gesture.axisLocked = absDx > absDy ? 'x' : 'y';

        if (gesture.axisLocked === 'y') {
            endGesture(false);

            return;
        }

        gesture.toast.classList.add(SWIPING_CLASS);
    }

    if (gesture.axisLocked !== 'x') {
        return;
    }

    event.preventDefault();

    const dx = Math.max(0, rawDx);
    const now = performance.now();

    gesture.dx = dx;
    gesture.lastX = event.clientX;
    gesture.lastTime = now;

    applyDrag(gesture.toast, dx);
}

/**
 * @param {PointerEvent} event
 */
function onPointerUp(event) {
    if (! gesture || event.pointerId !== gesture.pointerId) {
        return;
    }

    const { toast, dx, startTime, lastTime, lastX, startX, axisLocked } =
        gesture;

    gesture = null;

    if (! document.contains(toast)) {
        return;
    }

    try {
        toast.releasePointerCapture(event.pointerId);
    } catch {
        // Already released or never captured.
    }

    if (axisLocked !== 'x') {
        clearDragStyles(toast);

        return;
    }

    const width = Math.max(toast.offsetWidth, 1);
    const threshold = Math.max(MIN_DISMISS_PX, width * DISMISS_RATIO);
    const elapsed = Math.max(lastTime - startTime, 1);
    const velocity = (lastX - startX) / elapsed;

    if (dx >= threshold || velocity >= VELOCITY_DISMISS) {
        dismissToast(toast, dx);

        return;
    }

    snapBack(toast);
}

/**
 * @param {boolean} clearStyles
 */
function endGesture(clearStyles) {
    if (! gesture) {
        return;
    }

    const { toast, pointerId } = gesture;

    gesture = null;

    try {
        toast.releasePointerCapture(pointerId);
    } catch {
        // Already released.
    }

    if (clearStyles && document.contains(toast)) {
        clearDragStyles(toast);
    } else if (document.contains(toast)) {
        toast.classList.remove(SWIPING_CLASS);
    }
}

/**
 * @param {PointerEvent} event
 */
function onPointerCancel(event) {
    if (! gesture || event.pointerId !== gesture.pointerId) {
        return;
    }

    const toast = gesture.toast;

    gesture = null;

    if (document.contains(toast)) {
        clearDragStyles(toast);
    }
}

document.addEventListener('pointerdown', onPointerDown, { capture: true });
document.addEventListener('pointermove', onPointerMove, {
    capture: true,
    passive: false,
});
document.addEventListener('pointerup', onPointerUp, { capture: true });
document.addEventListener('pointercancel', onPointerCancel, { capture: true });
