<?php

declare(strict_types=1);

use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('receipt upload page lists recent invoices', function () {
    $invoice = Invoice::factory()->create([
        'original_filename' => 'wa_receipt_preview.jpg',
        'image_path' => 'receipts/wa_receipt_preview.jpg',
    ]);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee('wa_receipt....jpg');
});

test('filename links to file in a new tab', function () {
    Storage::fake();

    $path = 'receipts/wa_receipt_preview.jpg';
    Storage::put($path, 'fake-image-bytes');

    $invoice = Invoice::factory()->create([
        'original_filename' => 'wa_receipt_preview.jpg',
        'image_path' => $path,
    ]);

    $url = Storage::temporaryUrl($path, now()->addMinutes(30));

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml(e($url));
});

test('filename without file path has no link', function () {
    $invoice = Invoice::factory()->create([
        'original_filename' => 'missing_file.jpg',
        'image_path' => null,
    ]);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertDontSeeHtml('missing_file.jpg</a>');
});

test('receipt upload page truncates long merchant names with full name in tooltip', function () {
    $longMerchant = 'Cosmo Restaurants Sdn Bhd';
    $invoice = Invoice::factory()->create([
        'merchant_name' => $longMerchant,
    ]);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee('Cosmo Restaurants Sd...');

    $column = Livewire::test(ReceiptUploadPage::class)
        ->instance()
        ->getTable()
        ->getColumn('merchant_name');

    expect($column)->not->toBeNull()
        ->and($column->getCharacterLimit())->toBe(20);

    $tooltip = $column->record($invoice)->getTooltip($longMerchant);

    expect($tooltip)->toBe($longMerchant);
});

test('upload button shows loading spinner while saving', function () {
    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertSeeHtml('wire:target="save"')
        ->assertSeeHtml('wire:loading.delay')
        ->assertSeeHtml('M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z')
        ->assertSee('Upload and Start AI Extraction');
});

test('receipt upload page edit action spa navigates to invoice edit', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $invoice = Invoice::factory()->create([
        'original_filename' => null,
        'image_path' => null,
    ]);

    $editUrl = InvoiceResource::getUrl('edit', ['record' => $invoice]);
    $editAction = TestAction::make('edit')->table($invoice);

    $table = Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertActionExists($editAction)
        ->assertActionHasUrl($editAction, $editUrl)
        ->assertActionShouldNotOpenUrlInNewTab($editAction)
        ->assertSee($editUrl, false)
        ->assertSee('wire:navigate', false)
        ->instance()
        ->getTable();

    $action = $table->getAction('edit');

    expect($action)->not->toBeNull()
        ->and($action->isIconButton())->toBeTrue()
        ->and($action->getTooltip())->toBe($action->getLabel());
});
