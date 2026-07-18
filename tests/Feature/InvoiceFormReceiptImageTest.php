<?php

declare(strict_types=1);

use App\Filament\Forms\Components\NotesRichEditor;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

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
        ->assertSee('Image & Uploads')
        ->assertSee('fi-invoice-form-page', false)
        ->assertSee('fi-invoice-sidebar-sticky', false);
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
