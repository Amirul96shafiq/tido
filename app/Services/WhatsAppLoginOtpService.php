<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\CurrentHousehold;
use App\Support\PhoneNumber;
use App\Support\WhatsAppLoginDevOtp;
use App\Support\WhatsAppMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppLoginOtpService
{
    private const OTP_TTL_SECONDS = 600;

    private const MAX_VERIFY_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_SENDS_PER_HOUR = 10;

    public function __construct(
        private readonly EvolutionSettingsService $settings,
    ) {}

    public function send(User $user): bool
    {
        $phone = PhoneNumber::normalize($user->phone);

        if ($phone === null) {
            throw new RuntimeException('User does not have a valid WhatsApp phone number.');
        }

        $setting = $this->settings->forHousehold((int) $user->household_id);

        if (
            ! $setting->whatsapp_enabled
            || ! $this->settings->isConfigured($setting)
        ) {
            throw new RuntimeException('Evolution API is not configured.');
        }

        $remaining = $this->cooldownRemainingSeconds($user);
        if ($remaining > 0) {
            throw new RuntimeException("Please wait {$remaining}s before requesting another code.");
        }

        $hourlyKey = $this->hourlyKey($user);
        $hourlyCount = (int) (Cache::get($hourlyKey) ?? Cache::get($this->legacyHourlyKey($user), 0));
        if ($hourlyCount >= self::MAX_SENDS_PER_HOUR) {
            throw new RuntimeException('Too many code requests. Try again later.');
        }

        $code = WhatsAppLoginDevOtp::isDevPhone($phone)
            ? (string) WhatsAppLoginDevOtp::code()
            : (string) random_int(100000, 999999);

        Cache::put($this->otpKey($user), [
            'hash' => hash('sha256', $code),
            'attempts' => 0,
        ], self::OTP_TTL_SECONDS);
        Cache::put($this->legacyOtpKey($user), Cache::get($this->otpKey($user)), self::OTP_TTL_SECONDS);

        $endsAt = time() + self::RESEND_COOLDOWN_SECONDS;
        Cache::put($this->cooldownKey($user), $endsAt, self::RESEND_COOLDOWN_SECONDS);
        Cache::put($this->legacyCooldownKey($user), $endsAt, self::RESEND_COOLDOWN_SECONDS);
        Cache::put($hourlyKey, $hourlyCount + 1, now()->addHour());
        Cache::put($this->legacyHourlyKey($user), $hourlyCount + 1, now()->addHour());

        if (WhatsAppLoginDevOtp::isDevPhone($phone)) {
            return true;
        }

        $previousHouseholdId = CurrentHousehold::id();
        CurrentHousehold::set((int) $user->household_id);

        try {
            $sent = app(WhatsAppNotificationService::class)->sendMessage(
                $phone,
                WhatsAppMessage::compose(
                    '🔐',
                    'Login code',
                    "Code: *{$code}*\n\nThis code expires in 10 minutes and is valid for one login only.\n\nIgnore this message if this login was not requested.",
                ),
            );
        } finally {
            if ($previousHouseholdId === null) {
                CurrentHousehold::clear();
            } else {
                CurrentHousehold::set($previousHouseholdId);
            }
        }

        if (! $sent) {
            $this->forgetOtpState($user);
            Log::warning('WhatsApp login OTP send failed', ['user_id' => $user->id]);

            throw new RuntimeException('Failed to send WhatsApp code. Try again or use password login.');
        }

        return true;
    }

    public function verify(User $user, string $code): bool
    {
        $payload = Cache::get($this->legacyOtpKey($user)) ?? Cache::get($this->otpKey($user));

        if (! is_array($payload) || ! isset($payload['hash'], $payload['attempts'])) {
            return false;
        }

        $attempts = (int) $payload['attempts'];

        if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget($this->otpKey($user));
            Cache::forget($this->legacyOtpKey($user));

            return false;
        }

        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';

        if ($normalizedCode === '' || ! hash_equals((string) $payload['hash'], hash('sha256', $normalizedCode))) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($this->otpKey($user), $payload, self::OTP_TTL_SECONDS);
            Cache::put($this->legacyOtpKey($user), $payload, self::OTP_TTL_SECONDS);

            return false;
        }

        Cache::forget($this->otpKey($user));
        Cache::forget($this->cooldownKey($user));
        Cache::forget($this->legacyOtpKey($user));
        Cache::forget($this->legacyCooldownKey($user));

        return true;
    }

    public function cooldownRemainingSeconds(User $user): int
    {
        $endsAt = Cache::get($this->cooldownKey($user)) ?? Cache::get($this->legacyCooldownKey($user));

        if (! is_int($endsAt) && ! is_numeric($endsAt)) {
            return 0;
        }

        return max(0, (int) $endsAt - time());
    }

    public function cooldownEndsAt(User $user): ?int
    {
        $endsAt = Cache::get($this->cooldownKey($user)) ?? Cache::get($this->legacyCooldownKey($user));

        if (! is_int($endsAt) && ! is_numeric($endsAt)) {
            return null;
        }

        $endsAt = (int) $endsAt;

        return $endsAt > time() ? $endsAt : null;
    }

    private function otpKey(User $user): string
    {
        return 'wa_login_otp:'.$user->household_id.':'.$user->id;
    }

    private function cooldownKey(User $user): string
    {
        return 'wa_login_otp_cooldown:'.$user->household_id.':'.$user->id;
    }

    private function hourlyKey(User $user): string
    {
        return 'wa_login_otp_hourly:'.$user->household_id.':'.$user->id;
    }

    private function legacyOtpKey(User $user): string
    {
        return 'wa_login_otp:'.$user->id;
    }

    private function legacyCooldownKey(User $user): string
    {
        return 'wa_login_otp_cooldown:'.$user->id;
    }

    private function legacyHourlyKey(User $user): string
    {
        return 'wa_login_otp_hourly:'.$user->id;
    }

    private function forgetOtpState(User $user): void
    {
        Cache::forget($this->otpKey($user));
        Cache::forget($this->cooldownKey($user));
        Cache::forget($this->legacyOtpKey($user));
        Cache::forget($this->legacyCooldownKey($user));
    }
}
