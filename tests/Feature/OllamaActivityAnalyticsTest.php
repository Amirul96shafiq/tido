<?php

declare(strict_types=1);

use App\Filament\Support\OllamaActivityAnalytics;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

test('ollama activity analytics trend returns six month series grouped by created_at', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00', 'Asia/Kuala_Lumpur'));

    Expense::unsetEventDispatcher();

    $julyParsed = Expense::create([
        'merchant_name' => 'July Parsed PDF',
        'invoice_number' => 'INV-OLLAMA-JUL-PDF',
        'receipt_hash' => hash('sha256', 'ollama-jul-pdf'),
        'date_time' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 10.00,
        'total_tax' => 0,
        'total_amount' => 10.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'parsed',
        'file_mime_type' => 'application/pdf',
    ]);
    $julyParsed->forceFill([
        'created_at' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kuala_Lumpur'),
        'updated_at' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kuala_Lumpur'),
    ])->saveQuietly();

    $augustReviewed = Expense::create([
        'merchant_name' => 'August Reviewed Image',
        'invoice_number' => 'INV-OLLAMA-AUG-IMG',
        'receipt_hash' => hash('sha256', 'ollama-aug-img'),
        'date_time' => Carbon::parse('2026-08-05 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 20.00,
        'total_tax' => 0,
        'total_amount' => 20.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'status' => 'reviewed',
        'file_mime_type' => 'image/jpeg',
    ]);
    $augustReviewed->forceFill([
        'created_at' => Carbon::parse('2026-08-05 10:00:00', 'Asia/Kuala_Lumpur'),
        'updated_at' => Carbon::parse('2026-08-05 10:00:00', 'Asia/Kuala_Lumpur'),
    ])->saveQuietly();

    $augustManual = Expense::create([
        'merchant_name' => 'August Manual Text',
        'invoice_number' => 'INV-OLLAMA-AUG-TXT',
        'receipt_hash' => hash('sha256', 'ollama-aug-txt'),
        'date_time' => Carbon::parse('2026-08-08 10:00:00', 'Asia/Kuala_Lumpur'),
        'subtotal' => 15.00,
        'total_tax' => 0,
        'total_amount' => 15.00,
        'currency' => 'MYR',
        'source' => 'whatsapp',
        'status' => 'requires_manual_review',
        'file_mime_type' => null,
    ]);
    $augustManual->forceFill([
        'created_at' => Carbon::parse('2026-08-08 10:00:00', 'Asia/Kuala_Lumpur'),
        'updated_at' => Carbon::parse('2026-08-08 10:00:00', 'Asia/Kuala_Lumpur'),
    ])->saveQuietly();

    Expense::setEventDispatcher(app('events'));

    $trend = OllamaActivityAnalytics::make()->trend(6);

    expect($trend['labels'])->toHaveCount(6)
        ->and($trend['labels'][4])->toBe('07/26')
        ->and($trend['labels'][5])->toBe('08/26')
        ->and($trend['parsed'])->toHaveCount(6)
        ->and($trend['parsed'][4])->toBe(1.0)
        ->and($trend['parsed'][5])->toBe(0.0)
        ->and($trend['reviewed'][5])->toBe(1.0)
        ->and($trend['manual_review'][5])->toBe(1.0)
        ->and($trend['pdf'][4])->toBe(1.0)
        ->and($trend['image'][5])->toBe(1.0)
        ->and($trend['text_only'][5])->toBe(1.0);
});

test('ollama activity analytics trend fills missing months with zero', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00', 'Asia/Kuala_Lumpur'));

    $trend = OllamaActivityAnalytics::make()->trend(6);

    expect($trend['parsed'])->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 0.0])
        ->and($trend['reviewed'])->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 0.0])
        ->and($trend['manual_review'])->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 0.0])
        ->and($trend['pdf'])->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 0.0])
        ->and($trend['image'])->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 0.0])
        ->and($trend['text_only'])->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
});
