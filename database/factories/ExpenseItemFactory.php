<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseItem>
 */
class ExpenseItemFactory extends Factory
{
    protected $model = ExpenseItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(3, 1, 5);
        $unitPrice = $this->faker->randomFloat(2, 1, 100);
        $lineTotal = round($quantity * $unitPrice, 2);

        return [
            'expense_id' => Expense::factory(),
            'label_id' => Label::factory(),
            'description' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'warranty_expiry_date' => $this->faker->optional(0.1)->dateTimeBetween('now', '+3 years')?->format('Y-m-d'),
            'serial_number' => $this->faker->optional(0.1)->bothify('SN-######-??'),
        ];
    }
}
