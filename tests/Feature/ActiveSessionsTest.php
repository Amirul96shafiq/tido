<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use App\Services\ActiveSessionService;
use App\Support\UserAgentDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function insertActiveSession(User $user, string $id, array $overrides = []): void
{
    DB::table('sessions')->insert(array_merge([
        'id' => $id,
        'user_id' => $user->getKey(),
        'ip_address' => '192.168.1.10',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'payload' => 'test-payload',
        'last_activity' => now()->subMinutes(5)->timestamp,
        'created_at' => now()->subHour()->timestamp,
    ], $overrides));
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('profile exposes active sessions section anchor', function () {
    $html = Livewire::test(EditProfile::class)->html();

    expect($html)->toContain('id="active-sessions"');
});

test('active sessions lists current session as this device', function () {
    $currentSessionId = session()->getId();

    DB::table('sessions')->updateOrInsert(
        ['id' => $currentSessionId],
        [
            'user_id' => $this->user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
            'created_at' => now()->subMinutes(30)->timestamp,
        ],
    );

    Livewire::test(EditProfile::class)
        ->assertSee('Active Sessions')
        ->assertSee('This device')
        ->assertSee('Web')
        ->assertSee('Chrome on Windows');
});

test('active sessions distinguishes multiple web sessions', function () {
    $currentSessionId = session()->getId();

    DB::table('sessions')->updateOrInsert(
        ['id' => $currentSessionId],
        [
            'user_id' => $this->user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
            'created_at' => now()->subMinutes(30)->timestamp,
        ],
    );

    insertActiveSession($this->user, 'firefox-session', [
        'ip_address' => '192.168.1.20',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'created_at' => now()->subHours(2)->timestamp,
    ]);

    Livewire::test(EditProfile::class)
        ->assertSee('Chrome on Windows')
        ->assertSee('Firefox on Windows')
        ->assertSee('192.168.1.20');
});

test('active sessions shows mobile web device class', function () {
    insertActiveSession($this->user, 'mobile-session', [
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        'ip_address' => '10.0.0.5',
    ]);

    Livewire::test(EditProfile::class)
        ->assertSee('Mobile Web')
        ->assertSee('Safari on iOS');
});

test('revoke deletes another session but not the current one', function () {
    $currentSessionId = session()->getId();

    DB::table('sessions')->updateOrInsert(
        ['id' => $currentSessionId],
        [
            'user_id' => $this->user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
            'created_at' => now()->subMinutes(30)->timestamp,
        ],
    );

    insertActiveSession($this->user, 'other-session');

    Livewire::test(EditProfile::class)
        ->call('prepareRevokeSession', 'other-session')
        ->callMountedAction()
        ->assertNotified('Session revoked');

    expect(DB::table('sessions')->where('id', 'other-session')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', $currentSessionId)->exists())->toBeTrue();
});

test('revoke refuses the current session', function () {
    $currentSessionId = session()->getId();

    expect(fn () => app(ActiveSessionService::class)->revoke(
        $this->user,
        $currentSessionId,
        $currentSessionId,
    ))->toThrow(InvalidArgumentException::class);
});

test('revoke cannot delete another users session', function () {
    $otherUser = User::factory()->create();

    insertActiveSession($otherUser, 'foreign-session');

    app(ActiveSessionService::class)->revoke(
        $this->user,
        'foreign-session',
        session()->getId(),
    );

    expect(DB::table('sessions')->where('id', 'foreign-session')->exists())->toBeTrue();
});

test('user agent device parser classifies web and mobile web', function () {
    $web = UserAgentDevice::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    $mobile = UserAgentDevice::parse('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36');

    expect($web->deviceClass)->toBe('Web')
        ->and($web->browser)->toBe('Chrome')
        ->and($mobile->deviceClass)->toBe('Mobile Web')
        ->and($mobile->os)->toBe('Android');
});

test('active sessions revoke button wire click is compiled', function () {
    insertActiveSession($this->user, 'wire-click-session');

    $html = Livewire::test(EditProfile::class)->html();

    expect($html)
        ->toContain('wire:click="prepareRevokeSession(')
        ->not->toContain('prepareRevokeSession(@js');
});

test('stamp created at only fills missing values', function () {
    $sessionId = 'stamp-session';

    DB::table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => $this->user->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'test-payload',
        'last_activity' => now()->timestamp,
        'created_at' => null,
    ]);

    app(ActiveSessionService::class)->stampCreatedAt($sessionId);

    expect(DB::table('sessions')->where('id', $sessionId)->value('created_at'))->not->toBeNull();
});
