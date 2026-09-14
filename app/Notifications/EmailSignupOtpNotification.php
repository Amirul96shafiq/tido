<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailSignupOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $code,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Email Confirmation Code')
            ->line('A 6-digit confirmation code was requested for a new tido account.')
            ->line("Code: **{$this->code}**")
            ->line('This code expires in 10 minutes.')
            ->line('If this request was not made, no action is required.');
    }
}
