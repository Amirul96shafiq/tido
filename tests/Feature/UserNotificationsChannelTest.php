<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Null/log drivers skip channel auth. Pusher signs locally without a live websocket.
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'tido-test-key',
        'broadcasting.connections.pusher.secret' => 'tido-test-secret',
        'broadcasting.connections.pusher.app_id' => 'tido-test',
        'broadcasting.connections.pusher.options.cluster' => 'mt1',
        'broadcasting.connections.pusher.options.host' => '127.0.0.1',
        'broadcasting.connections.pusher.options.port' => 443,
        'broadcasting.connections.pusher.options.scheme' => 'https',
    ]);

    app(BroadcastFactory::class)->purge();
    require base_path('routes/channels.php');
});

/**
 * @return array{socket_id: string, channel_name: string}
 */
function userNotificationsAuthPayload(int $userId): array
{
    return [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-App.Models.User.'.$userId,
    ];
}

test('guests cannot subscribe to user notification channels', function (): void {
    $user = User::factory()->create();

    $this->postJson('/broadcasting/auth', userNotificationsAuthPayload($user->id))
        ->assertForbidden();
});

test('users can subscribe to their own notification channel', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', userNotificationsAuthPayload($user->id))
        ->assertSuccessful();
});

test('login-enabled family members can subscribe to their own notification channel', function (): void {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', userNotificationsAuthPayload($user->id))
        ->assertSuccessful();
});

test('users cannot subscribe to another users notification channel', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', userNotificationsAuthPayload($other->id))
        ->assertForbidden();
});
