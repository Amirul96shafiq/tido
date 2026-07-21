/**
 * Global receipt drag-and-drop catcher for the Filament admin panel.
 */

const UPLOAD_FIELD_SELECTORS = [
    'input[type="file"]',
    '.fi-fo-file-upload',
    '.fi-fo-file-upload input[type="file"]',
    '[wire\\:model*="file"]',
    '[wire\\:model*="upload"]',
    '.fi-input[data-field*="file"]',
    '.fi-input[data-field*="upload"]',
    'input[name*="file"]',
    'input[name*="upload"]',
    'input[name*="receipts"]',
];

/**
 * FilePond default spring (stiffness .5, damping .75, mass 10).
 * Matches filament/forms FileUpload drip-blob motion.
 */
const DRIP_SPRING = {
    stiffness: 0.5,
    damping: 0.75,
    mass: 10,
    restEpsilon: 0.001,
};

const DRIP_VISIBLE_OPACITY = 0.4;
const DRIP_OPACITY_MS = 250;
const DRIP_INITIAL_SCALE = 2.5;

const DragDropUpload = {
    overlay: null,
    drip: null,
    dragCounter: 0,
    active: false,
    listeners: [],
    dripRafId: null,
    dripState: null,

    allowedTypes: [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ],

    maxSizeBytes: 10 * 1024 * 1024,
    sessionStorageLimitBytes: 5 * 1024 * 1024,

    init() {
        this.teardown();

        if (this.isAuthPage() || this.hasFilamentUploadFields()) {
            return;
        }

        this.createOverlay();
        this.bindEvents();
        this.active = true;
    },

    isAuthPage() {
        const path = window.location.pathname;

        return (
            path.endsWith('/login') ||
            path.includes('/password-reset') ||
            path.includes('/email-verification')
        );
    },

    teardown() {
        this.dragCounter = 0;
        this.stopDripAnimation();
        this.hideDrip({ immediate: true });

        this.listeners.forEach(({ eventName, handler }) => {
            document.removeEventListener(eventName, handler, false);
        });
        this.listeners = [];

        if (this.overlay?.parentNode) {
            this.overlay.parentNode.removeChild(this.overlay);
        }

        this.overlay = null;
        this.drip = null;
        this.dripState = null;
        this.active = false;
    },

    hasFilamentUploadFields() {
        return UPLOAD_FIELD_SELECTORS.some((selector) => {
            return document.querySelectorAll(selector).length > 0;
        });
    },

    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.id = 'drag-drop-overlay';
        this.overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 208, 125, 0.6);
            border: 5px dashed rgb(120, 53, 15);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: rgb(120, 53, 15);
            font-weight: bold;
            backdrop-filter: blur(10px);
            flex-direction: column;
            pointer-events: none;
            overflow: hidden;
        `;
        this.drip = document.createElement('div');
        this.drip.id = 'drag-drop-drip-blob';
        this.drip.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 8em;
            height: 8em;
            margin-top: -4em;
            margin-left: -4em;
            border-radius: 50%;
            background: rgb(120, 53, 15);
            opacity: 0;
            pointer-events: none;
            will-change: transform, opacity;
            transform-origin: 50% 50%;
            transition: opacity ${DRIP_OPACITY_MS}ms linear;
            z-index: 0;
        `;
        this.overlay.appendChild(this.drip);
        this.resetDripState(0, 0);

        const content = document.createElement('div');
        content.style.cssText =
            'position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center;';
        content.innerHTML = `
            <svg class="w-12 h-12 mb-4 animate-bounce" style="animation-duration: 2s; width: 3rem; height: 3rem; margin-bottom: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"></path>
            </svg>
            <div style="text-align: center;">
                <div style="font-size: 24px; font-weight: bold; margin-bottom: 8px;">${
                    window.dragDropLang?.drop_receipt ||
                    'Drop receipt to upload'
                }</div>
                <div style="font-size: 16px; font-weight: 600; opacity: 0.8;">${
                    window.dragDropLang?.drop_receipt_helper ||
                    'Automatically uploads images below 5MB. Larger files require manual upload on the next page.'
                }</div>
            </div>
        `;
        this.overlay.appendChild(content);

        document.body.appendChild(this.overlay);
    },

    resetDripState(x, y) {
        this.dripState = {
            x,
            y,
            scale: DRIP_INITIAL_SCALE,
            targetX: x,
            targetY: y,
            targetScale: DRIP_INITIAL_SCALE,
            vx: 0,
            vy: 0,
            vs: 0,
            hideOverlayWhenResting: false,
        };
        this.applyDripTransform();
    },

    showOverlay() {
        if (!this.overlay) {
            return;
        }

        this.overlay.style.display = 'flex';
    },

    hideOverlay({ immediate = false } = {}) {
        if (immediate) {
            this.stopDripAnimation();
            if (this.overlay) {
                this.overlay.style.display = 'none';
            }
            this.hideDrip({ immediate: true });
            return;
        }

        if (this.overlay) {
            this.overlay.style.display = 'none';
        }

        this.hideDrip({ immediate: true });
    },

    applyDripTransform() {
        if (!this.drip || !this.dripState) {
            return;
        }

        const { x, y, scale } = this.dripState;
        this.drip.style.transform = `translate3d(${x}px, ${y}px, 0) scale3d(${scale}, ${scale}, 1)`;
    },

    setDripOpacity(opacity) {
        if (!this.drip) {
            return;
        }

        this.drip.style.opacity = String(opacity);
    },

    stopDripAnimation() {
        if (this.dripRafId !== null) {
            cancelAnimationFrame(this.dripRafId);
            this.dripRafId = null;
        }
    },

    springStep(current, target, velocity) {
        const force = -(current - target) * DRIP_SPRING.stiffness;
        const nextVelocity =
            (velocity + force / DRIP_SPRING.mass) * DRIP_SPRING.damping;
        let nextCurrent = current + nextVelocity;

        if (
            Math.abs(nextCurrent - target) < DRIP_SPRING.restEpsilon &&
            Math.abs(nextVelocity) < DRIP_SPRING.restEpsilon
        ) {
            return { value: target, velocity: 0, resting: true };
        }

        return { value: nextCurrent, velocity: nextVelocity, resting: false };
    },

    tickDripAnimation() {
        this.dripRafId = null;

        if (!this.drip || !this.dripState) {
            return;
        }

        const state = this.dripState;
        const xStep = this.springStep(state.x, state.targetX, state.vx);
        const yStep = this.springStep(state.y, state.targetY, state.vy);
        const scaleStep = this.springStep(
            state.scale,
            state.targetScale,
            state.vs,
        );

        state.x = xStep.value;
        state.vx = xStep.velocity;
        state.y = yStep.value;
        state.vy = yStep.velocity;
        state.scale = scaleStep.value;
        state.vs = scaleStep.velocity;

        this.applyDripTransform();

        const resting = xStep.resting && yStep.resting && scaleStep.resting;

        if (!resting) {
            this.dripRafId = requestAnimationFrame(() =>
                this.tickDripAnimation(),
            );
            return;
        }

        if (state.hideOverlayWhenResting && this.overlay) {
            this.overlay.style.display = 'none';
            state.hideOverlayWhenResting = false;
            this.resetDripState(state.x, state.y);
            this.setDripOpacity(0);
        }
    },

    startDripAnimation() {
        if (this.dripRafId !== null) {
            return;
        }

        this.dripRafId = requestAnimationFrame(() => this.tickDripAnimation());
    },

    moveDrip(event) {
        if (!this.drip || !this.dripState) {
            return;
        }

        const { clientX, clientY } = event;
        const state = this.dripState;
        const isFirstFrame = state.targetScale === DRIP_INITIAL_SCALE;

        if (isFirstFrame) {
            state.x = clientX;
            state.y = clientY;
            state.scale = DRIP_INITIAL_SCALE;
            state.vx = 0;
            state.vy = 0;
            state.vs = 0;
            this.applyDripTransform();
        }

        state.targetX = clientX;
        state.targetY = clientY;
        state.targetScale = 1;
        state.hideOverlayWhenResting = false;
        this.setDripOpacity(DRIP_VISIBLE_OPACITY);
        this.startDripAnimation();
    },

    hideDrip({ immediate = false, expand = false } = {}) {
        if (!this.drip || !this.dripState) {
            return;
        }

        const state = this.dripState;

        if (immediate) {
            this.stopDripAnimation();
            this.setDripOpacity(0);
            this.resetDripState(state.targetX, state.targetY);
            return;
        }

        this.setDripOpacity(0);

        if (expand) {
            // FilePond DID_DROP: scale to 2.5 + fade opacity
            state.targetScale = DRIP_INITIAL_SCALE;
            state.hideOverlayWhenResting = true;
            this.startDripAnimation();
            return;
        }

        // FilePond DID_END_DRAG: fade opacity only
        window.setTimeout(() => {
            if (!this.overlay) {
                return;
            }

            this.overlay.style.display = 'none';
            this.stopDripAnimation();
            this.resetDripState(state.targetX, state.targetY);
        }, DRIP_OPACITY_MS);
    },

    endDrip() {
        this.hideDrip({ expand: false });
    },

    dropDrip() {
        this.hideDrip({ expand: true });
    },

    addListener(eventName, handler) {
        document.addEventListener(eventName, handler, false);
        this.listeners.push({ eventName, handler });
    },

    bindEvents() {
        const preventDefaults = (event) => {
            if (!this.isFileDrag(event) || this.shouldIgnoreEvent(event)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
        };

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
            this.addListener(eventName, preventDefaults);
        });

        this.addListener('dragenter', (event) => this.handleDragEnter(event));
        this.addListener('dragover', (event) => this.handleDragOver(event));
        this.addListener('dragleave', (event) => this.handleDragLeave(event));
        this.addListener('drop', (event) => this.handleDrop(event));
    },

    isFileDrag(event) {
        const types = event.dataTransfer?.types;

        if (!types) {
            return false;
        }

        return Array.from(types).includes('Files');
    },

    shouldIgnoreTarget(target) {
        if (!(target instanceof Element)) {
            return false;
        }

        return Boolean(
            target.closest('.no-drag-image') ||
                target.closest('[wire\\:sort]') ||
                target.closest('[wire\\:sort\\:item]') ||
                target.classList.contains('no-drag-image'),
        );
    },

    shouldIgnoreEvent(event) {
        return this.shouldIgnoreTarget(event.target);
    },

    handleDragEnter(event) {
        if (!this.isFileDrag(event) || this.shouldIgnoreEvent(event)) {
            return;
        }

        this.dragCounter++;
        if (this.dragCounter === 1) {
            this.showOverlay();
            this.moveDrip(event);
        }
    },

    handleDragOver(event) {
        if (!this.isFileDrag(event) || this.shouldIgnoreEvent(event)) {
            return;
        }

        event.preventDefault();
        this.showOverlay();
        this.moveDrip(event);
    },

    handleDragLeave(event) {
        if (!this.isFileDrag(event) || this.shouldIgnoreEvent(event)) {
            return;
        }

        this.dragCounter--;
        if (this.dragCounter === 0) {
            this.endDrip();
        }
    },

    handleDrop(event) {
        this.dragCounter = 0;
        this.dropDrip();

        if (!this.isFileDrag(event) || this.shouldIgnoreEvent(event)) {
            return;
        }

        const files = event.dataTransfer?.files;
        if (!files || files.length === 0) {
            return;
        }

        const file = files[0];
        if (!this.validateFile(file)) {
            return;
        }

        this.processFile(file);
    },

    validateFile(file) {
        if (!this.allowedTypes.includes(file.type)) {
            alert(
                window.dragDropLang?.unsupported_file_type ||
                    'Only receipt images (JPEG, PNG, WebP, GIF) are supported.',
            );
            return false;
        }

        return true;
    },

    processFile(file) {
        if (file.size > this.maxSizeBytes) {
            const fileSizeMB = (file.size / 1024 / 1024).toFixed(1);
            const message =
                window.dragDropLang?.file_too_large?.replace(
                    ':sizeMB',
                    fileSizeMB,
                ) ||
                `File size exceeds 10MB limit. Your file is ${fileSizeMB}MB.`;
            alert(message);
            return;
        }

        const fileData = {
            name: file.name,
            size: file.size,
            type: file.type,
            lastModified: file.lastModified,
        };

        if (file.size <= this.sessionStorageLimitBytes) {
            const reader = new FileReader();
            reader.onload = (loadEvent) => {
                fileData.content = loadEvent.target.result;
                this.storeAndNavigate(fileData);
            };
            reader.readAsDataURL(file);
            return;
        }

        this.handleLargeFile(fileData);
    },

    handleLargeFile(fileData) {
        const metadata = {
            name: fileData.name,
            size: fileData.size,
            type: fileData.type,
            lastModified: fileData.lastModified,
            isLargeFile: true,
        };

        try {
            sessionStorage.setItem('draggedReceipt', JSON.stringify(metadata));
            this.navigateToUpload();
        } catch (error) {
            console.error('Error storing large receipt metadata:', error);
            const fileSizeMB = (fileData.size / 1024 / 1024).toFixed(1);
            alert(
                window.dragDropLang?.file_too_large?.replace(
                    ':sizeMB',
                    fileSizeMB,
                ) ||
                    'File too large for drag-and-drop. Please use the upload form directly.',
            );
        }
    },

    storeAndNavigate(fileData) {
        try {
            sessionStorage.setItem('draggedReceipt', JSON.stringify(fileData));
            this.navigateToUpload();
        } catch (error) {
            console.error('Error storing receipt:', error);
            alert('Error processing receipt. Please try again.');
        }
    },

    navigateToUpload() {
        const uploadUrl =
            window.dragDropLang?.uploadUrl || '/admin/upload-receipts';

        if (window.Livewire?.navigate) {
            window.Livewire.navigate(uploadUrl);
            return;
        }

        window.location.assign(uploadUrl);
    },
};

function bootstrapDragDropUpload() {
    DragDropUpload.init();
}

document.addEventListener('DOMContentLoaded', bootstrapDragDropUpload);
document.addEventListener('livewire:navigated', bootstrapDragDropUpload);
