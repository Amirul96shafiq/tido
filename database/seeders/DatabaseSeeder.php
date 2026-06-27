<?php

declare(strict_types=1);

namespace Database\Seeders;

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
            'email' => 'admin@trackall.local',
            'password' => bcrypt('password'),
        ]);

        \App\Models\Budget::factory(5)->create();
        
        \App\Models\Invoice::factory(50)->create()->each(function ($invoice) {
            \App\Models\InvoiceItem::factory(random_int(1, 5))->create([
                'invoice_id' => $invoice->id,
            ]);
        });
    }
}
