<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 5, 500);
        $tax = round($subtotal * 0.06, 2);
        $total = $subtotal + $tax;
        $invoiceNum = 'INV-'.$this->faker->unique()->numberBetween(100000, 999999);
        $dateTime = $this->faker->dateTimeThisYear();

        return [
            'merchant_name' => $this->faker->company(),
            'invoice_number' => $invoiceNum,
            'receipt_hash' => hash('sha256', $invoiceNum.$dateTime->format('Y-m-d H:i:s').$total),
            'date_time' => $dateTime,
            'subtotal' => $subtotal,
            'total_tax' => $tax,
            'discount_total' => 0,
            'rounding_amount' => 0,
            'total_amount' => $total,
            'currency' => 'MYR',
            'payment_method' => $this->faker->randomElement(PaymentMethod::cases()),
            'source' => $this->faker->randomElement(['manual', 'whatsapp', 'google_drive']),
            'status' => $this->faker->randomElement(['reviewed', 'parsed']),
            'google_drive_file_id' => null,
            'original_filename' => $this->faker->word().'.jpg',
            'image_path' => null,
            'raw_ai_response' => null,
            'notes' => $this->faker->sentence(),
        ];
    }
}
