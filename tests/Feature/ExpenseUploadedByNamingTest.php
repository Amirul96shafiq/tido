<?php

declare(strict_types=1);

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Models\FamilyMember;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create([
        'name' => 'Primary Account Owner',
        'display_name' => 'admin',
    ]));
});

test('invoice create form uses uploaded by wording', function () {
    FamilyMember::factory()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'nor',
    ]);

    Livewire::test(CreateExpense::class)
        ->assertSuccessful()
        ->assertSee('Uploaded By')
        ->assertSee('admin')
        ->assertSee('nor')
        ->assertDontSee('Nor Ezrieana Harun');
});

test('invoice list shows uploaded by usernames', function () {
    $member = FamilyMember::factory()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'nor',
    ]);

    Expense::unsetEventDispatcher();

    Expense::factory()->create([
        'merchant_name' => 'Primary Invoice',
        'family_member_id' => null,
    ]);

    Expense::factory()->create([
        'merchant_name' => 'Family Invoice',
        'family_member_id' => $member->id,
    ]);

    $this->get(ExpenseResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('Uploaded By')
        ->assertSee('admin')
        ->assertSee('nor')
        ->assertDontSee('Nor Ezrieana Harun');
});
