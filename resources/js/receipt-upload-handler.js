/**
 * Processes sessionStorage handoff on the Upload Receipts page (SPA-safe).
 */

const dragDropLang = {
    largeFileTitle:
        window.dragDropLang?.large_file_title || 'Large receipt detected',
    largeFileMessage:
        window.dragDropLang?.large_file_message ||
        ':filename (:sizeMB) is too large for automatic upload. Please drop it on the upload field.',
};

let isProcessing = false;
let lastHandledReceiptKey = null;
let autoFillStartedForKey = null;
let injectedReceiptKey = null;
let submittedReceiptKey = null;

function getUploadPagePath() {
    try {
        const uploadUrl =
            window.dragDropLang?.uploadUrl || '/admin/upload-receipts';
        return new URL(uploadUrl, window.location.origin).pathname;
    } catch {
        return '/admin/upload-receipts';
    }
}

function isUploadPage() {
    return window.location.pathname === getUploadPagePath();
}

function getReceiptKey(fileData) {
    return `${fileData.name}:${fileData.lastModified}:${fileData.size}`;
}

function processDraggedReceipt() {
    if (!isUploadPage() || isProcessing) {
        return;
    }

    const draggedReceipt = sessionStorage.getItem('draggedReceipt');
    if (!draggedReceipt) {
        return;
    }

    let fileData;
    try {
        fileData = JSON.parse(draggedReceipt);
    } catch (error) {
        console.error('Invalid dragged receipt payload:', error);
        sessionStorage.removeItem('draggedReceipt');
        return;
    }

    const receiptKey = getReceiptKey(fileData);
    if (receiptKey === lastHandledReceiptKey) {
        return;
    }

    isProcessing = true;
    lastHandledReceiptKey = receiptKey;
    sessionStorage.removeItem('draggedReceipt');

    if (fileData.isLargeFile) {
        initializeLargeFileAutoFill(fileData);
        document.addEventListener(
            'livewire:init',
            () => {
                setTimeout(() => initializeLargeFileAutoFill(fileData), 100);
            },
            { once: true },
        );
        isProcessing = false;
        return;
    }

    if (window.Livewire) {
        initializeFormAutoFill(fileData);
    } else {
        document.addEventListener(
            'livewire:init',
            () => {
                initializeFormAutoFill(fileData);
            },
            { once: true },
        );
    }
}

function initializeFormAutoFill(fileData) {
    const receiptKey = getReceiptKey(fileData);

    if (autoFillStartedForKey === receiptKey) {
        return;
    }

    autoFillStartedForKey = receiptKey;

    attemptFileInjection(fileData, receiptKey, 0);
}

function attemptFileInjection(fileData, receiptKey, attempt) {
    if (injectedReceiptKey === receiptKey) {
        scheduleFormSubmit(fileData, receiptKey);
        return;
    }

    const fileInput = findFileInput();
    if (!fileInput) {
        if (attempt < 5) {
            setTimeout(() => attemptFileInjection(fileData, receiptKey, attempt + 1), 500);
        }
        return;
    }

    setFileUpload(fileData, receiptKey);
    injectedReceiptKey = receiptKey;
    scheduleFormSubmit(fileData, receiptKey);
}

function hasReceiptPreview() {
    return (
        document.querySelectorAll('.fi-fo-file-upload .filepond--item').length > 0 ||
        document.querySelectorAll('.fi-fo-file-upload [data-file-upload-item]').length > 0
    );
}

function scheduleFormSubmit(fileData, receiptKey) {
    if (submittedReceiptKey === receiptKey) {
        return;
    }

    attemptFormSubmit(fileData, receiptKey, 0);
}

function attemptFormSubmit(fileData, receiptKey, attempt) {
    if (submittedReceiptKey === receiptKey) {
        return;
    }

    if (hasReceiptPreview()) {
        submitUploadForm(fileData, receiptKey);
        return;
    }

    if (attempt < 10) {
        setTimeout(() => attemptFormSubmit(fileData, receiptKey, attempt + 1), 500);
    } else {
        isProcessing = false;
    }
}

function initializeLargeFileAutoFill(fileData) {
    showLargeFileMessage(fileData);
}

function showLargeFileMessage(fileData) {
    const existingNotification = document.querySelector(
        '.large-receipt-notification',
    );
    if (existingNotification) {
        return;
    }

    const fileSizeMB = (fileData.size / 1024 / 1024).toFixed(1);
    const message = dragDropLang.largeFileMessage
        .replace(':sizeMB', `${fileSizeMB}MB`)
        .replace(':filename', fileData.name);

    const notificationArea =
        document.querySelector('.fi-form') ||
        document.querySelector('.fi-main') ||
        document.querySelector('main');

    if (!notificationArea) {
        return;
    }

    const notification = document.createElement('div');
    notification.className =
        'large-receipt-notification fi-no-notification w-full overflow-hidden transition-all duration-500 ease-in-out max-w-full rounded-xl bg-white shadow-lg ring-1 dark:bg-gray-900 ring-amber-600/20 dark:ring-amber-400/30';
    notification.style.cssText = `
        margin: 10px 0;
        opacity: 0;
        transform: translateY(-20px);
        transition: opacity 0.5s ease-in-out, transform 0.5s ease-in-out;
    `;
    notification.innerHTML = `
        <div class="flex w-full gap-3 p-4 bg-amber-50 dark:bg-amber-400/10">
            <div class="mt-0.5 grid flex-1">
                <h3 class="fi-no-notification-title text-sm font-medium text-gray-950 dark:text-white">
                    ${dragDropLang.largeFileTitle}
                </h3>
                <div class="fi-no-notification-body overflow-hidden break-words text-sm text-gray-500 dark:text-gray-400 mt-1">
                    ${message}
                </div>
            </div>
        </div>
    `;

    notificationArea.insertBefore(notification, notificationArea.firstChild);

    requestAnimationFrame(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateY(0)';
    });

    setTimeout(() => {
        if (!notification.parentNode) {
            return;
        }

        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-20px)';

        setTimeout(() => {
            notification.remove();
        }, 500);
    }, 10000);
}

function setFileUpload(fileData, receiptKey) {
    if (injectedReceiptKey === receiptKey) {
        return;
    }

    const file = createFileFromBase64(fileData);
    const fileInput = findFileInput();

    if (!fileInput) {
        return;
    }

    setFileInputValue(fileInput, file);
}

function submitUploadForm(fileData, receiptKey) {
    if (submittedReceiptKey === receiptKey) {
        return;
    }

    if (!hasReceiptPreview()) {
        return;
    }

    const submitButton =
        document.querySelector('form[wire\\:submit="save"] button[type="submit"]') ||
        document.querySelector('[wire\\:target="save"]');

    if (!submitButton || submitButton.disabled) {
        return;
    }

    if (document.querySelector('[wire\\:loading][wire\\:target="save"]')) {
        return;
    }

    submittedReceiptKey = receiptKey;
    submitButton.click();
    isProcessing = false;
}

function findElement(selectors) {
    for (const selector of selectors) {
        const element = document.querySelector(selector);
        if (element) {
            return element;
        }
    }

    return null;
}

function findFileInput() {
    const fileSelectors = [
        'input[name="data[receipts]"]',
        'input[wire\\:model="data.receipts"]',
        '[wire\\:model="data.receipts"] input[type="file"]',
        '[data-field="receipts"] input[type="file"]',
        '.fi-fo-file-upload input[type="file"]',
        'input[type="file"][accept*="image"]',
        'input[type="file"]',
    ];

    return findElement(fileSelectors);
}

function createFileFromBase64(fileData) {
    const byteCharacters = atob(fileData.content.split(',')[1]);
    const byteNumbers = new Array(byteCharacters.length);

    for (let index = 0; index < byteCharacters.length; index++) {
        byteNumbers[index] = byteCharacters.charCodeAt(index);
    }

    const byteArray = new Uint8Array(byteNumbers);
    return new File([byteArray], fileData.name, { type: fileData.type });
}

function setFileInputValue(fileInput, file) {
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    fileInput.files = dataTransfer.files;

    ['change', 'input'].forEach((eventType) => {
        fileInput.dispatchEvent(
            new Event(eventType, { bubbles: true, cancelable: true }),
        );
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(processDraggedReceipt, 100);
});

document.addEventListener('livewire:navigated', () => {
    setTimeout(processDraggedReceipt, 300);
});
