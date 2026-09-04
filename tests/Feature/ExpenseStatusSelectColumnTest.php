<?php

declare(strict_types=1);

use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('primary user can update expense status inline and receives from-to notification', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    Expense::unsetEventDispatcher();

    $expense = Expense::factory()->create([
        'status' => 'parsed',
        'family_member_id' => null,
    ]);

    Expense::setEventDispatcher(app('events'));

    $this->actingAs($user);

    Livewire::test(ListExpenses::class)
        ->call('updateTableColumnState', 'status', (string) $expense->getKey(), 'reviewed')
        ->assertNotified(
            Notification::make()
                ->title('Status Updated')
                ->body("Expense ID {$expense->getKey()}'s status changed from Parsed by AI to Reviewed.")
                ->success(),
        );

    expect($expense->fresh()->status)->toBe('reviewed');
});

test('family member cannot update status inline on a non-owned expense', function (): void {
    Queue::fake();

    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60111111111',
    ]);
    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    Expense::unsetEventDispatcher();

    $primaryExpense = Expense::factory()->create([
        'status' => 'parsed',
        'family_member_id' => null,
    ]);

    Expense::setEventDispatcher(app('events'));

    $this->actingAs($user);

    Livewire::test(ListExpenses::class)
        ->call('updateTableColumnState', 'status', (string) $primaryExpense->getKey(), 'reviewed')
        ->assertNotNotified('Status Updated');

    expect($primaryExpense->fresh()->status)->toBe('parsed');
});

test('family member can update status inline on an owned expense', function (): void {
    Queue::fake();

    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60133333333',
    ]);
    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    Expense::unsetEventDispatcher();

    $ownExpense = Expense::factory()->create([
        'status' => 'parsed',
        'family_member_id' => $member->id,
    ]);

    Expense::setEventDispatcher(app('events'));

    $this->actingAs($user);

    Livewire::test(ListExpenses::class)
        ->call('updateTableColumnState', 'status', (string) $ownExpense->getKey(), 'reviewed')
        ->assertNotified(
            Notification::make()
                ->title('Status Updated')
                ->body("Expense ID {$ownExpense->getKey()}'s status changed from Parsed by AI to Reviewed.")
                ->success(),
        );

    expect($ownExpense->fresh()->status)->toBe('reviewed');
});
