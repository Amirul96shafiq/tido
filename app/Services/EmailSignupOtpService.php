<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\EmailSignupOtpNotification;
use App\Support\EmailSignupDevOtp;
use App\Support\FieldCharacterLimits;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class EmailSignupOtpService
{
    private const OTP_TTL_SECONDS = 600;

    private const MAX_VERIFY_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_SENDS_PER_HOUR = 10;

    /**
     * @return array{email: string, password: string, name: string}
     */
    public function send(string $email, string $password): array
    {
        $normalizedEmail = EmailSignupDevOtp::normalizeEmail($email);

        if ($normalizedEmail === null) {
            throw new RuntimeException('Enter a valid email address.');
        }

        if (User::query()->where('email', $normalizedEmail)->exists()) {
            throw new RuntimeException('Unable to send a confirmation code for this email.');
        }

        $remaining = $this->cooldownRemainingSeconds($normalizedEmail);
        if ($remaining > 0) {
            throw new RuntimeException("Please wait {$remaining}s before requesting another code.");
        }

        $hourlyKey = $this->hourlyKey($normalizedEmail);
        $hourlyCount = (int) (Cache::get($hourlyKey) ?? 0);
        if ($hourlyCount >= self::MAX_SENDS_PER_HOUR) {
            throw new RuntimeException('Too many code requests. Try again later.');
        }

        $name = $this->deriveNameFromEmail($normalizedEmail);

        $code = EmailSignupDevOtp::isDevAddress($normalizedEmail)
            ? (string) EmailSignupDevOtp::code()
            : (string) random_int(100000, 999999);

        Cache::put($this->otpKey($normalizedEmail), [
            'hash' => hash('sha256', $code),
            'attempts' => 0,
        ], self::OTP_TTL_SECONDS);

        Cache::put($this->pendingKey($normalizedEmail), [
            'email' => $normalizedEmail,
            'password' => encrypt($password),
            'name' => $name,
        ], self::OTP_TTL_SECONDS);

        $endsAt = time() + self::RESEND_COOLDOWN_SECONDS;
        Cache::put($this->cooldownKey($normalizedEmail), $endsAt, self::RESEND_COOLDOWN_SECONDS);
        Cache::put($hourlyKey, $hourlyCount + 1, now()->addHour());

        if (! EmailSignupDevOtp::isDevAddress($normalizedEmail)) {
            Notification::route('mail', $normalizedEmail)
                ->notify(new EmailSignupOtpNotification($code));
        }

        return [
            'email' => $normalizedEmail,
            'password' => $password,
            'name' => $name,
        ];
    }

    /**
     * @return array{email: string, password: string, name: string}|null
     */
    public function verify(string $email, string $code): ?array
    {
        $normalizedEmail = EmailSignupDevOtp::normalizeEmail($email);

        if ($normalizedEmail === null) {
            return null;
        }

        $payload = Cache::get($this->otpKey($normalizedEmail));

        if (! is_array($payload) || ! isset($payload['hash'], $payload['attempts'])) {
            return null;
        }

        $attempts = (int) $payload['attempts'];

        if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
            $this->forgetOtpState($normalizedEmail);

            return null;
        }

        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';

        if ($normalizedCode === '' || ! hash_equals((string) $payload['hash'], hash('sha256', $normalizedCode))) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($this->otpKey($normalizedEmail), $payload, self::OTP_TTL_SECONDS);

            return null;
        }

        $pending = Cache::get($this->pendingKey($normalizedEmail));

        if (! is_array($pending) || ! isset($pending['email'], $pending['password'], $pending['name'])) {
            $this->forgetOtpState($normalizedEmail);

            return null;
        }

        $this->forgetOtpState($normalizedEmail);

        $decryptedPassword = decrypt((string) $pending['password']);

        return [
            'email' => (string) $pending['email'],
            'password' => is_string($decryptedPassword) ? $decryptedPassword : '',
            'name' => (string) $pending['name'],
        ];
    }

    public function cooldownRemainingSeconds(string $email): int
    {
        $normalizedEmail = EmailSignupDevOtp::normalizeEmail($email);

        if ($normalizedEmail === null) {
            return 0;
        }

        $endsAt = Cache::get($this->cooldownKey($normalizedEmail));

        if (! is_int($endsAt) && ! is_numeric($endsAt)) {
            return 0;
        }

        return max(0, (int) $endsAt - time());
    }

    public function cooldownEndsAt(string $email): ?int
    {
        $normalizedEmail = EmailSignupDevOtp::normalizeEmail($email);

        if ($normalizedEmail === null) {
            return null;
        }

        $endsAt = Cache::get($this->cooldownKey($normalizedEmail));

        if (! is_int($endsAt) && ! is_numeric($endsAt)) {
            return null;
        }

        $endsAt = (int) $endsAt;

        return $endsAt > time() ? $endsAt : null;
    }

    public function deriveNameFromEmail(string $email): string
    {
        $localPart = Str::before($email, '@');
        $name = Str::title(str_replace(['.', '_', '-'], ' ', $localPart));

        return FieldCharacterLimits::truncate($name, FieldCharacterLimits::USER_NAME);
    }

    private function otpKey(string $normalizedEmail): string
    {
        return 'email_signup_otp:'.$normalizedEmail;
    }

    private function pendingKey(string $normalizedEmail): string
    {
        return 'email_signup_pending:'.$normalizedEmail;
    }

    private function cooldownKey(string $normalizedEmail): string
    {
        return 'email_signup_otp_cooldown:'.$normalizedEmail;
    }

    private function hourlyKey(string $normalizedEmail): string
    {
        return 'email_signup_otp_hourly:'.$normalizedEmail;
    }

    private function forgetOtpState(string $normalizedEmail): void
    {
        Cache::forget($this->otpKey($normalizedEmail));
        Cache::forget($this->pendingKey($normalizedEmail));
        Cache::forget($this->cooldownKey($normalizedEmail));
    }
}
