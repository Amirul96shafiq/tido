<?php

declare(strict_types=1);

use App\Filament\Widgets\ReceiptsBySource;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('receipts by source widget renders source labels', function () {
    Invoice::unsetEventDispatcher();

    Invoice::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'source' => 'manual',
    ]);

    Invoice::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'source' => 'whatsapp',
        'image_path' => 'receipts/wa_parse.jpg',
    ]);

    Invoice::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'source' => 'whatsapp',
        'image_path' => null,
    ]);

    Invoice::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'source' => 'google_drive',
    ]);

    Invoice::setEventDispatcher(app('events'));

    Livewire::test(ReceiptsBySource::class)
        ->assertSuccessful()
        ->assertSee('borderRadius', false)
        ->assertSee('Manual Upload')
        ->assertSee('WhatsApp (Parse)')
        ->assertSee('WhatsApp (Manual)')
        ->assertSee('Google Drive')
        ->assertSeeHtml('wire:poll.30s');
});

test('receipts by source widget shows empty channels when only whatsapp has receipts', function () {
    Invoice::unsetEventDispatcher();

    Invoice::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'source' => 'whatsapp',
        'image_path' => 'receipts/wa_only.jpg',
    ]);

    Invoice::setEventDispatcher(app('events'));

    Livewire::test(ReceiptsBySource::class)
        ->assertSuccessful()
        ->assertSee('WhatsApp (Parse)')
        ->assertSee('WhatsApp (Manual)')
        ->assertSee('Google Drive')
        ->assertSee('Manual Upload')
        ->assertDontSee('No receipts');
});

test('receipts by source widget polls while empty', function () {
    Livewire::test(ReceiptsBySource::class)
        ->assertSuccessful()
        ->assertSee('No receipts')
        ->assertSeeHtml('wire:poll.30s="updateChartData"');
});
