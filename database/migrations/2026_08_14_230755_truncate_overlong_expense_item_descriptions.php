<?php

declare(strict_types=1);

use App\Support\FieldCharacterLimits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $max = FieldCharacterLimits::LINE_ITEM_DESCRIPTION;

        DB::table('expense_items')
            ->select(['id', 'description'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $item) use ($max): void {
                $description = (string) $item->description;

                if (mb_strlen($description) <= $max) {
                    return;
                }

                DB::table('expense_items')
                    ->where('id', $item->id)
                    ->update([
                        'description' => FieldCharacterLimits::truncate($description, $max),
                    ]);
            });
    }

    public function down(): void
    {
        // Truncation cannot restore the original descriptions.
    }
};
