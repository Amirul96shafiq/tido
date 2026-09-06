<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EvolutionApiSetting;
use App\Models\Household;

/**
 * MH-008: Resolve household from inbound Evolution webhook secret.
 */
final class EvolutionWebhookHousehold
{
    public static function findByWebhookSecret(?string $secret): ?Household
    {
        if (! is_string($secret) || $secret === '') {
            return null;
        }

        foreach (EvolutionApiSetting::query()->withoutGlobalScopes()->cursor() as $setting) {
            if ($setting->resolvesWebhookSecret($secret)) {
                return $setting->household;
            }
        }

        // Fallback: install-wide env secret maps to household #1 during transition.
        $envSecret = config('services.evolution.webhook_secret');

        if (is_string($envSecret) && $envSecret !== '' && hash_equals($envSecret, $secret)) {
            return Household::query()->find(1);
        }

        return null;
    }
}
