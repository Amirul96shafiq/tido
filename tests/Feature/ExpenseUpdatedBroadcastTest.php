<?php

declare(strict_types=1);

use App\Events\ExpenseUpdated;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
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

test('unrelated expense updates do not broadcast', function (): void {
    $expense = Expense::factory()->create([
        'status' => 'parsed',
        'source' => 'whatsapp',
        'image_path' => null,
        'merchant_name' => 'Original Merchant',
    ]);

    Event::fake([ExpenseUpdated::class]);

    $expense->update(['merchant_name' => 'Renamed Merchant']);

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
