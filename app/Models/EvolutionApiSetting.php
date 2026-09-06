<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Support\CurrentHousehold;
use Illuminate\Database\Eloquent\Model;

class EvolutionApiSetting extends Model
{
    use BelongsToHousehold;

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
    ];

    protected $casts = [
        'whatsapp_enabled' => 'boolean',
        'setup_completed_at' => 'datetime',
    ];

    public static function forHousehold(?int $householdId = null): self
    {
        $householdId ??= CurrentHousehold::id() ?? 1;

        /** @var self $setting */
        $setting = self::query()->firstOrCreate(
            ['household_id' => $householdId],
            [
                'api_url' => config('services.evolution.api_url'),
                'instance_name' => config('services.evolution.instance_name'),
                'whatsapp_enabled' => $householdId === 1,
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

        if ($enabled === null) {
            // MH-008: household #1 inherits install WhatsApp until a settings row exists.
            return $householdId === 1;
        }

        return (bool) $enabled;
    }

    public function resolvesWebhookSecret(string $secret): bool
    {
        $stored = $this->webhook_secret;

        return is_string($stored) && $stored !== '' && hash_equals($stored, $secret);
    }
}
