/**
 * Clipboard copy with an insecure-context fallback.
 *
 * `navigator.clipboard` is only exposed in secure contexts. tido is served over
 * plain HTTP on a custom hostname (http://tido.local), which Chrome treats as
 * insecure, so the async Clipboard API is undefined there and copy CTAs must
 * fall back to a selection + `document.execCommand('copy')`.
 */

/**
 * @param {string} text
 * @returns {boolean}
 */
function copyViaSelection(text) {
    const textarea = document.createElement('textarea');

    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '-9999px';
    textarea.style.opacity = '0';

    document.body.appendChild(textarea);

    const selection = document.getSelection();
    const previousRange =
        selection && selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

    textarea.focus();
    textarea.select();
    textarea.setSelectionRange(0, text.length);

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    textarea.remove();

    if (previousRange && selection) {
        selection.removeAllRanges();
        selection.addRange(previousRange);
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
