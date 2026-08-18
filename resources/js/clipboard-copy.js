/**
 * Clipboard copy with an insecure-context fallback.
 *
 * `navigator.clipboard` is only exposed in secure contexts. tido is served over
 * plain HTTP on a custom hostname (http://tido.local), which Chrome treats as
 * insecure, so the async Clipboard API is undefined there and copy CTAs must
 * fall back to a selection + `document.execCommand('copy')`.
 *
 * Filament modals use Alpine `x-trap`, which keeps focus on the Copy button and
 * blocks focusing an off-screen textarea on `document.body`. The fallback
 * therefore inserts a hidden text node inside the trap and copies a Range.
 */

/**
 * @param {string} text
 * @returns {boolean}
 */
function copyViaSelection(text) {
    const activeBefore = document.activeElement;
    const trapRoot =
        activeBefore instanceof Element
            ? activeBefore.closest('[x-trap], .fi-modal, dialog')
            : null;
    const root = trapRoot ?? document.body;

    const host = document.createElement('span');
    host.textContent = text;
    host.setAttribute('aria-hidden', 'true');
    host.style.position = 'fixed';
    host.style.top = '0';
    host.style.left = '0';
    host.style.opacity = '0';
    host.style.whiteSpace = 'pre';
    host.style.pointerEvents = 'none';

    root.appendChild(host);

    const selection = document.getSelection();
    const previousRange =
        selection && selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

    let copied = false;

    try {
        if (selection) {
            const range = document.createRange();
            range.selectNodeContents(host);
            selection.removeAllRanges();
            selection.addRange(range);
        }

        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    if (!copied) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.top = '0';
        textarea.style.left = '0';
        textarea.style.width = '1px';
        textarea.style.height = '1px';
        textarea.style.padding = '0';
        textarea.style.border = '0';
        textarea.style.opacity = '0';

        root.appendChild(textarea);
        textarea.focus({ preventScroll: true });
        textarea.select();
        textarea.setSelectionRange(0, text.length);

        try {
            copied = document.execCommand('copy');
        } catch {
            copied = false;
        }

        textarea.remove();
    }

    host.remove();

    if (previousRange && selection) {
        try {
            selection.removeAllRanges();
            selection.addRange(previousRange);
        } catch {
            selection.removeAllRanges();
        }
    }

    if (activeBefore instanceof HTMLElement) {
        activeBefore.focus({ preventScroll: true });
    }

    return copied;
}

/**
 * @param {string} text
 * @returns {Promise<boolean>}
 */
function copyToClipboard(text) {
    const value = String(text ?? '');

    if (value === '') {
        return Promise.resolve(false);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard
            .writeText(value)
            .then(() => true)
            .catch(() => copyViaSelection(value));
    }

    return Promise.resolve(copyViaSelection(value));
}

window.tidoCopyToClipboard = copyToClipboard;
