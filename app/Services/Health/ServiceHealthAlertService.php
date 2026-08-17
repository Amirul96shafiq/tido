<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Enums\HouseholdRole;
use App\Enums\ServiceHealthStatus;
use App\Filament\Pages\ServiceStatusPage;
use App\Models\ServiceHealthSample;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class ServiceHealthAlertService
{
    /**
     * @param  array<string, ServiceHealthSample>  $previousByService
     * @param  list<ServiceHealthSample>  $newSamples
     */
    public function notifyTransitions(array $previousByService, array $newSamples): void
    {
        $problems = [];
        $recoveries = [];
        $hasDown = false;
        $hasDegraded = false;

        foreach ($newSamples as $sample) {
            $service = $sample->service;
            $previous = $previousByService[$service->value] ?? null;

            if (! $previous instanceof ServiceHealthSample) {
                continue;
            }

            if ($previous->status === $sample->status) {
                continue;
            }

            $label = $service->label();

            if (
                $sample->status === ServiceHealthStatus::Operational
                && in_array($previous->status, [ServiceHealthStatus::Degraded, ServiceHealthStatus::Down], true)
            ) {
                $recoveries[] = $label.' recovered to operational.';

                continue;
            }

            if ($sample->status === ServiceHealthStatus::Down) {
                $problems[] = $label.' is down.';
                $hasDown = true;

                continue;
            }

            if ($sample->status === ServiceHealthStatus::Degraded) {
                $problems[] = $label.' is degraded.';
                $hasDegraded = true;
            }
        }

        if ($problems === [] && $recoveries === []) {
            return;
        }

        $recipients = User::query()
            ->where(function ($query): void {
                $query->where('household_role', HouseholdRole::Primary)
                    ->orWhereNull('household_role');
            })
            ->where('notify_service_status', true)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $bodyParts = [...$problems, ...$recoveries];
        $body = implode(' ', $bodyParts);

        $title = match (true) {
            $hasDown => 'Service Status down',
            $hasDegraded => 'Service Status degraded',
            default => 'Service Status recovered',
        };

        $actions = [];

        try {
            $actions[] = Action::make('openServiceStatus')
                ->label('Open Service Status')
                ->button()
                ->url(ServiceStatusPage::getUrl(), shouldOpenInNewTab: true)
                ->markAsRead();
        } catch (Throwable $throwable) {
            Log::warning('Service health inbox alert skipped Service Status URL', [
                'error' => $throwable->getMessage(),
            ]);
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-signal')
            ->actions($actions);

        if ($hasDown) {
            $notification->danger();
        } elseif ($hasDegraded) {
            $notification->warning();
        } else {
            $notification->success();
        }

        try {
            $notification->sendToDatabase($recipients);
        } catch (Throwable $throwable) {
            Log::error('Service health inbox alert failed', [
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
