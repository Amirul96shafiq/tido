<?php

declare(strict_types=1);

use App\Services\PdfPageRenderer;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

test('renders PDF pages to JPEG contents in page order', function () {
    config([
        'services.documents.pdfinfo_binary' => 'pdfinfo',
        'services.documents.pdftocairo_binary' => 'pdftocairo',
        'services.documents.pdf_render_dpi' => 144,
    ]);

    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) {
        if (is_array($process->command) && $process->command[0] === 'pdfinfo') {
            return Process::result(output: "Pages: 2\n");
        }

        $outputPrefix = $process->command[array_key_last($process->command)];
        File::put($outputPrefix.'-1.jpg', 'page-one-image');
        File::put($outputPrefix.'-2.jpg', 'page-two-image');

        return Process::result();
    });

    $pages = app(PdfPageRenderer::class)->render('%PDF-1.7 test');

    expect($pages)->toBe(['page-one-image', 'page-two-image']);

    Process::assertRan(function (PendingProcess $process): bool {
        return is_array($process->command)
            && $process->command[0] === 'pdftocairo'
            && in_array('-jpeg', $process->command, true)
            && in_array('144', $process->command, true);
    });
});
