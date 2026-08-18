<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OllamaSetting extends Model
{
    public const SINGLETON_ID = 1;

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

    public static function singleton(): self
    {
        /** @var self $setting */
        $setting = self::query()->firstOrCreate(['id' => self::SINGLETON_ID]);

        return $setting;
    }
}
