<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('profile saves the stylized background preference', function (bool $enabled): void {
    $user = User::factory()->create([
        'stylized_background_enabled' => ! $enabled,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.stylized_background_enabled', $enabled)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->stylized_background_enabled)->toBe($enabled);
})->with([
    'enabled' => true,
    'disabled' => false,
]);

test('changing the stylized background preference reports the profile change', function (): void {
    $user = User::factory()->create([
        'stylized_background_enabled' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.stylized_background_enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    $notification = $user->fresh()->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['body'])->toContain('Stylized background');
});

test('background preview reflects the current toggle state', function (): void {
    $user = User::factory()->create([
        'stylized_background_enabled' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('bg-disabled-l-v2.png')
        ->set('data.stylized_background_enabled', true)
        ->assertSee('bg-enabled-l-v2.png');
});
