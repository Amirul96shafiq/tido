<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('guest can open login page', function (): void {
    $this->get('/admin/login')->assertSuccessful();
});

test('signed in primary user visiting login page is redirected to dashboard', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/login')
        ->assertRedirect('/admin');
});

test('signed in family member visiting login page is redirected to dashboard', function (): void {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($user)
        ->get('/admin/login')
        ->assertRedirect('/admin');
});

test('signed in user visiting forgot password page is redirected to dashboard', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/password-reset/request')
        ->assertRedirect('/admin');
});

test('signed in user visiting reset password page is redirected to dashboard', function (): void {
    $user = User::factory()->create();

    $url = URL::temporarySignedRoute(
        'filament.admin.auth.password-reset.reset',
        now()->addHour(),
        [
            'email' => $user->email,
            'token' => 'test-token',
        ],
    );

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect('/admin');
});
