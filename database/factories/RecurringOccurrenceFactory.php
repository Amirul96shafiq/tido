<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecurringOccurrenceStatus;
use App\Models\Expense;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringOccurrence>
 */
class RecurringOccurrenceFactory extends Factory
{
    protected $model = RecurringOccurrence::class;

    public function definition(): array
    {
        $dueOn = now()->startOfDay();

        return [
            'recurring_id' => Recurring::factory(),
            'period_start' => $dueOn->toDateString(),
            'period_end' => $dueOn->copy()->endOfMonth()->toDateString(),
            'due_on' => $dueOn->toDateString(),
            'status' => RecurringOccurrenceStatus::Due,
            'expected_amount' => $this->faker->randomFloat(2, 20, 500),
            'actual_amount' => null,
            'expense_id' => null,
            'reminded_at' => null,
        ];
    }

    public function completed(?Expense $expense = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecurringOccurrenceStatus::Completed,
            'expense_id' => $expense?->id ?? Expense::factory(),
            'actual_amount' => $attributes['expected_amount'] ?? 50,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecurringOccurrenceStatus::Overdue,
            'due_on' => now()->subDays(3)->toDateString(),
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecurringOccurrenceStatus::Skipped,
        ]);
    }
}
