<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DangerZoneTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $resetUser = User::query()->updateOrCreate(
            ['email' => 'resetdata@mail.com'],
            [
                'name' => 'Reset Data Test User',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'deleteacc@mail.com'],
            [
                'name' => 'Delete Account Test User',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        if (Invoice::query()->count() < 5) {
            Invoice::factory(8)->create()->each(function (Invoice $invoice): void {
                InvoiceItem::factory(random_int(1, 3))->create([
                    'invoice_id' => $invoice->id,
                ]);
            });

            Budget::factory(2)->create();
        }

        unset($resetUser);
    }
}
