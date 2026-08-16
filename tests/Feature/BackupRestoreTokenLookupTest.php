<?php

declare(strict_types=1);

use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use App\Support\RestoreToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    config([
        'backup.backup.name' => 'tido',
        'backup.backup.destination.disks' => ['local'],
        'backup.backup.restore.max_upload_kilobytes' => 51200,
        'backup.backup.restore.per_ip_attempts_per_minute' => 5,
        'backup.backup.restore.global_attempts_per_minute' => 10,
    ]);

    RateLimiter::clear('guest-restore:ip:127.0.0.1');
    RateLimiter::clear('guest-restore:global');
});

/**
 * @return non-empty-string
 */
function sec008Token(string $selector, string $secret): string
{
    return $selector.'.'.$secret;
}

function sec008TinyZip(): string
{
    $zipPath = storage_path('app/backup-temp/'.uniqid('sec008_', true).'.zip');
    File::ensureDirectoryExists(dirname($zipPath));

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('database.sqlite', 'sqlite');
    $zip->close();

    return $zipPath;
}

test('keyed lookup finds the matching catalog row without scanning the catalog', function () {
    $matchingToken = sec008Token('aabbccddeeff0011', '11223344556677889900aabbccddeeff');

    for ($index = 0; $index < 15; $index++) {
        Backup::factory()->withRestoreToken(sec008Token(
            str_pad(dechex($index + 1), 16, '0', STR_PAD_LEFT),
            str_pad(dechex($index + 100), 32, '0', STR_PAD_LEFT),
        ))->create([
            'created_by' => null,
            'path' => 'tido/sec008-other-'.$index.'.zip',
            'filename' => 'sec008-other-'.$index.'.zip',
        ]);
    }

    $matching = Backup::factory()->withRestoreToken($matchingToken)->create([
        'created_by' => null,
        'path' => 'tido/sec008-match.zip',
        'filename' => 'sec008-match.zip',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $found = app(BackupService::class)->findBackupByRestoreToken($matchingToken);

    expect($found)->not->toBeNull()
        ->and($found?->is($matching))->toBeTrue();

    $selectQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select'));

    expect($selectQueries)->toHaveCount(1)
        ->and(strtolower($selectQueries->first()['query']))->toContain('restore_token_lookup');
});

test('unknown selector and malformed tokens reject without logging the token', function () {
    expect(User::query()->exists())->toBeFalse();

    Backup::factory()->withRestoreToken(sec008Token(
        'aabbccddeeff0011',
        '11223344556677889900aabbccddeeff',
    ))->create([
        'created_by' => null,
        'path' => 'tido/sec008-unknown.zip',
        'filename' => 'sec008-unknown.zip',
    ]);

    $zipPath = sec008TinyZip();
    Event::fake([MessageLogged::class]);

    $unknownToken = sec008Token('ffffffffffffffff', '00112233445566778899aabbccddeeff');

    $this->postJson(route('restore-backup'), [
        'token' => $unknownToken,
        'backup' => new UploadedFile($zipPath, 'backup.zip', 'application/zip', null, true),
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid restore token or backup.',
        ]);

    Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($unknownToken): bool {
        if ($event->level !== 'warning' || $event->message !== 'backup.restore_failed') {
            return false;
        }

        $encoded = json_encode($event->context);

        return ($event->context['outcome'] ?? null) === 'invalid_token'
            && is_string($encoded)
            && ! str_contains($encoded, $unknownToken)
            && ! array_key_exists('token', $event->context);
    });

    $this->postJson(route('restore-backup'), [
        'token' => 'not-a-valid-token',
        'backup' => new UploadedFile($zipPath, 'backup.zip', 'application/zip', null, true),
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid restore token or backup.',
        ]);

    File::delete($zipPath);
});

test('hash-only legacy catalog rows fail closed', function () {
    $legacyToken = 'legacy-plain-token-without-selector';

    Backup::factory()->create([
        'created_by' => null,
        'path' => 'tido/sec008-legacy.zip',
        'filename' => 'sec008-legacy.zip',
        'restore_token_hash' => Hash::make($legacyToken),
        'restore_token_lookup' => null,
    ]);

    expect(app(BackupService::class)->findBackupByRestoreToken($legacyToken))->toBeNull();
});

test('consume clears lookup and hash and reissue writes a new selector', function () {
    $token = sec008Token('aabbccddeeff0011', '11223344556677889900aabbccddeeff');
    $zipPath = sec008TinyZip();

    $backup = Backup::factory()->withRestoreToken($token)->create([
        'created_by' => null,
        'disk' => 'local',
        'path' => 'tido/sec008-reissue.zip',
        'filename' => 'sec008-reissue.zip',
    ]);
    Storage::disk('local')->put($backup->path, File::get($zipPath));

    $service = app(BackupService::class);
    $service->consumeRestoreToken($backup->fresh());

    expect($backup->fresh()->restore_token_hash)->toBeNull()
        ->and($backup->fresh()->restore_token_lookup)->toBeNull();

    $reissued = $service->issueRestoreToken($backup->fresh());
    $parsed = RestoreToken::parse($reissued);

    expect($parsed)->not->toBeNull()
        ->and($backup->fresh()->restore_token_lookup)->toBe($parsed['selector'])
        ->and($backup->fresh()->restore_token_hash)->not->toBeNull()
        ->and($service->assertRestoreToken($backup->fresh(), $reissued))->toBeTrue()
        ->and($service->findBackupByRestoreToken($token))->toBeNull()
        ->and($service->findBackupByRestoreToken($reissued)?->is($backup))->toBeTrue();

    File::delete($zipPath);
});

test('guest restore enforces the per-ip rate limit without leaking the token', function () {
    expect(User::query()->exists())->toBeFalse();

    config([
        'backup.backup.restore.per_ip_attempts_per_minute' => 5,
        'backup.backup.restore.global_attempts_per_minute' => 100,
    ]);

    $zipPath = sec008TinyZip();
    $token = sec008Token('aabbccddeeff0011', '11223344556677889900aabbccddeeff');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('restore-backup'), [
            'token' => $token,
            'backup' => new UploadedFile($zipPath, 'backup.zip', 'application/zip', null, true),
        ])->assertStatus(422);
    }

    $response = $this->postJson(route('restore-backup'), [
        'token' => $token,
        'backup' => new UploadedFile($zipPath, 'backup.zip', 'application/zip', null, true),
    ]);

    $response->assertStatus(429)
        ->assertJson([
            'success' => false,
            'message' => 'Too many restore attempts. Try again later.',
        ]);

    expect(json_encode($response->json()))->not->toContain($token);

    File::delete($zipPath);
});

test('guest restore enforces the global rate limit across ips', function () {
    expect(User::query()->exists())->toBeFalse();

    config([
        'backup.backup.restore.per_ip_attempts_per_minute' => 100,
        'backup.backup.restore.global_attempts_per_minute' => 10,
    ]);

    RateLimiter::clear('guest-restore:global');

    $zipPath = sec008TinyZip();
    $token = sec008Token('aabbccddeeff0011', '11223344556677889900aabbccddeeff');

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $ip = '10.8.0.'.($attempt + 1);
        RateLimiter::clear('guest-restore:ip:'.$ip);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(route('restore-backup'), [
                'token' => $token,
                'backup' => new UploadedFile($zipPath, 'backup.zip', 'application/zip', null, true),
            ])
            ->assertStatus(422);
    }

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.8.0.99'])
        ->postJson(route('restore-backup'), [
            'token' => $token,
            'backup' => new UploadedFile($zipPath, 'backup.zip', 'application/zip', null, true),
        ]);

    $response->assertStatus(429)
        ->assertJson([
            'success' => false,
            'message' => 'Too many restore attempts. Try again later.',
        ]);

    expect(json_encode($response->json()))->not->toContain($token);

    File::delete($zipPath);
});

test('issue restore token omits the plain token from structured logs', function () {
    $user = User::factory()->create();
    $zipPath = sec008TinyZip();

    $backup = Backup::factory()->create([
        'created_by' => $user->getKey(),
        'disk' => 'local',
        'path' => 'tido/sec008-create-log.zip',
        'filename' => 'sec008-create-log.zip',
        'restore_token_hash' => null,
        'restore_token_lookup' => null,
    ]);
    Storage::disk('local')->put($backup->path, File::get($zipPath));

    Event::fake([MessageLogged::class]);

    $plainToken = app(BackupService::class)->issueRestoreToken($backup);

    Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $event) use ($plainToken): bool {
        $encoded = json_encode($event->context);

        return is_string($encoded) && str_contains($encoded, $plainToken);
    });

    expect(RestoreToken::isValidFormat($plainToken))->toBeTrue()
        ->and($backup->fresh()->restore_token_lookup)->not->toBeNull();

    File::delete($zipPath);
});
