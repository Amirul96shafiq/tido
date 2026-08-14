<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LabelType;
use App\Models\Label;
use App\Support\FieldCharacterLimits;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    protected $model = Label::class;

    public function definition(): array
    {
        $name = FieldCharacterLimits::truncate($this->faker->unique()->words(2, true), FieldCharacterLimits::LABEL_NAME);

        return [
            'type' => LabelType::Finance,
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => 'heroicon-o-briefcase',
            'color' => $this->faker->safeHexColor(),
            'description' => $this->faker->optional()->passthrough(
                FieldCharacterLimits::truncate($this->faker->sentence(), FieldCharacterLimits::NOTES),
            ),
            'is_system' => false,
        ];
    }
}
