<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleOAuthSetting extends Model
{
    public const PLATFORM_HOUSEHOLD_ID = 1;

    protected $table = 'google_oauth_settings';

    protected $fillable = [
        'household_id',
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

    /**
     * Canonical platform credentials row (household #1).
     */
    public static function platform(): self
    {
        /** @var self $setting */
        $setting = self::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
                ['household_id' => self::PLATFORM_HOUSEHOLD_ID],
                [
                    'client_id' => null,
                    'client_secret' => null,
                    'enabled' => false,
                    'setup_completed_at' => null,
                ],
            );

        return $setting;
    }
}
