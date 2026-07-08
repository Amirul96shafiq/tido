<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LabelingType;
use App\Models\Labeling;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Labeling>
 */
class LabelingFactory extends Factory
{
    protected $model = Labeling::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'type' => LabelingType::Finance,
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => 'heroicon-o-briefcase',
            'color' => $this->faker->safeHexColor(),
            'is_system' => false,
        ];
    }
}
