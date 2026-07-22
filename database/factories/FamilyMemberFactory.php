<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FamilyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    protected $model = FamilyMember::class;

    public function definition(): array
    {
        $local = $this->faker->unique()->numerify('1########');

        return [
            'name' => $this->faker->name(),
            'phone' => '60'.$local,
            'allowlist_enabled' => true,
        ];
    }

    public function allowlisted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'allowlist_enabled' => true,
        ]);
    }

    public function notAllowlisted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'allowlist_enabled' => false,
        ]);
    }
}
