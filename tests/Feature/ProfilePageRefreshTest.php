<?php

declare(strict_types=1);

use App\Enums\UserDateFormat;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('changing regional preference fields reloads the page after save', function (string $field, mixed $value): void {
    $user = User::factory()->create([
        'timezone' => 'Asia/Kuala_Lumpur',
        'locale' => 'en',
        'date_format' => UserDateFormat::DmySlash->value,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set("data.{$field}", $value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertJs('window.location.reload()');
})->with([
    'timezone' => ['timezone', 'Asia/Singapore'],
    'date format' => ['date_format', UserDateFormat::Iso->value],
]);

test('changing mobile nav reloads the page after save', function (): void {
    $user = User::factory()->create([
        'mobile_nav_enabled' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.mobile_nav_enabled', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertJs('window.location.reload()');
});

test('toggling mobile nav without save does not apply client preference', function (): void {
    $user = User::factory()->create([
        'mobile_nav_enabled' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.mobile_nav_enabled', true)
        ->assertNoJs();
});

test('changing reduce motion reloads the page after save', function (): void {
    $user = User::factory()->create([
        'reduce_motion' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.reduce_motion', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertJs('window.location.reload()');
});

test('changing stylized background reloads the page after save', function (): void {
    $user = User::factory()->create([
        'stylized_background_enabled' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.stylized_background_enabled', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertJs('window.location.reload()');
});

test('saving unrelated profile fields does not reload the page', function (): void {
    $user = User::factory()->create([
        'display_name' => 'Original',
        'notify_profile_updates' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.display_name', 'Updated Display')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNoJs();
});
