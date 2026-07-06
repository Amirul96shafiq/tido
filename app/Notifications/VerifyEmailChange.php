<?php

namespace App\Notifications;

use Filament\Auth\Notifications\VerifyEmailChange as BaseVerifyEmailChange;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

class VerifyEmailChange extends BaseVerifyEmailChange
{
    public function toMail($notifiable)
    {
        $expireMinutes = Config::get('auth.verification.expire', 60);

        if ($expireMinutes >= 1440) {
            $expireText = intdiv($expireMinutes, 1440) . ' day(s)';
        } elseif ($expireMinutes >= 60) {
            $expireText = intdiv($expireMinutes, 60) . ' hour(s)';
        } else {
            $expireText = $expireMinutes . ' minute(s)';
        }

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
