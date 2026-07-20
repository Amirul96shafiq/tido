<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;

final class FilenameDisplay
{
    public const PREFIX_LENGTH = 10;

    public const MANUAL_INVOICE_LABEL = 'Manual invoice';

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

    public static function labelForInvoice(Invoice $invoice): string
    {
        if (blank($invoice->image_path) && blank($invoice->original_filename)) {
            return self::MANUAL_INVOICE_LABEL;
        }

        return self::truncate($invoice->original_filename);
    }

    public static function configureTextColumn(TextColumn $column): TextColumn
    {
        return $column->getStateUsing(
            fn (Invoice $record): string => self::labelForInvoice($record),
        );
    }
}
