<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Str;

final class FieldCharacterLimits
{
    public const EXTRA_CLASS = 'fi-character-count';

    public const USER_NAME = 25;

    public const DISPLAY_NAME = 20;

    public const RELATIONSHIP_OTHER = 20;

    public const MERCHANT_NAME = 80;

    public const NOTES = 100;

    public const LINE_ITEM_DESCRIPTION = 80;

    public const BUDGET_TITLE = 30;

    public const RECURRING_TITLE = 30;

    public const LABEL_NAME = 30;

    public const PAYMENT_METHOD_NAME = 30;

    public static function truncate(?string $value, int $max): string
    {
        $text = $value ?? '';

        if ($text === '' || Str::length($text) <= $max) {
            return $text;
        }

        return Str::substr($text, 0, $max);
    }

    public static function applyTextInput(
        TextInput $field,
        int $max,
        string|Closure|null $helperText = null,
    ): TextInput {
        $field->maxLength($max);

        $counter = self::counterText($max);

        if ($helperText === null) {
            return $field->belowContent(Schema::end([$counter]));
        }

        return $field->belowContent(Schema::between([
            Text::make($helperText),
            $counter,
        ]));
    }

    public static function applyRichEditor(RichEditor $field, int $max): RichEditor
    {
        return $field
            ->maxLength($max)
            ->belowContent(Schema::end([self::counterText($max, rich: true)]));
    }

    public static function counterText(int $max, bool $rich = false): Text
    {
        return Text::make(self::counterJs($max, $rich))
            ->size(TextSize::ExtraSmall)
            ->extraAttributes(['class' => self::EXTRA_CLASS])
            ->js();
    }

    private static function counterJs(int $max, bool $rich): string
    {
        $max = max(0, $max);

        if (! $rich) {
            return "[...String(\$state ?? '')].length + '/{$max}'";
        }

        return <<<JS
((s, m) => {
    const walk = (node) => {
        if (node == null) {
            return 0;
        }

        if (typeof node === 'string') {
            return [...node.replace(/<[^>]*>/g, '')].length;
        }

        if (Array.isArray(node)) {
            return node.reduce((total, item) => total + walk(item), 0);
        }

        if (typeof node === 'object') {
            const text = typeof node.text === 'string' ? [...node.text].length : 0;

            return text + walk(node.content);
        }

        return 0;
    };

    return walk(s) + '/' + m;
})(\$state, {$max})
JS;
    }
}
