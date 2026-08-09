<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Expense;
use Filament\Tables\Columns\TextColumn;

final class FilenameDisplay
{
    public const PREFIX_LENGTH = 10;

    public const MANUAL_EXPENSE_LABEL = 'Manual expense';

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

    public static function labelForExpense(Expense $expense): string
    {
        if (blank($expense->image_path) && blank($expense->original_filename)) {
            return self::MANUAL_EXPENSE_LABEL;
        }

        return self::truncate($expense->original_filename);
    }

    public static function configureTextColumn(TextColumn $column): TextColumn
    {
        return $column->getStateUsing(
            fn (Expense $record): string => self::labelForExpense($record),
        );
    }
}
