/**
 * Full-width receipt preview inside Filament FileUpload (FilePond item).
 * Opt in with `.fi-receipt-image-upload` on the FileUpload.
 *
 * FilePond's canvas preview letterboxes tall receipts; this injects a native
 * <img> into the item (optionally cropped), caps height at 500px, and keeps
 * FilePond remove/open/download controls on top.
 */
const ROOT_SELECTOR = '.fi-receipt-image-upload';
const NATIVE_IMG_CLASS = 'tido-receipt-native-preview';
const BG_DELTA = 28;
const MAX_PREVIEW_HEIGHT = 500;

/**
 * @param {HTMLImageElement} image
 */
function revokeNativeUrl(image) {
    if (image.dataset.tidoObjectUrl) {
        URL.revokeObjectURL(image.dataset.tidoObjectUrl);
        delete image.dataset.tidoObjectUrl;
    }
}

/**
 * @param {Uint8ClampedArray} data
 * @param {number} width
 * @param {number} height
 * @returns {{ minX: number, maxX: number, minY: number, maxY: number } | null}
 */
function findContentBounds(data, width, height) {
    const sample = (x, y) => {
        const i = (y * width + x) * 4;

        return [data[i], data[i + 1], data[i + 2]];
    };

    const corners = [
        sample(0, 0),
        sample(width - 1, 0),
        sample(0, height - 1),
        sample(width - 1, height - 1),
    ];

    const bg = [
        Math.round(corners.reduce((sum, color) => sum + color[0], 0) / 4),
        Math.round(corners.reduce((sum, color) => sum + color[1], 0) / 4),
        Math.round(corners.reduce((sum, color) => sum + color[2], 0) / 4),
    ];

    const isBackground = (x, y) => {
        const [r, g, b] = sample(x, y);

        return (
            Math.abs(r - bg[0]) <= BG_DELTA &&
            Math.abs(g - bg[1]) <= BG_DELTA &&
            Math.abs(b - bg[2]) <= BG_DELTA
        );
    };

    let minX = width;
    let maxX = -1;
    let minY = height;
    let maxY = -1;

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (! isBackground(x, y)) {
                minX = Math.min(minX, x);
                maxX = Math.max(maxX, x);
                minY = Math.min(minY, y);
                maxY = Math.max(maxY, y);
            }
        }
    }

    if (maxX < minX || maxY < minY) {
        return null;
    }

    const pad = 2;

    return {
        minX: Math.max(0, minX - pad),
        maxX: Math.min(width - 1, maxX + pad),
        minY: Math.max(0, minY - pad),
        maxY: Math.min(height - 1, maxY + pad),
    };
}

/**
 * @param {Blob} blob
 * @returns {Promise<string>}
 */
async function cropReceiptBlobUrl(blob) {
    const bitmap = await createImageBitmap(blob);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;

    const ctx = canvas.getContext('2d');

    if (! ctx) {
        bitmap.close();

        return URL.createObjectURL(blob);
    }

    ctx.drawImage(bitmap, 0, 0);
    bitmap.close();

    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const bounds = findContentBounds(imageData.data, canvas.width, canvas.height);

    if (! bounds) {
        return URL.createObjectURL(blob);
    }

    const cropW = bounds.maxX - bounds.minX + 1;
    const cropH = bounds.maxY - bounds.minY + 1;
    const coverageX = cropW / canvas.width;
    const coverageY = cropH / canvas.height;

    if (coverageX > 0.98 && coverageY > 0.98) {
        return URL.createObjectURL(blob);
    }

    const out = document.createElement('canvas');
    out.width = cropW;
    out.height = cropH;

    const outCtx = out.getContext('2d');

    if (! outCtx) {
        return URL.createObjectURL(blob);
    }

    outCtx.drawImage(
        canvas,
        bounds.minX,
        bounds.minY,
        cropW,
        cropH,
        0,
        0,
        cropW,
        cropH,
    );

    const croppedBlob = await new Promise((resolve) => {
        out.toBlob((result) => resolve(result), blob.type || 'image/jpeg', 0.92);
    });

    if (! (croppedBlob instanceof Blob)) {
        return URL.createObjectURL(blob);
    }

    return URL.createObjectURL(croppedBlob);
}

/**
 * @param {HTMLElement} host
 * @param {HTMLElement} item
 * @returns {HTMLImageElement}
 */
function ensureNativeImageInItem(host, item) {
    let image = item.querySelector(`img.${NATIVE_IMG_CLASS}`);

    if (image instanceof HTMLImageElement) {
        return image;
    }

    host.querySelectorAll(`img.${NATIVE_IMG_CLASS}`).forEach((node) => {
        if (node instanceof HTMLImageElement && ! item.contains(node)) {
            revokeNativeUrl(node);
            node.remove();
        }
    });

    image = document.createElement('img');
    image.className = NATIVE_IMG_CLASS;
    image.alt = 'Receipt image';

    // FilePond paints .filepond--panel above early item children; put the
    // preview in the image-preview layer so it sits under the action overlay.
    const preview =
        item.querySelector('.filepond--image-preview') ||
        item.querySelector('.filepond--image-preview-wrapper');

    if (preview instanceof HTMLElement) {
        preview.appendChild(image);
    } else {
        item.insertBefore(image, item.firstChild);
    }

    host.classList.add('tido-receipt-has-native-preview');

    return image;
}

/**
 * @param {HTMLElement} host
 * @param {HTMLElement} item
 * @param {HTMLImageElement} image
 */
function sizeItemToImage(host, item, image) {
    const width = item.clientWidth || image.clientWidth;

    if (! width || ! image.naturalWidth || ! image.naturalHeight) {
        return;
    }

    const naturalHeight = Math.round(width * (image.naturalHeight / image.naturalWidth));
    const height = Math.min(MAX_PREVIEW_HEIGHT, naturalHeight);

    item.style.height = `${height}px`;
    item.style.minHeight = `${height}px`;
    host.classList.toggle('tido-receipt-preview-capped', naturalHeight > MAX_PREVIEW_HEIGHT);

    const preview = item.querySelector('.filepond--image-preview-wrapper, .filepond--image-preview');

    if (preview instanceof HTMLElement) {
        preview.style.height = `${height}px`;
    }
}

/**
 * @param {object} file
 * @returns {Promise<Blob | null>}
 */
async function resolveImageBlob(file) {
    if (file?.file instanceof Blob && file.file.type.startsWith('image/')) {
        return file.file;
    }

    const source = file?.source;

    if (typeof source === 'string' && (/^https?:\/\//.test(source) || source.startsWith('blob:'))) {
        const response = await fetch(source, { cache: 'no-store' });

        if (! response.ok) {
            return null;
        }

        const blob = await response.blob();

        return blob.type.startsWith('image/') ? blob : null;
    }

    return null;
}

/**
 * @param {HTMLElement} host
 * @param {object} pond
 */
async function syncNativePreview(host, pond) {
    const file = pond.getFile?.();
    const item = host.querySelector('.filepond--item');

    if (! file || ! (item instanceof HTMLElement)) {
        host.querySelectorAll(`img.${NATIVE_IMG_CLASS}`).forEach((node) => {
            if (node instanceof HTMLImageElement) {
                revokeNativeUrl(node);
                node.remove();
            }
        });

        host.classList.remove('tido-receipt-has-native-preview', 'tido-receipt-preview-capped');

        if (item instanceof HTMLElement) {
            item.style.height = '';
            item.style.minHeight = '';
        }

        return;
    }

    const blob = await resolveImageBlob(file);

    if (! blob) {
        return;
    }

    const image = ensureNativeImageInItem(host, item);

    try {
        const url = await cropReceiptBlobUrl(blob);

        revokeNativeUrl(image);
        image.dataset.tidoObjectUrl = url;
        image.onload = () => sizeItemToImage(host, item, image);
        image.src = url;
    } catch {
        revokeNativeUrl(image);

        const objectUrl = URL.createObjectURL(blob);
        image.dataset.tidoObjectUrl = objectUrl;
        image.onload = () => sizeItemToImage(host, item, image);
        image.src = objectUrl;
    }
}

/**
 * @param {HTMLElement} host
 */
function enhanceHost(host) {
    const pondRoot = host.querySelector('.filepond--root');

    if (! pondRoot || ! window.FilePond?.find) {
        return;
    }

    if (host.dataset.tidoReceiptEnhanced === '1') {
        const pond = window.FilePond.find(pondRoot);

        if (pond) {
            void syncNativePreview(host, pond);
        }

        return;
    }

    const pond = window.FilePond.find(pondRoot);

    if (! pond) {
        return;
    }

    host.dataset.tidoReceiptEnhanced = '1';

    pond.setOptions({
        allowImagePreview: true,
        imagePreviewHeight: null,
        imagePreviewMaxHeight: 4096,
    });

    const sync = () => {
        void syncNativePreview(host, pond);
    };

    pond.on('initfile', sync);
    pond.on('addfile', sync);
    pond.on('removefile', sync);
    sync();
}

function enhanceAll() {
    document.querySelectorAll(ROOT_SELECTOR).forEach((host) => {
        if (host instanceof HTMLElement) {
            enhanceHost(host);
        }
    });
}

function startObserver() {
    if (window.__tidoReceiptPreviewObserver) {
        return;
    }

    window.__tidoReceiptPreviewObserver = new MutationObserver(() => {
        enhanceAll();
    });

    window.__tidoReceiptPreviewObserver.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });
}

startObserver();
enhanceAll();

document.addEventListener('alpine:init', enhanceAll);
document.addEventListener('livewire:navigated', enhanceAll);

const pollId = window.setInterval(() => {
    if (! window.FilePond?.find) {
        return;
    }

    enhanceAll();
    window.clearInterval(pollId);
}, 100);
