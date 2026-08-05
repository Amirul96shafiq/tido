const EDITOR_OVERLAY_SELECTOR = '.fi-fo-file-upload-editor-overlay';

function closeFileUploadEditorFromOverlay(event) {
    if (!(event.target instanceof Element)) {
        return;
    }

    const overlay = event.target.closest(EDITOR_OVERLAY_SELECTOR);

    if (!overlay || event.target !== overlay) {
        return;
    }

    const editor = overlay.closest('.fi-fo-file-upload-editor');
    const cancelButton = editor?.querySelector(
        '.fi-fo-file-upload-editor-control-panel-footer button.fi-btn',
    );

    if (!cancelButton) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    cancelButton.click();
}

document.addEventListener('click', closeFileUploadEditorFromOverlay, true);
