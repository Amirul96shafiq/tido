<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseItem;
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
                'phone' => null,
                'notify_budget_alerts' => false,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'deleteacc@mail.com'],
            [
                'name' => 'Delete Account Test User',
                'password' => 'password',
                'email_verified_at' => now(),
                'phone' => null,
                'notify_budget_alerts' => false,
            ],
        );

        if (Expense::query()->count() < 5) {
            Expense::factory(8)->create()->each(function (Expense $expense): void {
                ExpenseItem::factory(random_int(1, 3))->create([
                    'expense_id' => $expense->id,
                ]);
            });

            Budget::factory(2)->create();
        }

        unset($resetUser);
    }
}
