<?php

declare(strict_types=1);

test('changelog commit rows use visible dark theme separators', function () {
    $blade = (string) file_get_contents(resource_path('views/components/changelog-modal.blade.php'));

    expect($blade)
        ->toContain('border-b border-gray-200 dark:border-gray-700')
        ->not->toContain('dark:border-gray-800');
});
