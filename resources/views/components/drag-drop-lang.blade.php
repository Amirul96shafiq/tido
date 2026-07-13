<script>
    window.dragDropLang = {
        uploadUrl: @json(\App\Filament\Pages\ReceiptUploadPage::getUrl()),
        drop_receipt: 'Drop receipt to upload',
        drop_receipt_helper: 'Automatically uploads images below 5MB. Larger files require manual upload on the next page.',
        unsupported_file_type: 'Only receipt images (JPEG, PNG, WebP, GIF) are supported.',
        file_too_large: 'File size exceeds 10MB limit. Your file is :sizeMB MB.',
        large_file_title: 'Large receipt detected',
        large_file_message: ':filename (:sizeMB) is too large for automatic upload. Please drop it on the upload field.',
    };
</script>
