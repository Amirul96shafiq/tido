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

const DragDropUpload = {
    overlay: null,
    dragCounter: 0,
    active: false,
    listeners: [],

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

        this.listeners.forEach(({ eventName, handler }) => {
            document.removeEventListener(eventName, handler, false);
        });
        this.listeners = [];

        if (this.overlay?.parentNode) {
            this.overlay.parentNode.removeChild(this.overlay);
        }

        this.overlay = null;
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
        `;
        this.overlay.innerHTML = `
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
        document.body.appendChild(this.overlay);
    },

    addListener(eventName, handler) {
        document.addEventListener(eventName, handler, false);
        this.listeners.push({ eventName, handler });
    },

    bindEvents() {
        const preventDefaults = (event) => {
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

    shouldIgnoreTarget(target) {
        return (
            target.closest('.no-drag-image') ||
            target.classList.contains('no-drag-image')
        );
    },

    handleDragEnter(event) {
        if (this.shouldIgnoreTarget(event.target)) {
            return;
        }

        this.dragCounter++;
        if (this.dragCounter === 1 && this.overlay) {
            this.overlay.style.display = 'flex';
        }
    },

    handleDragOver(event) {
        if (this.shouldIgnoreTarget(event.target)) {
            return;
        }

        event.preventDefault();
        if (this.overlay) {
            this.overlay.style.display = 'flex';
        }
    },

    handleDragLeave() {
        this.dragCounter--;
        if (this.dragCounter === 0 && this.overlay) {
            this.overlay.style.display = 'none';
        }
    },

    handleDrop(event) {
        this.dragCounter = 0;
        if (this.overlay) {
            this.overlay.style.display = 'none';
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
