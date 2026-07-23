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

test('background preview shows real panel art at full height', function (): void {
    $user = User::factory()->create([
        'stylized_background_enabled' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('tido-stylized-preview', false)
        ->assertSee('images/bg-l.png', false)
        ->assertSee('images/bg-d.png', false)
        ->assertSee('tido_dark_logo', false)
        ->assertSee('aspect-ratio: 1919 / 1079', false)
        ->assertSee('Enabled: Stylized Mode', false)
        ->assertSee('Disabled: Focus Mode', false)
        ->assertDontSee('bg-disabled-l-v2.png')
        ->assertDontSee('bg-enabled-l-v2.png');
});
