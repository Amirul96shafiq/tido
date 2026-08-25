<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('profile personalize preferences section renders reduce motion toggle', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('PREFERENCES', false)
        ->assertSee('Reduce Motion', false)
        ->assertSee('Disable count-up, marquee, and other decorative animation.', false)
        ->assertSet('data.reduce_motion', false);
});

test('profile saves the reduce motion preference', function (bool $enabled): void {
    $user = User::factory()->create([
        'reduce_motion' => ! $enabled,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.reduce_motion', $enabled)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->reduce_motion)->toBe($enabled);
})->with([
    'enabled' => true,
    'disabled' => false,
]);

test('changing the reduce motion preference reports the profile change', function (): void {
    $user = User::factory()->create([
        'reduce_motion' => false,
        'notify_profile_updates' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.reduce_motion', true)
        ->call('save')
        ->assertHasNoErrors();

    $notification = $user->fresh()->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['body'])->toContain('Reduce Motion');
});

test('admin panel injects reduce motion script and class when preference is enabled', function (): void {
    $user = User::factory()->create([
        'reduce_motion' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSuccessful()
        ->assertSee('tidoPrefersReducedMotion', false)
        ->assertSee('tidoSetReduceMotion', false)
        ->assertSee("document.documentElement.classList.add('tido-reduce-motion')", false);
});

test('admin panel does not inject reduce motion class when preference is disabled', function (): void {
    $user = User::factory()->create([
        'reduce_motion' => false,
    ]);

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSuccessful()
        ->assertSee('tidoPrefersReducedMotion', false)
        ->assertDontSee("document.documentElement.classList.add('tido-reduce-motion')", false);
});
