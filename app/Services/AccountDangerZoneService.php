<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BackupType;
use App\Models\Budget;
use App\Models\ContentDraft;
use App\Models\EvolutionApiConnectionLog;
use App\Models\Expense;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Models\Recurring;
use App\Models\User;
use App\Support\CreatedBackup;
use App\Support\CurrentHousehold;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

class AccountDangerZoneService
{
    public function __construct(
        private readonly BackupService $backupService,
    ) {}

    public function resetData(User $user): CreatedBackup
    {
        $created = $this->backupService->create(BackupType::Auto, $user);

        $this->wipeSharedAppData($user);

        return $created;
    }

    public function createPreDeleteBackup(User $user): CreatedBackup
    {
        return $this->backupService->create(BackupType::Auto, $user);
    }

    public function completeAccountDeletion(User $user): void
    {
        $this->wipeSharedAppData($user);
        $this->deleteUserAccount($user);
    }

    public function deleteAccount(User $user): CreatedBackup
    {
        $created = $this->createPreDeleteBackup($user);
        $this->completeAccountDeletion($user);

        return $created;
    }

    public function wipeSharedAppData(User $user): void
    {
        $householdId = (int) $user->household_id;

        DB::transaction(function () use ($user, $householdId): void {
            CurrentHousehold::set($householdId);

            try {
                foreach ($this->domainWipeCallbacks($user, $householdId) as $callback) {
                    $callback();
                }
            } finally {
                CurrentHousehold::clear();
            }
        });
    }

    /**
     * Ordered wipe callbacks for domain data within one household.
     *
     * @return list<callable(): void>
     */
    protected function domainWipeCallbacks(User $user, int $householdId): array
    {
        return [
            function () use ($householdId): void {
                $this->wipeExpenses($householdId);
            },
            function () use ($householdId): void {
                $this->wipeRecurrings($householdId);
            },
            function () use ($householdId): void {
                $this->wipeBudgets($householdId);
            },
            function () use ($householdId): void {
                $this->wipeUserCreatedLabels($householdId);
            },
            function () use ($householdId): void {
                $this->wipeUserCreatedPaymentMethods($householdId);
            },
            fn (): mixed => EvolutionApiConnectionLog::query()->where('household_id', $householdId)->delete(),
            fn (): mixed => Activity::query()->delete(),
            fn (): mixed => $user->notifications()->delete(),
            fn (): mixed => ContentDraft::query()->where('user_id', $user->getKey())->delete(),
        ];
    }

    protected function wipeExpenses(int $householdId): void
    {
        Expense::query()
            ->withTrashed()
            ->where('household_id', $householdId)
            ->cursor()
            ->each(function (Expense $expense): void {
                if (filled($expense->image_path) && Storage::exists($expense->image_path)) {
                    Storage::delete($expense->image_path);
                }

                $expense->forceDelete();
            });
    }

    protected function wipeBudgets(int $householdId): void
    {
        Budget::query()
            ->withTrashed()
            ->where('household_id', $householdId)
            ->cursor()
            ->each(fn (Budget $budget): mixed => $budget->forceDelete());
    }

    protected function wipeRecurrings(int $householdId): void
    {
        Recurring::query()
            ->withTrashed()
            ->where('household_id', $householdId)
            ->cursor()
            ->each(fn (Recurring $recurring): mixed => $recurring->forceDelete());
    }

    protected function wipeUserCreatedLabels(int $householdId): void
    {
        Label::query()
            ->withTrashed()
            ->where('household_id', $householdId)
            ->where('is_system', false)
            ->cursor()
            ->each(fn (Label $label): mixed => $label->forceDelete());
    }

    protected function wipeUserCreatedPaymentMethods(int $householdId): void
    {
        PaymentMethod::query()
            ->withTrashed()
            ->where('household_id', $householdId)
            ->where('is_system', false)
            ->cursor()
            ->each(fn (PaymentMethod $paymentMethod): mixed => $paymentMethod->forceDelete());
    }

    protected function deleteUserAccount(User $user): void
    {
        $householdId = (int) $user->household_id;

        // Multi-household: remove only accounts in this household.
        User::query()
            ->where('household_id', $householdId)
            ->orderBy('id')
            ->cursor()
            ->each(function (User $account): void {
                if (filled($account->avatar_url) && Storage::disk('public')->exists($account->avatar_url)) {
                    Storage::disk('public')->delete($account->avatar_url);
                }

                if (filled($account->profile_banner) && Storage::disk('public')->exists($account->profile_banner)) {
                    Storage::disk('public')->delete($account->profile_banner);
                }

                $account->delete();
            });
    }
}
