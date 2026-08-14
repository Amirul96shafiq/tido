<?php

declare(strict_types=1);

use App\Events\ExpenseUpdated;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('creating an expense broadcasts id and status only', function (): void {
    Event::fake([ExpenseUpdated::class]);

    $expense = Expense::factory()->create([
        'status' => 'pending',
        'source' => 'whatsapp',
        'image_path' => null,
    ]);

    Event::assertDispatched(ExpenseUpdated::class, function (ExpenseUpdated $event) use ($expense): bool {
        return $event->expenseId === $expense->id
            && $event->status === 'pending'
            && $event->broadcastWith() === [
                'id' => $expense->id,
                'status' => 'pending',
            ]
            && $event->broadcastAs() === 'ExpenseUpdated'
            && $event->broadcastOn()[0]->name === 'private-household.expenses';
    });
});

test('status changes broadcast expense updates', function (): void {
    $expense = Expense::factory()->create([
        'status' => 'pending',
        'source' => 'whatsapp',
        'image_path' => null,
    ]);

    Event::fake([ExpenseUpdated::class]);

    $expense->update(['status' => 'parsed']);

    Event::assertDispatched(ExpenseUpdated::class, function (ExpenseUpdated $event) use ($expense): bool {
        return $event->expenseId === $expense->id
            && $event->status === 'parsed';
    });
});

test('important expense field changes broadcast updates', function (string $attribute, mixed $value): void {
    $expense = Expense::factory()->create([
        'status' => 'parsed',
        'source' => 'whatsapp',
        'image_path' => null,
        'merchant_name' => 'Original Merchant',
        'total_amount' => 10.00,
    ]);

    if ($attribute === 'payment_method_id') {
        $value = PaymentMethod::factory()->create()->id;
    }

    Event::fake([ExpenseUpdated::class]);

    $expense->update([$attribute => $value]);

    Event::assertDispatched(ExpenseUpdated::class, function (ExpenseUpdated $event) use ($expense): bool {
        return $event->expenseId === $expense->id
            && $event->status === 'parsed'
            && $event->broadcastWith() === [
                'id' => $expense->id,
                'status' => 'parsed',
            ];
    });
})->with([
    'merchant' => ['merchant_name', 'Renamed Merchant'],
    'total amount' => ['total_amount', 42.50],
    'payment method' => ['payment_method_id', null],
    'source' => ['source', 'manual'],
]);

test('soft deleting and restoring an expense broadcasts updates', function (): void {
    $expense = Expense::factory()->create([
        'status' => 'parsed',
        'source' => 'whatsapp',
        'image_path' => null,
    ]);

    Event::fake([ExpenseUpdated::class]);

    $expense->delete();

    Event::assertDispatched(ExpenseUpdated::class, function (ExpenseUpdated $event) use ($expense): bool {
        return $event->expenseId === $expense->id
            && $event->status === 'parsed';
    });

    Event::fake([ExpenseUpdated::class]);

    $expense->restore();

    Event::assertDispatched(ExpenseUpdated::class, function (ExpenseUpdated $event) use ($expense): bool {
        return $event->expenseId === $expense->id
            && $event->status === 'parsed';
    });
});

test('notes-only expense updates do not broadcast', function (): void {
    $expense = Expense::factory()->create([
        'status' => 'parsed',
        'source' => 'whatsapp',
        'image_path' => null,
        'notes' => null,
    ]);

    Event::fake([ExpenseUpdated::class]);

    $expense->update(['notes' => 'Private reminder']);

    Event::assertNotDispatched(ExpenseUpdated::class);
});

test('expense item label and amount changes broadcast parent updates', function (): void {
    $expense = Expense::factory()->create([
        'status' => 'reviewed',
        'source' => 'manual',
    ]);
    $item = ExpenseItem::factory()->for($expense)->create([
        'line_total' => 5.00,
    ]);
    $newLabel = Label::factory()->create();

    Event::fake([ExpenseUpdated::class]);

    $item->update(['label_id' => $newLabel->id]);

    Event::assertDispatched(ExpenseUpdated::class, function (ExpenseUpdated $event) use ($expense): bool {
        return $event->expenseId === $expense->id
            && $event->status === 'reviewed';
    });

    Event::fake([ExpenseUpdated::class]);

    $item->update(['line_total' => 9.75]);

    Event::assertDispatched(ExpenseUpdated::class, function (ExpenseUpdated $event) use ($expense): bool {
        return $event->expenseId === $expense->id
            && $event->status === 'reviewed';
    });
});

test('expense item description changes do not broadcast', function (): void {
    $expense = Expense::factory()->create([
        'status' => 'reviewed',
        'source' => 'manual',
    ]);
    $item = ExpenseItem::factory()->for($expense)->create([
        'description' => 'Original line',
    ]);

    Event::fake([ExpenseUpdated::class]);

    $item->update(['description' => 'Renamed line']);

    Event::assertNotDispatched(ExpenseUpdated::class);
});

test('expenses list listens for echo expense updates without polling', function (): void {
    $pending = Expense::factory()->create([
        'status' => 'pending',
        'source' => 'whatsapp',
        'image_path' => null,
    ]);

    $component = Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('wire:poll.10s.visible')
        ->assertSeeHtml('tido-expense-status-pending')
        ->assertCanSeeTableRecords([$pending]);

    expect($component->instance()->getListeners())
        ->toHaveKey('echo-private:household.expenses,.ExpenseUpdated');
});
