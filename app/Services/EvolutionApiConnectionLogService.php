<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvolutionApiConnectionEvent;
use App\Models\EvolutionApiConnectionLog;
use App\Support\PhoneNumber;

class EvolutionApiConnectionLogService
{
    /**
     * @param  array{
     *     status?: string|null,
     *     connected_number?: string|null,
     *     profile_name?: string|null,
     *     instance_name?: string|null,
     *     message?: string|null,
     *     meta?: array<string, mixed>|null
     * }  $context
     */
    public function log(EvolutionApiConnectionEvent $event, array $context = []): EvolutionApiConnectionLog
    {
        $instanceName = $context['instance_name']
            ?? config('services.evolution.instance_name', 'tido');

        if (! is_string($instanceName) || $instanceName === '') {
            $instanceName = 'tido';
        }

        $connectedNumber = PhoneNumber::normalize(
            isset($context['connected_number']) && is_string($context['connected_number'])
                ? $context['connected_number']
                : null,
        );

        $profileName = isset($context['profile_name']) && is_string($context['profile_name'])
            ? trim($context['profile_name'])
            : null;

        $status = isset($context['status']) && is_string($context['status'])
            ? trim($context['status'])
            : null;

        $message = isset($context['message']) && is_string($context['message'])
            ? trim($context['message'])
            : $this->defaultMessage($event, $connectedNumber);

        $meta = isset($context['meta']) && is_array($context['meta'])
            ? $context['meta']
            : null;

        return EvolutionApiConnectionLog::query()->create([
            'event' => $event,
            'status' => $status !== '' ? $status : null,
            'connected_number' => $connectedNumber,
            'profile_name' => $profileName !== '' ? $profileName : null,
            'instance_name' => $instanceName,
            'message' => $message !== '' ? $message : null,
            'meta' => $meta,
        ]);
    }

    private function defaultMessage(EvolutionApiConnectionEvent $event, ?string $connectedNumber): string
    {
        $suffix = $connectedNumber !== null ? ' ('.$connectedNumber.')' : '';

        return match ($event) {
            EvolutionApiConnectionEvent::Connected => 'Evolution API session connected'.$suffix.'.',
            EvolutionApiConnectionEvent::Disconnected => 'Evolution API session disconnected'.$suffix.'.',
            EvolutionApiConnectionEvent::Logout => 'Evolution API session logged out'.$suffix.'.',
        };
    }
}
