<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FamilyRelationship;
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
            'display_name' => $this->faker->optional()->firstName(),
            'phone' => '60'.$local,
            'email' => $this->faker->optional()->safeEmail(),
            'relationship' => $this->faker->optional()->randomElement(
                array_filter(
                    FamilyRelationship::cases(),
                    fn (FamilyRelationship $case): bool => $case !== FamilyRelationship::Other,
                ),
            ),
            'relationship_other' => null,
            'date_of_birth' => $this->faker->optional()->date(),
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

    public function otherRelationship(string $custom): static
    {
        return $this->state(fn (array $attributes): array => [
            'relationship' => FamilyRelationship::Other,
            'relationship_other' => $custom,
        ]);
    }
}
