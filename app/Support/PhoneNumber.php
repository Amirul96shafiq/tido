<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\HouseholdRole;
use App\Models\FamilyMember;
use App\Models\User;
use Filament\AvatarProviders\UiAvatarsProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

final class PhoneNumber
{
    /**
     * Normalize a Malaysian WhatsApp number to digits only (e.g. 60123456789).
     * Accepts +60…, 60…, and leading-0 local forms (012…).
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '60')) {
            return null;
        }

        $length = strlen($digits);

        // MY mobiles: 60 + 9–10 national digits (11–12 total). Reject longer typos.
        if ($length < 11 || $length > 12) {
            return null;
        }

        return $digits;
    }

    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }

    /**
     * Build a https://wa.me/{digits}?text=… chat link for a Malaysian number.
     */
    public static function whatsAppMeUrl(?string $value, string $text = 'help'): ?string
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            return null;
        }

        return 'https://wa.me/'.$normalized.'?'.http_build_query(['text' => $text]);
    }

    /**
     * Parse a comma/space/semicolon-separated list of Malaysian numbers.
     *
     * @return list<string>
     */
    public static function parseList(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $trimmed) ?: [];
        $numbers = [];

        foreach ($parts as $part) {
            $normalized = self::normalize($part);

            if ($normalized !== null) {
                $numbers[] = $normalized;
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * Primary account for the current household (Profile WhatsApp / allowlist owner).
     * Falls back to household #1 when no household context is set (CLI / early boot).
     */
    public static function primaryUser(): ?User
    {
        $householdId = CurrentHousehold::id()
            ?? (auth()->user() instanceof User ? auth()->user()->household_id : null)
            ?? 1;

        $user = User::query()
            ->withoutGlobalScope('household')
            ->where('household_id', $householdId)
            ->where(function ($query): void {
                $query->where('household_role', HouseholdRole::Primary)
                    ->orWhereNull('household_role');
            })
            ->orderBy('id')
            ->first();

        // #region agent log
        file_put_contents(base_path('debug-304ce6.log'), json_encode([
            'sessionId' => '304ce6',
            'runId' => 'post-fix',
            'hypothesisId' => 'A',
            'location' => 'PhoneNumber.php:primaryUser',
            'message' => 'primaryUser lookup',
            'data' => [
                'found' => $user !== null,
                'foundUserId' => $user?->getKey(),
                'foundHouseholdId' => $user?->household_id,
                'resolvedHouseholdId' => $householdId,
                'currentHouseholdId' => CurrentHousehold::id(),
                'authUserId' => auth()->id(),
                'authHouseholdId' => auth()->user()?->household_id,
                'authHasPhone' => filled(auth()->user()?->phone),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
        // #endregion

        return $user;
    }

    /**
     * Owner outbound target for ping, welcome, and budget WhatsApp alerts.
     * Profile phone on the current household Primary.
     */
    public static function primaryWhatsAppNumber(): ?string
    {
        $user = self::primaryUser();

        if ($user === null) {
            return null;
        }

        return self::normalize(is_string($user->phone) ? $user->phone : null);
    }

    /**
     * Numbers allowed to trigger WhatsApp bot replies / receipt import.
     * Current household Primary Profile phone plus Family Members with allowlist enabled.
     *
     * @return list<string>
     */
    public static function allowedWhatsAppSenders(): array
    {
        $numbers = [];

        $primary = self::primaryWhatsAppNumber();

        if ($primary !== null) {
            $numbers[] = $primary;
        }

        $familyPhones = FamilyMember::query()
            ->allowlisted()
            ->pluck('phone')
            ->all();

        foreach ($familyPhones as $phone) {
            $normalized = self::normalize(is_string($phone) ? $phone : null);

            if ($normalized !== null) {
                $numbers[] = $normalized;
            }
        }

        $result = array_values(array_unique($numbers));

        // #region agent log
        file_put_contents(base_path('debug-304ce6.log'), json_encode([
            'sessionId' => '304ce6',
            'runId' => 'post-fix',
            'hypothesisId' => 'A',
            'location' => 'PhoneNumber.php:allowedWhatsAppSenders',
            'message' => 'Allowlist numbers resolved',
            'data' => [
                'primaryPresent' => $primary !== null,
                'familyCount' => count($familyPhones),
                'resultCount' => count($result),
                'currentHouseholdId' => CurrentHousehold::id(),
                'authUserId' => auth()->id(),
                'authHasPhone' => filled(auth()->user()?->phone),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
        // #endregion

        return $result;
    }

    /**
     * Allowlist entries grouped for Evolution API UI.
     *
     * @return array{
     *     primary: list<array{name: string, display_name: string|null, phone: string, whatsapp_lid: string|null, avatar_url: string}>,
     *     family: list<array{id: int, name: string, display_name: string|null, relationship_label: string|null, phone: string, whatsapp_lid: string|null, avatar_url: string}>
     * }
     */
    public static function allowedWhatsAppSenderEntries(): array
    {
        $primary = [];
        $family = [];
        $seen = [];

        $user = self::primaryUser();

        if ($user !== null) {
            $normalized = self::normalize(is_string($user->phone) ? $user->phone : null);

            if ($normalized !== null) {
                $seen[$normalized] = true;
                $primary[] = [
                    'name' => filled($user->name) ? (string) $user->name : 'Primary',
                    'display_name' => filled($user->display_name) ? (string) $user->display_name : null,
                    'phone' => $normalized,
                    'whatsapp_lid' => is_string($user->whatsapp_lid) && $user->whatsapp_lid !== ''
                        ? $user->whatsapp_lid
                        : null,
                    'avatar_url' => self::avatarDisplayUrl($user),
                ];
            }
        }

        $members = FamilyMember::query()
            ->allowlisted()
            ->latest('created_at')
            ->orderByDesc('id')
            ->get(['id', 'name', 'display_name', 'relationship', 'relationship_other', 'phone', 'whatsapp_lid', 'avatar_url']);

        foreach ($members as $member) {
            $normalized = self::normalize($member->phone);

            if ($normalized === null || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $family[] = [
                'id' => (int) $member->id,
                'name' => filled($member->name) ? (string) $member->name : 'Family member',
                'display_name' => filled($member->display_name) ? (string) $member->display_name : null,
                'relationship_label' => $member->relationshipLabel(),
                'phone' => $normalized,
                'whatsapp_lid' => is_string($member->whatsapp_lid) && $member->whatsapp_lid !== ''
                    ? $member->whatsapp_lid
                    : null,
                'avatar_url' => self::avatarDisplayUrl($member),
            ];
        }

        return [
            'primary' => $primary,
            'family' => $family,
        ];
    }

    /**
     * Resolve an inbound WhatsApp JID / number to an allowlisted phone.
     * Supports classic @s.whatsapp.net JIDs and linked @lid identities.
     */
    public static function resolveAllowlistedSenderPhone(string $senderJidOrNumber): ?string
    {
        $trimmed = trim($senderJidOrNumber);

        if ($trimmed === '') {
            return null;
        }

        if (WhatsAppLid::isLidIdentifier($trimmed)) {
            return WhatsAppLid::allowlistedPhoneForLid($trimmed);
        }

        $local = explode('@', $trimmed, 2)[0];
        $normalized = self::normalize($local);

        if ($normalized === null) {
            return null;
        }

        return in_array($normalized, self::allowedWhatsAppSenders(), true) ? $normalized : null;
    }

    public static function isAllowedWhatsAppSender(string $senderNumber): bool
    {
        return self::resolveAllowlistedSenderPhone($senderNumber) !== null;
    }

    /**
     * Uploaded public avatar URL, or Filament ui-avatars initials fallback.
     */
    private static function avatarDisplayUrl(Model $record): string
    {
        $path = $record->getAttribute('avatar_url');

        if (is_string($path) && $path !== '') {
            return Storage::disk('public')->url($path);
        }

        return app(UiAvatarsProvider::class)->get($record);
    }
}
