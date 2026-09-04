<?php

declare(strict_types=1);

use App\Enums\UserDateFormat;
use App\Filament\Forms\Components\NotesRichEditor;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Support\SelectValueMarquee;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
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

test('expense edit form uses private visibility for receipt image', function () {
    $expense = Expense::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'image_path',
            checkComponentUsing: fn (FileUpload $component): bool => $component->getVisibility() === 'private',
        );
});

test('expense edit form receipt image upload uses natural height preview class', function () {
    $expense = Expense::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
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

test('expense form uses rich editor for notes', function () {
    $expense = Expense::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'notes',
            checkComponentUsing: function (NotesRichEditor $component): bool {
                expect($component->getExtraAttributes()['class'])->toContain(NotesRichEditor::EXTRA_CLASS);

                return true;
            },
        );
});

test('expense form uses left right sticky layout', function () {
    $expense = Expense::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Receipt Details')
        ->assertSee('Expense Notes')
        ->assertSee('Line Items')
        ->assertSee('Status')
        ->assertSee('Image & Uploads')
        ->assertSee('fi-expense-form-page', false)
        ->assertSee('fi-expense-sidebar-sticky', false)
        ->assertSeeInOrder([
            'Receipt Details',
            'Expense Notes',
            'Line Items',
            'Status',
        ]);
});

test('expense currency select uses looping text marquee markup', function () {
    $expense = Expense::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(SelectValueMarquee::EXTRA_CLASS, false)
        ->assertSchemaComponentExists(
            'currency',
            checkComponentUsing: function (Select $component): bool {
                expect($component->canOptionLabelsWrap())->toBeFalse();

                return true;
            },
        );

    expect(file_get_contents(resource_path('js/select-value-marquee.js')))
        ->toContain('tido-text-marquee-track')
        ->toContain('tido-text-marquee-segment')
        ->toContain('.fi-select-input-option')
        ->toContain('enhanceOptionLabels')
        ->toContain('tido-option-marquee-clip')
        ->not->toContain('--tido-marquee-clip');
});

test('foreign expense form uses source currency prefixes and shows conversion context', function () {
    $expense = Expense::factory()->create([
        'image_path' => null,
        'currency' => 'USD',
        'original_currency' => 'USD',
        'original_total_amount' => 6.00,
        'currency_conversion_status' => Expense::CONVERSION_FAILED,
        'status' => 'requires_manual_review',
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Original Currency')
        ->assertSee('Rate Provider')
        ->assertSchemaComponentExists(
            'total_amount',
            checkComponentUsing: fn (TextInput $component): bool => $component->getPrefixLabel() === 'USD',
        );
});

test('expense edit form serves receipt image via temporary url', function () {
    Storage::fake();
    $this->travelTo(now()->startOfMinute());

    $path = 'receipts/20260708_174004.jpg';
    Storage::put($path, 'fake-image-bytes');

    $expense = Expense::factory()->create([
        'image_path' => $path,
        'original_filename' => '20260708_174004.jpg',
    ]);

    $temporaryUrl = Storage::temporaryUrl(
        $path,
        now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30))->endOfHour(),
    );

    $component = Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
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

test('expense line item repeater uses description and line total as item label', function () {
    $expense = Expense::factory()->create();
    $item = ExpenseItem::factory()->for($expense)->create([
        'description' => 'Nasi Lemak Special',
        'line_total' => 10.00,
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldExists(
            'expenseItems',
            function (Repeater $field) use ($item): bool {
                expect($field->hasItemLabels())->toBeTrue();
                expect($field->getItemLabel("record-{$item->getKey()}"))->toBe('Nasi Lemak Special (RM10.00)');

                return true;
            },
        );
});

test('expense line item quantity supports hundredths', function () {
    $expense = Expense::factory()->create();

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldExists(
            'expenseItems',
            function (Repeater $field): bool {
                $quantity = collect($field->getChildSchema()->getFlatComponents(withHidden: true))->first(
                    fn (mixed $component): bool => $component instanceof TextInput && $component->getName() === 'quantity',
                );

                expect($quantity)
                    ->not->toBeNull()
                    ->and($quantity->getStep())->toBe(0.01);

                return true;
            },
        );
});

test('expense line item description and line total restore defaults when emptied', function () {
    $expense = Expense::factory()->create();
    $item = ExpenseItem::factory()->for($expense)->create([
        'description' => 'Nasi Lemak Special',
        'line_total' => 10.00,
    ]);

    $itemKey = "record-{$item->getKey()}";

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldExists(
            'expenseItems',
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
        ->set("data.expenseItems.{$itemKey}.description", '')
        ->assertSet("data.expenseItems.{$itemKey}.description", 'Item name')
        ->set("data.expenseItems.{$itemKey}.line_total", '')
        ->assertSet("data.expenseItems.{$itemKey}.line_total", '0.00');
});

test('expense date time picker follows the profile date format and timezone', function (string $format, string $timezone, string $expectedDisplayFormat): void {
    $this->actingAs(User::factory()->create([
        'date_format' => $format,
        'timezone' => $timezone,
    ]));

    $expense = Expense::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'date_time',
            checkComponentUsing: function (DateTimePicker $component) use ($expectedDisplayFormat, $timezone): bool {
                expect($component->hasSeconds())->toBeFalse()
                    ->and($component->getDisplayFormat())->toBe($expectedDisplayFormat)
                    ->and($component->getTimezone())->toBe($timezone);

                return true;
            },
        );
})->with([
    'dmy slash kl' => [UserDateFormat::DmySlash->value, 'Asia/Kuala_Lumpur', 'd/m/Y H:i'],
    'dmy long auckland' => [UserDateFormat::DmyLong->value, 'Pacific/Auckland', 'd M Y H:i'],
    'iso london' => [UserDateFormat::Iso->value, 'Europe/London', 'Y-m-d H:i'],
]);

test('expense receipt fields have placeholders for empty values', function () {
    $expense = Expense::factory()->create([
        'image_path' => null,
    ]);

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
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
