<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\Recurring;
use App\Support\FieldCharacterLimits;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recurring>
 */
class RecurringFactory extends Factory
{
    protected $model = Recurring::class;

    public function definition(): array
    {
        $title = $this->faker->words(2, true);

        return [
            'title' => FieldCharacterLimits::truncate(ucfirst($title), FieldCharacterLimits::RECURRING_TITLE),
            'notes' => null,
            'type' => RecurringType::Subscription,
            'label_id' => Label::factory(),
            'family_member_id' => null,
            'is_shared' => false,
            'expected_amount' => $this->faker->randomFloat(2, 20, 500),
            'goal_target_amount' => null,
            'frequency' => RecurringFrequency::Repeating,
            'interval_months' => 1,
            'anchor_day' => $this->faker->numberBetween(1, 28),
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => null,
            'next_due_on' => now()->startOfMonth()->day(min(28, (int) now()->day))->toDateString(),
            'instalment_total' => null,
            'instalment_remaining' => null,
            'merchant_aliases' => [$title],
            'notify_filament' => true,
            'notify_whatsapp' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function shared(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_shared' => true,
        ]);
    }

    public function forFamilyMember(?FamilyMember $familyMember = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'family_member_id' => $familyMember?->id ?? FamilyMember::factory(),
            'is_shared' => false,
        ]);
    }

    public function once(): static
    {
        return $this->state(fn (array $attributes): array => [
            'frequency' => RecurringFrequency::Once,
            'interval_months' => null,
        ]);
    }

    public function withGoal(float $target, float $monthly): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => RecurringType::TransferInvestment,
            'goal_target_amount' => $target,
            'expected_amount' => $monthly,
            'instalment_total' => null,
            'instalment_remaining' => null,
        ]);
    }
}
