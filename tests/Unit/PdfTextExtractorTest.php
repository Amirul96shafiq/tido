<?php

declare(strict_types=1);

use App\Services\PdfTextExtractor;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'services.documents.pdftotext_binary' => 'pdftotext',
        'services.documents.pdf_text_timeout' => 15,
    ]);

    Process::preventStrayProcesses();
});

test('extracts embedded PDF text with pdftotext', function () {
    Process::fake([
        '*' => Process::result(output: "Subtotal USD 20.00\nTotal USD 6.00\n"),
    ]);

    expect(app(PdfTextExtractor::class)->extract('%PDF-1.7 test'))
        ->toBe("Subtotal USD 20.00\nTotal USD 6.00");

    Process::assertRan(function (PendingProcess $process): bool {
        return is_array($process->command)
            && $process->command[0] === 'pdftotext'
            && $process->command[1] === '-layout'
            && $process->command[array_key_last($process->command)] === '-'
            && $process->timeout === 15;
    });
});

test('returns empty text when the PDF text utility is unavailable', function () {
    Process::fake([
        '*' => Process::result(
            errorOutput: 'The system cannot find the path specified.',
            exitCode: 1,
        ),
    ]);

    expect(app(PdfTextExtractor::class)->extract('%PDF-1.7 test'))->toBe('');
});
