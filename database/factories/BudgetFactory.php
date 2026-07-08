<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Labeling;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        $period = $this->faker->randomElement(['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);
        $quarter = ($period === 'quarterly') ? $this->faker->numberBetween(1, 4) : null;

        return [
            'labeling_id' => $this->faker->boolean(80) ? Labeling::factory() : null,
            'amount' => $this->faker->randomFloat(2, 50, 5000),
            'period' => $period,
            'quarter' => $quarter,
            'year' => (int) now()->year,
            'alert_threshold' => $this->faker->numberBetween(50, 100),
            'is_active' => true,
        ];
    }
}
