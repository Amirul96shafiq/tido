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
            $existingUser->update([
                'name' => $member->name,
                'display_name' => $member->display_name,
                'phone' => $member->phone,
                'avatar_url' => $member->avatar_url,
                'household_role' => HouseholdRole::FamilyMember,
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
            'email' => $this->syntheticEmail($member),
            'password' => Hash::make(Str::random(64)),
            'phone' => $member->phone,
            'avatar_url' => $member->avatar_url,
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

    private function syntheticEmail(FamilyMember $member): string
    {
        return 'family+'.$member->id.'@tido.local';
    }
}
