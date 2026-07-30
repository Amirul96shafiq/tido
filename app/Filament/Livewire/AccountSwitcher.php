<?php

declare(strict_types=1);

namespace App\Filament\Livewire;

use App\Models\FamilyMember;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountSwitcher extends Component
{
    public const SESSION_KEY = 'account_switcher_original_user_id';

    public function isVisible(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if (self::isImpersonating()) {
            return true;
        }

        return $user->isPrimary() && $this->getSwitchableMembers()->isNotEmpty();
    }

    public static function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function originalUserId(): ?int
    {
        return session()->get(self::SESSION_KEY);
    }

    /**
     * @return Collection<int, FamilyMember>
     */
    public function getSwitchableMembers(): Collection
    {
        return FamilyMember::query()
            ->where('login_enabled', true)
            ->whereHas('loginUser')
            ->orderBy('name')
            ->get();
    }

    public function switchTo(int $familyMemberId): void
    {
        $currentUser = Auth::user();

        if (! $currentUser instanceof User) {
            return;
        }

        // Only primary users (or those already impersonating) can switch
        if (! $currentUser->isPrimary() && ! self::isImpersonating()) {
            Notification::make()
                ->title('Permission denied')
                ->body('Only the primary account can switch users.')
                ->danger()
                ->send();

            return;
        }

        $member = FamilyMember::query()
            ->where('id', $familyMemberId)
            ->where('login_enabled', true)
            ->first();

        if (! $member instanceof FamilyMember) {
            Notification::make()
                ->title('Cannot switch')
                ->body('This family member does not have login enabled.')
                ->danger()
                ->send();

            return;
        }

        $linkedUser = $member->loginUser;

        if (! $linkedUser instanceof User) {
            Notification::make()
                ->title('Cannot switch')
                ->body('This family member does not have a linked login account.')
                ->danger()
                ->send();

            return;
        }

        // Store the original primary user ID (only if not already set — prevent nested overwrite)
        $originalId = session()->get(self::SESSION_KEY, $currentUser->id);

        Filament::auth()->login($linkedUser, remember: true);
        session()->regenerate();
        session()->put(self::SESSION_KEY, $originalId);

        $displayName = $member->display_name ?? $member->name;

        Notification::make()
            ->title("Switched to {$displayName}")
            ->success()
            ->send();

        $this->redirect(Filament::getUrl(), navigate: false);
    }

    public function switchBack(): void
    {
        $originalUserId = session()->get(self::SESSION_KEY);

        if ($originalUserId === null) {
            return;
        }

        $primaryUser = User::query()->find($originalUserId);

        if (! $primaryUser instanceof User || ! $primaryUser->isPrimary()) {
            Notification::make()
                ->title('Cannot switch back')
                ->body('The original primary account could not be found.')
                ->danger()
                ->send();

            return;
        }

        Filament::auth()->login($primaryUser, remember: true);
        session()->regenerate();
        session()->forget(self::SESSION_KEY);

        $displayName = $primaryUser->display_name ?? $primaryUser->name;

        Notification::make()
            ->title("Switched back to {$displayName}")
            ->success()
            ->send();

        $this->redirect(Filament::getUrl(), navigate: false);
    }

    public function render(): View
    {
        return view('filament.livewire.account-switcher', [
            'currentUser' => Auth::user(),
            'isImpersonating' => self::isImpersonating(),
            'switchableMembers' => $this->isVisible() ? $this->getSwitchableMembers() : collect(),
        ]);
    }
}
