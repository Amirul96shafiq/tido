<?php

declare(strict_types=1);

use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Support\DashboardMonthPeriod;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Support\DashboardSpenderScope;
use App\Support\HouseholdAccess;
use App\Support\WhatsAppSpendingCommandParser;
use App\Support\WhatsAppSpendingReplyBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('summary includes total receipts comparison and footer', function () {
    Expense::unsetEventDispatcher();

    Expense::create([
        'merchant_name' => 'Store A',
        'invoice_number' => 'INV-001',
        'receipt_hash' => 'hash-spend-001',
        'date_time' => now()->copy()->startOfMonth()->addDay(),
        'subtotal' => 100.00,
        'total_tax' => 0.00,
        'total_amount' => 100.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Expense::create([
        'merchant_name' => 'Store B',
        'invoice_number' => 'INV-002',
        'receipt_hash' => 'hash-spend-002',
        'date_time' => now()->copy()->subMonth()->startOfMonth()->addDay(),
        'subtotal' => 50.00,
        'total_tax' => 0.00,
        'total_amount' => 50.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    Expense::setEventDispatcher(app('events'));

    $message = (new WhatsAppSpendingReplyBuilder(now()->format('Y-m')))->build();

    expect($message)
        ->toContain('💰 *Monthly Spending*')
        ->toContain('Showing: *Your expenses*')
        ->toContain('Total spent: *RM 100.00*')
        ->toContain('Receipts: *1* processed')
        ->toContain('Forecast (end of month):')
        ->toContain('Top merchants:')
        ->toContain('*Store A*')
        ->toContain('— Powered by *tido*');
});

test('labels mode lists label spending for selected month', function () {
    Expense::unsetEventDispatcher();

    $targetMonth = now()->copy()->subMonth()->format('Y-m');
    $bounds = DashboardMonthPeriod::boundsFromFilters(['month' => $targetMonth]);

    $label = Label::factory()->create([
        'name' => 'Groceries',
        'slug' => 'groceries',
    ]);

    $expense = Expense::create([
        'merchant_name' => 'Grocery Store',
        'invoice_number' => 'INV-GROC',
        'receipt_hash' => 'hash-groc-spend',
        'date_time' => $bounds['start']->copy()->addDay(),
        'subtotal' => 45.00,
        'total_tax' => 0.00,
        'total_amount' => 45.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
    ]);

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Vegetables',
        'quantity' => 1,
        'unit_price' => 45.00,
        'line_total' => 45.00,
    ]);

    Expense::setEventDispatcher(app('events'));

    $message = (new WhatsAppSpendingReplyBuilder(
        $targetMonth,
        WhatsAppSpendingCommandParser::MODE_LABELS,
    ))->build();

    expect($message)
        ->toContain('🏷️ *Spending by Label*')
        ->toContain('*Groceries* — RM 45.00');
});

test('budgets mode includes active budgets', function () {
    Budget::factory()->create([
        'title' => 'Food Budget',
        'amount' => 500.00,
        'period' => 'monthly',
        'is_active' => true,
    ]);

    $message = (new WhatsAppSpendingReplyBuilder(
        now()->format('Y-m'),
        WhatsAppSpendingCommandParser::MODE_BUDGETS,
    ))->build();

    expect($message)
        ->toContain('📊 *Budget Status*')
        ->toContain('*Food Budget*');
});

test('summary reports empty month when no receipts exist', function () {
    $message = (new WhatsAppSpendingReplyBuilder('2020-01'))->build();

    expect($message)->toContain('No receipts recorded for *January 2020*');
});

test('recent mode excludes explicit non-receipt documents', function () {
    Expense::unsetEventDispatcher();

    Expense::create([
        'merchant_name' => 'Valid Receipt',
        'invoice_number' => 'INV-RECENT-001',
        'receipt_hash' => 'hash-recent-receipt',
        'date_time' => now(),
        'subtotal' => 25.00,
        'total_tax' => 0.00,
        'total_amount' => 25.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
        'document_classification' => Expense::DOCUMENT_CLASSIFICATION_RECEIPT,
    ]);

    Expense::create([
        'merchant_name' => 'Non-receipt document',
        'receipt_hash' => 'hash-recent-non-receipt',
        'date_time' => now(),
        'subtotal' => 999.00,
        'total_tax' => 0.00,
        'total_amount' => 999.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'requires_manual_review',
        'document_classification' => Expense::DOCUMENT_CLASSIFICATION_NOT_RECEIPT,
    ]);

    Expense::setEventDispatcher(app('events'));

    $message = (new WhatsAppSpendingReplyBuilder(
        now()->format('Y-m'),
        WhatsAppSpendingCommandParser::MODE_RECENT,
    ))->build();

    expect($message)
        ->toContain('*Valid Receipt*')
        ->not->toContain('Non-receipt document')
        ->not->toContain('999.00');
});

test('recurrings mode lists overdue before due items for the current month', function () {
    $overdueRecurring = Recurring::factory()->create(['title' => 'Home Financing']);
    $dueRecurring = Recurring::factory()->create(['title' => 'Netflix']);

    RecurringOccurrence::factory()->overdue()->create([
        'recurring_id' => $overdueRecurring->id,
        'due_on' => now()->copy()->subMonth()->startOfMonth()->addDays(4)->toDateString(),
        'expected_amount' => 1327.00,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $dueRecurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->copy()->endOfMonth()->toDateString(),
        'expected_amount' => 25.00,
    ]);

    $message = (new WhatsAppSpendingReplyBuilder(
        now()->format('Y-m'),
        WhatsAppSpendingCommandParser::MODE_RECURRINGS,
    ))->build();

    $overduePos = strpos($message, '*Home Financing*');
    $duePos = strpos($message, '*Netflix*');

    expect($message)
        ->toContain('⏰ *Recurring Payments*')
        ->toContain('Period: *'.now()->format('F Y').'*')
        ->toContain('Overdue:')
        ->toContain('View recurrings')
        ->and($overduePos)->not->toBeFalse()
        ->and($duePos)->not->toBeFalse()
        ->and($overduePos)->toBeLessThan($duePos);
});

test('recurrings mode for a past month excludes overdue from other months', function () {
    $targetMonth = now()->copy()->subMonth();

    $inMonth = Recurring::factory()->create(['title' => 'Last Month Bill']);
    $older = Recurring::factory()->create(['title' => 'Older Overdue']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $inMonth->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => $targetMonth->copy()->startOfMonth()->addDays(2)->toDateString(),
        'expected_amount' => 80.00,
    ]);

    RecurringOccurrence::factory()->overdue()->create([
        'recurring_id' => $older->id,
        'due_on' => now()->copy()->subMonths(2)->startOfMonth()->addDays(2)->toDateString(),
        'expected_amount' => 40.00,
    ]);

    $message = (new WhatsAppSpendingReplyBuilder(
        $targetMonth->format('Y-m'),
        WhatsAppSpendingCommandParser::MODE_RECURRINGS,
    ))->build();

    expect($message)
        ->toContain('*Last Month Bill*')
        ->not->toContain('Older Overdue');
});

test('recurrings mode reports empty month when no open occurrences exist', function () {
    $message = (new WhatsAppSpendingReplyBuilder(
        '2020-01',
        WhatsAppSpendingCommandParser::MODE_RECURRINGS,
    ))->build();

    expect($message)
        ->toContain('📅 *Recurring Payments*')
        ->toContain('No open recurring payments for *January 2020*');
});

test('summary scopes to primary expenses by default and household when all scope is set', function () {
    Expense::unsetEventDispatcher();

    $member = FamilyMember::factory()->allowlisted()->create();

    Expense::create([
        'merchant_name' => 'Primary Store',
        'invoice_number' => 'INV-PRIMARY',
        'receipt_hash' => 'hash-primary-scope',
        'date_time' => now()->copy()->startOfMonth()->addDay(),
        'subtotal' => 100.00,
        'total_tax' => 0.00,
        'total_amount' => 100.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
        'family_member_id' => null,
    ]);

    Expense::create([
        'merchant_name' => 'Family Store',
        'invoice_number' => 'INV-FAMILY',
        'receipt_hash' => 'hash-family-scope',
        'date_time' => now()->copy()->startOfMonth()->addDay(),
        'subtotal' => 50.00,
        'total_tax' => 0.00,
        'total_amount' => 50.00,
        'currency' => 'MYR',
        'source' => 'manual',
        'status' => 'reviewed',
        'family_member_id' => $member->id,
    ]);

    Expense::setEventDispatcher(app('events'));

    $primaryMessage = (new WhatsAppSpendingReplyBuilder(
        now()->format('Y-m'),
        WhatsAppSpendingCommandParser::MODE_SUMMARY,
        new DashboardSpenderScope(DashboardSpenderScope::PRIMARY),
    ))->build();

    $householdMessage = (new WhatsAppSpendingReplyBuilder(
        now()->format('Y-m'),
        WhatsAppSpendingCommandParser::MODE_SUMMARY,
        new DashboardSpenderScope(DashboardSpenderScope::ALL),
    ))->build();

    expect($primaryMessage)
        ->toContain('Showing: *Your expenses*')
        ->toContain('Total spent: *RM 100.00*')
        ->not->toContain('Family Store');

    expect($householdMessage)
        ->toContain('Showing: *Household*')
        ->toContain('Total spent: *RM 150.00*');
});

test('family sender budgets mode hides primary personal budgets but keeps shared and own', function () {
    $member = FamilyMember::factory()->allowlisted()->create();

    Budget::factory()->create([
        'title' => 'Primary Personal',
        'amount' => 500.00,
        'period' => 'monthly',
        'is_active' => true,
        'is_shared' => false,
        'family_member_id' => null,
    ]);

    Budget::factory()->create([
        'title' => 'Household Shared',
        'amount' => 300.00,
        'period' => 'monthly',
        'is_active' => true,
        'is_shared' => true,
        'family_member_id' => null,
    ]);

    Budget::factory()->create([
        'title' => 'Member Budget',
        'amount' => 200.00,
        'period' => 'monthly',
        'is_active' => true,
        'is_shared' => false,
        'family_member_id' => $member->id,
    ]);

    $message = (new WhatsAppSpendingReplyBuilder(
        now()->format('Y-m'),
        WhatsAppSpendingCommandParser::MODE_BUDGETS,
        new DashboardSpenderScope(DashboardSpenderScope::ALL),
        $member->id,
    ))->build();

    expect($message)
        ->not->toContain('*Primary Personal*')
        ->toContain('*Household Shared*')
        ->toContain('*Member Budget*');
});

test('family sender recurrings mode hides primary personal templates', function () {
    $member = FamilyMember::factory()->allowlisted()->create();

    $primaryPersonal = Recurring::factory()->create([
        'title' => 'Primary Personal Bill',
        'is_shared' => false,
        'family_member_id' => null,
    ]);

    $shared = Recurring::factory()->create([
        'title' => 'Shared Bill',
        'is_shared' => true,
        'family_member_id' => null,
    ]);

    $memberRecurring = Recurring::factory()->create([
        'title' => 'Member Bill',
        'is_shared' => false,
        'family_member_id' => $member->id,
    ]);

    foreach ([$primaryPersonal, $shared, $memberRecurring] as $recurring) {
        RecurringOccurrence::factory()->create([
            'recurring_id' => $recurring->id,
            'due_on' => now()->copy()->startOfMonth()->addDays(5)->toDateString(),
            'expected_amount' => 50.00,
        ]);
    }

    $message = (new WhatsAppSpendingReplyBuilder(
        now()->format('Y-m'),
        WhatsAppSpendingCommandParser::MODE_RECURRINGS,
        new DashboardSpenderScope(DashboardSpenderScope::ALL),
        $member->id,
    ))->build();

    expect($message)
        ->not->toContain('*Primary Personal Bill*')
        ->toContain('*Shared Bill*')
        ->toContain('*Member Bill*');
});

test('primary self recurrings mode lists family templates with owner names', function () {
    $member = FamilyMember::factory()->allowlisted()->create([
        'name' => 'Bayu',
        'display_name' => 'Bayu',
    ]);

    $primaryPersonal = Recurring::factory()->create([
        'title' => 'Primary Personal Bill',
        'is_shared' => false,
        'family_member_id' => null,
    ]);

    $familyPersonal = Recurring::factory()->create([
        'title' => 'Family Personal Bill',
        'is_shared' => false,
        'family_member_id' => $member->id,
    ]);

    foreach ([$primaryPersonal, $familyPersonal] as $recurring) {
        RecurringOccurrence::factory()->overdue()->create([
            'recurring_id' => $recurring->id,
            'due_on' => now()->copy()->startOfMonth()->addDays(4)->toDateString(),
            'expected_amount' => 50.00,
        ]);
    }

    $message = (new WhatsAppSpendingReplyBuilder(
        now()->format('Y-m'),
        WhatsAppSpendingCommandParser::MODE_RECURRINGS,
        new DashboardSpenderScope(DashboardSpenderScope::PRIMARY),
    ))->build();

    expect($message)
        ->toContain('*Primary Personal Bill*')
        ->toContain('*Family Personal Bill*')
        ->toContain('Owner: *'.HouseholdAccess::primaryDisplayName().'*')
        ->toContain('Owner: *Bayu*');
});
