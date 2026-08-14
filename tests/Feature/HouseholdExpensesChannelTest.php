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
function householdExpensesAuthPayload(): array
{
    return [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-household.expenses',
    ];
}

test('guests cannot subscribe to household expenses', function (): void {
    $this->postJson('/broadcasting/auth', householdExpensesAuthPayload())
        ->assertForbidden();
});

test('primary users can subscribe to household expenses', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson('/broadcasting/auth', householdExpensesAuthPayload())
        ->assertSuccessful();
});

test('login-enabled family members can subscribe to household expenses', function (): void {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', householdExpensesAuthPayload())
        ->assertSuccessful();
});

test('users without panel access cannot subscribe to household expenses', function (): void {
    $this->actingAs(User::factory()->familyMember()->create())
        ->postJson('/broadcasting/auth', householdExpensesAuthPayload())
        ->assertForbidden();
});
