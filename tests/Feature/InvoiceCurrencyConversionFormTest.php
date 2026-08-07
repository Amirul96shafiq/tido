<?php

declare(strict_types=1);

use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Label;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'cache.default' => 'array',
        'services.currencyapi.provider' => 'currencyapi',
        'services.currencyapi.api_key' => 'test-key',
        'services.currencyapi.base_url' => 'https://currencyapi.test',
        'services.currencyapi.cainfo' => null,
        'services.currencyapi.retry_delays' => [0, 0],
    ]);

    $this->actingAs(User::factory()->create());
});

function invoiceFormCurrencyAction(): TestAction
{
    return TestAction::make('convertCurrency')
        ->schemaComponent('currencyConversionActions', schema: 'form');
}

function fakeInvoiceCurrencyRate(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-07-08T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.5]],
        ]),
    ]);
}

test('create invoice form converts foreign amounts and saves conversion metadata', function () {
    Queue::fake();
    fakeInvoiceCurrencyRate();

    $label = Label::factory()->create();

    $component = Livewire::test(CreateInvoice::class)
        ->assertSchemaComponentExists(
            'currency',
            checkComponentUsing: fn (Select $component): bool => array_key_exists('USD', $component->getOptions()),
        )
        ->fillForm([
            'merchant_name' => 'Manual Currency Store',
            'invoice_number' => 'USD-FORM-1',
            'date_time' => '2026-07-08 00:00:00',
            'subtotal' => '20.00',
            'total_tax' => '0.00',
            'discount_total' => '0.00',
            'rounding_amount' => '0.00',
            'total_amount' => '20.00',
            'currency' => 'USD',
            'source' => 'manual',
            'status' => 'reviewed',
            'invoiceItems' => [[
                'description' => 'Cursor Pro',
                'label_id' => $label->id,
                'quantity' => 1,
                'unit_price' => '20.00',
                'line_total' => '20.00',
            ]],
        ])
        ->assertActionVisible(invoiceFormCurrencyAction())
        ->callAction(invoiceFormCurrencyAction())
        ->assertNotified('Currency converted to MYR');

    expect($component->get('data.currency'))->toBe('MYR')
        ->and($component->get('data.subtotal'))->toBe('90.00')
        ->and($component->get('data.total_amount'))->toBe('90.00')
        ->and($component->get('data.original_currency'))->toBe('USD')
        ->and($component->get('data.original_total_amount'))->toBe('20.00')
        ->and($component->get('data.currency_conversion_status'))->toBe(Invoice::CONVERSION_CONVERTED)
        ->and($component->get('data.currency_conversion_rate'))->toBe(4.5)
        ->and($component->get('data.currency_conversion_date'))->toBe('2026-07-08')
        ->and($component->get('data.currency_conversion_provider'))->toBe('currencyapi')
        ->and(collect($component->get('data.invoiceItems'))->first()['line_total'])->toBe('90.00');

    $component
        ->call('create')
        ->assertHasNoFormErrors();

    $invoice = Invoice::query()
        ->where('merchant_name', 'Manual Currency Store')
        ->firstOrFail();

    expect($invoice->currency)->toBe(Invoice::CURRENCY_MYR)
        ->and($invoice->total_amount)->toBe('90.00')
        ->and($invoice->original_currency)->toBe('USD')
        ->and($invoice->original_total_amount)->toBe('20.00')
        ->and($invoice->currency_conversion_status)->toBe(Invoice::CONVERSION_CONVERTED)
        ->and((float) $invoice->currency_conversion_rate)->toBe(4.5)
        ->and($invoice->currency_conversion_date->format('Y-m-d'))->toBe('2026-07-08')
        ->and($invoice->currency_conversion_provider)->toBe('currencyapi')
        ->and($invoice->currency_conversion_fetched_at)->not->toBeNull()
        ->and($invoice->invoiceItems->first()->line_total)->toBe('90.00');
});

test('edit invoice form converts source amounts and clears the conversion review marker', function () {
    fakeInvoiceCurrencyRate();

    $invoice = Invoice::factory()->create([
        'merchant_name' => 'Cursor',
        'date_time' => '2026-07-08 00:00:00',
        'subtotal' => 20.00,
        'total_tax' => 0.00,
        'discount_total' => 0.00,
        'rounding_amount' => 0.00,
        'total_amount' => 20.00,
        'currency' => 'USD',
        'original_currency' => 'USD',
        'original_total_amount' => 20.00,
        'currency_conversion_status' => Invoice::CONVERSION_FAILED,
        'status' => 'requires_manual_review',
        'notes' => '<p>[AI] Currency conversion could not be completed; verify the source amount and rate.</p>',
    ]);

    InvoiceItem::factory()->for($invoice)->create([
        'description' => 'Cursor Pro',
        'quantity' => 1,
        'unit_price' => 20.00,
        'line_total' => 20.00,
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertActionVisible(invoiceFormCurrencyAction())
        ->callAction(invoiceFormCurrencyAction())
        ->assertNotified('Currency converted to MYR')
        ->call('save')
        ->assertHasNoFormErrors();

    $invoice->refresh();

    expect($invoice->currency)->toBe(Invoice::CURRENCY_MYR)
        ->and($invoice->total_amount)->toBe('90.00')
        ->and($invoice->original_currency)->toBe('USD')
        ->and($invoice->currency_conversion_status)->toBe(Invoice::CONVERSION_CONVERTED)
        ->and($invoice->notes)->not->toContain('Currency conversion could not be completed')
        ->and($invoice->invoiceItems->first()->unit_price)->toBe('90.00')
        ->and($invoice->invoiceItems->first()->line_total)->toBe('90.00');
});

test('invoice form keeps source amounts when the existing rate provider fails', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/historical*' => Http::failedConnection(),
    ]);

    $invoice = Invoice::factory()->create([
        'date_time' => '2026-07-08 00:00:00',
        'subtotal' => 20.00,
        'total_tax' => 0.00,
        'discount_total' => 0.00,
        'rounding_amount' => 0.00,
        'total_amount' => 20.00,
        'currency' => 'USD',
        'original_currency' => 'USD',
        'original_total_amount' => 20.00,
        'currency_conversion_status' => Invoice::CONVERSION_FAILED,
        'status' => 'requires_manual_review',
    ]);

    $component = Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction(invoiceFormCurrencyAction())
        ->assertNotified('Currency conversion failed');

    expect($component->get('data.currency'))->toBe('USD')
        ->and($component->get('data.total_amount'))->toBe('20.00')
        ->and($component->get('data.currency_conversion_status'))->toBe(Invoice::CONVERSION_FAILED)
        ->and($invoice->fresh()->currency)->toBe('USD')
        ->and($invoice->fresh()->total_amount)->toBe('20.00');
});
