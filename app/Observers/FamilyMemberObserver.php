<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FamilyMember;
use App\Services\FamilyMemberLoginService;

class FamilyMemberObserver
{
    public function __construct(
        private readonly FamilyMemberLoginService $loginService,
    ) {}

    public function saved(FamilyMember $familyMember): void
    {
        if (! $familyMember->wasChanged('login_enabled')
            && ! $familyMember->wasRecentlyCreated
            && ! $familyMember->wasChanged(['name', 'display_name', 'phone', 'avatar_url', 'date_of_birth'])) {
            return;
        }

        if ($familyMember->login_enabled) {
            $this->loginService->syncLoginUser($familyMember);

            return;
        }

        if ($familyMember->wasChanged('login_enabled')) {
            $this->loginService->revokeLoginAccess($familyMember);
        }
    }

    public function deleted(FamilyMember $familyMember): void
    {
        $this->loginService->revokeLoginAccess($familyMember);
    }

    public function restored(FamilyMember $familyMember): void
    {
        if ($familyMember->login_enabled) {
            $this->loginService->syncLoginUser($familyMember);
        }
    }
}
