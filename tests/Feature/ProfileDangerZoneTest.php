<?php

declare(strict_types=1);

use App\Enums\BackupType;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\Backup;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\Label;
use App\Models\User;
use App\Services\AccountDangerZoneService;
use App\Services\BackupService;
use Database\Seeders\LabelSeeder;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(LabelSeeder::class);

    $this->user = User::factory()->create([
        'email' => 'danger-zone@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($this->user);

    $this->mock(BackupService::class, function ($mock): void {
        $mock->shouldReceive('create')
            ->andReturnUsing(function (BackupType $type, ?User $createdBy): Backup {
                return Backup::factory()->create([
                    'type' => $type,
                    'created_by' => $createdBy?->getKey(),
                ]);
            });
    });
});

function formAction(string $name): TestAction
{
    return TestAction::make($name)->schemaComponent('form-actions', schema: 'content');
}

test('reset data cta stays hidden until exact phrase and password are filled', function () {
    $component = Livewire::test(EditProfile::class)
        ->set('data.enable_reset_data', true)
        ->set('data.reset_confirmation_phrase', 'WRONG PHRASE')
        ->set('data.reset_confirmation_password', 'password')
        ->assertActionVisible(formAction('save'));

    expect(fn () => $component->callAction(formAction('resetData')))
        ->toThrow(ActionNotResolvableException::class);
});

test('exact phrase and password show reset cta and hide save', function () {
    $component = Livewire::test(EditProfile::class)
        ->set('data.enable_reset_data', true)
        ->set('data.reset_confirmation_phrase', 'CONFIRM RESET DATA')
        ->set('data.reset_confirmation_password', 'password')
        ->assertActionVisible(formAction('resetData'));

    expect(fn () => $component->callAction(formAction('save')))
        ->toThrow(ActionNotResolvableException::class);
});

test('wrong password on reset data shows notification and does not wipe', function () {
    Invoice::factory()->create();
    $initialInvoiceCount = Invoice::query()->count();

    Livewire::test(EditProfile::class)
        ->set('data.enable_reset_data', true)
        ->set('data.reset_confirmation_phrase', 'CONFIRM RESET DATA')
        ->set('data.reset_confirmation_password', 'wrong-password')
        ->callAction(formAction('resetData'))
        ->assertNotified('Incorrect password');

    expect(Invoice::query()->count())->toBe($initialInvoiceCount);

    $this->assertAuthenticatedAs($this->user);
});

test('reset data wipes domain data keeps user system labels and backups', function () {
    Invoice::factory(3)->create();
    Budget::factory(2)->create();

    $systemLabelCount = Label::query()->where('is_system', true)->count();

    $userLabel = Label::factory()->create([
        'name' => 'Custom Label',
        'is_system' => false,
    ]);

    $backup = Backup::factory()->create([
        'type' => BackupType::Manual,
        'created_by' => $this->user->getKey(),
    ]);

    Livewire::test(EditProfile::class)
        ->set('data.enable_reset_data', true)
        ->set('data.reset_confirmation_phrase', 'CONFIRM RESET DATA')
        ->set('data.reset_confirmation_password', 'password')
        ->callAction(formAction('resetData'))
        ->callMountedAction()
        ->assertRedirect('/admin/login');

    expect(Invoice::query()->count())->toBe(0)
        ->and(Budget::query()->count())->toBe(0)
        ->and(Label::query()->whereKey($userLabel->getKey())->exists())->toBeFalse()
        ->and(Label::query()->where('is_system', true)->count())->toBe($systemLabelCount)
        ->and(Backup::query()->whereKey($backup->getKey())->exists())->toBeTrue()
        ->and(Backup::query()->where('type', BackupType::Auto)->exists())->toBeTrue()
        ->and(User::query()->whereKey($this->user->getKey())->exists())->toBeTrue();

    $this->assertGuest();
});

test('delete account action delegates to danger zone service and logs out', function () {
    $this->mock(AccountDangerZoneService::class, function ($mock): void {
        $mock->shouldReceive('deleteAccount')
            ->once()
            ->withArgs(fn (User $user): bool => $user->is($this->user));
    });

    Livewire::test(EditProfile::class)
        ->set('data.enable_delete_account', true)
        ->set('data.delete_confirmation_phrase', 'CONFIRM DELETE ACCOUNT')
        ->set('data.delete_confirmation_password', 'password')
        ->callAction(formAction('deleteAccount'))
        ->callMountedAction()
        ->assertRedirect('/admin/login');

    $this->assertGuest();
});

test('disabling reset data toggle clears confirmation fields and hides cta', function () {
    $component = Livewire::test(EditProfile::class)
        ->set('data.enable_reset_data', true)
        ->set('data.reset_confirmation_phrase', 'CONFIRM RESET DATA')
        ->set('data.reset_confirmation_password', 'password')
        ->assertActionVisible(formAction('resetData'))
        ->set('data.enable_reset_data', false);

    expect($component->get('data.reset_confirmation_phrase'))->toBeNull()
        ->and($component->get('data.reset_confirmation_password'))->toBeNull();

    expect(fn () => $component->callAction(formAction('resetData')))
        ->toThrow(ActionNotResolvableException::class);

    $component->assertActionVisible(formAction('save'));
});

test('disabling delete account toggle clears confirmation fields and hides cta', function () {
    $component = Livewire::test(EditProfile::class)
        ->set('data.enable_delete_account', true)
        ->set('data.delete_confirmation_phrase', 'CONFIRM DELETE ACCOUNT')
        ->set('data.delete_confirmation_password', 'password')
        ->assertActionVisible(formAction('deleteAccount'))
        ->set('data.enable_delete_account', false);

    expect($component->get('data.delete_confirmation_phrase'))->toBeNull()
        ->and($component->get('data.delete_confirmation_password'))->toBeNull();

    expect(fn () => $component->callAction(formAction('deleteAccount')))
        ->toThrow(ActionNotResolvableException::class);

    $component->assertActionVisible(formAction('save'));
});

test('account danger zone service wipe preserves backups table', function () {
    Invoice::factory()->create();

    $backup = Backup::factory()->create();

    app(AccountDangerZoneService::class)->wipeSharedAppData($this->user);

    expect(Invoice::query()->count())->toBe(0)
        ->and(Backup::query()->whereKey($backup->getKey())->exists())->toBeTrue();
});

test('account danger zone service delete account removes user', function () {
    $user = User::factory()->create();

    Backup::factory()->create([
        'created_by' => $user->getKey(),
    ]);

    app(AccountDangerZoneService::class)->deleteAccount($user);

    expect(User::query()->whereKey($user->getKey())->exists())->toBeFalse();
});
