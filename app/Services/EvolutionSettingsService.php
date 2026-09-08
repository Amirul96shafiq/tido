<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EvolutionApiSetting;
use App\Support\EvolutionCredential;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class EvolutionSettingsService
{
    public function forHousehold(int $householdId): EvolutionApiSetting
    {
        return EvolutionApiSetting::forHousehold($householdId);
    }

    /**
     * @return array{
     *     api_url: string,
     *     api_key: ?string,
     *     webhook_secret: ?string,
     *     instance_name: string,
     *     whatsapp_enabled: bool
     * }
     */
    public function effective(EvolutionApiSetting $setting): array
    {
        $householdId = (int) $setting->household_id;
        $apiKey = $setting->api_key;
        $webhookSecret = $setting->webhook_secret;

        if ($householdId === 1) {
            $apiKey ??= $this->configuredValue('services.evolution.api_key');
            $webhookSecret ??= $this->configuredValue('services.evolution.webhook_secret');
        }

        return [
            'api_url' => (string) ($setting->api_url ?: config('services.evolution.api_url')),
            'api_key' => $apiKey,
            'webhook_secret' => $webhookSecret,
            'instance_name' => (string) ($setting->instance_name ?: 'tido-hh-'.$householdId),
            'whatsapp_enabled' => (bool) $setting->whatsapp_enabled,
        ];
    }

    public function isConfigured(EvolutionApiSetting $setting): bool
    {
        $effective = $this->effective($setting);

        return $this->isValidApiUrl($effective['api_url'])
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,63}$/', $effective['instance_name']) === 1
            && is_string($effective['api_key'])
            && is_string($effective['webhook_secret'])
            && EvolutionCredential::areDistinct($effective['api_key'], $effective['webhook_secret']);
    }

    public function setEnabled(int $householdId, bool $enabled): EvolutionApiSetting
    {
        $setting = $this->forHousehold($householdId);

        if ($enabled && ! $this->isConfigured($setting)) {
            throw ValidationException::withMessages([
                'whatsapp_enabled' => 'Complete a valid Evolution API setup before enabling WhatsApp.',
            ]);
        }

        $setting->whatsapp_enabled = $enabled;
        $setting->save();

        return $setting->fresh();
    }

    /**
     * @param array{
     *     api_url?: string,
     *     instance_name?: string,
     *     api_key?: ?string,
     *     webhook_secret?: ?string,
     *     whatsapp_enabled?: bool
     * } $attributes
     */
    public function save(int $householdId, array $attributes): EvolutionApiSetting
    {
        $setting = $this->forHousehold($householdId);
        $apiUrl = trim((string) ($attributes['api_url'] ?? $setting->api_url));
        $instanceName = $this->generatedInstanceName($householdId);

        if (! $this->isValidApiUrl($apiUrl)) {
            throw ValidationException::withMessages([
                'api_url' => 'Enter an allowed absolute Evolution API URL.',
            ]);
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,63}$/', $instanceName) !== 1) {
            throw ValidationException::withMessages([
                'instance_name' => 'Use 2–64 letters, numbers, hyphens, or underscores.',
            ]);
        }

        $duplicate = EvolutionApiSetting::query()
            ->withoutGlobalScopes()
            ->where('instance_name', $instanceName)
            ->where($setting->getKeyName(), '!=', $setting->getKey())
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'instance_name' => 'That Evolution instance name is already in use.',
            ]);
        }

        $setting->api_url = $apiUrl;
        $setting->instance_name = $instanceName;

        foreach (['api_key', 'webhook_secret'] as $credential) {
            $value = $attributes[$credential] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $setting->{$credential} = trim($value);
            }
        }

        $setting->whatsapp_enabled = (bool) ($attributes['whatsapp_enabled'] ?? $setting->whatsapp_enabled);

        if ($setting->whatsapp_enabled && ! $this->isConfigured($setting)) {
            throw ValidationException::withMessages([
                'whatsapp_enabled' => 'Valid, distinct API and webhook credentials are required before enabling WhatsApp.',
            ]);
        }

        if ($this->isConfigured($setting)) {
            $setting->setup_completed_at = Carbon::now();
        }

        $setting->save();

        return $setting->fresh();
    }

    public function generatedInstanceName(int $householdId): string
    {
        return 'tido-hh-'.$householdId;
    }

    public function isValidApiUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return false;
        }

        if (! isset($parts['host']) || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])) {
            return false;
        }

        $allowedHosts = config('services.evolution.allowed_api_hosts', []);

        return is_array($allowedHosts) && in_array(strtolower((string) $parts['host']), array_map('strtolower', $allowedHosts), true);
    }

    private function configuredValue(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
