<?php

declare(strict_types=1);

use App\Filament\Widgets\ReceiptsBySource;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('receipts by source widget renders source labels', function () {
    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'source' => 'manual',
    ]);

    Expense::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'source' => 'whatsapp',
        'image_path' => 'receipts/wa_parse.jpg',
    ]);

    Expense::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'source' => 'whatsapp',
        'image_path' => null,
    ]);

    Expense::setEventDispatcher(app('events'));

    Livewire::test(ReceiptsBySource::class)
        ->assertSuccessful()
        ->assertSee('borderRadius', false)
        ->assertSee('Manual Upload')
        ->assertSee('WhatsApp (Parse)')
        ->assertSee('WhatsApp (Manual)')
        ->assertDontSee('Google Drive')
        ->assertDontSeeHtml('wire:poll.30s');
});

test('receipts by source widget shows empty channels when only whatsapp has receipts', function () {
    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'date_time' => now(),
        'status' => 'reviewed',
        'source' => 'whatsapp',
        'image_path' => 'receipts/wa_only.jpg',
    ]);

    Expense::setEventDispatcher(app('events'));

    Livewire::test(ReceiptsBySource::class)
        ->assertSuccessful()
        ->assertSee('WhatsApp (Parse)')
        ->assertSee('WhatsApp (Manual)')
        ->assertDontSee('Google Drive')
        ->assertSee('Manual Upload')
        ->assertDontSee('No receipts');
});

test('receipts by source widget listens for echo expense updates without polling', function () {
    $component = Livewire::test(ReceiptsBySource::class)
        ->assertSuccessful()
        ->assertSee('No receipts')
        ->assertDontSeeHtml('wire:poll.30s')
        ->assertDontSeeHtml('wire:poll.5s');

    expect($component->instance()->getListeners())
        ->toHaveKey('echo-private:household.expenses,.ExpenseUpdated');
});
