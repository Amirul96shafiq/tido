<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Labeling;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(3, 1, 5);
        $unitPrice = $this->faker->randomFloat(2, 1, 100);
        $lineTotal = round($quantity * $unitPrice, 2);

        return [
            'invoice_id' => Invoice::factory(),
            'labeling_id' => Labeling::factory(),
            'description' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'warranty_expiry_date' => $this->faker->optional(0.1)->dateTimeBetween('now', '+3 years')?->format('Y-m-d'),
            'serial_number' => $this->faker->optional(0.1)->bothify('SN-######-??'),
        ];
    }
}
