<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LabelType;
use App\Models\Label;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LabelSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            [
                'name' => 'Food & Dining',
                'description' => 'Ready-to-eat meals, restaurant items, convenience-store snacks and drinks',
                'icon' => 'heroicon-o-cake',
                'color' => '#FFBE3B',
            ],
            [
                'name' => 'Transportation & Fuel',
                'description' => 'Petrol, diesel, fuel, tolls, parking related to travel',
                'icon' => 'heroicon-o-truck',
                'color' => '#FFAF24',
            ],
            [
                'name' => 'Groceries & Household',
                'description' => 'Supermarket pantry, fresh produce, cleaning supplies, baby wipes',
                'icon' => 'heroicon-o-shopping-cart',
                'color' => '#FFDCA1',
            ],
            [
                'name' => 'Electronics & Gadgets',
                'description' => 'Phones, computers, accessories, cables, appliances',
                'icon' => 'heroicon-o-cpu-chip',
                'color' => '#E09210',
            ],
            [
                'name' => 'Utilities & Bills',
                'description' => 'Electricity, water, internet, phone bills',
                'icon' => 'heroicon-o-bolt',
                'color' => '#FFD07D',
            ],
            [
                'name' => 'Healthcare & Medical',
                'description' => 'Pharmacy, clinic, vitamins, medical supplies',
                'icon' => 'heroicon-o-heart',
                'color' => '#B87307',
            ],
            [
                'name' => 'Entertainment & Leisure',
                'description' => 'Movies, games, hobbies, sports, leisure activities',
                'icon' => 'heroicon-o-film',
                'color' => '#FFC154',
            ],
            [
                'name' => 'Office Supplies',
                'description' => 'Stationery, printer supplies, workplace consumables',
                'icon' => 'heroicon-o-briefcase',
                'color' => '#8F5404',
            ],
            [
                'name' => 'Subscriptions & Memberships',
                'description' => 'Streaming, gym, software, recurring memberships',
                'icon' => 'heroicon-o-credit-card',
                'color' => '#FFE7C2',
            ],
        ];

        foreach ($labels as $label) {
            Label::updateOrCreate(
                [
                    'type' => LabelType::Finance,
                    'slug' => Str::slug($label['name']),
                ],
                [
                    'name' => $label['name'],
                    'description' => $label['description'],
                    'icon' => $label['icon'],
                    'color' => $label['color'],
                    'is_system' => true,
                ]
            );
        }
    }
}
