<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
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
        ];
    }

    public function withRestoreToken(string $plainToken = 'test-restore-token'): static
    {
        return $this->state(fn (array $attributes): array => [
            'restore_token_hash' => Hash::make($plainToken),
        ]);
    }

    public function auto(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => BackupType::Auto,
        ]);
    }
}
