<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Support\CurrentHousehold;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class EvolutionApiSetting extends Model
{
    use BelongsToHousehold;

    protected $attributes = [
        'whatsapp_enabled' => false,
    ];

    protected $fillable = [
        'api_url',
        'api_key',
        'webhook_secret',
        'instance_name',
        'whatsapp_enabled',
        'setup_completed_at',
    ];

    protected $hidden = [
        'api_key',
        'webhook_secret',
        'webhook_secret_hash',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            if (! $setting->isDirty('webhook_secret')) {
                return;
            }

            $secret = $setting->webhook_secret;
            $setting->webhook_secret_hash = is_string($secret) && trim($secret) !== ''
                ? self::secretHash($secret)
                : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'whatsapp_enabled' => 'boolean',
            'setup_completed_at' => 'datetime',
        ];
    }

    public static function forHousehold(?int $householdId = null): self
    {
        $householdId ??= CurrentHousehold::id() ?? auth()->user()?->household_id;

        if ($householdId === null) {
            throw new RuntimeException('A household is required to load Evolution API settings.');
        }

        /** @var self $setting */
        $setting = self::query()->firstOrCreate(
            ['household_id' => $householdId],
            [
                'api_url' => config('services.evolution.api_url'),
                'instance_name' => 'tido-hh-'.$householdId,
                'whatsapp_enabled' => false,
            ],
        );

        return $setting;
    }

    /**
     * Read-only WhatsApp enablement for the current (or given) household.
     * Does not create rows — safe for navigation badge rendering.
     */
    public static function isWhatsappEnabledForHousehold(?int $householdId = null): bool
    {
        $householdId ??= CurrentHousehold::id() ?? auth()->user()?->household_id;

        if ($householdId === null) {
            return false;
        }

        $enabled = self::query()
            ->withoutGlobalScopes()
            ->where('household_id', $householdId)
            ->value('whatsapp_enabled');

        return $enabled !== null && (bool) $enabled;
    }

    public static function secretHash(string $secret): string
    {
        return hash_hmac('sha256', trim($secret), (string) config('app.key'));
    }

    public function resolvesWebhookSecret(string $secret): bool
    {
        $stored = $this->webhook_secret;

        return is_string($stored) && $stored !== '' && hash_equals($stored, $secret);
    }
}
