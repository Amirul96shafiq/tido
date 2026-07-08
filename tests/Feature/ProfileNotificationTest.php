<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('updating profile name triggers database notification', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();
    expect($notification->data['title'])->toBe('Profile Settings Updated');
    expect($notification->data['body'])->toContain('Name');
    expect($notification->data['actions'][0]['url'])->toBe(EditProfile::getUrl());
});

test('updating profile photo triggers database notification', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'avatar_url' => null,
    ]);

    $this->actingAs($user);

    // Use UploadedFile array to satisfy FileUpload component type expectations
    $file = UploadedFile::fake()->image('avatar.jpg');

    Livewire::test(EditProfile::class)
        ->set('data.avatar_url', [$file])
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();
    expect($notification->data['body'])->toContain('Profile photo');
});

test('updating password triggers database notification', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.change_password', true)
        ->set('data.currentPassword', 'password')
        ->set('data.password', 'new-password-123')
        ->set('data.passwordConfirmation', 'new-password-123')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();
    expect($notification->data['body'])->toContain('Password');
});

test('updating email triggers database notification', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.email', 'new@example.com')
        ->set('data.currentPassword', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();
    expect($notification->data['body'])->toContain('Email');
});

test('saving profile without changes does not trigger notification', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.name', 'Original Name')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->notifications()->count())->toBe(0);
});

test('updating phone and preferences persists values', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'phone' => null,
        'timezone' => 'Asia/Kuala_Lumpur',
        'locale' => 'en',
        'date_format' => 'd/m/Y',
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.phone', '+60123456789')
        ->set('data.timezone', 'Asia/Singapore')
        ->set('data.locale', 'ms')
        ->set('data.date_format', 'Y-m-d')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->phone)->toBe('+60123456789')
        ->and($user->timezone)->toBe('Asia/Singapore')
        ->and($user->locale)->toBe('ms')
        ->and($user->date_format)->toBe('Y-m-d');
});

test('updating profile with notify_profile_updates disabled does not trigger notification', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'notify_profile_updates' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('New Name')
        ->and($user->notifications()->count())->toBe(0);
});
