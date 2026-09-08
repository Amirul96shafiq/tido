<?php

declare(strict_types=1);

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Filament\Pages\EvolutionApiPage;
use App\Filament\Pages\GoogleOAuthPage;
use App\Filament\Support\DashboardMonthAnalytics;
use App\Models\EvolutionApiSetting;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\GoogleOAuthSetting;
use App\Models\Household;
use App\Models\Label;
use App\Models\ServiceHealthSample;
use App\Models\User;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use App\Support\CurrentHousehold;
use App\Support\HouseholdAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CurrentHousehold::clear();
});

it('stamps factory models onto household one', function (): void {
    expect(Household::query()->whereKey(1)->exists())->toBeTrue();

    $user = User::factory()->create();
    expect($user->household_id)->toBe(1);

    $expense = Expense::factory()->create();
    expect($expense->household_id)->toBe(1);

    $label = Label::factory()->create();
    expect($label->household_id)->toBe(1);
});

it('scopes expenses to the current household only', function (): void {
    $householdOne = Household::query()->findOrFail(1);
    $householdTwo = Household::factory()->create(['name' => 'Other Household']);

    $expenseOne = Expense::factory()->create(['household_id' => $householdOne->id]);
    $expenseTwo = Expense::factory()->create(['household_id' => $householdTwo->id]);

    CurrentHousehold::set($householdOne->id);

    $ids = Expense::query()->pluck('id')->all();

    expect($ids)->toContain($expenseOne->id)
        ->and($ids)->not->toContain($expenseTwo->id);
});

it('does not leak label spending across households', function (): void {
    $householdOne = Household::query()->findOrFail(1);
    $householdTwo = Household::factory()->create(['name' => 'Empty Household']);

    $label = Label::factory()->create(['household_id' => $householdOne->id]);
    $expense = Expense::factory()->create([
        'household_id' => $householdOne->id,
        'status' => 'reviewed',
        'date_time' => now(),
        'currency' => 'MYR',
        'currency_conversion_status' => 'not_required',
    ]);
    ExpenseItem::factory()->create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'line_total' => 50,
    ]);

    CurrentHousehold::set($householdTwo->id);
    DashboardMonthAnalytics::flushInstances();

    $bounds = [
        'start' => now()->startOfMonth()->startOfDay(),
        'end' => now()->endOfMonth()->endOfDay(),
        'previous_start' => now()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
        'previous_end' => now()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
    ];

    expect(DashboardMonthAnalytics::for($bounds)->spentByLabel())->toBeEmpty();
});

it('denies mutate across households', function (): void {
    $householdTwo = Household::factory()->create();
    $user = User::factory()->create(['household_id' => 1]);
    $foreignExpense = Expense::factory()->create(['household_id' => $householdTwo->id]);

    $this->actingAs($user);

    expect(HouseholdAccess::canMutateExpense($foreignExpense))->toBeFalse();
});

it('hides evolution Active when household whatsapp is off', function (): void {
    $householdTwo = Household::factory()->create(['name' => 'No WhatsApp Household']);
    $user = User::factory()->create(['household_id' => $householdTwo->id]);

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Evolution,
        'status' => ServiceHealthStatus::Operational,
        'checked_at' => now(),
        'latency_ms' => 18,
        'meta' => ['message' => 'WhatsApp session is connected.'],
    ]);

    $this->actingAs($user);
    CurrentHousehold::set($householdTwo->id);

    expect(EvolutionApiSetting::isWhatsappEnabledForHousehold($householdTwo->id))->toBeFalse()
        ->and(EvolutionApiPage::getNavigationBadge())->toBeNull();
});

it('shows google Active only when platform ready and primary linked', function (): void {
    $householdTwo = Household::factory()->create();
    $user = User::factory()->create([
        'household_id' => $householdTwo->id,
        'google_id' => null,
    ]);

    GoogleOAuthSetting::platform()->update([
        'client_id' => 'test-google-client-id',
        'client_secret' => 'test-google-client-secret',
        'enabled' => true,
        'setup_completed_at' => now(),
    ]);
    GoogleOAuthSettings::platform()->forgetCache();

    $this->actingAs($user);
    CurrentHousehold::set($householdTwo->id);

    expect(GoogleOAuthPage::getNavigationBadge())->toBeNull();

    $user->forceFill([
        'google_id' => 'google-sub-hh2',
        'google_linked_at' => now(),
    ])->save();

    expect(GoogleOAuthPage::getNavigationBadge())->toBe('Active');
});

it('shows degraded google status for households that have not linked yet', function (): void {
    $householdTwo = Household::factory()->create(['name' => 'Other Household']);
    $user = User::factory()->create([
        'household_id' => $householdTwo->id,
        'google_id' => null,
    ]);

    GoogleOAuthSetting::platform()->update([
        'client_id' => 'test-google-client-id',
        'client_secret' => 'test-google-client-secret',
        'enabled' => true,
        'setup_completed_at' => now(),
    ]);
    GoogleOAuthSettings::platform()->forgetCache();

    $this->actingAs($user);
    CurrentHousehold::set($householdTwo->id);

    Livewire::test(GoogleOAuthPage::class)
        ->assertSuccessful()
        ->assertSet('connectionStatus', 'degraded')
        ->assertSee('Link this Primary')
        ->assertActionHidden('configureSetup');
});
