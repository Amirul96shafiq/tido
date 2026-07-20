<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'slug' => 'mastercard',
                'name' => 'Mastercard',
                'aliases' => ['master', 'master_card', 'card', 'mc'],
                'icon' => 'heroicon-o-credit-card',
                'color' => '#FFA524',
            ],
            [
                'slug' => 'visa',
                'name' => 'Visa',
                'aliases' => [],
                'icon' => 'heroicon-o-credit-card',
                'color' => '#FFD07D',
            ],
            [
                'slug' => 'mykasih',
                'name' => 'MYKASIH',
                'aliases' => ['my_kasih'],
                'icon' => 'heroicon-o-identification',
                'color' => '#FFE2A3',
            ],
            [
                'slug' => 'cash',
                'name' => 'Cash',
                'aliases' => [],
                'icon' => 'heroicon-o-banknotes',
                'color' => '#FFA524',
            ],
            [
                'slug' => 'pay_with_qr',
                'name' => 'Pay with QR',
                'aliases' => ['qr', 'qr_pay', 'qr_payment', 'duitnow_qr', 'duitnow'],
                'icon' => 'heroicon-o-qr-code',
                'color' => '#FFD07D',
            ],
            [
                'slug' => 'touchngo',
                'name' => "Touch 'n Go",
                'aliases' => ['touch_n_go', 'touchngo_ewallet', 'tng', 't_ngo', 'tngo'],
                'icon' => 'heroicon-o-device-phone-mobile',
                'color' => '#FFE2A3',
            ],
            [
                'slug' => 'other',
                'name' => 'Other',
                'aliases' => ['debit', 'credit', 'debit_card', 'credit_card', 'mydebit', 'my_debit', 'card_payment'],
                'icon' => 'heroicon-o-ellipsis-horizontal',
                'color' => '#9CA3AF',
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['slug' => $method['slug']],
                [
                    'name' => $method['name'],
                    'aliases' => $method['aliases'],
                    'icon' => $method['icon'],
                    'color' => $method['color'],
                    'is_system' => true,
                ],
            );
        }
    }
}
