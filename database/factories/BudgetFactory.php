<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        $period = $this->faker->randomElement(['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);
        $quarter = ($period === 'quarterly') ? $this->faker->numberBetween(1, 4) : null;

        return [
            'category_id' => $this->faker->boolean(80) ? Category::factory() : null,
            'amount' => $this->faker->randomFloat(2, 50, 5000),
            'period' => $period,
            'quarter' => $quarter,
            'year' => (int) now()->year,
            'alert_threshold' => $this->faker->numberBetween(50, 100),
            'is_active' => true,
        ];
    }
}
