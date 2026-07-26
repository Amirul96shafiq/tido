<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('prune seed users keeps primary and deletes others with notifications', function () {
    $primary = User::factory()->create(['email' => 'admin@tido.local']);
    $extra = User::factory()->create(['email' => 'seed@example.com']);

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'Filament\\Notifications\\DatabaseNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $extra->id,
        'data' => json_encode(['title' => 'Seed']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('tido:prune-seed-users', ['--keep' => $primary->id, '--force' => true])
        ->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->value('id'))->toBe($primary->id)
        ->and(DB::table('notifications')->count())->toBe(0);
});

test('prune seed users refuses to run outside local and testing', function () {
    User::factory()->create();
    User::factory()->create();

    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('tido:prune-seed-users', ['--force' => true])
        ->assertFailed();

    expect(User::query()->count())->toBe(2);
});
