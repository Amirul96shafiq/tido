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
    public static function findSettingByWebhookSecret(?string $secret): ?EvolutionApiSetting
    {
        if (! is_string($secret) || trim($secret) === '') {
            return null;
        }

        $secret = trim($secret);
        $environmentSecret = config('services.evolution.webhook_secret');

        if (is_string($environmentSecret) && $environmentSecret !== '' && hash_equals(trim($environmentSecret), $secret)) {
            /** @var EvolutionApiSetting|null $householdOneSetting */
            $householdOneSetting = EvolutionApiSetting::query()
                ->withoutGlobalScopes()
                ->where('household_id', 1)
                ->first();

            return $householdOneSetting;
        }

        $hash = EvolutionApiSetting::secretHash($secret);

        /** @var EvolutionApiSetting|null $setting */
        $setting = EvolutionApiSetting::query()
            ->withoutGlobalScopes()
            ->where('webhook_secret_hash', $hash)
            ->first();

        if ($setting !== null && $setting->resolvesWebhookSecret($secret)) {
            return $setting;
        }

        return null;
    }

    public static function findByWebhookSecret(?string $secret): ?Household
    {
        return self::findSettingByWebhookSecret($secret)?->household;
    }
}
