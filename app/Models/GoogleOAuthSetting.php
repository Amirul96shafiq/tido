<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleOAuthSetting extends Model
{
    public const SINGLETON_ID = 1;

    protected $table = 'google_oauth_settings';

    protected $fillable = [
        'client_id',
        'client_secret',
        'enabled',
        'setup_completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'enabled' => 'boolean',
            'setup_completed_at' => 'datetime',
        ];
    }

    public static function singleton(): self
    {
        /** @var self $setting */
        $setting = self::query()->firstOrCreate(['id' => self::SINGLETON_ID]);

        return $setting;
    }
}
