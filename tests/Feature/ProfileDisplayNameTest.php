<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('profile form labels full name and display name fields', function () {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'display_name' => null,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'name',
            checkComponentUsing: function (TextInput $component): bool {
                expect($component->getLabel())->toBe('Full Name')
                    ->and($component->getPlaceholder())->toBe('Full name');

                return true;
            },
        )
        ->assertSchemaComponentExists(
            'display_name',
            checkComponentUsing: function (TextInput $component): bool {
                expect($component->getLabel())->toBe('Display Name')
                    ->and($component->getPlaceholder())->toBe('Display name');

                return true;
            },
        );
});

test('updating profile saves display name', function () {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'display_name' => null,
        'notify_profile_updates' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.display_name', 'Ada')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->refresh()->display_name)->toBe('Ada');
});

test('updating display name triggers database notification', function () {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'display_name' => null,
        'notify_profile_updates' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.display_name', 'Ada')
        ->call('save')
        ->assertHasNoErrors();

    $notification = $user->notifications()->first();

    expect($user->notifications()->count())->toBe(1)
        ->and($notification->data['body'])->toContain('Display Name');
});
