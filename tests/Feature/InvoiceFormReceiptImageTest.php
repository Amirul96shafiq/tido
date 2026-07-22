<?php

declare(strict_types=1);

use App\Filament\Forms\Components\NotesRichEditor;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Support\SelectValueMarquee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('invoice edit form uses private visibility for receipt image', function () {
    $invoice = Invoice::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'image_path',
            checkComponentUsing: fn (FileUpload $component): bool => $component->getVisibility() === 'private',
        );
});

test('invoice edit form receipt image upload uses natural height preview class', function () {
    $invoice = Invoice::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('fi-receipt-image-upload', false)
        ->assertSchemaComponentExists(
            'image_path',
            checkComponentUsing: function (FileUpload $component): bool {
                expect($component->getExtraAttributes())->toMatchArray([
                    'class' => 'fi-receipt-image-upload',
                ]);

                return true;
            },
        );
});

test('invoice form uses rich editor for notes', function () {
    $invoice = Invoice::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'notes',
            checkComponentUsing: function (NotesRichEditor $component): bool {
                expect($component->getExtraAttributes())->toMatchArray([
                    'class' => NotesRichEditor::EXTRA_CLASS,
                ]);

                return true;
            },
        );
});

test('invoice form uses left right sticky layout', function () {
    $invoice = Invoice::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Receipt Details')
        ->assertSee('Invoice Notes')
        ->assertSee('Line Items')
        ->assertSee('Status')
        ->assertSee('Image & Uploads')
        ->assertSee('fi-invoice-form-page', false)
        ->assertSee('fi-invoice-sidebar-sticky', false)
        ->assertSeeInOrder([
            'Receipt Details',
            'Invoice Notes',
            'Line Items',
            'Status',
        ]);
});

test('invoice currency select uses single-line marquee markup', function () {
    $invoice = Invoice::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(SelectValueMarquee::EXTRA_CLASS, false)
        ->assertSchemaComponentExists(
            'currency',
            checkComponentUsing: function (Select $component): bool {
                expect($component->canOptionLabelsWrap())->toBeFalse();

                return true;
            },
        );
});

test('invoice edit form serves receipt image via temporary url', function () {
    Storage::fake();
    $this->travelTo(now()->startOfMinute());

    $path = 'receipts/20260708_174004.jpg';
    Storage::put($path, 'fake-image-bytes');

    $invoice = Invoice::factory()->create([
        'image_path' => $path,
        'original_filename' => '20260708_174004.jpg',
    ]);

    $temporaryUrl = Storage::temporaryUrl(
        $path,
        now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30))->endOfHour(),
    );

    $component = Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaStateSet([
            'image_path' => $path,
        ]);

    $uploadedFiles = $component->instance()->callSchemaComponentMethod('form.image_path', 'getUploadedFiles');

    expect($uploadedFiles)->not->toBeEmpty();

    $fileMeta = collect($uploadedFiles)->first();

    expect($fileMeta)
        ->not->toBeNull()
        ->and($fileMeta['url'])->toBe($temporaryUrl)
        ->and($fileMeta['name'])->toBe('20260708_174004.jpg');
});

test('invoice line item repeater uses description and line total as item label', function () {
    $invoice = Invoice::factory()->create();
    $item = InvoiceItem::factory()->for($invoice)->create([
        'description' => 'Nasi Lemak Special',
        'line_total' => 10.00,
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldExists(
            'invoiceItems',
            function (Repeater $field) use ($item): bool {
                expect($field->hasItemLabels())->toBeTrue();
                expect($field->getItemLabel("record-{$item->getKey()}"))->toBe('Nasi Lemak Special (RM10.00)');

                return true;
            },
        );
});

test('invoice line item description and line total restore defaults when emptied', function () {
    $invoice = Invoice::factory()->create();
    $item = InvoiceItem::factory()->for($invoice)->create([
        'description' => 'Nasi Lemak Special',
        'line_total' => 10.00,
    ]);

    $itemKey = "record-{$item->getKey()}";

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldExists(
            'invoiceItems',
            function (Repeater $field): bool {
                $components = collect($field->getChildSchema()->getFlatComponents(withHidden: true));

                $description = $components->first(
                    fn (mixed $component): bool => $component instanceof TextInput && $component->getName() === 'description',
                );
                $lineTotal = $components->first(
                    fn (mixed $component): bool => $component instanceof TextInput && $component->getName() === 'line_total',
                );

                expect($description)->not->toBeNull()
                    ->and($description->getDefaultState())->toBe('Item name')
                    ->and($lineTotal)->not->toBeNull()
                    ->and($lineTotal->getDefaultState())->toBe('0.00');

                return true;
            },
        )
        ->set("data.invoiceItems.{$itemKey}.description", '')
        ->assertSet("data.invoiceItems.{$itemKey}.description", 'Item name')
        ->set("data.invoiceItems.{$itemKey}.line_total", '')
        ->assertSet("data.invoiceItems.{$itemKey}.line_total", '0.00');
});

test('invoice receipt fields have placeholders for empty values', function () {
    $invoice = Invoice::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'merchant_name',
            checkComponentUsing: fn (TextInput $component): bool => $component->getPlaceholder() === 'Merchant name',
        )
        ->assertSchemaComponentExists(
            'invoice_number',
            checkComponentUsing: fn (TextInput $component): bool => $component->getPlaceholder() === 'Invoice number',
        )
        ->assertSchemaComponentExists(
            'subtotal',
            checkComponentUsing: fn (TextInput $component): bool => $component->getPlaceholder() === '0.00',
        )
        ->assertSchemaComponentExists(
            'total_amount',
            checkComponentUsing: fn (TextInput $component): bool => $component->getPlaceholder() === '0.00',
        );
});
