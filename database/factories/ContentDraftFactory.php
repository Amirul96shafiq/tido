<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContentDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentDraft>
 */
class ContentDraftFactory extends Factory
{
    protected $model = ContentDraft::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => 'invoice-create',
            'payload' => [
                'merchant_name' => $this->faker->company(),
                'notes' => $this->faker->sentence(),
            ],
        ];
    }
}
