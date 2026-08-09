<?php

declare(strict_types=1);

use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Models\Expense;
use App\Models\ExpenseItem;
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

function fakeExpenseCurrencyRate(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/historical*' => Http::response([
            'meta' => ['last_updated_at' => '2026-07-08T23:59:59Z'],
            'data' => ['MYR' => ['code' => 'MYR', 'value' => 4.5]],
        ]),
    ]);
}

test('create expense form converts foreign amounts and saves conversion metadata', function () {
    Queue::fake();
    fakeExpenseCurrencyRate();

    $label = Label::factory()->create();

    $component = Livewire::test(CreateExpense::class)
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
            'expenseItems' => [[
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
        ->and($component->get('data.currency_conversion_status'))->toBe(Expense::CONVERSION_CONVERTED)
        ->and($component->get('data.currency_conversion_rate'))->toBe(4.5)
        ->and($component->get('data.currency_conversion_date'))->toBe('2026-07-08')
        ->and($component->get('data.currency_conversion_provider'))->toBe('currencyapi')
        ->and(collect($component->get('data.expenseItems'))->first()['line_total'])->toBe('90.00');

    $component
        ->call('create')
        ->assertHasNoFormErrors();

    $expense = Expense::query()
        ->where('merchant_name', 'Manual Currency Store')
        ->firstOrFail();

    expect($expense->currency)->toBe(Expense::CURRENCY_MYR)
        ->and($expense->total_amount)->toBe('90.00')
        ->and($expense->original_currency)->toBe('USD')
        ->and($expense->original_total_amount)->toBe('20.00')
        ->and($expense->currency_conversion_status)->toBe(Expense::CONVERSION_CONVERTED)
        ->and((float) $expense->currency_conversion_rate)->toBe(4.5)
        ->and($expense->currency_conversion_date->format('Y-m-d'))->toBe('2026-07-08')
        ->and($expense->currency_conversion_provider)->toBe('currencyapi')
        ->and($expense->currency_conversion_fetched_at)->not->toBeNull()
        ->and($expense->expenseItems->first()->line_total)->toBe('90.00');
});

test('edit expense form converts source amounts and clears the conversion review marker', function () {
    fakeExpenseCurrencyRate();

    $expense = Expense::factory()->create([
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
        'currency_conversion_status' => Expense::CONVERSION_FAILED,
        'status' => 'requires_manual_review',
        'notes' => '<p>[AI] Currency conversion could not be completed; verify the source amount and rate.</p>',
    ]);

    ExpenseItem::factory()->for($expense)->create([
        'description' => 'Cursor Pro',
        'quantity' => 1,
        'unit_price' => 20.00,
        'line_total' => 20.00,
    ]);

    $component = Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSeeInOrder([
            'Original Currency',
            'Original Amount',
            'Rate (MYR per unit)',
            'Rate Date',
            'Rate Provider',
            'Convert to MYR',
        ])
        ->assertActionVisible(invoiceFormCurrencyAction())
        ->callAction(invoiceFormCurrencyAction())
        ->assertNotified('Currency converted to MYR')
        ->call('save')
        ->assertHasNoFormErrors();

    $expense->refresh();

    expect($expense->currency)->toBe(Expense::CURRENCY_MYR)
        ->and($expense->total_amount)->toBe('90.00')
        ->and($expense->original_currency)->toBe('USD')
        ->and($expense->currency_conversion_status)->toBe(Expense::CONVERSION_CONVERTED)
        ->and($expense->notes)->not->toContain('Currency conversion could not be completed')
        ->and($expense->expenseItems->first()->unit_price)->toBe('90.00')
        ->and($expense->expenseItems->first()->line_total)->toBe('90.00');
});

test('expense form keeps source amounts when the existing rate provider fails', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://currencyapi.test/v3/historical*' => Http::failedConnection(),
    ]);

    $expense = Expense::factory()->create([
        'date_time' => '2026-07-08 00:00:00',
        'subtotal' => 20.00,
        'total_tax' => 0.00,
        'discount_total' => 0.00,
        'rounding_amount' => 0.00,
        'total_amount' => 20.00,
        'currency' => 'USD',
        'original_currency' => 'USD',
        'original_total_amount' => 20.00,
        'currency_conversion_status' => Expense::CONVERSION_FAILED,
        'status' => 'requires_manual_review',
    ]);

    $component = Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->callAction(invoiceFormCurrencyAction())
        ->assertNotified('Currency conversion failed');

    expect($component->get('data.currency'))->toBe('USD')
        ->and($component->get('data.total_amount'))->toBe('20.00')
        ->and($component->get('data.currency_conversion_status'))->toBe(Expense::CONVERSION_FAILED)
        ->and($expense->fresh()->currency)->toBe('USD')
        ->and($expense->fresh()->total_amount)->toBe('20.00');
});
