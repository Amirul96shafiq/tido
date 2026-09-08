<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Support\CurrentHousehold;
use Illuminate\Database\Eloquent\Model;

class OllamaSetting extends Model
{
    use BelongsToHousehold;

    protected $fillable = [
        'host',
        'model',
        'timeout',
        'num_ctx',
        'max_image_dimension',
        'pdfinfo_binary',
        'pdftocairo_binary',
        'pdftotext_binary',
        'setup_completed_at',
    ];

    protected $casts = [
        'timeout' => 'integer',
        'num_ctx' => 'integer',
        'max_image_dimension' => 'integer',
        'setup_completed_at' => 'datetime',
    ];

    /**
     * Settings row for the current or given household (replaces install-wide singleton).
     */
    public static function forHousehold(?int $householdId = null): self
    {
        $householdId ??= CurrentHousehold::id() ?? 1;

        /** @var self $setting */
        $setting = self::query()->firstOrCreate(['household_id' => $householdId]);

        return $setting;
    }

    /**
     * @deprecated Use forHousehold(); retained for call-site compatibility during MH-004.
     */
    public static function singleton(): self
    {
        return self::forHousehold(CurrentHousehold::id() ?? 1);
    }
}
