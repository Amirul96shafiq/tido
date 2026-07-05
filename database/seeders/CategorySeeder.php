<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Food & Dining',
                'icon' => 'heroicon-o-cake',
                'color' => '#FFBE3B',
            ],
            [
                'name' => 'Transportation & Fuel',
                'icon' => 'heroicon-o-truck',
                'color' => '#FFAF24',
            ],
            [
                'name' => 'Groceries & Household',
                'icon' => 'heroicon-o-shopping-cart',
                'color' => '#FFDCA1',
            ],
            [
                'name' => 'Electronics & Gadgets',
                'icon' => 'heroicon-o-cpu-chip',
                'color' => '#E09210',
            ],
            [
                'name' => 'Utilities & Bills',
                'icon' => 'heroicon-o-bolt',
                'color' => '#FFD07D',
            ],
            [
                'name' => 'Healthcare & Medical',
                'icon' => 'heroicon-o-heart',
                'color' => '#B87307',
            ],
            [
                'name' => 'Entertainment & Leisure',
                'icon' => 'heroicon-o-film',
                'color' => '#FFC154',
            ],
            [
                'name' => 'Office Supplies',
                'icon' => 'heroicon-o-briefcase',
                'color' => '#8F5404',
            ],
            [
                'name' => 'Subscriptions & Memberships',
                'icon' => 'heroicon-o-credit-card',
                'color' => '#FFE7C2',
            ],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(
                ['slug' => Str::slug($cat['name'])],
                [
                    'name' => $cat['name'],
                    'icon' => $cat['icon'],
                    'color' => $cat['color'],
                    'is_system' => true,
                ]
            );
        }
    }
}
