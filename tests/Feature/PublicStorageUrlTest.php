<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('public disk urls are relative so FilePond stays same-origin across hosts', function () {
    $url = Storage::disk('public')->url('avatars/example.png');

    expect($url)->toStartWith('/storage/')
        ->and($url)->not->toContain('://');
});

test('user filament avatar url stays relative when avatar is set', function () {
    $user = User::factory()->make([
        'avatar_url' => 'avatars/example.png',
    ]);

    expect($user->getFilamentAvatarUrl())->toBe('/storage/avatars/example.png');
});
