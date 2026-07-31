<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\AuthLinkExpiry;
use Filament\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class ResetPassword extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        return (new MailMessage)
            ->subject(Lang::get('Reset Password Notification'))
            ->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))
            ->action(Lang::get('Reset Password'), $this->resetUrl($notifiable))
            ->line('This password reset link will expire in **'.AuthLinkExpiry::label().'**.')
            ->line(Lang::get('If you did not request a password reset, no further action is required.'));
    }
}
