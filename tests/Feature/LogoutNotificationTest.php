<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\FilamentAuthLogout;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => null,
    ]);
});

test('logging out shows a success toast on the auth page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/admin')
        ->post(route('filament.admin.auth.logout'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();

    Notification::assertNotified('Signed out successfully');
});

test('filament auth logout helper flashes signed out notification', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    FilamentAuthLogout::logoutToLogin();

    $this->assertGuest();

    Notification::assertNotified('Signed out successfully');
});
