<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

use function Filament\Support\generate_icon_html;

class IconPicker extends Field
{
    protected string $view = 'filament.forms.components.icon-picker';

    public const PAGE_SIZE = 48;

    /**
     * @return list<string>
     */
    public static function curatedIconValues(): array
    {
        return [
            'heroicon-o-cake',
            'heroicon-o-truck',
            'heroicon-o-shopping-cart',
            'heroicon-o-cpu-chip',
            'heroicon-o-bolt',
            'heroicon-o-heart',
            'heroicon-o-film',
            'heroicon-o-briefcase',
            'heroicon-o-credit-card',
            'heroicon-o-home',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        $options = [];

        foreach (Heroicon::cases() as $heroicon) {
            if (! str_starts_with($heroicon->value, 'o-')) {
                continue;
            }

            $value = $heroicon->getIconForSize(IconSize::Medium);
            $options[$value] = (string) Str::of($heroicon->value)
                ->after('o-')
                ->replace('-', ' ')
                ->title();
        }

        asort($options);

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function iconOptionsPage(int $limit = self::PAGE_SIZE): array
    {
        return array_slice(self::iconOptions(), 0, max($limit, 0), true);
    }

    /**
     * @return array<string, string>
     */
    public static function searchIconOptions(string $search, int $limit = self::PAGE_SIZE): array
    {
        $search = Str::lower(trim($search));

        if ($search === '') {
            return self::iconOptionsPage($limit);
        }

        return collect(self::iconOptions())
            ->filter(function (string $label, string $value) use ($search): bool {
                return str_contains(Str::lower($label), $search)
                    || str_contains(Str::lower($value), $search);
            })
            ->take($limit)
            ->all();
    }

    public static function iconOptionLabel(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return self::iconOptions()[$value] ?? (string) Str::of($value)
            ->after('heroicon-o-')
            ->replace('-', ' ')
            ->title();
    }

    /**
     * @return list<array{value: string, label: string, html: string}>
     */
    public function getIconsForPicker(): array
    {
        $icons = [];

        foreach (self::iconOptions() as $value => $label) {
            $icons[] = [
                'value' => $value,
                'label' => $label,
                'html' => generate_icon_html($value, size: IconSize::Large)->toHtml(),
            ];
        }

        return $icons;
    }

    /**
     * @return list<array{value: string, label: string, html: string}>
     */
    public function getCuratedIconsForPicker(): array
    {
        $options = self::iconOptions();
        $icons = [];

        foreach (self::curatedIconValues() as $value) {
            if (! isset($options[$value])) {
                continue;
            }

            $icons[] = [
                'value' => $value,
                'label' => $options[$value],
                'html' => generate_icon_html($value, size: IconSize::Medium)->toHtml(),
            ];
        }

        return $icons;
    }

    public function getPageSize(): int
    {
        return self::PAGE_SIZE;
    }
}
