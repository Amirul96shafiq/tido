<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LabelSeeder::class,
            PaymentMethodSeeder::class,
        ]);

        $phone = PhoneNumber::normalize(
            is_string(config('services.evolution.personal_number'))
                ? config('services.evolution.personal_number')
                : null,
        );

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@tido.local',
            'password' => bcrypt('password'),
            'phone' => $phone ?? '60123456789',
        ]);

        $this->call([
            WhatsAppAllowlistFromEnvSeeder::class,
            FamilyMemberLoginTestSeeder::class,
        ]);

        Budget::factory(5)->create();

        Expense::factory(50)->create()->each(function ($invoice) {
            ExpenseItem::factory(random_int(1, 5))->create([
                'expense_id' => $invoice->id,
            ]);
        });

        $this->call([
            DangerZoneTestUsersSeeder::class,
        ]);
    }
}
