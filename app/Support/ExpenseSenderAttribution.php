<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FamilyMember;

final class ExpenseSenderAttribution
{
    public static function familyMemberIdForSender(?string $senderNumber): ?int
    {
        $normalized = PhoneNumber::normalize($senderNumber);

        if ($normalized === null) {
            return null;
        }

        $member = FamilyMember::query()
            ->allowlisted()
            ->where('phone', $normalized)
            ->first(['id']);

        return $member?->id;
    }
}
