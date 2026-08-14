<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContentDraft;
use App\Models\User;
use App\Support\FieldCharacterLimits;
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
            'key' => 'expense-create',
            'payload' => [
                'merchant_name' => FieldCharacterLimits::truncate(
                    $this->faker->company(),
                    FieldCharacterLimits::MERCHANT_NAME,
                ),
                'notes' => FieldCharacterLimits::truncate($this->faker->sentence(), FieldCharacterLimits::NOTES),
            ],
        ];
    }
}
