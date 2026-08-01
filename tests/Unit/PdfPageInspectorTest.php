<?php

declare(strict_types=1);

use App\Services\PdfInspectionException;
use App\Services\PdfPageInspector;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'services.documents.pdfinfo_binary' => 'pdfinfo',
        'services.documents.pdf_inspection_timeout' => 15,
    ]);

    Process::preventStrayProcesses();
});

test('reads the PDF page count with pdfinfo', function () {
    Process::fake([
        '*' => Process::result(output: "Title: Receipt\nPages:          3\n"),
    ]);

    expect(app(PdfPageInspector::class)->pageCount('%PDF-1.7 test'))->toBe(3);

    Process::assertRan(function (PendingProcess $process): bool {
        return is_array($process->command)
            && $process->command[0] === 'pdfinfo'
            && $process->timeout === 15;
    });
});

test('reports password protected PDFs distinctly', function () {
    Process::fake([
        '*' => Process::result(errorOutput: 'Command Line Error: Incorrect password', exitCode: 1),
    ]);

    try {
        app(PdfPageInspector::class)->pageCount('%PDF-1.7 encrypted');
    } catch (PdfInspectionException $exception) {
        expect($exception->reason)->toBe(PdfInspectionException::PASSWORD_PROTECTED);

        return;
    }

    $this->fail('Expected a PDF inspection exception.');
});

test('reports a missing PDF utility distinctly', function () {
    Process::fake([
        '*' => Process::result(errorOutput: 'The system cannot find the path specified.', exitCode: 1),
    ]);

    try {
        app(PdfPageInspector::class)->pageCount('%PDF-1.7 test');
    } catch (PdfInspectionException $exception) {
        expect($exception->reason)->toBe(PdfInspectionException::DEPENDENCY_MISSING);

        return;
    }

    $this->fail('Expected a PDF inspection exception.');
});
