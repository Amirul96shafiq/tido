<?php

declare(strict_types=1);

namespace App\View\Components;

use Filament\Support\View\Components\ButtonComponent as BaseButtonComponent;

class ButtonComponent extends BaseButtonComponent
{
    /**
     * @param  array<int, string>  $color
     * @return array<string, int>
     */
    public function getColorMap(array $color): array
    {
        $map = parent::getColorMap($color);

        if ($this->isOutlined) {
            return $map;
        }

        // Pale brand golds resolve to dark text in light mode but white in dark mode.
        // Mirror the light pairing so CTA labels use dark primary instead of white.
        if (($map['dark:text'] ?? null) === 0 && ($map['text'] ?? null) >= 800) {
            $map['dark:bg'] = $map['bg'];
            $map['dark:hover:bg'] = $map['hover:bg'];
            $map['dark:text'] = $map['text'];
            $map['dark:hover:text'] = $map['hover:text'];
        }

        return $map;
    }
}
