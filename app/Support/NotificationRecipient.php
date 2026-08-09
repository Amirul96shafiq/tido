<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class NotificationRecipient
{
    /**
     * Owner account for Filament system alerts (always user id 1).
     */
    public static function primaryAdmin(): ?User
    {
        return PhoneNumber::primaryUser();
    }

    /**
     * Users whose phone matches the given WhatsApp number (any stored format).
     *
     * @return Collection<int, User>
     */
    public static function findUsersByPhone(?string $phone): Collection
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return new Collection;
        }

        $localForm = '0'.substr($normalized, 2);

        return User::query()
            ->where(function ($query) use ($normalized, $localForm): void {
                $query->where('phone', $normalized)
                    ->orWhere('phone', '+'.$normalized)
                    ->orWhere('phone', $localForm);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve a single Filament notification recipient for a phone number.
     * Prefers primary admin when multiple users share the number; falls back to primary when none match.
     */
    public static function forPhone(?string $phone): ?User
    {
        $matches = self::findUsersByPhone($phone);

        if ($matches->isEmpty()) {
            return self::primaryAdmin();
        }

        $primary = self::primaryAdmin();

        if ($primary !== null && $matches->contains(fn (User $user): bool => $user->is($primary))) {
            return $primary;
        }

        return $matches->first();
    }

    /**
     * Recipient for expense-related Filament alerts (WhatsApp sender phone when present).
     */
    public static function forExpense(Expense $expense): ?User
    {
        if (filled($expense->whatsapp_sender)) {
            return self::forPhone((string) $expense->whatsapp_sender);
        }

        return self::primaryAdmin();
    }
}
