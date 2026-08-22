<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LabelType;
use App\Models\ExpenseItem;
use App\Models\Label;
use Illuminate\Support\Str;

class LabelBoundaryRelabelService
{
    /** @var array<string, string> */
    public const LABEL_DESCRIPTIONS = [
        'groceries-household' => 'Supermarket pantry, fresh produce, cleaning supplies, baby wipes, garbage bags, detergents, air fresheners',
        'electronics-gadgets' => 'Phones, computers, tablets, accessories, cables — not large home appliances or furniture',
        'furniture-home-appliances' => 'Durable furniture, mattresses, bedding sets, and home appliances (fridge, washer, aircon, vacuum, kettle, rice cooker, water filters). Home improvement durables from DIY/hardware stores.',
    ];

    /** @var list<int> */
    public const FURNITURE_TO_GROCERIES_ITEM_IDS = [
        544,
        547,
        564,
        636,
        637,
        702,
        1067,
        1116,
        1117,
    ];

    /**
     * @return array{descriptions_updated: int, furniture_to_groceries: int, groceries_to_furniture: int}
     */
    public function run(): array
    {
        $descriptionsUpdated = $this->syncLabelDescriptions();

        $groceriesLabel = $this->resolveLabel('groceries-household');
        $furnitureLabel = $this->resolveLabel('furniture-home-appliances');

        $furnitureToGroceries = ExpenseItem::query()
            ->whereIn('id', self::FURNITURE_TO_GROCERIES_ITEM_IDS)
            ->where('label_id', $furnitureLabel->id)
            ->update(['label_id' => $groceriesLabel->id]);

        return [
            'descriptions_updated' => $descriptionsUpdated,
            'furniture_to_groceries' => $furnitureToGroceries,
            'groceries_to_furniture' => 0,
        ];
    }

    private function syncLabelDescriptions(): int
    {
        $updated = 0;

        foreach (self::LABEL_DESCRIPTIONS as $slug => $description) {
            $label = Label::query()
                ->where('type', LabelType::Finance)
                ->where('slug', $slug)
                ->first();

            if ($label === null) {
                continue;
            }

            if ($label->description === $description) {
                continue;
            }

            $label->update(['description' => $description]);
            $updated++;
        }

        if (! Label::query()->where('type', LabelType::Finance)->where('slug', 'furniture-home-appliances')->exists()) {
            Label::query()->create([
                'type' => LabelType::Finance,
                'name' => 'Furniture & Home Appliances',
                'slug' => 'furniture-home-appliances',
                'description' => self::LABEL_DESCRIPTIONS['furniture-home-appliances'],
                'icon' => 'heroicon-o-home-modern',
                'color' => '#C4A574',
                'is_system' => false,
            ]);
            $updated++;
        }

        return $updated;
    }

    private function resolveLabel(string $slug): Label
    {
        $existing = Label::query()
            ->where('type', LabelType::Finance)
            ->where('slug', $slug)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Label::query()->create([
            'type' => LabelType::Finance,
            'name' => match ($slug) {
                'groceries-household' => 'Groceries & Household',
                'furniture-home-appliances' => 'Furniture & Home Appliances',
                default => Str::title(str_replace('-', ' ', $slug)),
            },
            'slug' => $slug,
            'description' => self::LABEL_DESCRIPTIONS[$slug] ?? '',
            'is_system' => $slug !== 'furniture-home-appliances',
        ]);
    }
}
