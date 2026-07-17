<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Label;
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
            'title' => null,
            'icon' => null,
            'label_id' => $this->faker->boolean(80) ? Label::factory() : null,
            'amount' => $this->faker->randomFloat(2, 50, 5000),
            'period' => $period,
            'quarter' => $quarter,
            'year' => (int) now()->year,
            'alert_threshold' => 80,
            'critical_threshold' => 100,
            'notify_filament' => true,
            'notify_whatsapp' => true,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
