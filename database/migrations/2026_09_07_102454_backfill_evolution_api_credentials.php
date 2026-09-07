<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('evolution_api_settings')->get();
        $applicationKey = (string) config('app.key');

        foreach ($rows as $row) {
            $apiKey = $this->plainValue($row->api_key);
            $webhookSecret = $this->plainValue($row->webhook_secret);

            if ((int) $row->household_id === 1) {
                $apiKey ??= $this->configuredValue('services.evolution.api_key');
                $webhookSecret ??= $this->configuredValue('services.evolution.webhook_secret');
            }

            $updates = [];

            if ($apiKey !== null) {
                $updates['api_key'] = Crypt::encryptString($apiKey);
            }

            if ($webhookSecret !== null) {
                $updates['webhook_secret'] = Crypt::encryptString($webhookSecret);
                $updates['webhook_secret_hash'] = hash_hmac('sha256', $webhookSecret, $applicationKey);
            }

            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('evolution_api_settings')
                    ->where('id', $row->id)
                    ->update($updates);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('evolution_api_settings')->get() as $row) {
            $updates = [];

            if (($apiKey = $this->plainValue($row->api_key)) !== null) {
                $updates['api_key'] = $apiKey;
            }

            if (($webhookSecret = $this->plainValue($row->webhook_secret)) !== null) {
                $updates['webhook_secret'] = $webhookSecret;
            }

            $updates['webhook_secret_hash'] = null;
            $updates['updated_at'] = now();

            DB::table('evolution_api_settings')
                ->where('id', $row->id)
                ->update($updates);
        }
    }

    private function configuredValue(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function plainValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($value);
        } catch (Throwable) {
            $decrypted = $value;
        }

        $decrypted = trim($decrypted);

        return $decrypted !== '' ? $decrypted : null;
    }
};
