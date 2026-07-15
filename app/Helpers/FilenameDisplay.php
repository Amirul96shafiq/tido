<?php

declare(strict_types=1);

namespace App\Helpers;

use Filament\Tables\Columns\TextColumn;

final class FilenameDisplay
{
    public const PREFIX_LENGTH = 10;

    public static function truncate(?string $filename, int $prefixLength = self::PREFIX_LENGTH): string
    {
        if (blank($filename)) {
            return '';
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        if (mb_strlen($basename) <= $prefixLength) {
            return $filename;
        }

        $suffix = filled($extension) ? '.'.$extension : '';

        return mb_substr($basename, 0, $prefixLength).'...'.$suffix;
    }

    public static function configureTextColumn(TextColumn $column): TextColumn
    {
        return $column->formatStateUsing(
            fn (?string $state): string => self::truncate($state),
        );
    }
}
