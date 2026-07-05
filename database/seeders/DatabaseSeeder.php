<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@tido.local',
            'password' => bcrypt('password'),
        ]);

        Budget::factory(5)->create();

        Invoice::factory(50)->create()->each(function ($invoice) {
            InvoiceItem::factory(random_int(1, 5))->create([
                'invoice_id' => $invoice->id,
            ]);
        });
    }
}
