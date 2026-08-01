<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\AuthLinkExpiry;
use Filament\Auth\Notifications\VerifyEmailChange as BaseVerifyEmailChange;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailChange extends BaseVerifyEmailChange
{
    public function toMail($notifiable): MailMessage
    {
        $expireText = AuthLinkExpiry::label();

        return (new MailMessage)
            ->subject('Verify Email Address Change')
            ->greeting('Hello!')
            ->line('We received a request to change the email address on your account.')
            ->line('Please click the button below to confirm your new email address.')
            ->action('Verify New Email Address', $this->verificationUrl($notifiable))
            ->line("This verification link will expire in **{$expireText}**.")
            ->line('If you did not request this change, no action is required and your email address will remain unchanged.');
    }
}
