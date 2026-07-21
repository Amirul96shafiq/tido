<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\Budget;
use App\Models\ContentDraft;
use App\Models\EvolutionApiConnectionLog;
use App\Models\Invoice;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

class AccountDangerZoneService
{
    public function __construct(
        private readonly BackupService $backupService,
    ) {}

    public function resetData(User $user): void
    {
        $this->backupService->create(BackupType::Auto, $user);

        $this->wipeSharedAppData($user);
    }

    public function deleteAccount(User $user): Backup
    {
        $backup = $this->backupService->create(BackupType::Auto, $user);

        $this->wipeSharedAppData($user);

        $this->deleteUserAccount($user);

        return $backup;
    }

    public function wipeSharedAppData(User $user): void
    {
        DB::transaction(function () use ($user): void {
            foreach ($this->domainWipeCallbacks($user) as $callback) {
                $callback();
            }
        });
    }

    /**
     * Ordered wipe callbacks for domain data. Add future domain resources here.
     *
     * @return list<callable(): void>
     */
    protected function domainWipeCallbacks(User $user): array
    {
        return [
            function (): void {
                $this->wipeInvoices();
            },
            fn (): mixed => Budget::query()->delete(),
            function (): void {
                $this->wipeUserCreatedLabels();
            },
            function (): void {
                $this->wipeUserCreatedPaymentMethods();
            },
            fn (): mixed => EvolutionApiConnectionLog::query()->delete(),
            fn (): mixed => Activity::query()->delete(),
            fn (): mixed => $user->notifications()->delete(),
            fn (): mixed => ContentDraft::query()->where('user_id', $user->getKey())->delete(),
        ];
    }

    protected function wipeInvoices(): void
    {
        Invoice::query()
            ->withTrashed()
            ->cursor()
            ->each(function (Invoice $invoice): void {
                if (filled($invoice->image_path) && Storage::exists($invoice->image_path)) {
                    Storage::delete($invoice->image_path);
                }

                $invoice->forceDelete();
            });
    }

    protected function wipeUserCreatedLabels(): void
    {
        Label::query()
            ->withTrashed()
            ->where('is_system', false)
            ->cursor()
            ->each(fn (Label $label): mixed => $label->forceDelete());
    }

    protected function wipeUserCreatedPaymentMethods(): void
    {
        PaymentMethod::query()
            ->withTrashed()
            ->where('is_system', false)
            ->cursor()
            ->each(fn (PaymentMethod $paymentMethod): mixed => $paymentMethod->forceDelete());
    }

    protected function deleteUserAccount(User $user): void
    {
        // Single-tenant: remove every account so OTP/password login cannot succeed via leftover users.
        User::query()
            ->orderBy('id')
            ->cursor()
            ->each(function (User $account): void {
                if (filled($account->avatar_url) && Storage::disk('public')->exists($account->avatar_url)) {
                    Storage::disk('public')->delete($account->avatar_url);
                }

                $account->delete();
            });
    }
}
