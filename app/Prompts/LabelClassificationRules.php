<?php

declare(strict_types=1);

namespace App\Prompts;

class LabelClassificationRules
{
    public static function promptLines(): string
    {
        return <<<'RULES'
- Groceries & Household → consumable / replenishable pantry, fresh produce, cleaning supplies, baby wipes, garbage bags, detergents, air fresheners, shop bags.
- Furniture & Home Appliances → durable furniture, mattresses, bed frames, bedding sets, and home appliances (fridge, washer, aircon, vacuum, kettle, rice cooker, water filters). Home improvement durables from hardware/DIY stores also belong here.
- Do not classify durable furniture or appliances as Groceries & Household just because the receipt is from a supermarket or Mr DIY.
- Electronics & Gadgets → phones, computers, tablets, accessories, cables — not large home appliances or furniture.
RULES;
    }
}
