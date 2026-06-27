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
                'color' => '#ef4444',
            ],
            [
                'name' => 'Transportation & Fuel',
                'icon' => 'heroicon-o-truck',
                'color' => '#f97316',
            ],
            [
                'name' => 'Groceries & Household',
                'icon' => 'heroicon-o-shopping-cart',
                'color' => '#84cc16',
            ],
            [
                'name' => 'Electronics & Gadgets',
                'icon' => 'heroicon-o-cpu-chip',
                'color' => '#3b82f6',
            ],
            [
                'name' => 'Utilities & Bills',
                'icon' => 'heroicon-o-bolt',
                'color' => '#eab308',
            ],
            [
                'name' => 'Healthcare & Medical',
                'icon' => 'heroicon-o-heart',
                'color' => '#ec4899',
            ],
            [
                'name' => 'Entertainment & Leisure',
                'icon' => 'heroicon-o-film',
                'color' => '#8b5cf6',
            ],
            [
                'name' => 'Office Supplies',
                'icon' => 'heroicon-o-briefcase',
                'color' => '#6b7280',
            ],
            [
                'name' => 'Subscriptions & Memberships',
                'icon' => 'heroicon-o-credit-card',
                'color' => '#14b8a6',
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
