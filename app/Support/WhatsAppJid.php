<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Inbound WhatsApp JID shape for webhook payloads.
 * Rejects group/broadcast domains so a phone local-part cannot spoof the allowlist.
 */
final class WhatsAppJid
{
    /**
     * Domains accepted for classic phone JIDs on the webhook.
     *
     * @var list<string>
     */
    public const PHONE_DOMAINS = ['s.whatsapp.net', 'c.us'];

    public static function isValidInbound(string $remoteJid): bool
    {
        $trimmed = trim($remoteJid);

        if ($trimmed === '' || str_contains($trimmed, '/') || str_contains($trimmed, '\\')) {
            return false;
        }

        if (WhatsAppLid::isLidIdentifier($trimmed)) {
            return WhatsAppLid::normalize($trimmed) !== null;
        }

        $parts = explode('@', $trimmed, 2);

        if (count($parts) !== 2) {
            return false;
        }

        [$local, $domain] = $parts;
        $domain = strtolower($domain);

        if (! in_array($domain, self::PHONE_DOMAINS, true)) {
            return false;
        }

        return PhoneNumber::normalize($local) !== null;
    }

    /**
     * Resolve an inbound webhook JID to an allowlisted phone, or null.
     * Only phone JIDs with an allowed domain (or linked LIDs) are considered.
     */
    public static function resolveAllowlistedSenderPhone(string $remoteJid): ?string
    {
        $trimmed = trim($remoteJid);

        if ($trimmed === '' || ! self::isValidInbound($trimmed)) {
            return null;
        }

        return PhoneNumber::resolveAllowlistedSenderPhone($trimmed);
    }
}
