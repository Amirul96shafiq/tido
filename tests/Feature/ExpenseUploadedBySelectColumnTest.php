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

test('primary user can update uploaded by inline and receives from-to notification', function (): void {
    Queue::fake();

    $user = User::factory()->create([
        'name' => 'Primary Account Owner',
        'display_name' => 'admin',
    ]);
    $member = FamilyMember::factory()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'nor',
    ]);

    Expense::unsetEventDispatcher();

    $expense = Expense::factory()->create([
        'family_member_id' => null,
    ]);

    Expense::setEventDispatcher(app('events'));

    $this->actingAs($user);

    Livewire::test(ListExpenses::class)
        ->assertSeeHtml('fi-ta-col-lightweight-select tido-expense-table-select px-3 py-4')
        ->call('updateExpenseInlineSelect', 'family_member_id', (string) $expense->getKey(), (string) $member->getKey())
        ->assertNotified(
            Notification::make()
                ->title('Uploaded By Updated')
                ->body("Expense ID {$expense->getKey()}'s uploaded by changed from admin to nor.")
                ->success(),
        );

    expect($expense->fresh()->family_member_id)->toBe($member->id);
});

test('family member cannot update uploaded by inline on an owned expense', function (): void {
    Queue::fake();

    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60144444444',
        'display_name' => 'along',
    ]);
    $other = FamilyMember::factory()->create([
        'display_name' => 'other',
    ]);
    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    Expense::unsetEventDispatcher();

    $ownExpense = Expense::factory()->create([
        'family_member_id' => $member->id,
    ]);

    Expense::setEventDispatcher(app('events'));

    $this->actingAs($user);

    Livewire::test(ListExpenses::class)
        ->call('updateExpenseInlineSelect', 'family_member_id', (string) $ownExpense->getKey(), (string) $other->getKey())
        ->assertNotNotified('Uploaded By Updated');

    expect($ownExpense->fresh()->family_member_id)->toBe($member->id);
});
