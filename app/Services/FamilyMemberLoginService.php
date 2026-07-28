<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HouseholdRole;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FamilyMemberLoginService
{
    public function syncLoginUser(FamilyMember $member): ?User
    {
        if (! $member->login_enabled) {
            $this->revokeLoginAccess($member);

            return null;
        }

        if (blank($member->phone)) {
            return null;
        }

        $existingUser = User::query()
            ->where('family_member_id', $member->id)
            ->first();

        if ($existingUser instanceof User) {
            $email = $this->resolvedLoginEmail($member, $existingUser);

            $existingUser->update([
                'name' => $member->name,
                'display_name' => $member->display_name,
                'phone' => $member->phone,
                'email' => $email,
                'avatar_url' => $member->avatar_url,
                'date_of_birth' => $member->date_of_birth,
                'household_role' => HouseholdRole::FamilyMember,
                'email_verified_at' => $existingUser->email === $email
                    ? $existingUser->email_verified_at
                    : now(),
            ]);

            return $existingUser->fresh();
        }

        $phoneConflict = User::query()
            ->where('phone', $member->phone)
            ->where('family_member_id', '!=', $member->id)
            ->exists();

        if ($phoneConflict) {
            return null;
        }

        return User::query()->create([
            'name' => $member->name,
            'display_name' => $member->display_name,
            'email' => $this->resolvedLoginEmail($member),
            'password' => Hash::make(Str::random(64)),
            'phone' => $member->phone,
            'avatar_url' => $member->avatar_url,
            'date_of_birth' => $member->date_of_birth,
            'household_role' => HouseholdRole::FamilyMember,
            'family_member_id' => $member->id,
            'email_verified_at' => now(),
            'timezone' => config('app.timezone', 'Asia/Kuala_Lumpur'),
            'locale' => 'en',
            'date_format' => 'd/m/Y',
            'notify_budget_alerts' => false,
            'notify_profile_updates' => false,
            'notify_email_digest' => false,
            'notify_evolution_api' => false,
        ]);
    }

    public function revokeLoginAccess(FamilyMember $member): void
    {
        User::query()
            ->where('family_member_id', $member->id)
            ->where('household_role', HouseholdRole::FamilyMember)
            ->delete();
    }

    private function resolvedLoginEmail(FamilyMember $member, ?User $existingUser = null): string
    {
        if (filled($member->email)) {
            $conflictQuery = User::query()->where('email', $member->email);

            if ($existingUser instanceof User) {
                $conflictQuery->where('id', '!=', $existingUser->id);
            }

            if (! $conflictQuery->exists()) {
                return (string) $member->email;
            }
        }

        return $this->syntheticEmail($member);
    }

    private function syntheticEmail(FamilyMember $member): string
    {
        return 'family+'.$member->id.'@tido.local';
    }
}
