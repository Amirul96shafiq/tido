<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WhatsAppConnectionEvent;
use App\Models\WhatsAppConnectionLog;
use App\Support\PhoneNumber;

class WhatsAppConnectionLogService
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
    public function log(WhatsAppConnectionEvent $event, array $context = []): WhatsAppConnectionLog
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

        return WhatsAppConnectionLog::query()->create([
            'event' => $event,
            'status' => $status !== '' ? $status : null,
            'connected_number' => $connectedNumber,
            'profile_name' => $profileName !== '' ? $profileName : null,
            'instance_name' => $instanceName,
            'message' => $message !== '' ? $message : null,
            'meta' => $meta,
        ]);
    }

    private function defaultMessage(WhatsAppConnectionEvent $event, ?string $connectedNumber): string
    {
        $suffix = $connectedNumber !== null ? ' ('.$connectedNumber.')' : '';

        return match ($event) {
            WhatsAppConnectionEvent::Connected => 'WhatsApp session connected'.$suffix.'.',
            WhatsAppConnectionEvent::Disconnected => 'WhatsApp session disconnected'.$suffix.'.',
            WhatsAppConnectionEvent::Logout => 'WhatsApp session logged out'.$suffix.'.',
        };
    }
}
