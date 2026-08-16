<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Support\RestoreToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timestamp = now()->timezone('Asia/Kuala_Lumpur')->format('Y-m-d-His');
        $filename = 'tido-app-local-'.$timestamp.'-manual.zip';

        return [
            'type' => BackupType::Manual,
            'disk' => 'local',
            'path' => config('backup.backup.name', 'laravel-backup').'/'.$filename,
            'filename' => $filename,
            'size_bytes' => fake()->numberBetween(1024, 5_000_000),
            'created_by' => User::factory(),
            'restore_token_hash' => null,
            'restore_token_lookup' => null,
            'content_sha256' => null,
            'manifest_hmac' => null,
        ];
    }

    public function withRestoreToken(string $plainToken = 'aabbccddeeff0011.11223344556677889900aabbccddeeff'): static
    {
        return $this->state(function (array $attributes) use ($plainToken): array {
            $parsed = RestoreToken::parse($plainToken);

            if ($parsed === null) {
                return [
                    'restore_token_hash' => Hash::make($plainToken),
                    'restore_token_lookup' => null,
                ];
            }

            return [
                'restore_token_lookup' => $parsed['selector'],
                'restore_token_hash' => Hash::make($plainToken),
            ];
        });
    }

    public function auto(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => BackupType::Auto,
        ]);
    }
}
